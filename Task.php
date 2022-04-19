<?php


class Task
{
    protected $taskId;
    protected $beforeFirstYield = true;
    protected $coroutine;
    protected $sendValue = null;
    protected $exception = null;

    public function __construct($taskId, Generator $coroutine)
    {
        $assist = new Assist();
        $this->taskId = $taskId;
        $this->coroutine = $assist->stackedCoroutine($coroutine);
    }

    /**
     * 获取任务id
     * @return mixed
     */
    public function getTaskId()
    {
        return $this->taskId;
    }

    /**
     * 获取传进来的数据
     * @return null
     */
    public function getSendValue()
    {
        return $this->sendValue;
    }

    /**
     * 设置外面传进任务数据
     * @param $sendValue
     */
    public function setSendValue($sendValue)
    {
        $this->sendValue = $sendValue;
    }

    /**
     * 设置外面传进来的错误信息
     * @param $exception
     */
    public function setException($exception)
    {
        $this->exception = $exception;
    }

    /**
     * 判断是否已经执行完
     * @return bool
     */
    public function isFinished()
    {
        return !$this->coroutine->valid();
    }

    /**
     * 执行任务
     * @return mixed
     */
    public function run()
    {
        if ($this->beforeFirstYield) {
            $this->beforeFirstYield = false;
            return $this->coroutine->current();
        } else if ($this->exception) {
            $retval = $this->coroutine->throw($this->exception);
            $this->exception = null;
            return $retval;
        } else {
            $retval = $this->coroutine->send($this->sendValue);
            $this->sendValue = null;
            return $retval;
        }
    }
}


