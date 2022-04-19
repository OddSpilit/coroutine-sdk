<?php


class Assist
{
    /**
     * 获取任务id
     * @return SystemCall
     */
    function getTaskId()
    {
        return new SystemCall(function(Task $task, Schedule $schedule) {
            $task->setSendValue($task->getTaskId());
            $schedule->schedule($task);
        });
    }

    /**
     * 创建一个新的任务
     * @param Generator $coroutine
     * @return SystemCall
     */
    public function newTask(Generator $coroutine)
    {
        return new SystemCall(function(Task $task, Schedule $schedule) use($coroutine) {
            $tid = $schedule->newTask($coroutine);
            $task->setSendValue($tid);
            $schedule->schedule($task);
        });
    }

    /**
     * 杀掉任务
     * @param $tid
     * @return SystemCall
     */
    function killTask($tid)
    {
        return new SystemCall(function (Task $task, Schedule $schedule) use($tid) {
            $res = $schedule->killTask($tid);
            if ($res) {
                $task->setSendValue($res);
                $schedule->schedule($task);
            } else {
                throw new InvalidArgumentException('Invite task ID');
            }
        });
    }

    /**
     * 压入等待读任务队列
     * @param $socket
     * @return SystemCall
     */
    function waitForRead($socket)
    {
        return new SystemCall(function(Task $task, Schedule $schedule) use($socket) {
            $schedule->waitForRead($socket, $task);
        });
    }

    /**
     * 压入等待写队列
     * @param $socket
     * @return SystemCall
     */
    function waitForWrite($socket)
    {
        return new SystemCall(function(Task $task, Schedule $schedule) use($socket) {
            $schedule->waitForWrite($socket, $task);
        });
    }

    /**
     *
     * @param $value
     * @return CoroutineReturnValue
     */
    function retval($value)
    {
        return new CoroutineReturnValue($value);
    }

    /**
     * 封装协程包 -- 支持嵌套协程
     * @param Generator $gen
     * @return Generator|void
     * @throws Exception
     */
    function stackedCoroutine(Generator $gen)
    {
        $stack = new SplStack;
        $exception = null;
        for (;;) {
            try {
                if ($exception) {
                    $gen->throw($exception);
                    $exception = null;
                    continue;
                }
                $value = $gen->current();
                if ($value instanceof Generator) {
                    $stack->push($gen);
                    $gen = $value;
                    continue;
                }
                $isReturnValue = $value instanceof CoroutineReturnValue;
                if (!$gen->valid() || $isReturnValue) {
                    if ($stack->isEmpty()) {
                        return;
                    }
                    $gen = $stack->pop();
                    $gen->send($isReturnValue ? $value->getValue() : NULL);
                    continue;
                }
                try {
                    $sendValue = (yield $gen->key() => $value);
                } catch (Exception $e) {
                    $gen->throw($e);
                    continue;
                }
                $gen->send($sendValue);
            } catch (Exception $e) {
                if ($stack->isEmpty()) {
                    throw $e;
                }
                $gen = $stack->pop();
                $exception = $e;
            }
        }
    }
}
