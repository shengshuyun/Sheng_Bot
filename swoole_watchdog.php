<?php
declare(strict_types=1);
use Swoole\Process;
use Swoole\Coroutine;
use Swoole\Runtime;

const MAIN_SCRIPT = __DIR__ . '/server.php';
const LOG_FILE    = __DIR__ . '/日志/wd.log';

!is_dir(__DIR__ . '/日志') && mkdir(__DIR__ . '/日志', 0777, true);

function println(string $s): void
{
    echo date('Y-m-d H:i:s ') . $s . PHP_EOL;
}

while (true) {
    $proc = new Process(function () {
        require MAIN_SCRIPT;
    }, false, 1);
    $pid = $proc->start();
    if ($pid === false) {
        exit("启动主进程失败\n");
    }
    println("主进程已启动，pid={$pid}");

    Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
    
    $shouldExit = false;
    
    Coroutine\run(function () use ($proc, $pid, &$shouldExit) {
        $pipe = fopen('php://fd/' . $proc->pipe, 'r+');
        stream_set_blocking($pipe, false);

        go(function () use ($pipe, &$shouldExit) {
            while (!$shouldExit) {
                Coroutine::sleep(0.1);
                $out = fread($pipe, 8192);
                if ($out === false || $out === '') {
                    if (feof($pipe)) break;
                    continue;
                }
                echo $out;
            }
        });

        while (!$shouldExit) {
            Coroutine::sleep(3);
            $ret = Process::wait(false);
            if ($ret !== false) {
                $code = $ret['code'] ?? -1;
                file_put_contents(LOG_FILE,
                    date('Y-m-d H:i:s ') . "主进程死亡，exitCode={$code}" . PHP_EOL,
                    FILE_APPEND | LOCK_EX);
                println("主进程死亡，exitCode={$code}，3 s 后重启");
                $shouldExit = true;
            }
        }
    });

    println("回到外层，准备重新 fork");
    sleep(3);
}