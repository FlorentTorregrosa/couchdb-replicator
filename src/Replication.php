<?php
/**
 * Created by PhpStorm.
 * User: abhi
 * Date: 22/5/15
 * Time: 6:51 PM
 */

namespace Relaxed\Replicator;

use Relaxed\Replicator\ReplicationTask;
use Doctrine\CouchDB\CouchDBClient;
use Doctrine\CouchDB\HTTP\HTTPException;

/**
 * Class Replication
 * @package Relaxed\Replicator\replicator
 */
class Replication {
    /**
     * @var CouchDBClient
     */
    protected $source;
    /**
     * @var CouchDBClient
     */
    protected $target;
    /**
     * @var ReplicationTask
     */
    protected $task;

    /**
     * @param CouchDBClient $source
     * @param CouchDBClient $target
     * @param ReplicationTask $task
     */
    public function __construct(CouchDBClient $source, CouchDBClient $target, ReplicationTask $task)
    {
        $this->source = $source;
        $this->target = $target;
        $this->task = $task;
    }

    public function start()
    {
        list($sourceInfo, $targetInfo) = $this->verifyPeers($this->source, $this->target, $this->task);
        $this->$task->setRepId(
            $this->generateReplicationId());
    }


    /**
     * @return array
     * @throws HTTPException
     * @throws \Exception
     */
    public function verifyPeers()
    {
        $sourceInfo = null;
        try {
            $sourceInfo = $this->source->getDatabaseInfo($this->source->getDatabase());
        } catch (HTTPException $e) {
            throw new \Exception('Source not reachable.');
        }

        $targetInfo = null;
        try {
            $targetInfo = $this->target->getDatabaseInfo($this->target->getDatabase());
        } catch (HTTPException $e) {
            if ($e->getCode() == 404 && $this->task->getCreateTarget()) {
                $this->target->createDatabase($this->target->getDatabase());
                $targetInfo = $this->target->getDatabaseInfo($this->target->getDatabase());
            } else {
                throw new \Exception("Target database does not exist.");
            }
        }
        return array($sourceInfo, $targetInfo);
    }

    public function generateReplicationId()
    {
        $filterCode = '';
        $filter = $this->task->getFilter();
        if ($filter != null) {
            if ($filter[0] !== '_') {
                list($designDoc, $functionName) = explode('/', $filter);
                $filterCode = $this->source->getDesignDocument($designDoc)['filters'][$functionName];
            }
        }
        return md5(
            $this->source->getDatabase() .
            $this->target->getDatabase() .
            \var_export($this->task->getDocIds(), true) .
            ($this->task->getCreateTarget() ? '1' : '0') .
            ($this->task->getContinuous() ? '1' : '0') .
            $filter .
            $filterCode .
            $this->task->getStyle() .
            \var_export($this->task->getHeartbeat(), true)
        );
    }


}