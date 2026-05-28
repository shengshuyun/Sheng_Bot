<?php
declare(strict_types=1);

namespace ShengBot\Core;

use ShengBot\Adapters\OfficialQQBot;
use ShengBot\Adapters\NapCatBot;

class Router
{
    public static function 分发(\Swoole\Http\Request $请求, \Swoole\Http\Response $响应, array $配置): void
    {
        if ($请求->getMethod() !== 'POST') {
            $响应->status(200);
            $响应->end('Zzzz....');
            return;
        }

        $适配器 = match (true) {
            isset($请求->header['x-bot-appid']) => new OfficialQQBot($配置['QQBOT'] ?? []),
            isset($请求->header['x-self-id'])   => new NapCatBot($配置['napcat'] ?? []),
            default => null
        };

        if ($适配器 === null) {
            $响应->status(200);
            $响应->end('框架睡着了....原因:未兼容的请求');
            return;
        }

        $适配器->主入口($请求, $响应);
    }
}
