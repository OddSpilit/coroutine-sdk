<?php


class Schedule
{
    protected $maxTaskId = 0; // 当前任务id
    protected $taskMap = []; // 任务映射表
    protected $waitForRead = []; // 等待读队列
    protected $waitForWrite = []; // 等待写队列
    protected $taskQueue;

    public function __construct()
    {
        $this->taskQueue = new SplQueue();
    }

    /**
     * 生成新的任务
     * @param Generator $coroutine
     * @return int
     */
    public function newTask(Generator $coroutine)
    {
        $tid = $this->maxTaskId++;
        $task = new Task($tid, $coroutine);
        $this->taskMap[$tid] = $task;
        $this->schedule($task);
        return $tid;
    }

    /**
     * 将任务入队
     * @param $task
     */
    public function schedule($task)
    {
        $this->taskQueue->enqueue($task);
    }

    /**
     * 杀掉任务
     * @param $tid
     * @return bool
     */
    public function killTask($tid)
    {
        if (!isset($this->taskMap[$tid])) {
            return false;
        }
        unset($this->taskMap[$tid]);

        foreach ($this->taskQueue as $i => $task) {
            if ($tid == $this->taskQueue[$i]->getTaskId()) {
                unset($this->taskQueue[$i]);
                break;
            }
        }
        return true;
    }

    /**
     * 压入读等待队列
     * @param $socket
     * @param $task
     */
    public function waitForRead($socket, $task)
    {
        if (isset($this->waitForRead[(int)$socket])) {
            $this->waitForRead[(int)$socket][] = $task;
        } else {
            $this->waitForRead[(int)$socket] = [$task];
        }
    }

    /**
     * 压入等待写队列
     * @param $socket
     * @param $task
     */
    public function waitForWrite($socket, $task)
    {
        if (isset($this->waitForWrite[(int)$socket])) {
            $this->waitForWrite[(int)$socket][] = $task;
        } else {
            $this->waitForWrite[(int)$socket] = [$task];
        }
    }

    /**
     * 探测io
     * @param $timeout
     */
    public function ioPoll($timeout)
    {
        $rSocks = array_keys($this->waitForRead);
        $wSocks = array_keys($this->waitForWrite);
        $eSocks = []; // dummy

        // 等待读或者写请求的到来
        if (!stream_select($rSocks, $wSocks, $eSocks, $timeout)) {
            return;
        }

        foreach ($rSocks as $socket) {
            list(, $tasks) = $this->waitForRead[(int) $socket];
            unset($this->waitForRead[(int)$socket]);
            foreach ($tasks as $task) {
                $this->schedule($task);
            }
        }

        foreach ($wSocks as $socket) {
            list(, $tasks) = $this->waitForWrite[(int) $socket];
            unset($this->waitForWrite[(int)$socket]);
            foreach ($tasks as $task) {
                $this->schedule($task);
            }
        }
    }

    /**
     * 探测是否有读写任务
     * @return Generator
     */
    protected function ioPollTask()
    {
        while (true) {
            if ($this->taskQueue->isEmpty()) {
                $this->ioPoll(null);
            } else {
                $this->ioPoll(0);
            }
            yield;
        }
    }

    /**
     * 执行调度器
     */
    public function run()
    {
//        $this->newTask($this->ioPollTask());
        while (!$this->taskQueue->isEmpty()) {
            $task = $this->taskQueue->dequeue();
            $retval = $task->run();
            if ($retval instanceof SystemCall) {
                try {
                    $retval($task, $this);
                } catch (Exception $e) {
                    $task->setException($e);
                    $this->schedule($task);
                }
                continue;
            }
            if ($task->isFinished()) {
                unset($this->taskMap[$task->getTaskId()]);
            } else {
                $this->taskQueue->enqueue($task);
            }
        }
    }

}
