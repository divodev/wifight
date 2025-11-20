<?php
/**
 * WiFight ISP System - Cache Manager
 *
 * Provides caching layer with file and Redis support
 */

class CacheManager {
    private $driver;
    private $prefix = 'wifight:';
    private $defaultTTL = 300; // 5 minutes

    public function __construct() {
        $cacheDriver = getenv('CACHE_DRIVER') ?: 'file';

        if ($cacheDriver === 'redis' && class_exists('Redis')) {
            $this->driver = $this->initRedis();
        } else {
            $this->driver = $this->initFileCache();
        }
    }

    /**
     * Get cached value
     */
    public function get(string $key) {
        $key = $this->prefix . $key;

        if ($this->driver instanceof Redis) {
            $value = $this->driver->get($key);
            return $value !== false ? json_decode($value, true) : null;
        } else {
            return $this->driver['get']($key);
        }
    }

    /**
     * Set cache value
     */
    public function set(string $key, $value, int $ttl = null) {
        $key = $this->prefix . $key;
        $ttl = $ttl ?? $this->defaultTTL;

        if ($this->driver instanceof Redis) {
            $this->driver->setex($key, $ttl, json_encode($value));
        } else {
            $this->driver['set']($key, $value, $ttl);
        }

        return true;
    }

    /**
     * Delete cached value
     */
    public function delete(string $key) {
        $key = $this->prefix . $key;

        if ($this->driver instanceof Redis) {
            return $this->driver->del($key) > 0;
        } else {
            return $this->driver['delete']($key);
        }
    }

    /**
     * Check if key exists
     */
    public function has(string $key) {
        $key = $this->prefix . $key;

        if ($this->driver instanceof Redis) {
            return $this->driver->exists($key) > 0;
        } else {
            return $this->driver['has']($key);
        }
    }

    /**
     * Clear all cache
     */
    public function flush() {
        if ($this->driver instanceof Redis) {
            return $this->driver->flushDB();
        } else {
            return $this->driver['flush']();
        }
    }

    /**
     * Remember: Get from cache or execute callback and cache result
     */
    public function remember(string $key, callable $callback, int $ttl = null) {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Cache query results
     */
    public function cacheQuery(string $key, PDOStatement $stmt, int $ttl = null) {
        return $this->remember($key, function() use ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, $ttl);
    }

    /**
     * Initialize Redis cache
     */
    private function initRedis() {
        try {
            $redis = new Redis();
            $redis->connect(
                getenv('REDIS_HOST') ?: '127.0.0.1',
                getenv('REDIS_PORT') ?: 6379
            );

            if ($password = getenv('REDIS_PASSWORD')) {
                $redis->auth($password);
            }

            $redis->select((int)(getenv('REDIS_DB') ?: 0));

            return $redis;
        } catch (Exception $e) {
            error_log('Redis connection failed: ' . $e->getMessage());
            return $this->initFileCache();
        }
    }

    /**
     * Initialize file-based cache
     */
    private function initFileCache() {
        $cacheDir = __DIR__ . '/../../../storage/cache/';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        return [
            'get' => function($key) use ($cacheDir) {
                $file = $cacheDir . md5($key) . '.cache';

                if (!file_exists($file)) {
                    return null;
                }

                $data = unserialize(file_get_contents($file));

                if ($data['expires'] < time()) {
                    unlink($file);
                    return null;
                }

                return $data['value'];
            },

            'set' => function($key, $value, $ttl) use ($cacheDir) {
                $file = $cacheDir . md5($key) . '.cache';
                $data = [
                    'value' => $value,
                    'expires' => time() + $ttl
                ];
                file_put_contents($file, serialize($data));
            },

            'delete' => function($key) use ($cacheDir) {
                $file = $cacheDir . md5($key) . '.cache';
                return file_exists($file) ? unlink($file) : false;
            },

            'has' => function($key) use ($cacheDir) {
                $file = $cacheDir . md5($key) . '.cache';

                if (!file_exists($file)) {
                    return false;
                }

                $data = unserialize(file_get_contents($file));
                return $data['expires'] >= time();
            },

            'flush' => function() use ($cacheDir) {
                $files = glob($cacheDir . '*.cache');
                foreach ($files as $file) {
                    unlink($file);
                }
                return true;
            }
        ];
    }

    /**
     * Increment counter
     */
    public function increment(string $key, int $value = 1) {
        $key = $this->prefix . $key;

        if ($this->driver instanceof Redis) {
            return $this->driver->incrBy($key, $value);
        } else {
            $current = $this->get($key) ?? 0;
            $new = $current + $value;
            $this->set($key, $new);
            return $new;
        }
    }

    /**
     * Decrement counter
     */
    public function decrement(string $key, int $value = 1) {
        return $this->increment($key, -$value);
    }

    /**
     * Get cache statistics
     */
    public function getStats() {
        if ($this->driver instanceof Redis) {
            $info = $this->driver->info();
            return [
                'driver' => 'redis',
                'keys' => $this->driver->dbSize(),
                'memory_used' => $info['used_memory_human'] ?? 'N/A',
                'uptime' => $info['uptime_in_seconds'] ?? 0,
                'hits' => $info['keyspace_hits'] ?? 0,
                'misses' => $info['keyspace_misses'] ?? 0,
            ];
        } else {
            $cacheDir = __DIR__ . '/../../../storage/cache/';
            $files = glob($cacheDir . '*.cache');
            return [
                'driver' => 'file',
                'keys' => count($files),
                'size' => array_sum(array_map('filesize', $files))
            ];
        }
    }
}
