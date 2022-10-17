<?php

namespace MyCoroutine;

enum TaskAPI
{
    case NewTask;
    case CancelSelf;
    case WaitSocketAccept;
    case WaitSocketRead;
    case WaitSocketWrite;
}

class SysCall
{
    public TaskAPI $api;
    public mixed $param1;
    public mixed $param2;
    public mixed $param3;

    public function __construct(TaskAPI $api, mixed $param1 = null, mixed $param2 = null, mixed $param3 = null)
    {
        $this->api = $api;
        $this->param1 = $param1;
        $this->param2 = $param2;
        $this->param3 = $param3;
    }
}
