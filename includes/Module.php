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

use MediaWiki\ResourceLoader as RL;

/**
 * Resource loader for the resource module UseResource adds to the page
 */
class Module extends RL\Module {
	/** @var string */
	private $script;

	/**
	 * @param array $options An array of options. Keys:
	 *  - (string) code: The code of the module
	 */
	public function __construct( $options ) {
		$this->script = $options['code'];
		$this->targets = [ 'desktop', 'mobile' ];
	}

	/** @inheritDoc */
	public function getScript( $context ) {
		return $this->script;
	}

	/** @inheritDoc */
	public function getGroup() {
		return 'private';
	}
}
