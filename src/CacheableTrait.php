<?php

namespace Sinpe\Cache;

/**
 * Class CacheableTrait.
 *
 * @see   http://www.sinpe.com/
 *
 * @author Sinpe, Inc.
 * @author 18222544@qq.com
 */
trait CacheableTrait
{
    /**
     * 缓存管理器.
     *
     * @var ManagerInterface
     */
    protected static $cacheManager;

    /**
     * The number of seconds.
     *
     * @var null|false|int
     */
    protected $ttl = 60;

    /**
     * 设置缓存管理器，可选调用.
     */
    public static function setCacheManager(ManagerInterface $cache)
    {
        static::$cacheManager = $cache;
    }

    /**
     * 获取缓存管理器.
     */
    public function getCacheManager()
    {
        return static::$cacheManager;
    }

    /**
     * Cache a value in the model's cache collection.
     *
     * @param $key
     * @param Closure $value
     * @param $ttl
     *
     * @return mixed
     */
    public function cache($key, \Closure $value, $ttl = null)
    {
        // $value可以是callable或是值
        if (static::$cacheManager) {
            if (!static::$cacheManager->getAssistant()) {
                static::$cacheManager = static::$cacheManager->withTags(get_class($this));
            }

            return static::$cacheManager->remember(
                $key,
                $value,
                $ttl ?: $this->ttl
            );
        } else {
            return $value();
        }
    }

    /**
     * Flush the model's cache.
     *
     * @return $this
     */
    public static function flushCache()
    {
        // $value可以是callable或是值
        if (static::$cacheManager) {
            if (!static::$cacheManager->getAssistant()) {
                static::$cacheManager = static::$cacheManager->withTags(get_called_class());
            }

            static::$cacheManager->getAssistant()->flushCache();
        }
    }
}
