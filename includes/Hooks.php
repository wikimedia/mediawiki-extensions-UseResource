<?php
/**
 * Copyright (C) 2021 Brandon Fowler
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace MediaWiki\Extension\UseResource;

use ContentHandler;
use CssContent;
use Html;
use JavaScriptContent;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Revision\SlotRecord;
use OutputPage;
use Parser;
use ParserOutput;
use TextContent;
use Title;

/**
 * Hook handlers for the UseResource extension
 */
class Hooks implements OutputPageParserOutputHook, ParserFirstCallInitHook {
	/**
	 * Show an error message
	 * @param Parser $parser Current Parser object
	 * @param string $key The key of the message
	 * @param mixed ...$args Message parameters
	 * @return string HTML
	 */
	private static function showError( $parser, $key, ...$args ) {
		$parser->addTrackingCategory( 'useresource-error-category' );
		return Html::rawElement(
			'strong',
			[ 'class' => 'error' ],
			wfMessage( $key, ...$args )->inContentLanguage()->parse()
		);
	}

	/**
	 * Handler for the OutputPageParserOutput hook
	 * @param OutputPage $out
	 * @param ParserOutput $parserOutput
	 */
	public function onOutputPageParserOutput( $out, $parserOutput ): void {
		$data = $parserOutput->getExtensionData( 'useresource' );

		if ( !$data ) {
			return;
		}

		if ( !empty( $data['js'] ) ) {
			$out->getResourceLoader()->register( 'ext.useresource', [
				'class' => Module::class,
				'code' => $data['js']
			] );
			$out->addModules( 'ext.useresource' );
		}

		if ( !empty( $data['css'] ) ) {
			$out->addHeadItem( 'useresource', '<style>' . $data['css'] . '</style>' );
		}
	}

	/**
	 * Handler for the ParserFirstCallInit hook
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'usescript', self::createTagHandler(
			'js', JavaScriptContent::class, CONTENT_MODEL_JAVASCRIPT, [ '!function(){', '}();' ]
		) );

		$parser->setHook( 'usestyle', self::createTagHandler(
			'css', CssContent::class, CONTENT_MODEL_CSS, []
		) );
	}

	/**
	 * Create a tag handler function
	 * @param string $type The language of the content
	 * @param string $class The required content class, must be a child of TextContent
	 * @param string $model The model id
	 * @param string[] $wrap Two element array with text to wrap around the code
	 * @return callable A callback compatible with Parser::setHook
	 */
	public static function createTagHandler( $type, $class, $model, $wrap ) {
		return function ( $input, array $args, Parser $parser, $frame ) use ( $type, $class, $model, $wrap ) {
			if ( !isset( $args['src'] ) || trim( $args['src'] ) === '' ) {
				return self::showError( $parser, 'useresource-empty-src' );
			}

			$title = Title::newFromText( $args['src'], NS_MEDIAWIKI );

			if ( !$title ) {
				return self::showError( $parser, 'useresource-invalid-title' );
			}

			if ( !$title->inNamespace( NS_MEDIAWIKI ) ) {
				return self::showError(
					$parser,
					'useresource-invalid-namespace',
					$title->getFullText(),
					$parser->getContentLanguage()->getFormattedNsText( NS_MEDIAWIKI )
				);
			}

			$revRecord = $parser->fetchCurrentRevisionRecordOfTitle( $title );
			$articleID = $title->getArticleID();
			$content = $revRecord ? $revRecord->getContent( SlotRecord::MAIN, $revRecord::RAW ) : null;

			// Register as a template so the page is re-parsed when the script is edited
			$parser->getOutput()->addTemplate( $title, $articleID, $revRecord ? $revRecord->getId() : null );

			if ( !$content ) {
				return self::showError( $parser, 'useresource-no-content', $title->getFullText() );
			}

			if ( !is_a( $content, $class ) || !$content instanceof TextContent ) {
				return self::showError(
					$parser,
					'useresource-invalid-content',
					$title->getFullText(),
					ContentHandler::getLocalizedName( $model ),
					ContentHandler::getLocalizedName( $content->getModel() )
				);
			}

			$data = $parser->getOutput()->getExtensionData( 'useresource' ) ?? [ 'pages' => [] ];

			if ( in_array( $articleID, $data['pages'] ) ) {
				return '';
			}

			$data['pages'][] = $articleID;
			$text = $content->getText();

			if ( $text ) {
				$text = ResourceLoader::filter( 'minify-' . $type, $text );
				$data[$type] = ( $data[$type] ?? '' ) . ( $wrap ? $wrap[0] . $text . $wrap[1] : $text );
			}

			$parser->getOutput()->setExtensionData( 'useresource', $data );

			return '';
		};
	}
}
