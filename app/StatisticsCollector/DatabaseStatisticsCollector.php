<?php

namespace Expose\Server\StatisticsCollector;

use Expose\Server\Contracts\StatisticsCollector;
use Clue\React\SQLite\DatabaseInterface;

class DatabaseStatisticsCollector implements StatisticsCollector
{
    /** @var DatabaseInterface */
    protected $database;

    /** @var array */
    protected $sharedPorts = [];

    /** @var array */
    protected $sharedSites = [];

    /** @var int */
    protected $requests = 0;

    /** @var int */
    protected $cooldowns = 0;

    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /**
     * Flush the stored statistics.
     *
     * @return void
     */
    public function flush()
    {
        $this->sharedPorts = [];
        $this->sharedSites = [];
        $this->requests = 0;
        $this->cooldowns = 0;
    }

    public function siteShared($authToken = null)
    {
        if (! $this->shouldCollectStatistics()) {
            return;
        }

        if (! isset($this->sharedSites[$authToken])) {
            $this->sharedSites[$authToken] = 0;
        }

        $this->sharedSites[$authToken]++;
    }

    public function portShared($authToken = null)
    {
        if (! $this->shouldCollectStatistics()) {
            return;
        }

        if (! isset($this->sharedPorts[$authToken])) {
            $this->sharedPorts[$authToken] = 0;
        }

        $this->sharedPorts[$authToken]++;
    }

    public function incomingRequest()
    {
        if (! $this->shouldCollectStatistics()) {
            return;
        }

        $this->requests++;
    }

    public function cooldownTriggered()
    {
        if (! $this->shouldCollectStatistics()) {
            return;
        }

        $this->cooldowns++;
    }

    public function save()
    {
        $sharedSites = 0;
        collect($this->sharedSites)->map(function ($numSites) use (&$sharedSites) {
            $sharedSites += $numSites;
        });

        $sharedPorts = 0;
        collect($this->sharedPorts)->map(function ($numPorts) use (&$sharedPorts) {
            $sharedPorts += $numPorts;
        });

        $this->database->query('
                    INSERT INTO statistics (timestamp, shared_sites, shared_ports, unique_shared_sites, unique_shared_ports, incoming_requests, cooldown_count)
                    VALUES (:timestamp, :shared_sites, :shared_ports, :unique_shared_sites, :unique_shared_ports, :incoming_requests, :cooldown_count)
                ', [
            'timestamp' => today()->toDateString(),
            'shared_sites' => $sharedSites,
            'shared_ports' => $sharedPorts,
            'unique_shared_sites' => count($this->sharedSites),
            'unique_shared_ports' => count($this->sharedPorts),
            'incoming_requests' => $this->requests,
            'cooldown_count' => $this->cooldowns,
        ])
        ->then(function () {
            $this->flush();
        });
    }

    public function shouldCollectStatistics(): bool
    {
        return config('expose-server.statistics.enable_statistics', true);
    }
}
