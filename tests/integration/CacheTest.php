<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/services/cache/CacheManager.php';

class CacheTest extends TestCase
{
    private CacheManager $cache;

    protected function setUp(): void
    {
        $this->cache = new CacheManager();
        $this->cache->flush(); // Clear cache before each test
    }

    protected function tearDown(): void
    {
        $this->cache->flush(); // Clean up after tests
    }

    public function testSetAndGet()
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->cache->set($key, $value);
        $result = $this->cache->get($key);

        $this->assertEquals($value, $result);
    }

    public function testGetNonExistentKey()
    {
        $result = $this->cache->get('non_existent_key');

        $this->assertNull($result);
    }

    public function testGetWithDefault()
    {
        $result = $this->cache->get('non_existent_key', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    public function testDelete()
    {
        $key = 'test_key';
        $this->cache->set($key, 'value');

        $this->assertTrue($this->cache->delete($key));
        $this->assertNull($this->cache->get($key));
    }

    public function testHas()
    {
        $key = 'test_key';

        $this->assertFalse($this->cache->has($key));

        $this->cache->set($key, 'value');

        $this->assertTrue($this->cache->has($key));
    }

    public function testExpiration()
    {
        $key = 'expiring_key';
        $this->cache->set($key, 'value', 1); // 1 second TTL

        $this->assertEquals('value', $this->cache->get($key));

        sleep(2); // Wait for expiration

        $this->assertNull($this->cache->get($key));
    }

    public function testRemember()
    {
        $key = 'remember_key';
        $called = 0;

        $callback = function() use (&$called) {
            $called++;
            return 'computed_value';
        };

        $result1 = $this->cache->remember($key, $callback, 60);
        $this->assertEquals('computed_value', $result1);
        $this->assertEquals(1, $called);

        // Second call should use cached value
        $result2 = $this->cache->remember($key, $callback, 60);
        $this->assertEquals('computed_value', $result2);
        $this->assertEquals(1, $called); // Callback not called again
    }

    public function testIncrement()
    {
        $key = 'counter';

        $this->cache->increment($key);
        $this->assertEquals(1, $this->cache->get($key));

        $this->cache->increment($key, 5);
        $this->assertEquals(6, $this->cache->get($key));
    }

    public function testDecrement()
    {
        $key = 'counter';
        $this->cache->set($key, 10);

        $this->cache->decrement($key);
        $this->assertEquals(9, $this->cache->get($key));

        $this->cache->decrement($key, 5);
        $this->assertEquals(4, $this->cache->get($key));
    }

    public function testFlush()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $this->cache->flush();

        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
    }

    public function testCacheComplexData()
    {
        $key = 'complex_data';
        $value = [
            'user' => [
                'id' => 1,
                'name' => 'John Doe',
                'roles' => ['admin', 'user']
            ],
            'metadata' => [
                'created_at' => time(),
                'tags' => ['tag1', 'tag2']
            ]
        ];

        $this->cache->set($key, $value);
        $result = $this->cache->get($key);

        $this->assertEquals($value, $result);
    }

    public function testGetStats()
    {
        $stats = $this->cache->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('driver', $stats);
    }
}
