<?php

class CM_Paging_Mock extends CM_Paging_Abstract {

    protected function _processItem($itemRaw) {
        return (int) $itemRaw;
    }
}

class CM_Paging_Mock_Gaps extends CM_Paging_Mock {

    protected function _processItem($itemRaw) {
        if ($itemRaw % 3 == 0) {
            throw new CM_Exception_Nonexistent();
        }
        return parent::_processItem($itemRaw);
    }
}

class CM_Comparable_Mock implements CM_Comparable {

    private $_value;

    public function __construct($value) {
        $this->_value = $value;
    }

    public function getValue() {
        return $this->_value;
    }

    public function equals(CM_Comparable $other = null) {
        return ($other && $this->getValue() == $other->getValue());
    }
}

class CM_PagingSource_Mock extends CM_PagingSource_Abstract {

    private $_items;

    public function __construct($min, $max) {
        $this->_items = range($min, $max);
    }

    public function getCount($offset = null, $count = null) {
        return count($this->_items);
    }

    public function getItems($offset = null, $count = null) {
        return $this->_items;
    }

    protected function _cacheKeyBase() {
        throw new CM_Exception_NotImplemented();
    }
}

class CM_PagingSource_MockStale extends CM_PagingSource_Mock {

    public function getStalenessChance() {
        return 0.5;
    }
}

class CM_Paging_Mock_Comparable extends CM_Paging_Mock {

    protected function _processItem($itemRaw) {
        return new CM_Comparable_Mock($itemRaw);
    }
}

class CM_Paging_AbstractTest extends CMTest_TestCase {

    private static $_source;

    public static function setUpBeforeClass() {
        CM_Db_Db::exec('CREATE TABLE `test` (
					`id` INT(10) unsigned NOT NULL AUTO_INCREMENT,
					`num` INT(10) NOT NULL,
					PRIMARY KEY (`id`)
				)');
        for ($i = 0; $i < 100; $i++) {
            CM_Db_Db::insert('test', array('num' => $i));
        }
        self::$_source = new CM_PagingSource_Sql('`num`', 'test');
        CM_Db_Db::exec('CREATE TABLE `test2` (
					`id` INT(10) unsigned NOT NULL AUTO_INCREMENT,
					`num` INT(10) NOT NULL,
					PRIMARY KEY (`id`)
				)');
        for ($i = 0; $i < 50; $i++) {
            CM_Db_Db::insert('test2', array('num' => $i));
            CM_Db_Db::insert('test2', array('num' => $i));
        }
    }

    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();
        CM_Db_Db::exec('DROP TABLE `test`');
        CM_Db_Db::exec('DROP TABLE `test2`');
    }

    public function testGetCount() {
        $paging = new CM_Paging_Mock(self::$_source);
        $this->assertEquals(100, $paging->getCount());
    }

    public function testGetCountGroup() {
        $paging = new CM_Paging_Mock(new CM_PagingSource_Sql('`num`', 'test2', null, null, null, '`num`'));
        $this->assertEquals(50, $paging->getCount());

        $paging = new CM_Paging_Mock(new CM_PagingSource_Sql('`num`', 'test2', 'id=1', null, null, '`num`'));
        $this->assertEquals(1, $paging->getCount());

        $paging = new CM_Paging_Mock(new CM_PagingSource_Sql('`num`', 'test2', 'id=99999', null, null, '`num`'));
        $this->assertEquals(0, $paging->getCount());
    }

    public function testSetGetPage() {
        $paging = new CM_Paging_Mock(self::$_source);
        $this->assertEquals(1, $paging->getPage());
        $this->assertEquals(0, $paging->getPageCount());

        $paging = new CM_Paging_Mock(self::$_source);
        $paging->setPage(2, 10);
        $this->assertEquals(2, $paging->getPage());
        $this->assertEquals(10, $paging->getPageCount());

        $paging->setPage(10, 10);
        $this->assertEquals(10, $paging->getPage());
        $this->assertEquals(10, $paging->getPageCount());

        $paging->setPage(11, 10);
        $this->assertEquals(10, $paging->getPage());
        $this->assertEquals(10, $paging->getPageCount());

        $paging->setPage(-1, 10);
        $this->assertEquals(1, $paging->getPage());
        $this->assertEquals(10, $paging->getPageCount());

        $paging->setPage(13, 9);
        $this->assertEquals(12, $paging->getPage());
        $this->assertEquals(12, $paging->getPageCount());

        $paging->setPage(1, 9999);
        $this->assertEquals(1, $paging->getPage());
        $this->assertEquals(1, $paging->getPageCount());
    }

    public function testSetPage() {
        $paging = new CM_Paging_Mock(self::$_source);
        $this->assertEquals(array(0, 1), $paging->setPage(1, 2)->getItems());
        $this->assertEquals(array(6, 7, 8), $paging->setPage(3, 3)->getItems());
    }

    public function testPageEmpty() {
        $paging = new CM_Paging_Mock();
        $this->assertSame(1, $paging->getPage());
        $this->assertSame(0, $paging->getPageCount());

        $paging = new CM_Paging_Mock();
        $paging->setPage(2, 10);
        $this->assertSame(1, $paging->getPage());
        $this->assertSame(0, $paging->getPageCount());

        $paging->setPage(1, 9999);
        $this->assertSame(1, $paging->getPage());
        $this->assertSame(0, $paging->getPageCount());
    }

    public function testIsEmpty() {
        $paging = new CM_Paging_Mock(self::$_source);
        $this->assertFalse($paging->isEmpty());

        $paging = new CM_Paging_Mock();
        $this->assertTrue($paging->isEmpty());
    }

    public function testGetItems() {
        $paging = new CM_Paging_Mock(self::$_source);
        $items = $paging->getItems();
        $this->assertInternalType('array', $items);
        $this->assertCount(100, $items);
        $this->assertSame(range(0, 99), $items);

        $itemsRaw = $paging->getItemsRaw();
        $this->assertInternalType('array', $itemsRaw);
        $this->assertCount(100, $itemsRaw);
        for ($i = 0; $i < 100; $i++) {
            $this->assertSame((string) $i, $itemsRaw[$i]);
        }

        $items = $paging->getItems();
        $this->assertSame(range(0, 99), $items);

        // Positive offset
        $items = $paging->getItems(5);
        $this->assertSame(range(5, 99), $items);

        $items = $paging->getItems(9999);
        $this->assertSame(array(), $items);

        // Negative offset
        $items = $paging->getItems(-5);
        $this->assertSame(range(95, 99), $items);

        $items = $paging->getItems(-9999);
        $this->assertSame(range(0, 99), $items);

        // Length
        $items = $paging->getItems(5, 3);
        $this->assertSame(range(5, 7), $items);

        $items = $paging->getItems(9999, 3);
        $this->assertSame(array(), $items);

        $items = $paging->getItems(98, 5);
        $this->assertSame(array(98, 99), $items);

        $items = $paging->getItems(-5, 3);
        $this->assertSame(range(95, 97), $items);

        $items = $paging->getItems(-5, 30);
        $this->assertSame(range(95, 99), $items);

        $items = $paging->getItems(-9999, 3);
        $this->assertSame(range(0, 2), $items);

        $items = $paging->getItems(0, 9999);
        $this->assertSame(range(0, 99), $items);

        $items = $paging->getItems(0, 3);
        $this->assertSame(range(0, 2), $items);

        // Paged
        $paging->setPage(2, 10);
        $items = $paging->getItems();
        $this->assertInternalType('array', $items);
        $this->assertCount(10, $items);
        $this->assertSame(range(10, 19), $items);

        $paging = new CM_Paging_Mock_Gaps(new CM_PagingSource_MockStale(0, 20));

        $this->assertSame(array(7, 8, 10, 11, 13, 14, 16, 17, 19, 20), $paging->getItems(-10));

        $this->assertSame(array(1, 2, 4, 5, 7, 8, 10), $paging->getItems(0, 7));
        $this->assertSame(array(10, 11, 13, 14, 16, 17, 19, 20), $paging->getItems(10));
        $this->assertSame(array(13, 14, 16, 17, 19, 20), $paging->getItems(-6));
        $this->assertSame(array(1, 2, 4, 5, 7, 8, 10, 11, 13, 14, 16, 17, 19, 20), $paging->getItems(-30));
        $this->assertSame(array(1, 2, 4, 5, 7, 8, 10, 11, 13, 14, 16, 17, 19, 20), $paging->getItems(-17));

        $paging = new CM_Paging_Mock(new CM_PagingSource_Mock(0, 20));

        $this->assertSame(range(5, 9), $paging->getItems(5, 5));
        $this->assertSame(range(15, 20), $paging->getItems(15));
        $this->assertSame(range(15, 20), $paging->getItems(15, 30));
        $this->assertSame(array(), $paging->getItems(30, 50));
        $this->assertSame(range(16, 20), $paging->getItems(-5));
        $this->assertSame(range(16, 20), $paging->getItems(-5, 30));
        $this->assertSame(range(0, 20), $paging->getItems());
    }

    public function testGetItem() {
        $paging = new CM_Paging_Mock(self::$_source);
        for ($i = 0; $i < 100; $i++) {
            $this->assertSame($i, $paging->getItem($i));
            $this->assertSame($i, $paging->getItem(-100 + $i));
        }

        $item = $paging->getItem(9999);
        $this->assertNull($item, 'Could getItem() of nonexistent index');
    }

    public function testStaleness() {
        $paging = new CM_Paging_Mock_Gaps(new CM_PagingSource_MockStale(0, 20));
        $paging->setPage(1, 10);
        $this->assertEquals(range(0, 9), $paging->getItemsRaw());
        $this->assertSame(array(1, 2, 4, 5, 7, 8, 10, 11, 13, 14), $paging->getItems());

        $paging = new CM_Paging_Mock_Gaps(new CM_PagingSource_MockStale(0, 20));
        $paging->setPage(1, 10);
        $this->assertEquals(range(0, 9), $paging->getItemsRaw());
        $this->assertSame(array(null, 1, 2, null, 4, 5, null, 7, 8, null), $paging->getItems(null, null, true));

        $paging = new CM_Paging_Mock_Gaps(new CM_PagingSource_Mock(0, 20));
        try {
            $paging->getItems();
            $this->fail('Getting stale data with a not-stale-expecting source did not throw exception');
        } catch (CM_Exception_Nonexistent $e) {
            $this->assertTrue(true);
        }
    }

    public function testFilter() {
        $paging = new CM_Paging_Mock(self::$_source);
        $paging->setPage(1, 10);
        $paging->filter(function ($item) {
            return ($item % 2 == 0);
        });
        $this->assertSame(array(0, 2, 4, 6, 8, 10), $paging->getItems());

        $paging = new CM_Paging_Mock_Gaps(new CM_PagingSource_MockStale(0, 20));
        $paging->setPage(1, 10);
        $paging->filter(function ($item) {
            if (is_null($item)) {
                throw new CM_Exception_Invalid();
            }
            return ($item % 4 != 0);
        });
        $this->assertSame(array(1, 2, 5, 7, 10, 11, 13, 14, 17, 19), $paging->getItems());
        try {
            $this->assertSame(array(null, 1, 2, null, 5, null, 7, null, 10, 11), $paging->getItems(null, null, true));
        } catch (CM_Exception_Invalid $ex) {
            $this->fail('Applying filters to null items');
        }
    }

    public function testExclude() {
        $paging = new CM_Paging_Mock(self::$_source);
        $paging->setPage(1, 10);
        $paging->exclude(1);
        $paging->exclude(array(3, 5));
        $this->assertSame(array(0, 2, 4, 6, 7, 8, 9, 10), $paging->getItems());
        $this->assertEquals(0, $paging->getItem(0));
        $this->assertEquals(2, $paging->getItem(1));

        $paging->exclude(2);
        $this->assertSame(array(0, 4, 6, 7, 8, 9, 10), $paging->getItems());

        $paging = new CM_Paging_Mock_Comparable(new CM_PagingSource_Mock(1, 5));
        $paging->exclude(new CM_Comparable_Mock(2));
        $expected = array(new CM_Comparable_Mock(1), new CM_Comparable_Mock(3), new CM_Comparable_Mock(4), new CM_Comparable_Mock(5));
        $this->assertEquals($expected, $paging->getItems());

        $paging = new CM_Paging_Mock_Comparable(new CM_PagingSource_Mock(1, 5));
        $paging->exclude(array(new CM_Comparable_Mock(3), new CM_Comparable_Mock(2)));
        $expected = array(new CM_Comparable_Mock(1), new CM_Comparable_Mock(4), new CM_Comparable_Mock(5));
        $this->assertEquals($expected, $paging->getItems());
    }

    public function testIterator() {
        $paging = new CM_Paging_Mock(self::$_source);

        $paging->rewind();

        for ($i = 0; $i < 30; $i++) {
            $this->assertTrue($paging->valid());
            $this->assertSame($paging->current(), $i);
            $this->assertSame($paging->key(), $i);
            $paging->next();
        }

        $paging->_change();
        $this->assertFalse($paging->valid());
    }

    public function testGetItemsEvenlyDistributed() {
        $paging = new CM_Paging_Mock(new CM_PagingSource_Mock(1, 10));
        $items = $paging->getItemsEvenlyDistributed(7);
        $this->assertSame(7, count($items));
        $this->assertSame(array(1, 3, 4, 6, 7, 9, 10), $items);

        $paging = new CM_Paging_Mock(new CM_PagingSource_Mock(1, 30));
        $items = $paging->getItemsEvenlyDistributed(6);
        $this->assertSame(6, count($items));
        $this->assertSame(array(1, 7, 13, 18, 24, 30), $items);

        $items = $paging->getItemsEvenlyDistributed(2);
        $this->assertSame(2, count($items));
        $this->assertSame(array(1, 30), $items);

        $items = $paging->getItemsEvenlyDistributed(1);
        $this->assertSame(1, count($items));
        $this->assertSame(array(1), $items);

        $items = $paging->getItemsEvenlyDistributed(0);
        $this->assertSame(0, count($items));
        $this->assertSame(array(), $items);
    }

    public function testGetSum() {
        $paging = new CM_Paging_Mock(new CM_PagingSource_Array(array(array('id' => 1, 'type' => 1, 'amount' => 1),
            array('id' => 1, 'type' => 1, 'amount' => 2), array('id' => 1, 'type' => 1, 'amount' => 3),
            array('id' => 1, 'type' => 1, 'amount' => 4))));
        $this->assertSame(10, $paging->getSum('amount'));
        $this->assertSame(10, $paging->setPage(0, 1)->getSum('amount'));
        $paging = new CM_Paging_Mock(new CM_PagingSource_Array(array(array('id' => 1, 'type' => 1, 'amount' => 1),
            array('id' => 1, 'type' => 1, 'amount' => 2), array('id' => 1, 'type' => 1, 'amount' => 3), array('id' => 1, 'type' => 1))));
        try {
            $paging->getSum('amount');
        } catch (CM_Exception_Invalid $ex) {
            $this->assertContains('CM_Paging_Mock has no field `amount`.', $ex->getMessage());
        }
        $this->assertSame(4, $paging->getSum('id'));
    }

    public function testGetItemsRawTree() {
        $paging = new CM_Paging_Mock(new CM_PagingSource_Array(array(array('id' => 1, 'type' => 1, 'amount' => 1),
            array('id' => 2, 'type' => 1, 'amount' => 2), array('id' => 3, 'type' => 1, 'amount' => 3),
            array('id' => 4, 'type' => 1, 'amount' => 4))));
        $this->assertSame(array(1 => array('type' => 1, 'amount' => 1), 2 => array('type' => 1, 'amount' => 2),
                                3 => array('type' => 1, 'amount' => 3), 4 => array('type' => 1, 'amount' => 4)), $paging->getItemsRawTree());
    }

    public function testFlattenItems() {
        $paging = $this->getMockForAbstractClass('CM_Paging_Abstract', array(new CM_PagingSource_Array(range(0, 20))));
        $pagingSource = new CM_PagingSource_PagingGroup($paging, function ($value) {
            if (10 == $value) {
                return 'keyValue';
            }
            return $value % 10 . 'keyValue';
        });

        /** @var CM_Paging_Abstract $pagingGroup */
        $pagingGroup = $this->getMockForAbstractClass('CM_Paging_Abstract', array($pagingSource));
        $this->assertSame(10, $pagingGroup->getItem(10));

        $pagingGroup = $this->getMockForAbstractClass('CM_Paging_Abstract', array($pagingSource));
        $pagingGroup->setFlattenItems(false);
        $this->assertSame(array(10), $pagingGroup->getItem(10));
    }

    public function testGetPageSize() {
        $paging = new CM_Paging_Mock(new CM_PagingSource_Array(array(1, 2)));
        $this->assertNull($paging->getPageSize());
        $paging->setPage(1, 10);
        $this->assertSame(10, $paging->getPageSize());
    }

    public function testGetItemRand() {
        $data = range(1, 30);
        $paging = new CM_Paging_Mock(new CM_PagingSource_Array($data));

        $this->assertContains($paging->getItemRand(), $data);
        $this->assertSame($data, $paging->getItems());
        $this->assertNull($paging->getPageSize());
        $this->assertSame(1, $paging->getPage());

        $paging = new CM_Paging_Mock(new CM_PagingSource_Array(array()));
        $this->assertNull($paging->getItemRand());

        $paging = new CM_Paging_Mock(new CM_PagingSource_Array(array(1)));
        $this->assertSame(1, $paging->getItemRand());
    }

    /**
     * @expectedException CM_Exception_Invalid
     * @expectedExceptionMessage Can't get random item on a paged Paging.
     */
    public function testGetItemRandInvalidPaged() {
        $data = range(1, 30);
        $paging = new CM_Paging_Mock(new CM_PagingSource_Array($data));

        $paging->setPage(3, 10);
        $paging->getItemRand();
    }

    public function testGetItemRandMean() {
        $N = 10;
        $data = range(0, $N - 1);
        $paging = new CM_Paging_Mock(new CM_PagingSource_Array($data));

        $outOfBounds = false;
        foreach (array(null, .5, .4, .3, .2, .1) as $mean) {
            $count = 0;
            $n = 100;
            for ($i = 0; $i < $n; $i++) {
                $item = $paging->getItemRand($mean);
                if (!in_array($item, $data, true)) {
                    $outOfBounds = true;
                }
                $count += $item;
            }
            if (null === $mean) {
                $mean = .5;
            }
            $meanExpected = ($N - 1) * $mean;
            $maxDeviation = $meanExpected / 2;
            $this->assertEquals($meanExpected, $count / $n, '', $maxDeviation);
        }
        $this->assertFalse($outOfBounds);

        $paging = new CM_Paging_Mock(new CM_PagingSource_Array(array()));
        $this->assertNull($paging->getItemRand(.1));

        $paging = new CM_Paging_Mock(new CM_PagingSource_Array(array(1)));
        $this->assertSame(1, $paging->getItemRand(.1));
    }

    /**
     * @expectedException CM_Exception_Invalid
     * @expectedExceptionMessage Normalized mean item index must be positive.
     */
    public function testGetItemRandInvalidMeanNegative() {
        $data = range(1, 30);
        $paging = new CM_Paging_Mock(new CM_PagingSource_Array($data));

        $paging->getItemRand(-.1);
    }

    /**
     * @expectedException CM_Exception_NotImplemented
     * @expectedExceptionMessage Normalized mean item index cannot be greater than .5.
     */
    public function testGetItemRandInvalidMeanTooBig() {
        $data = range(1, 30);
        $paging = new CM_Paging_Mock(new CM_PagingSource_Array($data));

        $paging->getItemRand(.6);
    }

    public function testGetCountGetItemsProxyToSourceWithPagination() {
        $pagingSource = $this->getMockBuilder('CM_PagingSource_Abstract')->setMethods(array('getCount', 'getItems'))->getMockForAbstractClass();
        $pagingSource->expects($this->once())->method('getCount')->with($this->identicalTo(10), $this->identicalTo(5))->will($this->returnValue(100));
        $pagingSource->expects($this->once())->method('getItems')->with($this->identicalTo(10), $this->identicalTo(5))->will($this->returnValue(range(1, 5)));

        /** @var CM_Paging_Abstract $paging */
        $paging = $this->getMockBuilder('CM_Paging_Abstract')->setConstructorArgs(array($pagingSource))->getMockForAbstractClass();
        $paging->setPage(3, 5);
        $paging->getCount();
        $paging->getItems();
    }

    public function testGetCountGetItemsProxyToSourceWithoutPagination() {
        $pagingSource = $this->getMockBuilder('CM_PagingSource_Abstract')->setMethods(array('getCount', 'getItems'))->getMockForAbstractClass();
        $pagingSource->expects($this->once())->method('getCount')->with($this->identicalTo(null), $this->identicalTo(null))->will($this->returnValue(100));
        $pagingSource->expects($this->once())->method('getItems')->with($this->identicalTo(null), $this->identicalTo(null))->will($this->returnValue(range(1, 100)));

        /** @var CM_Paging_Abstract $paging */
        $paging = $this->getMockBuilder('CM_Paging_Abstract')->setConstructorArgs(array($pagingSource))->getMockForAbstractClass();
        $paging->getCount();
        $paging->getItems();
    }
}
