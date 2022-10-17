<?php
namespace MyCoroutine;

//class TaskScheduler
//{
//    private SplQueue $queue;
//
//    public function __construct()
//    {
//        $this->queue = new SplQueue();
//    }
//
//    public function newTask(Generator $generator): void
//    {
//        $this->queue->enqueue($generator);
//    }
//
//    public function hasTask(): bool
//    {
//        return !$this->queue->isEmpty();
//    }
//
//    public function run(): void
//    {
//        while ($this->hasTask()) {
//            $task = $this->queue->dequeue();
//            if ($task->valid()) {
//                $task->next();
//                $this->queue->enqueue($task);
//            }
//        }
//    }
//}

class TaskScheduler
{
    private \SplQueue $queue;

    public function __construct()
    {
        $this->queue = new \SplQueue();
    }

    public function newTask(Task $newTask): void
    {
        $this->queue->enqueue($newTask);
    }

    public function hasTask(): bool
    {
        return !$this->queue->isEmpty();
    }

    public function run(): void
    {
        if (!$this->hasTask()) {
            echo "error: run scheduler without task!\n";
            return;
        }

        $this->newTask(new Task($this->dispatchNetEvent()));
        while ($this->hasTask()) {
            $task = $this->queue->dequeue();
            $task->go();
            if (!$task->finished()) {
                $this->queue->enqueue($task);
            }

            $msg = $task->recvMsg();
            if ($msg instanceof SysCall) {
                $this->handleSyscall($task, $msg);
            }

            if ($msg instanceof \Generator) {
                $this->popTask($task);
                $childTask = $task->stackIn($msg);
                $this->queue->enqueue($childTask);
            }

            if ($msg instanceof TaskStackReturn) {
                $this->popTask($task);
                $parentTask = $task->stackOut();
                if ($parentTask) {
                    $this->queue->enqueue($parentTask->sendMsg($msg->getResult()));
                }
            }
        }
    }

    private function popTask(Task $task): void
    {
        if (!$task->finished()) {
            $popped = $this->queue->pop();
            if ($popped !== $task) {
                echo "error: wrong task popped!\n";
            }
        }
    }

    private array $waitingToAcceptSockets = [];
    private array $waitingToAcceptTaskMap = [];
    private array $waitingToReadSockets = [];
    private array $waitingToReadTaskMap = [];
    private array $waitingToWriteSockets = [];
    private array $waitingToWriteTaskMap = [];

    public function handleSyscall(Task $task, SysCall $call): void
    {
        switch ($call->api) {
            case TaskAPI::NewTask:
                $this->newTask(new Task($call->param1));
                break;
            case TaskAPI::CancelSelf:
                $this->popTask($task);
                break;
            case TaskAPI::WaitSocketAccept:
                $this->popTask($task);
                $this->waitingToAcceptSockets[(int)$call->param1] = $call->param1;
                $this->waitingToAcceptTaskMap[(int)$call->param1] = $task;
                break;
            case TaskAPI::WaitSocketRead:
                $this->popTask($task);
                $this->waitingToReadSockets[(int)$call->param1] = $call->param1;
                $this->waitingToReadTaskMap[(int)$call->param1] = $task;
                break;
            case TaskAPI::WaitSocketWrite:
                $this->popTask($task);
                $this->waitingToWriteSockets[(int)$call->param1] = $call->param1;
                $this->waitingToWriteTaskMap[(int)$call->param1] = $task;
                break;
            default:
                echo "error: unknown SysCall!\n";
                break;
        }
    }

    public function dispatchNetEvent(): \Generator
    {
        while (true) {
            $rSocketList = array_merge(
                array_values($this->waitingToReadSockets),
                array_values($this->waitingToAcceptSockets),
            );
            $wSocketList = array_values($this->waitingToWriteSockets);
            $eSocketList = array_merge($rSocketList, $wSocketList);

            if (!$eSocketList) {
                if (!$this->hasTask()) {
                    yield new SysCall(TaskAPI::CancelSelf);
                }
                yield;
                continue;
            }

            $seconds = $this->hasTask() ? 0 : 1;
            $num = stream_select($rSocketList, $wSocketList, $eSocketList, $seconds);
            if (!$num) {
                yield;
                continue;
            }

            foreach ($rSocketList as $rSocket) {
                if (isset($this->waitingToAcceptTaskMap[(int)$rSocket])) {
                    $acceptSocket = stream_socket_accept($rSocket, 0);
                    $this->queue->enqueue($this->waitingToAcceptTaskMap[(int)$rSocket]->sendMsg($acceptSocket));
                    unset($this->waitingToAcceptSockets[(int)$rSocket]);
                    unset($this->waitingToAcceptTaskMap[(int)$rSocket]);
                }
                if (isset($this->waitingToReadTaskMap[(int)$rSocket])) {
                    $this->queue->enqueue($this->waitingToReadTaskMap[(int)$rSocket]->sendMsg($rSocket));
                    unset($this->waitingToReadSockets[(int)$rSocket]);
                    unset($this->waitingToReadTaskMap[(int)$rSocket]);
                }
            }
            foreach ($wSocketList as $wSocket) {
                $this->queue->enqueue($this->waitingToWriteTaskMap[(int)$wSocket]->sendMsg($wSocket));
                unset($this->waitingToWriteSockets[(int)$wSocket]);
                unset($this->waitingToWriteTaskMap[(int)$wSocket]);
            }

            foreach ($eSocketList as $eSocket) {
                echo "error: exception in socket" . $eSocket . "\n";
                unset($this->waitingToAcceptSockets[$eSocket]);
                unset($this->waitingToAcceptTaskMap[$eSocket]);
                unset($this->waitingToReadSockets[$eSocket]);
                unset($this->waitingToReadTaskMap[$eSocket]);
                unset($this->waitingToWriteSockets[$eSocket]);
                unset($this->waitingToWriteTaskMap[$eSocket]);
            }

            yield;
        }
    }
}