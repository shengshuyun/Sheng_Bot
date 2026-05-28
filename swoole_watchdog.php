<?php
declare(strict_types=1);
use Swoole\Process;
use Swoole\Coroutine;
use Swoole\Runtime;

const MAIN_SCRIPT = __DIR__ . '/server.php';
const LOG_FILE    = __DIR__ . '/日志/wd.log';
const MAX_RESTARTS = 10;
const BASE_BACKOFF = 3;
const MAX_BACKOFF  = 60;

!is_dir(__DIR__ . '/日志') && mkdir(__DIR__ . '/日志', 0777, true);

function println(string $s): void
{
    echo date('Y-m-d H:i:s ') . $s . PHP_EOL;
}

$childPid = 0;
$shouldStop = false;

pcntl_signal(SIGTERM, function () use (&$childPid, &$shouldStop) {
    $shouldStop = true;
    if ($childPid > 0) {
        posix_kill($childPid, SIGTERM);
    }
});

pcntl_signal(SIGINT, function () use (&$childPid, &$shouldStop) {
    $shouldStop = true;
    if ($childPid > 0) {
        posix_kill($childPid, SIGTERM);
    }
});

$restartCount = 0;

while (!$shouldStop) {
    pcntl_signal_dispatch();

    $proc = new Process(function () {
        require MAIN_SCRIPT;
    }, false, 1);
    $pid = $proc->start();
    if ($pid === false) {
        exit("启动主进程失败\n");
    }
    $childPid = $pid;
    println("主进程已启动，pid={$pid}");
    $restartCount++;

    Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
    
    $exited = false;
    
    Coroutine\run(function () use ($proc, $pid, &$exited, &$shouldStop) {
        $pipe = fopen('php://fd/' . $proc->pipe, 'r+');
        stream_set_blocking($pipe, false);

        go(function () use ($pipe, &$exited) {
            while (!$exited) {
                Coroutine::sleep(0.1);
                $out = fread($pipe, 8192);
                if ($out === false || $out === '') {
                    if (feof($pipe)) break;
                    continue;
                }
                echo $out;
            }
            while (true) {
                $out = fread($pipe, 8192);
                if ($out === false || $out === '') break;
                echo $out;
            }
        });

        while (!$exited) {
            Coroutine::sleep(1);
            pcntl_signal_dispatch();
            if ($shouldStop) {
                posix_kill($pid, SIGTERM);
            }
            $ret = Process::wait(false);
            if ($ret !== false) {
                $code = $ret['code'] ?? -1;
                file_put_contents(LOG_FILE,
                    date('Y-m-d H:i:s ') . "主进程死亡，exitCode={$code}" . PHP_EOL,
                    FILE_APPEND | LOCK_EX);
                println("主进程死亡，exitCode={$code}");
                if ($code === 3) {
                    println("端口被占用，停止守护");
                    $shouldStop = true;
                }
                $exited = true;
            }
        }
    });

    $childPid = 0;

    if ($shouldStop) {
        println("收到终止信号，退出守护");
        exit(0);
    }

    if ($restartCount >= MAX_RESTARTS) {
        println("重启次数过多({$restartCount})，停止守护");
        file_put_contents(LOG_FILE,
            date('Y-m-d H:i:s ') . "重启次数过多({$restartCount})，停止守护" . PHP_EOL,
            FILE_APPEND | LOCK_EX);
        exit(1);
    }

    $sleepTime = min(BASE_BACKOFF * $restartCount, MAX_BACKOFF);
    println("第 {$restartCount} 次重启，等待 {$sleepTime}s");
    sleep($sleepTime);
}
