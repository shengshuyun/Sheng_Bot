<?php
declare(strict_types=1);

namespace ShengBot\Traits;

use Swoole\Timer;
use function Swoole\Coroutine\go;

trait TimerTrait
{
    public function 定时器(string $操作, ...$参数): mixed
    {
        return match($操作) {
            '延迟', 'after' => $this->定时器延迟(...$参数),
            '循环', 'tick' => $this->定时器循环(...$参数),
            '清除', 'clear' => $this->定时器清除(...$参数),
            '清除全部', 'clearAll' => $this->定时器清除全部(),
            '信息', 'info' => $this->定时器信息(...$参数),
            default => (function () use ($操作) {
                $this->logger->warning("[定时器] 未知操作类型: {$操作}");
                return null;
            })()
        };
    }

    private function 定时器延迟(...$参数): int
    {
        $毫秒 = (int)($参数[0] ?? 1000);
        $回调 = $参数[1] ?? function(){};
        $定时器ID = Timer::after($毫秒, function() use ($回调, $毫秒) {
            go(function() use ($回调, $毫秒) {
                try {
                    $回调();
                } catch (\Throwable $e) {
                    $this->logger->error("[定时器错误] 延迟{$毫秒}ms执行失败: " . $e->getMessage());
                }
            });
        });
        $this->logger->info("[定时器] 已设置延迟{$毫秒}ms，ID: {$定时器ID}");
        return $定时器ID;
    }

    private function 定时器循环(...$参数): int
    {
        $毫秒 = (int)($参数[0] ?? 1000);
        $回调 = $参数[1] ?? function(){};
        $定时器ID = Timer::tick($毫秒, function() use ($回调, $毫秒) {
            go(function() use ($回调, $毫秒) {
                try {
                    $回调();
                } catch (\Throwable $e) {
                    $this->logger->error("[定时器错误] 循环间隔{$毫秒}ms执行失败: " . $e->getMessage());
                }
            });
        });
        $this->logger->info("[定时器] 已设置循环间隔{$毫秒}ms，ID: {$定时器ID}");
        return $定时器ID;
    }

    private function 定时器清除(...$参数): bool
    {
        $定时器ID = $参数[0] ?? null;
        if ($定时器ID && Timer::clear($定时器ID)) {
            $this->logger->info("[定时器] 已清除ID: {$定时器ID}");
            return true;
        }
        return false;
    }

    private function 定时器清除全部(): bool
    {
        Timer::clearAll();
        $this->logger->info("[定时器] 已清除全部定时器");
        return true;
    }

    private function 定时器信息(...$参数): mixed
    {
        $定时器ID = $参数[0] ?? null;
        return $定时器ID ? Timer::info($定时器ID) : Timer::list();
    }
}
