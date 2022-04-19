<?php
include 'Task.php';
include 'Schedule.php';
include  'SystemCall.php';
include 'Assist.php';
include 'CoroutineReturnValue.php';

function gen() {
    try {
        yield (new Assist())->killTask(500);
    } catch (Exception $e) {
        echo "Exception: {$e->getMessage()}\n";
    }
}
//$schedule = new Schedule();
//$schedule->newTask(gen());
//$schedule->run();

/**
 * Schedule(newTask) ->
 * 通过中间生成器函数`stackedCoroutine`生成一个用栈 存储嵌套生成器 的生成器(stackGen) ->
 * 通过Schedule(run) ->
 * 实际是在跑stackGen （yield $gen->key() => $value） ->
 * 异常 设置异常对象 重新压入任务队列中执行
 * Task(run) ->
 * 抛出异常对象到stackGen ->
 * stackGen 抛出异常对象给到外面接收打印异常
 */




