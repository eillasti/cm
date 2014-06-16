<?php

class CM_Model_Splittest_UserTest extends CMTest_TestCase {

    public function tearDown() {
        CMTest_TH::clearDb();
    }

    public function testIsVariationFixture() {
        $user = CMTest_TH::createUser();

        /** @var CM_Model_Splittest_User $test */
        $test = CM_Model_Splittest_User::createStatic(array('name' => 'foo', 'variations' => array('v1', 'v2')));

        for ($i = 0; $i < 2; $i++) {
            $isVariationUser1 = $test->isVariationFixture($user, 'v1');
            $this->assertSame($isVariationUser1, $test->isVariationFixture($user, 'v1'));
        }
    }

    public function testSetConversion() {
        $user = CMTest_TH::createUser();
        $user2 = CMTest_TH::createUser();

        /** @var CM_Model_Splittest_User $test */
        $test = CM_Model_Splittest_User::createStatic(array('name' => 'bar', 'variations' => array('v1')));
        /** @var CM_Model_SplittestVariation $variation */
        $variation = $test->getVariations()->getItem(0);

        $test->isVariationFixture($user, 'v1');
        $test->isVariationFixture($user2, 'v1');
        $this->assertSame(0, $variation->getConversionCount(true));
        $test->setConversion($user);
        $this->assertSame(1, $variation->getConversionCount(true));
        $test->setConversion($user2, 2.5);
        $this->assertSame(1.75, $variation->getConversionRate(true));
    }

    public function testGetVariationFixtureEnabled() {
        $user = CMTest_TH::createUser();
        $this->assertFalse(CM_Model_Splittest_User::getVariationFixtureEnabled('foo', $user, 'bar'));
        CM_Model_Splittest_User::create('foo', ['bar']);
        CM_Cache_Local::getInstance()->flush();
        $this->assertTrue(CM_Model_Splittest_User::getVariationFixtureEnabled('foo', $user, 'bar'));
    }
}
