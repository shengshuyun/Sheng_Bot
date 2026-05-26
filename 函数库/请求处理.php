<?php

require_once __DIR__ . '/模块/官方QQ机器人.php';
require_once __DIR__ . '/模块/猫猫框架.php';

// 统一处理所有数据
return function (\Swoole\Http\Request $请求, \Swoole\Http\Response $响应, array $账号数组): void {
    $原始正文 = $请求->rawContent();
    $数据 = json_decode($原始正文, true);

    // print_r($请求) . PHP_EOL;
    // print_r($数据) . PHP_EOL;
    // print_r($账号数组) . PHP_EOL;

    if ($请求->getMethod() === 'POST') {
        $框架标识 = match(true) {
            isset($请求->header['x-bot-appid']) => '官方QQ',
            isset($请求->header['x-self-id'])   => 'napcat',
            default => null
        };
        
        if ($框架标识 === null) {
            $响应->status(200);
            $响应->end("框架睡着了....原因:未兼容的请求");
            return;
        }
        
        go(function () use ($请求, $响应, $原始正文, $账号数组, $框架标识) {
            $机器人 = match($框架标识) {
                '官方QQ' => new 官方QQ机器人($账号数组['QQBOT']),
                'napcat' => new 猫猫框架($账号数组['napcat']),
            };
            $机器人->主入口($请求, $响应);
        });
    } else {
        $响应->status(200);
        $响应->end("Zzzz....");
    }
};
