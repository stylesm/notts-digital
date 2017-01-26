<?php
/**
 * Nottingham Digital events
 *
 * @link      https://github.com/pavlakis/notts-digital
 * @copyright Copyright (c) 2017 Antonios Pavlakis
 * @license   https://github.com/pavlakis/notts-digital/blob/master/LICENSE (BSD 3-Clause License)
 */
namespace NottsDigital\Adapter;

use GuzzleHttp\Client,
    Psr\Log\LoggerInterface,
    NottsDigital\Event\GroupInfo,
    NottsDigital\Event\EventEntity,
    NottsDigital\Event\NullGroupInfo,
    NottsDigital\Event\NullEventEntity,
    NottsDigital\Adapter\AdapterInterface,
    NottsDigital\Event\EventEntityCollection;

/**
 * Class MeetupAdapter
 * @package NottsDigital\Adapter
 */
class MeetupAdapter implements AdapterInterface
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var array
     */
    protected $uris;

    /**
     * @var array
     */
    protected $groupConfig = [];

    /**
     * @var EventEntityCollection
     */
    protected $eventEntityCollection;

    /**
     * @var GroupInfo
     */
    protected $groupInfo;

    protected $logger;

    /**
     * MeetupAdapter constructor.
     * @param Client $client
     * @param $apiKey
     * @param $baseUrl
     * @param $uris
     * @param $config
     * @param EventEntityCollection $eventEntityCollection
     * @param LoggerInterface $logger
     */
    public function __construct(Client $client, $apiKey, $baseUrl, $uris, $config, EventEntityCollection $eventEntityCollection, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->uris = $uris;
        $this->config = $config;
        $this->eventEntityCollection = $eventEntityCollection;
        $this->logger = $logger;
    }

    /**
     * @param string $group
     * @return array
     */
    public function fetch($group)
    {
        if (!isset($this->config[$group])) {
            return [];
        }

        $this->loadEventInfo($group);

        $this->loadGroupInfo($group);
    }

    /**
     * @param $group
     * @throws \Exception
     */
    protected function loadEventInfo($group)
    {
        $groupUrlName = $this->config[$group]['group_urlname'];

        try {

            $response = $this->client->get($this->baseUrl . sprintf($this->uris['events'], $groupUrlName, $this->apiKey));
            $events = json_decode($response->getBody()->getContents(), true);

            if (!isset($events['results']) || empty($events['results'])) {
                throw new \Exception('No events found.');
            }

            $this->groupConfig = $this->config[$group];

            if (isset($this->config[$group]['match']) && isset($this->config[$group]['match']['name'])) {
                $this->eventEntityCollection->add(
                    new EventEntity($this->getByNameStringMatch($events['results'], $this->config[$group]['match']['name']))
                );
            } else {
                
                $this->eventEntityCollection->add(new EventEntity($events['results'][0], $this->groupConfig));

                if (isset($events['results'][1])) {
                    $this->eventEntityCollection->add(new EventEntity($events['results'][1], $this->groupConfig));
                }
            }

        } catch (\Exception $e) {
            $this->eventEntityCollection->add(new NullEventEntity());
            $this->logger->error($e->getMessage());
        }

    }

    /**
     * @param $group
     */
    protected function loadGroupInfo($group)
    {
        $groupUrlName = $this->config[$group]['group_urlname'];

        try {

            $response = $this->client->get($this->baseUrl . sprintf($this->uris['groups'], $groupUrlName, $this->apiKey));

            $groupInfo = json_decode($response->getBody()->getContents(), true);

            if (isset($groupInfo['results']) && !empty($groupInfo['results'])) {
                $groupInfo = $groupInfo['results'][0];

                $this->groupInfo = new GroupInfo($groupInfo['name'], $groupInfo['description'], $groupInfo['group_photo']['highres_link']);
            } else {
                $this->groupInfo = new NullGroupInfo();
            }

        } catch (\Exception $e) {
            $this->groupInfo = new NullGroupInfo();
        }
    }

    /**
     * @param $events
     * @param $nameMatch
     * @return bool
     */
    protected function getByNameStringMatch($events, $nameMatch)
    {
        foreach ($events as $event) {
            if (strpos($event['name'], $nameMatch) !== false) {
                return $event;
            }
        }
        return [];
    }

    /**
     * @return string
     */
    public function getGroupName()
    {
        return $this->groupInfo->getGroupName();
    }

    /**
     * @return string
     */
    public function getGroupDescription()
    {
        return $this->groupInfo->getGroupDescription();
    }

    /**
     * @return string
     */
    public function getGroupPhoto()
    {
        return $this->groupInfo->getGroupPhoto();
    }

    /**
     * @return array
     */
    public function getEventEntityCollection()
    {
        return $this->eventEntityCollection;
    }
}
