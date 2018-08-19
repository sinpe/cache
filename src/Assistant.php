<?php

namespace Sinpe\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Class Assistant.
 *
 * 存储缓存的key值，使得缓存可管理，比如可用于缓存手工清除
 *
 * @author    wupinglong <18222544@qq.com>
 * @copyright 2018 Sinpe, Inc.
 *
 * @see      http://www.sinpe.com/
 */
class Assistant
{
    /**
     * 格式化标签的回调.
     *
     * @var Closure
     */
    private static $_normalize;

    /**
     * The cache manager.
     *
     * @var \Sinpe\Cache\Psr16Manager
     */
    protected $manager;

    /**
     * The tags.
     *
     * @var array
     */
    protected $tags;

    /**
     * The items.
     *
     * @var array
     */
    protected $items = [];

    /**
     * 支持自定义格式化tag如何转换为key.
     *
     * @param Closure $normalize 匿名函数
     */
    public static function setNormalize(Closure $normalize)
    {
        static::$_normalize = $normalize;
    }

    /**
     * Set the cache manager.
     *
     * @param \Sinpe\Cache\Psr16Manager $manager 缓存管理
     * @param string|array              $tags    标签
     */
    public function __construct($manager, $tags)
    {
        if (!$manager instanceof Psr16Manager) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cache must be "%s", "%s" given',
                    CacheInterface::class,
                    is_object($manager) ? get_class($manager) : gettype($manager)
                )
            );
        }

        $this->manager = $manager;

        if (!is_string($tags) && !is_array($tags)) {
            throw new InvalidArgumentException(
                i18n(
                    'Tags must be string or string[], "%s" given',
                    gettype($tags)
                )
            );
        }

        $this->tags = (array) $tags;

        foreach ($this->tags as $tag) {
            $this->items[$tag] = $this->_getItemsByTag($tag);
        }
    }

    /**
     * Flush the cache.
     *
     * @return static
     */
    public function flushCache()
    {
        foreach ($this->tags as $tag) {
            $this->_removeCacheDatas($tag);
        }

        $this->items = [];

        return $this;
    }

    /**
     * Save the collection to cache.
     *
     * @return static
     */
    public function save()
    {
        foreach ($this->tags as $tag) {
            $this->_keepKeys($tag);
        }

        return $this;
    }

    /**
     * Add cached key.
     *
     * @param string $key 缓存的key
     *
     * @return static
     */
    public function add(string $key)
    {
        foreach ($this->items as  &$items) {
            array_push($items, $key);
        }

        return $this;
    }

    /**
     * Remove cached key.
     *
     * @param string $key 缓存的key
     *
     * @return static
     */
    public function remove(string $key)
    {
        foreach ($this->items as  &$items) {
            if ($pos = array_search($key, $items)) {
                array_splice($items, $pos, 1);
            }
        }

        return $this;
    }

    /**
     * 获取缓存key.
     *
     * @param string $tag 标签
     *
     * @return array
     */
    private function _getItemsByTag($tag)
    {
        $key = $this->normalizeTagForKey($tag);

        $result = $this->manager->get($key);
        // 缓存没有或者过期
        if (is_null($result)) {
            try {
                $result = Entity::where('key', '=', $key)->value('items');
            } catch (\Exception $e) {
                if ($this->manager->debug()) {
                    throw $e;
                } else {
                    $logger = $this->manager->logger();
                    if ($logger) {
                        $logger->warning($e->getMessage(), ['tag' => $tag, 'key' => $key]);
                    }
                }
            }
        }

        return is_null($result) ? [] : $result;
    }

    /**
     * Flush the cache data.
     *
     * @param string $tag 标签
     *
     * @return static
     */
    private function _removeCacheDatas($tag)
    {
        // 删除缓存的数据
        $this->manager->deleteMultiple($this->items[$tag]);

        $key = $this->normalizeTagForKey($tag);
        // 删除缓存键
        $this->manager->delete($key);
        // 删除数据库存储的键
        try {
            Entity::where('key', '=', $key)->delete();
        } catch (\Exception $e) {
            if ($this->manager->debug()) {
                throw $e;
            } else {
                $logger = $this->manager->logger();
                if ($logger) {
                    $logger->warning($e->getMessage(), ['tag' => $tag, 'key' => $key]);
                }
            }
        }

        return $this;
    }

    /**
     * 永久存储key.
     *
     * @param string $tag 标签
     *
     * @return static
     */
    private function _keepKeys($tag)
    {
        $key = $this->normalizeTagForKey($tag);
        $items = array_unique($this->items[$tag]);

        // 存入缓存
        $this->manager->set($key, $items, 999999999);
        // 永久存入数据库
        try {
            Entity::updateOrCreate(
                ['key' => $key],
                ['items' => $items]
            );
        } catch (\Exception $e) {
            // 不做任何处理
            if ($this->manager->debug()) {
                throw $e;
            } else {
                $logger = $this->manager->logger();
                if ($logger) {
                    $logger->warning($e->getMessage(), ['tag' => $tag, 'key' => $key]);
                }
            }
        }

        return $this;
    }

    /**
     * 格式化标签作为缓存key.
     *
     * @param string $tag 标签
     *
     * @return string
     */
    protected function normalizeTagForKey($tag)
    {
        if (!static::$_normalize) {
            static::$_normalize = function ($tag) {
                return 'cache::tags.'.$tag;
            };
        }

        return static::$_normalize($tag);
    }
}
