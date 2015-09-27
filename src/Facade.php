<?php

namespace Cronario;

final class Facade
{
    /**
     * @var array
     */
    protected static $producers = [];

    /**
     * @param Producer $producer
     *
     * @throws Exception\FacadeException
     */
    public static function addProducer(Producer $producer)
    {
        $appId = $producer->getAppId();

        if (array_key_exists($appId, static::$producers)) {
            throw new Exception\FacadeException("Producer with {$appId} already exists!");
        }

        static::$producers[$appId] = $producer;
    }

    /**
     * @param string $appId
     *
     * @return Producer|null
     * @throws Exception\FacadeException
     */
    public static function getProducer($appId = Producer::DEFAULT_APP_ID)
    {
        if (null === $appId) {
            $appId = Producer::DEFAULT_APP_ID;
        }

        if (!array_key_exists($appId, static::$producers)) {
            throw new Exception\FacadeException("Producer with appId: '{$appId}' not exists yet!");
        }

        return static::$producers[$appId];
    }

    /**
     * @return bool
     */
    public static function cleanProducers()
    {
        static::$producers = [];

        return true;
    }

    /**
     * @param $appId
     *
     * @return Storage\StorageInterface
     * @throws Exception\FacadeException
     */
    public static function getStorage($appId)
    {
        return static::getProducer($appId)->getStorage();
    }


    // region PRODUCERS STATS *******************************************************************

    /**
     * @return array
     */
    public static function getProducersStats()
    {
        $producersStats = [];
        foreach (static::$producers as $appId => $producer) {
            /** @var $producer Producer */
            $producersStats[$appId] = $producer->getStats();
        }

        return $producersStats;
    }

    /**
     * @return array
     */
    public static function getQueuesStats()
    {
        $queuesStats = [];
        foreach (static::$producers as $appId => $producer) {
            /** @var $producer Producer */
            $queuesStats[$appId] = $producer->getQueue()->getStats();
        }

        return $queuesStats;
    }

    /**
     * @return array
     */
    public static function getJobsReserved()
    {
        $jobsReserved = [];
        foreach (static::$producers as $appId => $producer) {
            /** @var $producer Producer */
            $jobsReserved[$appId] = $producer->getQueue()->getJobReserved();
        }

        return $jobsReserved;
    }


    /**
     * @return array
     */
    public static function getManagersStats()
    {

        $managersStats = [];
        foreach (static::$producers as $appId => $producer) {

            /** @var $producer Producer */
            $redis = $producer->getRedis();
            $keys = [
                'stat' => $redis->keys(Manager::REDIS_NS_STATS . '*'),
                'live' => $redis->keys(Manager::REDIS_NS_LIVE . '*')
            ];

            foreach ($keys as $type => $statsKeys) {
                foreach ($statsKeys as $statsKey) {
                    $parse = Manager::parseManagerStatKey($statsKey);
                    if ($appId != $parse['appId']) {
                        continue;
                    }

                    $statsItemData = $redis->hgetall($statsKey);
                    $statsItemData['workerClass'] = $parse['workerClass'];
                    $statsItemData['appId'] = $parse['appId'];
                    $managersStats[$parse['appId']][$type][] = $statsItemData;
                }
            }

        }

        return $managersStats;
    }

    // endregion ****************************************************************************
}