<?php


class SystemCall
{
    protected $callback;
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke(Task $task, Schedule $schedule)
    {
        // TODO: Implement __invoke() method.
        $callback = $this->callback;
        return $callback($task, $schedule);
    }
}
