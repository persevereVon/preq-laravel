<?php

namespace Per3evere\Preq;

use Illuminate\Contracts\Cache\Repository as CacheContract;
use Per3evere\Preq\Contract\StateStorage as StateStorageContract;

class IlluminateStateStorage implements StateStorageContract
{
    const BUCKET_EXPIRE_SECONDS = 120;

    const CACHE_PREFIX = 'preq';

    const OPENED_NAME = 'opened';

    const SINGLE_TEST_BLOCKED = 'single_test_blocked';

    /**
     * The cache repository contract.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * @var string
     */
    protected $tag = 'preq';

    public function __construct(CacheContract $cache)
    {
        $this->cache = clone $cache;
    }

    protected function prefix($name)
    {
        if (method_exists($this->cache, 'setPrefix')) {
            $this->cache->setPrefix('');
        }
        return self::CACHE_PREFIX . '_' . $name;
    }

    /**
     * 返回给定的 bucket 计数值.
     *
     * @return void
     */
    public function getBucket($commandKey, $type, $index)
    {
        $bucketName = $this->prefix($commandKey . '_' . $type . '_' . $index);
        return $this->cache->get($bucketName);
    }

    /**
     * 对给定的 bucket 增加计数.
     *
     * @return void
     */
    public function incrementBucket($commandKey, $type, $index)
    {
        $bucketName = $this->prefix($commandKey . '_' . $type . '_' . $index);

        if (! $this->cache->add($bucketName, 1, self::BUCKET_EXPIRE_SECONDS)) {
            $this->cache->increment($bucketName);
        }
    }

    /**
     * 如果存在该 bucket，重置计数为 0.
     *
     * @return void
     */
    public function resetBucket($commandKey, $type, $index)
    {
        $bucketName = $this->prefix($commandKey . '_' . $type . '_' . $index);

        if ($this->cache->has($bucketName)) {
            $this->cache->put($bucketName, 0, self::BUCKET_EXPIRE_SECONDS);
        }
    }

    /**
     * 标记给出的熔断器为开启状态
     *
     * @return void
     */
    public function openCircuit($commandKey, $sleepingWindowInMilliseconds, $closeCircuitBreakerInSeconds)
    {
        $openedKey = $this->prefix($commandKey . self::OPENED_NAME);
        $singleTestFlagKey = $this->prefix($commandKey . self::SINGLE_TEST_BLOCKED);

        $this->cache->put($openedKey, true, $closeCircuitBreakerInSeconds);

        $sleepingWindowInSeconds = ceil($sleepingWindowInMilliseconds / 1000);
        $this->cache->add($singleTestFlagKey, true, $sleepingWindowInSeconds);
    }

    /**
     * 判断单一测试是否允许.
     *
     * @return void
     */
    public function allowSingleTest($commandKey, $sleepingWindowInMilliseconds)
    {
        $singleTestFlagKey = $this->prefix($commandKey . self::SINGLE_TEST_BLOCKED);

        $sleepingWindowInSeconds = ceil($sleepingWindowInMilliseconds / 1000);

        return (boolean) $this->cache->add($singleTestFlagKey, true, $sleepingWindowInSeconds);
    }

    /**
     * 判断熔断器是否打开
     *
     * @return void
     */
    public function isCircuitOpen($commandKey)
    {
        $openedKey = $this->prefix($commandKey . self::OPENED_NAME);

        return (boolean) $this->cache->get($openedKey);
    }

    /**
     * 标记电路关闭.
     *
     * @return void
     */
    public function closeCircuit($commandKey)
    {
        $openedKey = $this->prefix($commandKey . self::OPENED_NAME);

        $this->cache->delete($openedKey);
    }
}
