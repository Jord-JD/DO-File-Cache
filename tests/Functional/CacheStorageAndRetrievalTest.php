<?php

namespace JordJD\DOFileCache\Tests;

use PHPUnit\Framework\TestCase;

final class CacheStorageAndRetrievalTest extends TestCase
{
    private $cache = null;

    protected function setUp(): void
    {
        $this->cache = new \JordJD\DOFileCache\DOFileCache();
        $this->cache->changeConfig(['cacheDirectory' => __DIR__.'/Data/']);
    }

    public function testBasicString()
    {
        $stored = 'Mary had a little lamb.';

        $key = __FUNCTION__;
        $this->cache->set($key, $stored, strtotime('+ 1 day'));

        $retrieved = $this->cache->get($key);

        $this->assertEquals($stored, $retrieved);
    }

    public function testEmptyArray()
    {
        $stored = [];

        $key = __FUNCTION__;
        $this->cache->set($key, $stored, strtotime('+ 1 day'));

        $retrieved = $this->cache->get($key);

        $this->assertEquals($stored, $retrieved);
    }

    public function testNumericZero()
    {
        $stored = 0;

        $key = __FUNCTION__;
        $this->cache->set($key, $stored, strtotime('+ 1 day'));

        $retrieved = $this->cache->get($key);

        $this->assertEquals($stored, $retrieved);
    }

    public function testBooleanFalse()
    {
        $stored = false;

        $key = __FUNCTION__;
        $this->cache->set($key, $stored, strtotime('+ 1 day'));

        $retrieved = $this->cache->get($key);

        $this->assertEquals($stored, $retrieved);
        $this->assertTrue($this->cache->has($key));
    }

    public function testBooleanTrue()
    {
        $stored = true;

        $key = __FUNCTION__;
        $this->cache->set($key, $stored, strtotime('+ 1 day'));

        $retrieved = $this->cache->get($key);

        $this->assertEquals($stored, $retrieved);
    }

    public function testDeepDirectoryCreation()
    {
        $stored = 'Deep directory creation test.';

        $key = 'deep.directory.creation.test';
        $this->cache->set($key, $stored, strtotime('+ 1 day'));

        $retrieved = $this->cache->get($key);

        $this->assertEquals($stored, $retrieved);
    }

    public function testDeepDirectoryCreationTwo()
    {
        $stored = 'Deep directory creation test 2.';

        $key = 'deep.directory.creation.test2';
        $this->cache->set($key, $stored, strtotime('+ 1 day'));

        $retrieved = $this->cache->get($key);

        $this->assertEquals($stored, $retrieved);
    }

    public function testDeepDirectoryCreationWithMultipleSlashes()
    {
        $stored = 'Deep directory creation test 2.';

        $key = 'deep//directory/creation////test';
        $this->cache->set($key, $stored, strtotime('+ 1 day'));

        $retrieved = $this->cache->get($key);

        $this->assertEquals($stored, $retrieved);
    }

    public function testCompressionCanBeEnabledForExistingUncompressedData()
    {
        $key = __FUNCTION__;
        $this->cache->changeConfig(['gzipCompression' => false]);
        $this->cache->set($key, 'uncompressed');
        $this->cache->changeConfig(['gzipCompression' => true]);

        $this->assertSame('uncompressed', $this->cache->get($key));
    }

    public function testCompressionCanBeDisabledForExistingCompressedData()
    {
        $key = __FUNCTION__;
        $this->cache->changeConfig(['gzipCompression' => true]);
        $this->cache->set($key, 'compressed');
        $this->cache->changeConfig(['gzipCompression' => false]);

        $this->assertSame('compressed', $this->cache->get($key));
    }
}
