<?php
/*
 * 运行前必要的检测
 */

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    throw new RuntimeException('PHP版本需要8.0或更高，当前版本：' . PHP_VERSION);
}

if (!extension_loaded('swoole')) {
    throw new RuntimeException('Swoole扩展未安装');
}

echo '✅ 所有前置检查通过，开始运行...' . PHP_EOL;
