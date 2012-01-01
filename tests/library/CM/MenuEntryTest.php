<?php

require_once dirname(__FILE__) . '/../../TestCase.php';

class CM_MenuEntryTest extends TestCase {

	public static function setUpBeforeClass() {
	}

	public static function tearDownAfterClass() {
		TH::clearEnv();
	}

	public function testGetters() {
		$pageName = 'CM_Page_Mock';
		$label = 'helloworld';

		$entry = new CM_MenuEntry(array('label' => $label, 'page' => $pageName), $this->_getMenu());

		$this->assertInstanceOf($pageName, $entry->getPage());
		$this->assertEquals($label, $entry->getLabel());
	}

	public function testIsActive() {
		$pageName = 'CM_Page_Mock';
		$label = 'helloworld';

		$request = new CM_Request_Get('/test');
		$entry = new CM_MenuEntry(array('label' => $label, 'page' => $pageName), $this->_getMenu($request));
		$this->assertFalse($entry->isActive());

		$request = new CM_Request_Get('/mock');
		$entry = new CM_MenuEntry(array('label' => $label, 'page' => $pageName), $this->_getMenu($request));
		$this->assertTrue($entry->isActive());
	}

	public function testGetParent() {
		$pageName = 'CM_Page_Mock';
		$label = 'helloworld';

		$entry1 = new CM_MenuEntry(array('label' => $label, 'page' => $pageName), $this->_getMenu());

		$entry2 = new CM_MenuEntry(array('label' => $label, 'page' => $pageName), $this->_getMenu(), $entry1);

		$this->assertFalse($entry1->hasParent());
		$this->assertTrue($entry2->hasParent());
		$this->assertEquals($entry1, $entry2->getParent());
	}

	public function testGetParentFalse() {
		$pageName = 'CM_Page_Mock';
		$label = 'helloworld';

		$entry = new CM_MenuEntry(array('label' => $label, 'page' => $pageName), $this->_getMenu());
		$this->assertFalse($entry->hasParent());
	}

	public function testGetParents() {
		$pageName = 'CM_Page_Mock';
		$label = 'helloworld';

		$entry1 = new CM_MenuEntry(array('label' => $label . '1', 'page' => $pageName), $this->_getMenu());
		$entry2 = new CM_MenuEntry(array('label' => $label . '2', 'page' => $pageName), $this->_getMenu(), $entry1);
		$entry3 = new CM_MenuEntry(array('label' => $label . '3', 'page' => $pageName), $this->_getMenu(), $entry2);

		$this->assertCount(0, $entry1->getParents());
		$this->assertCount(1, $entry2->getParents());
		$this->assertCount(2, $entry3->getParents());

		$parents = $entry3->getParents();

		$this->assertEquals($entry2, $parents[1]);
		$this->assertEquals($entry1, $parents[0]);
	}

	/**
	 * @param CM_Request_Abstract|null $request
	 * @return CM_Menu
	 */
	private function _getMenu(CM_Request_Abstract $request = null) {
		$entriesData = array(
			array('label' => 'Home', 'page' => 'CM_Page_Mock2'),
			array('label' => 'Example', 'page' => 'CM_Page_Mock')
		);
		if (!$request) {
			$request = new CM_Request_Get('/test');
		}
		return new CM_Menu($entriesData, $request);
	}

}

class CM_Page_Mock extends CM_Page_Abstract {
	/**
	 * @throws CM_Exception_Nonexistent
	 * @param CM_Response_Abstract $response
	 */
	public function prepare(CM_Response_Abstract $response) {
	}
}

class CM_Page_Mock2 extends CM_Page_Abstract {
	/**
	 * @throws CM_Exception_Nonexistent
	 * @param CM_Response_Abstract $response
	 */
	public function prepare(CM_Response_Abstract $response) {
	}
}
