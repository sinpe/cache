<?php

namespace Sinpe\Cache;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Sinpe\Support\Traits\MacroAware as MacroTrait;
use Illuminate\Support\Collection;

/**
 * Psr16Manager class
 *  *
 * 缓存处理器封装类.
 *
 * @author    wupinglong <18222544@qq.com>
 * @copyright 2018 Sinpe, Inc.
 *
 * @see      http://www.sinpe.com/
 */
class Psr16Manager implements ManagerInterface
{
    use MacroTrait;

    /**
     * The cache handler.
     *
     * @var \Psr\SimpleCache\CacheInterface
     */
    protected static $handler;

    /**
     * 是否处于调试.
     *
     * @var bool
     */
    private $_debug = false;

    /**
     * 日志处理器.
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $_logger;

    /**
     * The cache tag assistant.
     *
     * @var \Sinpe\Cache\TagAssistant
     */
    protected $assistant;

    /**
     * The default number of seconds to store items.
     *
     * @var int
     */
    protected $ttl = 60;

    /**
     * 类全局设置.
     *
     * @param \Psr\SimpleCache\CacheInterface $handler 缓存处理器
     */
    public static function setHandler(CacheInterface $handler)
    {
        static::$handler = $handler;
    }

    /**
     * Create a new cache repository instance.
     *
     * @param \Psr\SimpleCache\CacheInterface $handler 缓存处理器
     */
    public function __construct(CacheInterface $handler = null)
    {
        // 对象单独设置
        if (!is_null($handler)) {
            static::setHandler($handler);
        }
    }

    /**
     * 设置调试状态
     *
     * @param bool $debug 是否调试
     *
     * @return bool
     */
    public function debug($debug = null)
    {
        if (!is_null($debug)) {
            $this->_debug = (bool) $debug;
        }

        return $this->_debug;
    }

    /**
     * 日志.
     *
     * @param \Psr\Log\LoggerInterface $logger 日志处理器
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function logger(LoggerInterface $logger = null)
    {
        if (!is_null($logger)) {
            $this->_logger = $logger;
        }

        return $this->_logger;
    }

    /**
     * Return an instance with the tags.
     *
     * @param string $tags 键
     *
     * @return static
     */
    public function withTags($tags)
    {
        $clone = clone $this;

        $clone->assistant = new TagAssistant(
            $clone,
            is_array($tags) ? $tags : func_get_args()
        );

        return $clone;
    }

    /**
     * Return 缓存助手，缓存key管理.
     *
     * @return Assistant
     */
    public function getAssistant()
    {
        return $this->assistant;
    }

    /**
     * Return an instance with the ttl.
     *
     * @param int $ttl 生命值
     *
     * @return static
     */
    public function withTTL(int $ttl)
    {
        $clone = clone $this;

        $clone->ttl = $ttl;

        return $clone;
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string $key     键
     * @param mixed  $default 默认值
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $value = static::$handler->get($this->normalized($key));
        // If we could not find the cache value, we will get
        // the default value for this cache value. This default could be a callback
        // so we will execute the value function which will resolve it if needed.
        if (is_null($value)) {
            $value = is_callable($default) ? $default() : $default;
        }

        if (static::hasMacro()) {
            $value = $this->runMacro('getAfter', [$value, $key, false]);
        }

        return $value;
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param array $keys    a list of keys that can obtained in a single operation
     * @param mixed $default default value to return for keys that do not exist
     *
     * @return iterable A list of key => value pairs.
     *                  Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if $keys is neither an array nor a Traversable,
     *                                                   or if any of the $keys are not a legal value
     */
    public function getMultiple(array $keys, $default = null)
    {
        $keyMaps = [];

        $values = static::$handler->getMultiple(
            (new Collection($keys))->map(
                // 回调
                function ($value, $key) use ($keyMaps) {
                    $keyOri = is_string($key) ? $key : $value;
                    $keyNormalized = $this->normalized($keyOri);

                    $keyMaps[$keyNormalized] = $keyOri;

                    return $keyNormalized;
                }
            )->values()->all()
        );

        $values = (new Collection($values))->mapWithKeys(
            function ($value, $key) use ($keys, $default, $keyMaps) {
                $keyOri = $keyMaps[$key];
                // If we could not find the cache value, we will get
                // the default value for this cache value. This default could be a callback
                // so we will execute the value function which will resolve it if needed.
                if (is_null($value)) {
                    // 如果keys自带有默认值
                    if (isset($keys[$keyOri])) {
                        $keyDefault = $keys[$keyOri];

                        $value = is_callable($keyDefault)
                            ? $keyDefault() : $keyDefault;
                    }

                    if (!is_null($default)) {
                        $value = is_callable($default)
                            ? $default($keyOri) : $default;
                    }
                }

                return [$keyOri => $value];
            }
        )->all();

        if (static::hasMacro()) {
            $values = $this->runMacro('getAfter', [$values, $keys, true]);
        }

        return $values;
    }

    /**
     * Store an item in the cache.
     *
     * @param string                               $key   键
     * @param mixed                                $value 值
     * @param \DateTimeInterface|\DateInterval|int $ttl   生命值，秒
     *
     * @return bool
     */
    public function set(string $key, $value, int $ttl = null)
    {
        $keyNormalized = $this->normalized($key);

        $result = static::$handler->set($keyNormalized, $value, $ttl ?? $this->ttl);

        if ($result) {
            if ($this->assistant) {
                $this->assistant->add($keyNormalized);
                $this->assistant->save();
            }
        }

        return $result;
    }

    /**
     * Store multiple items in the cache for a given number of minutes.
     *
     * @param array                                $values key、value表
     * @param \DateTimeInterface|\DateInterval|int $ttl    生命值，秒
     */
    public function setMultiple(array $values, int $ttl = null)
    {
        $valuesNormalized = [];

        foreach ($values as $key => $value) {
            $valuesNormalized[$this->normalized($key)] = $value;
        }

        $result = static::$handler->setMultiple(
            $valuesNormalized,
            $ttl ?? $this->ttl
        );

        if ($result) {
            if ($this->assistant) {
                foreach (array_keys($valuesNormalized) as $key) {
                    $this->assistant->add($key);
                }
                $this->assistant->save();
            }
        }

        return $result;
    }

    /**
     * Retrieve an item from the cache and delete it.
     *
     * @param string $key     键
     * @param mixed  $default 默认值
     *
     * @return mixed|null
     */
    public function pull(string $key, $default = null)
    {
        $result = $this->get($key, $default);

        $this->delete($key);

        return $result;
    }

    /**
     * Store an item in the cache if the key does not exist.
     *
     * @param string                               $key   键
     * @param mixed                                $value 值
     * @param \DateTimeInterface|\DateInterval|int $ttl   生命值，秒
     *
     * @return bool
     */
    public function add(string $key, $value, int $ttl = null)
    {
        // If the store has an "add" method we will call the method on the store so it
        // has a chance to override this logic. Some drivers better support the way
        // this operation should work with a total "atomic" implementation of it.
        if (method_exists(static::$handler, 'add')) {
            return static::$handler->add(
                $this->normalized($key),
                $value,
                $ttl ?? $this->ttl
            );
        }

        // If the value did not exist in the cache, we will put the value in the cache
        // so it exists for subsequent requests. Then, we will return true so it is
        // easy to know if the value gets added. Otherwise, we will return false.
        if (is_null($this->get($key))) {
            return $this->set($key, $value, $ttl ?? $this->ttl);
        }

        return false;
    }

    /**
     * Get an item from the cache, or store the default value.
     *
     * @param string                               $key      键
     * @param \Closure                             $callback 获取不到时的取值
     * @param \DateTimeInterface|\DateInterval|int $ttl      生命值
     *
     * @return mixed
     */
    public function remember(string $key, \Closure $callback, $ttl = null)
    {
        $value = $this->get($key);
        // If the item exists in the cache we will just return this immediately and if
        // not we will execute the given Closure and cache the result of that for a
        // given number of seconds so it's available for all subsequent requests.
        if (!is_null($value)) {
            return $value;
        }

        $this->set($key, $value = $callback(), $ttl ?? $this->ttl);

        return $value;
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key 缓存键
     *
     * @return bool
     */
    public function delete(string $key)
    {
        $keyNormalized = $this->normalized($key);

        $result = static::$handler->delete($keyNormalized);

        if ($result) {
            if ($this->assistant) {
                $this->assistant->remove($keyNormalized);
                $this->assistant->save();
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys)
    {
        $keysNormalized = [];

        foreach ($keys as $key) {
            $keysNormalized[] = $this->normalized($key);
        }

        $result = static::$handler->deleteMultiple($keysNormalized);

        if ($result) {
            if ($this->assistant) {
                foreach ($keysNormalized as $key) {
                    $this->assistant->remove($key);
                }
                $this->assistant->save();
            }
        }

        return $result;
    }

    /**
     * Increment/Decrement the value of an item in the cache.
     *
     * @param string $key  键
     * @param mixed  $step 改变量
     *
     * @return int|bool
     */
    public function indecrement(string $key, $step = 1)
    {
        $value = $this->get($key, 0);

        $value += $step;

        $this->set($key, $value);

        return $value;
    }

    /**
     * 对存储到缓存系统的key进行格式化，通过子类覆盖来自定义你自己的规则。
     *
     * @param string $key 键
     *
     * @return string
     */
    protected function normalized($key)
    {
        return $key;
    }

    /**
     * Call a method.
     *
     * @param string $method    调用方法
     * @param array  $arguments 调用参数
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if (method_exists(static::$handler, $method)) {
            return static::$handler->$method(...$parameters);
        }

        throw new \BadMethodCallException(
            sprintf(
                'Method "%s::%s" does not exist.',
                get_class($this),
                $method
            )
        );
    }

    /**
     * Clone cache repository instance.
     */
    public function __clone()
    {
        $this->assistant = null;
    }
}
