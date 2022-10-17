<?php

namespace MyCoroutine;

class TaskStackReturn
{
    private mixed $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }
}