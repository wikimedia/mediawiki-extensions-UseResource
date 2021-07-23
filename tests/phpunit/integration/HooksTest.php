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

namespace MediaWiki\Extension\UseResource\Test\Integration;

use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use Parser;
use ParserOptions;
use RequestContext;
use Title;

/**
 * Tests for \MediaWiki\Extension\UseResource\Hooks
 *
 * @group UseResource
 * @group Database
 * @covers \MediaWiki\Extension\UseResource\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {
	/** @var Parser */
	private $parser;

	/** @var ParserOptions */
	private $parserOptions;

	protected function setUp(): void {
		parent::setUp();

		$this->setUserLang( 'qqx' );
		$this->setMwGlobals( 'wgLanguageCode', 'qqx' );

		$services = MediaWikiServices::getInstance();
		$services->getMessageCache()->disable();

		$this->parserOptions = ParserOptions::newFromContext( RequestContext::getMain() );
		$this->parser = $services->getParserFactory()->create();
	}

	public function addDBDataOnce() {
		$this->insertPage( 'MediaWiki:UseResourceTest.js', 'console.log(7)' );
		$this->insertPage( 'MediaWiki:UseResourceTest2.js', '' );
		$this->insertPage( 'MediaWiki:UseResourceTest3.js', 'alert(8)' );

		$this->insertPage( 'MediaWiki:UseResourceTest.css', '*{color:red}' );
		$this->insertPage( 'MediaWiki:UseResourceTest2.css', '' );
		$this->insertPage( 'MediaWiki:UseResourceTest3.css', 'body{background:green}' );

		$this->insertPage( 'MediaWiki:UseResourceTest', 'console.log(7)' );
		$this->insertPage( 'User:TestUser/UseResourceTest.js', 'console.log(7)' );
	}

	public function testOnParserFirstCallInit() {
		$this->assertContains(
			'usescript',
			$this->parser->getTags(),
			'usescript tag should be registered.'
		);
		$this->assertContains(
			'usestyle',
			$this->parser->getTags(),
			'usestyle tag should be registered.'
		);
	}

	/**
	 * @dataProvider provideOnOutputPageParserOutputWithCode
	 */
	public function testOnOutputPageParserOutputWithCode( string $input, $expectedJS, $expectedCSS ) {
		$title = Title::newFromText( 'Test' );

		$context = new RequestContext();
		$context->setTitle( $title );

		$parserOutput = $this->parser->parse( $input, $title, $this->parserOptions );

		$outputPage = $context->getOutput();
		$outputPage->addParserOutput( $parserOutput );

		$headHtml = strval( $outputPage->getRlClient()->getHeadHtml() );
		$headItems = $outputPage->getHeadItemsArray();

		if ( $expectedJS ) {
			$this->assertContains(
				'ext.useresource',
				$outputPage->getModules(),
				'ext.useresource should be registered.'
			);
			$this->assertStringContainsString(
				'mw.loader.implement("ext.useresource@',
				$headHtml,
				'ext.useresource should be implemented in JavaScript.'
			);
			$this->assertStringContainsString(
				$expectedJS,
				$headHtml,
				'ext.useresource should have the correct JavaScript code.'
			);
		} else {
			$this->assertArrayNotHasKey(
				'ext.useresource',
				$outputPage->getModules(),
				'ext.useresource should not be registered.'
			);
			$this->assertStringNotContainsString(
				'mw.loader.implement("ext.useresource@',
				$headHtml,
				'ext.useresource should not be implemented in JavaScript.'
			);
		}

		if ( $expectedCSS ) {
			$this->assertArrayHasKey(
				'useresource',
				$headItems,
				'useresource should be in head items.'
			);
			$this->assertSame(
				$headItems['useresource'],
				'<style>' . $expectedCSS . '</style>',
				'useresource should have the correct CSS code.'
			);
		} else {
			$this->assertArrayNotHasKey(
				'useresource',
				$headItems,
				'useresource should not be in head items.'
			);
		}
	}

	public function provideOnOutputPageParserOutputWithCode() {
		// phpcs:disable Generic.Files.LineLength
		return [
			'One usescript tag' => [
				'<usescript src="UseResourceTest.js" />',
				'function($,jQuery,require,module){!function(){console.log(7)}();});',
				false
			],
			'One usestyle tag' => [
				'<usestyle src="UseResourceTest.css" />',
				false,
				'*{color:red}'
			],
			'Multiple usescript tags' => [
				'<usescript src="UseResourceTest.js" /><usescript src="UseResourceTest.js" /><usescript src="UseResourceTest2.js" /><usescript src="UseResourceTest3.js" />',
				'function($,jQuery,require,module){!function(){console.log(7)}();!function(){alert(8)}();});',
				false
			],
			'Multiple usestyle tags' => [
				'<usestyle src="UseResourceTest.css" /><usestyle src="UseResourceTest.css" /><usestyle src="UseResourceTest2.css" /><usestyle src="UseResourceTest3.css" />',
				false,
				'*{color:red}body{background:green}'
			],
			'Multiple usescript and usestyle tags' => [
				'<usescript src="UseResourceTest.js" /><usescript src="UseResourceTest.js" /><usescript src="UseResourceTest2.js" /><usescript src="UseResourceTest3.js" /><usestyle src="UseResourceTest.css" /><usestyle src="UseResourceTest.css" /><usestyle src="UseResourceTest2.css" /><usestyle src="UseResourceTest3.css" />',
				'function($,jQuery,require,module){!function(){console.log(7)}();!function(){alert(8)}();});',
				'*{color:red}body{background:green}'
			],
			'No usescript or usestyle tags' => [
				'Hello <span>world</span>!',
				false,
				false
			],
			'usescript and usestyle produce no content' => [
				'<usescript src="UseResourceTest2.js" /><usestyle src="UseResourceTest2.css" />',
				false,
				false
			]
		];
		// phpcs:enable
	}

	/**
	 * @dataProvider provideHandleTag
	 */
	public function testHandleTag( string $input, string $expectedOutput, $expectedExtensionData ) {
		$this->setMwGlobals( [
			'wgScriptPath' => '',
			'wgScript' => '/index.php',
			'wgArticlePath' => '/wiki/$1',
		] );

		$output = $this->parser->parse( $input, Title::newFromText( 'Test' ), $this->parserOptions );

		if ( is_callable( $expectedExtensionData ) ) {
			$expectedExtensionData = $expectedExtensionData( [
				Title::newFromText( 'MediaWiki:UseResourceTest.js' )->getArticleID(),
				Title::newFromText( 'MediaWiki:UseResourceTest2.js' )->getArticleID(),
				Title::newFromText( 'MediaWiki:UseResourceTest3.js' )->getArticleID(),
				Title::newFromText( 'MediaWiki:UseResourceTest.css' )->getArticleID(),
			] );
		}

		$this->assertSame( $expectedOutput, $output->getText() );
		$this->assertSame( $expectedExtensionData, $output->getExtensionData( 'useresource' ) );
	}

	public function provideHandleTag() {
		// phpcs:disable Generic.Files.LineLength
		return [
			'Tag with empty content' => [
				'<usescript src="MediaWiki:UseResourceTest.js"></usescript>',
				'<div class="mw-parser-output"></div>',
				static function ( $ids ) {
					return [
						'pages' => [ $ids[0] ],
						'js' => '!function(){console.log(7)}();'
					];
				}
			],
			'Tag with content' => [
				'<usescript src="MediaWiki:UseResourceTest.js">Some text</usescript>',
				'<div class="mw-parser-output"></div>',
				static function ( $ids ) {
					return [
						'pages' => [ $ids[0] ],
						'js' => '!function(){console.log(7)}();'
					];
				}
			],
			'Tag without src' => [
				'<usescript />',
				'<div class="mw-parser-output"><p><strong class="error">(useresource-empty-src)</strong>
</p></div>',
				null
			],
			'Tag with invalid title' => [
				'<usescript src="{" />',
				'<div class="mw-parser-output"><p><strong class="error">(useresource-invalid-title)</strong>
</p></div>',
				null
			],
			'Tag with non-existent page' => [
				'<usescript src="MediaWiki:UseResourceTestDoesNotExist.js />',
				'<div class="mw-parser-output"><p><strong class="error">(useresource-no-content: MediaWiki:UseResourceTestDoesNotExist.js)</strong>
</p></div>',
				null
			],
			'Tag with invalid namespace' => [
				'<usescript src="User:TestUser/UseResourceTest.js" />',
				'<div class="mw-parser-output"><p><strong class="error">(useresource-invalid-namespace: User:TestUser/UseResourceTest.js, MediaWiki)</strong>
</p></div>',
				null
			],
			'usescript tag with WikitextContent content' => [
				'<usescript src="MediaWiki:UseResourceTest" />',
				'<div class="mw-parser-output"><p><strong class="error">(useresource-invalid-content: MediaWiki:UseResourceTest, (content-model-javascript), (content-model-wikitext))</strong>
</p></div>',
				null
			],
			'usescript tag with CssContent content' => [
				'<usescript src="MediaWiki:UseResourceTest.css" />',
				'<div class="mw-parser-output"><p><strong class="error">(useresource-invalid-content: MediaWiki:UseResourceTest.css, (content-model-javascript), (content-model-css))</strong>
</p></div>',
				null
			],
			'usestyle tag with WikitextContent content' => [
				'<usestyle src="MediaWiki:UseResourceTest" />',
				'<div class="mw-parser-output"><p><strong class="error">(useresource-invalid-content: MediaWiki:UseResourceTest, (content-model-css), (content-model-wikitext))</strong>
</p></div>',
				null
			],
			'usestyle tag with JavaScriptContent content' => [
				'<usestyle src="MediaWiki:UseResourceTest.js" />',
				'<div class="mw-parser-output"><p><strong class="error">(useresource-invalid-content: MediaWiki:UseResourceTest.js, (content-model-css), (content-model-javascript))</strong>
</p></div>',
				null
			],
			'Tag with valid namespace' => [
				'<usescript src="MediaWiki:UseResourceTest.js" />',
				'<div class="mw-parser-output"></div>',
				static function ( $ids ) {
					return [
						'pages' => [ $ids[0] ],
						'js' => '!function(){console.log(7)}();'
					];
				}
			],
			'Tag without namespace' => [
				'<usescript src="UseResourceTest.js" />',
				'<div class="mw-parser-output"></div>',
				static function ( $ids ) {
					return [
						'pages' => [ $ids[0] ],
						'js' => '!function(){console.log(7)}();'
					];
				}
			],
			'Tag with empty page' => [
				'<usescript src="MediaWiki:UseResourceTest2.js" />',
				'<div class="mw-parser-output"></div>',
				static function ( $ids ) {
					return [
						'pages' => [ $ids[1] ]
					];
				}
			],
			'Two tags' => [
				'<usescript src="MediaWiki:UseResourceTest.js" /><usescript src="MediaWiki:UseResourceTest3.js" />',
				'<div class="mw-parser-output"></div>',
				static function ( $ids ) {
					return [
						'pages' => [ $ids[0], $ids[2] ],
						'js' => '!function(){console.log(7)}();!function(){alert(8)}();'
					];
				}
			],
			'Two of the same tag'  => [
				'<usescript src="MediaWiki:UseResourceTest.js" /><usescript src="MediaWiki:UseResourceTest.js" />',
				'<div class="mw-parser-output"></div>',
				static function ( $ids ) {
					return [
						'pages' => [ $ids[0] ],
						'js' => '!function(){console.log(7)}();'
					];
				}
			],
			'Only a usestyle tag' => [
				'<usestyle src="MediaWiki:UseResourceTest.css" />',
				'<div class="mw-parser-output"></div>',
				static function ( $ids ) {
					return [
						'pages' => [ $ids[3] ],
						'css' => '*{color:red}'
					];
				}
			],
			'Only a usescript tag' => [
				'<usescript src="MediaWiki:UseResourceTest.js" />',
				'<div class="mw-parser-output"></div>',
				static function ( $ids ) {
					return [
						'pages' => [ $ids[0] ],
						'js' => '!function(){console.log(7)}();'
					];
				}
			],
			'Both usestyle and usescript tags'  => [
				'<usescript src="MediaWiki:UseResourceTest.js" /><usestyle src="MediaWiki:UseResourceTest.css" />',
				'<div class="mw-parser-output"></div>',
				static function ( $ids ) {
					return [
						'pages' => [ $ids[0], $ids[3] ],
						'js' => '!function(){console.log(7)}();',
						'css' => '*{color:red}'
					];
				}
			]
		];
		// phpcs:enable
	}
}
