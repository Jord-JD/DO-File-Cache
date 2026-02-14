<?php

namespace JordJD\DOFileCache\Tests;

use PHPUnit\Framework\TestCase;

final class BasicTest extends TestCase
{
    private $cache = null;

    protected function setUp(): void
    {
        $this->cache = new \JordJD\DOFileCache\DOFileCache();
    }

    public function testSettingInvalidCacheConfig()
    {
        $this->assertFalse($this->cache->changeConfig('invalid_data'));
    }

    public function testDelete()
    {
        $stored = 'Mary had a little lamb.';

        $key = __FUNCTION__;
        $this->cache->set($key, $stored, strtotime('+ 1 day'));

        $this->assertEquals($stored, $this->cache->get($key));

        $this->cache->delete($key);

        $this->assertFalse($this->cache->get($key));
    }

    public function testFlush()
    {
        $stored = 'Mary had a little lamb.';

        $key = __FUNCTION__;
        $this->cache->set($key, $stored, strtotime('+ 1 day'));

        $this->assertEquals($stored, $this->cache->get($key));

        $this->cache->flush($key);

        $this->assertFalse($this->cache->get($key));
    }

    public function testIncrementAndDecrement()
    {
        $stored = 1;

        $key = __FUNCTION__;
        $this->cache->set($key, $stored, strtotime('+ 1 day'));

        $retrieved = $this->cache->get($key);

        $this->assertEquals($stored, $retrieved);

        $this->cache->increment($key);

        $this->assertEquals(2, $this->cache->get($key));

        $this->cache->increment($key, 5);

        $this->assertEquals(7, $this->cache->get($key));

        $this->cache->decrement($key);

        $this->assertEquals(6, $this->cache->get($key));

        $this->cache->decrement($key, 10);

        $this->assertEquals(-4, $this->cache->get($key));
    }

    public function testReplace()
    {
        $stored = 'Mary had a little lamb.';
        $stored2 = 'Mary had a big dog.';

        $key = __FUNCTION__;

        $this->cache->replace($key, $stored, strtotime('+ 1 day'));

        $this->assertFalse($this->cache->get($key));

        $this->cache->set($key, $stored, strtotime('+ 1 day'));

        $this->assertEquals($stored, $this->cache->get($key));

        $this->cache->replace($key, $stored2, strtotime('+ 1 day'));

        $this->assertEquals($stored2, $this->cache->get($key));
    }

    public function testSetCacheThatHasAlreadyExpired()
    {
        $stored = 'Mary had a little lamb.';

        $key = __FUNCTION__;
        $this->cache->set($key, $stored, strtotime('- 1 day'));

        $this->assertFalse($this->cache->get($key));
    }

    public function testSetCacheWithoutExpiryTime()
    {
        $stored = 'Mary had a little lamb.';

        $key = __FUNCTION__;
        $this->cache->set($key, $stored);

        $this->assertEquals($stored, $this->cache->get($key));
    }

    public function testGetNonexistentCache()
    {
        $key = __FUNCTION__;
        $this->cache->get($key);

        $this->assertFalse($this->cache->get($key));
    }

    public function testIncrementNonExistentCache()
    {
        $key = __FUNCTION__;
        $this->assertFalse($this->cache->increment($key));
    }

    public function testDecrementNonExistentCache()
    {
        $key = __FUNCTION__;
        $this->assertFalse($this->cache->decrement($key));
    }

    public function testSetExpiryInSeconds()
    {
        $key = __FUNCTION__;
        $this->assertTrue($this->cache->set($key, 'test', 1));
    }

    public function testExpiryOfCache()
    {
        $stored = 'expiry_test_value';

        $key = __FUNCTION__;
        $this->cache->set($key, $stored, 2);

        $this->assertEquals($stored, $this->cache->get($key));

        if (getenv('TRAVIS') == 'true') {
            $this->markTestSkipped('Travis CI does not seem to sleep correctly, so cache expiry can not be tested correctly.');
        }

        sleep(2);

        $this->assertFalse($this->cache->get($key));
    }
}
