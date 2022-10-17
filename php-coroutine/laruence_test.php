<?php
require_once __DIR__ . "/vendor/autoload.php";

use LaruenceCoroutine\CoSocket;
use LaruenceCoroutine\Scheduler;
use function LaruenceCoroutine\newTask;

function server($port) {
    echo "Starting server at port $port...\n";
    $socket = @stream_socket_server("tcp://localhost:$port", $errNo, $errStr);
    if (!$socket) throw new Exception($errStr, $errNo);
    stream_set_blocking($socket, 0);
    $socket = new CoSocket($socket);
    while (true) {
        yield newTask(
            handleClient(yield $socket->accept())
        );
    }
}

function handleClient(CoSocket $socket)
{
    $data = (yield $socket->read(8192));
    $msg = "Received following request:\n\n$data";
    $msgLength = strlen($msg);
    $response = <<<res
HTTP/1.1 200 OK\r
Content-Type: text/plain\r
Content-Length: $msgLength\r
Connection: close\r
\r
$msg
res;
    yield $socket->write($response);
    yield $socket->close();
}

$scheduler = new Scheduler();
$scheduler->newTask(server(2333));
$scheduler->run();

//function foo()
//{
//    echo "foo\n";
//    $sendVal = yield "yield1\n";
//
//    var_dump($sendVal);
//    echo "bar\n";
//    $sendVal = yield "yield2\n";
//
//    var_dump($sendVal);
//    echo "quarkay\n";
//    $sendVal = yield "yield3\n";
//
//    var_dump($sendVal);
//    echo "finally stmt\n";
//}
//
//$fooRes = server();
//var_dump($fooRes);
//
//echo "==============\n";
//$valueId = 1;
//while ($fooRes->valid()) {
//    echo "--------------\n";
//    echo $fooRes->current();
//    echo "--------------\n";
//    echo $fooRes->send('test send value id: ' . $valueId++);
//}
//
//echo "==============\n";
//var_dump($fooRes->current());
//echo "==============\n";
//$fooRes->next();