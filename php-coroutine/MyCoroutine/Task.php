<?php

namespace MyCoroutine;

class Task
{
    private bool $firstRound;
    private \Generator $generator;

    private mixed $sendValue;
    private mixed $returnValue;

    public function __construct(\Generator $generator)
    {
        $this->firstRound = true;
        $this->generator = $generator;

        $this->sendValue = null;
        $this->returnValue = null;
    }

    public function sendMsg(mixed $sendValue): Task
    {
        $this->sendValue = $sendValue;
        return $this;
    }

    public function resetMsg(): Task
    {
        $this->sendValue = null;
        return $this;
    }

    public function recvMsg(): mixed
    {
        return $this->returnValue;
    }

    public function finished(): bool
    {
        return !$this->generator->valid();
    }

    public function go(): Task
    {
        if ($this->firstRound) {
            $this->firstRound = false;
            $this->returnValue = $this->generator->current();
            return $this;
        }

        if ($this->finished()) {
            echo "error: try to run already finished task!";
            return $this;
        }

        $this->generator->send($this->sendValue);
        $this->returnValue = $this->generator->current();
        $this->resetMsg();
        return $this;
    }

    private ?self $parentTask = null;

    public function stackIn(\Generator $gen): self
    {
        $childTask = new Task($gen);
        $childTask->parentTask = $this;
        return $childTask;
    }

    public function stackOut(): self|null
    {
        return $this->parentTask;
    }
}
