<?php

namespace LaruenceCoroutine;

function retval($value)
{
    return new CoroutineReturnValue($value);
}

//function stackedCoroutine(Generator $gen)
//{
//    $stack = new SplStack;
//    for (; ;) {
//        $value = $gen->current();
//        if ($value instanceof Generator) {
//            $stack->push($gen);
//            $gen = $value;
//            continue;
//        }
//        $isReturnValue = $value instanceof CoroutineReturnValue;
//        if (!$gen->valid() || $isReturnValue) {
//            if ($stack->isEmpty()) {
//                return;
//            }
//            $gen = $stack->pop();
//            $gen->send($isReturnValue ? $value->getValue() : NULL);
//            continue;
//        }
//        $gen->send(yield $gen->key() => $value);
//    }
//}

function stackedCoroutine(\Generator $gen)
{
    $stack = new \SplStack;
    $exception = null;
    for (; ;) {
        try {
            if ($exception) {
                $gen->throw($exception);
                $exception = null;
                continue;
            }
            $value = $gen->current();
            if ($value instanceof \Generator) {
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
            } catch (\Exception $e) {
                $gen->throw($e);
                continue;
            }
            $gen->send($sendValue);
        } catch (\Exception $e) {
            if ($stack->isEmpty()) {
                throw $e;
            }
            $gen = $stack->pop();
            $exception = $e;
        }
    }
}
