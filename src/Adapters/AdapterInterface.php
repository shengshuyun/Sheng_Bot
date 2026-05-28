<?php
declare(strict_types=1);

namespace ShengBot\Adapters;

interface AdapterInterface
{
    public function 主入口(\Swoole\Http\Request $请求, \Swoole\Http\Response $响应): void;
}
