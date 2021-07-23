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

use MediaWiki\Extension\UseResource\Module;
use ResourceLoaderTestCase;

/**
 * Test for \MediaWiki\Extension\UseResource\Module
 *
 * @group UseResource
 * @covers \MediaWiki\Extension\UseResource\Module
 */
class ModuleTest extends ResourceLoaderTestCase {
	/** @var Module */
	private $module;

	protected function setUp(): void {
		parent::setUp();
		$this->module = new Module( [
			'code' => 'console.log(7)'
		] );
	}

	public function testGetTargets() {
		$this->assertContains( 'desktop', $this->module->getTargets(), 'should target desktop.' );
		$this->assertContains( 'mobile', $this->module->getTargets(), 'should target mobile.' );
	}

	public function testGetScript() {
		$this->assertSame(
			'console.log(7)',
			$this->module->getScript( $this->getResourceLoaderContext() ),
			'should return same code that was given.'
		);
	}

	public function testGetGroup() {
		$this->assertSame( 'private', $this->module->getGroup(), 'group should be private.' );
	}
}
