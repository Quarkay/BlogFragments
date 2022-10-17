<?php
require_once __DIR__ . '/vendor/autoload.php';

use MyCoroutine\SysCall;
use MyCoroutine\Task;
use MyCoroutine\TaskAPI;
use MyCoroutine\TaskScheduler;

function listen()
{
    $socket = stream_socket_server("tcp://0.0.0.0:2333", $errno, $errMsg);
    if (!$socket) {
        echo "error: failed to listen, ${errMsg}\n";
        return null;
    }
    return $socket;
}

function demoHttpResp(): string
{
    return <<<http
HTTP/1.1 200 OK
Content-Length: 19
Content-Type: text/html
Server: Quarkay.Demo

hello from quarkay!
http;
}

function server(): Generator
{
    $listenSocket = listen();
    if (!$listenSocket) {
        yield new SysCall(TaskAPI::CancelSelf);
    }

    while (true) {
        $socket = yield new SysCall(TaskAPI::WaitSocketAccept, $listenSocket);
        stream_set_blocking($socket, 0);
        yield new SysCall(TaskAPI::NewTask, reply($socket));
    }
}

function reply($socket): Generator
{
    $data = yield from IO_Read($socket);
    if ($data) {
        yield from IO_Write($socket);
    }
}

function IO_Read($socket): Generator
{
    yield new SysCall(TaskAPI::WaitSocketRead, $socket);
    $data = stream_socket_recvfrom($socket, 1024);
    if (!$data) {
        echo "error: failed to read from socket!\n";
        stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
    }

    return $data;
}

function IO_Write($socket): Generator
{
    yield new SysCall(TaskAPI::WaitSocketWrite, $socket);
    $len = stream_socket_sendto($socket, demoHttpResp());
    if (!$len) {
        echo "error: failed to write to socket!\n";
        stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
    }

    return $len;
}

$runner = new TaskScheduler();
$server = new Task(server());
$runner->newTask($server);
$runner->run();
