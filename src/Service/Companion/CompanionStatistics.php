<?php

namespace App\Service\Companion;

use App\Entity\CompanionMarketItemEntry;
use App\Entity\CompanionMarketItemException;
use App\Entity\CompanionMarketItemUpdate;
use App\Repository\CompanionMarketItemEntryRepository;
use App\Repository\CompanionMarketItemExceptionRepository;
use App\Repository\CompanionMarketItemUpdateRepository;
use App\Service\Redis\Redis;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

class CompanionStatistics
{
    const FILENAME = __DIR__ . '/CompanionStatistics.json';

    // delete all update records older than 1 hour
    const UPDATE_TIME_LIMIT = (60 * 60);

    /** @var EntityManagerInterface */
    private $em;
    /** @var CompanionMarketItemUpdateRepository */
    private $repository;
    /** @var CompanionMarketItemEntryRepository */
    private $repositoryEntries;
    /** @var CompanionMarketItemExceptionRepository */
    private $repositoryExceptions;
    /** @var ConsoleOutput */
    private $console;
    /** @var array */
    private $updates = [];
    /** @var array */
    private $updatesQueue1 = [];
    /** @var array */
    private $queues = [];
    /** @var array */
    private $data = [];
    /** @var int */
    private $secondsPerItem = 0;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->repository = $em->getRepository(CompanionMarketItemUpdate::class);
        $this->repositoryEntries = $em->getRepository(CompanionMarketItemEntry::class);
        $this->repositoryExceptions = $em->getRepository(CompanionMarketItemException::class);

        $this->console = new ConsoleOutput();
    }

    public function run()
    {
        // grab all update records
        $this->updates = $this->repository->findBy([], [ 'added' => 'desc' ]);
        $this->updatesQueue1 = $this->repository->findBy([ 'priority' => 1 ], [ 'added' => 'desc' ]);

        // remove out of date records
        $this->removeOldUpdateRecords();
        
        // skip if no updates (eg: Maintenance)
        if (empty($this->updates)) {
            return null;
        }

        // Get queue sizes
        $this->getQueueSizes();
        
        // work out items per second speed
        $this->calculateItemsPerSecond();

        // build priority stats
        foreach (array_keys(CompanionConfiguration::QUEUE_INFO) as $priority) {
            $this->buildStatistics($priority);
        }
        
        // save
        $this->saveStatistics();

        // table
        $table = new Table($this->console);
        $table->setHeaders(array_keys($this->data[1]))->setRows($this->data);
        $table->render();
    }

    /**
     * Save our statistics
     */
    public function saveStatistics()
    {
        $data = [
            time(),
            $this->data,
            $this->queues,
            $this->getStatisticsView(),
        ];

        Redis::Cache()->set('stats_CompanionUpdateStatistics', $data, (60 * 60 * 24 * 7));
    }

    /**
     * Load our statistics
     */
    public function getStatistics()
    {
        return Redis::Cache()->get('stats_CompanionUpdateStatistics');
    }

    /**
     * Get exceptions thrown
     */
    public function getExceptions()
    {
        $exceptions = [];
        
        /** @var CompanionMarketItemException $ex */
        foreach($this->repositoryExceptions->findAll() as $ex) {
            $exceptions[] = [
                'arguments' => $ex->getException(),
                'message'   => $ex->getMessage(),
            ];
        }
        
        return $exceptions;
    }

    /**
     * Remove old update records
     */
    private function removeOldUpdateRecords()
    {
        $timeout = time() - self::UPDATE_TIME_LIMIT;

        /** @var CompanionMarketItemUpdate $update */
        foreach ($this->updates as $i => $update) {
            if ($update->getAdded() < $timeout) {
                $this->em->remove($update);
                unset($this->updates[$i]);
            }
        }

        $this->em->flush();
    }

    /**
     * Get the queue sizes
     */
    private function getQueueSizes()
    {
        $this->queues['all'] = 0;
        
        foreach($this->getCompanionQueuesView() as $row) {
            $this->queues[$row['priority']] = $row['total'];
            $this->queues['all'] += $row['total'];
        }
    }
    
    /**
     * Calculate the item per second avg based on how many items were updated
     * over a given duration period (in seconds)
     */
    private function calculateItemsPerSecond()
    {
        $duration   = reset($this->updatesQueue1)->getAdded() - end($this->updatesQueue1)->getAdded();
        $totalItems = count($this->updatesQueue1);
        
        // divide this by the number of updates
        $this->secondsPerItem = round(1 / round($totalItems / $duration, 3), 3);
    }

    /**
     * Build statistics for a particular priority
     */
    private function buildStatistics($priority)
    {
        // queue name
        $name = CompanionConfiguration::QUEUE_INFO[$priority] ?? 'Unknown Queue';
        
        // get the total items in this queue
        $totalItems = $this->queues[$priority] ?? 0;
        
        // some queues have no items
        if ($totalItems === 0) {
            return;
        }
        
        // get the number of consumers for this queue
        $consumers = CompanionConfiguration::QUEUE_CONSUMERS[$priority] ?? 0;
        
        // The completion time would be the total items multiple by how many seconds
        // it takes per item, divided by the number of consumers.
        $completionTime = ($totalItems * $this->secondsPerItem) / $consumers;

        // Work out the cycle speed
        $completionTime = Carbon::createFromTimestamp(time() + $completionTime);
        $completionTime = Carbon::now()->diff($completionTime)->format('%d days, %h hr, %i min');
        
        // Get the last updated entry
        $recentUpdate = $this->repositoryEntries->findOneBy([ 'priority' => $priority, ], [ 'updated' => 'desc' ]);
        $lastUpdate   = $this->repositoryEntries->findOneBy([ 'priority' => $priority, ], [ 'updated' => 'asc' ]);

        $this->data[$priority] = [
            'name'              => $name,
            'priority'          => $priority,
            'consumers'         => $consumers,
            'item_update_speed' => $this->secondsPerItem / $consumers,
            'total_items'       => number_format($totalItems),
            'total_requests'    => number_format($totalItems * 4),
            'updated_recently'  => date('Y-m-d H:i:s', $recentUpdate->getUpdated()),
            'updated_oldest'    => date('Y-m-d H:i:s', $lastUpdate->getUpdated()),
            'completion_time'   => $completionTime,
        ];
    }

    /**
     * Get statistics view
     */
    private function getStatisticsView()
    {
        $sql = $this->em->getConnection()->prepare('SELECT * FROM `companion stats` LIMIT 1');
        $sql->execute();

        return $sql->fetchAll()[0];
    }

    /**
     * @return mixed[]
     */
    private function getCompanionQueuesView()
    {
        $sql = $this->em->getConnection()->prepare('SELECT * FROM `companion queues`');
        $sql->execute();

        return $sql->fetchAll();
    }
}