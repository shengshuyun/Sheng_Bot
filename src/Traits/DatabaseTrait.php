<?php
declare(strict_types=1);

namespace ShengBot\Traits;

use ShengBot\Database\JsonDatabase;

trait DatabaseTrait
{
    private ?JsonDatabase $数据库实例 = null;
    private string $数据库路径;

    public function 数据库(string $操作, string $路径, mixed $数据 = null): mixed
    {
        if ($this->数据库实例 === null) {
            $this->数据库实例 = new JsonDatabase($this->数据库路径);
        }
        return ($this->数据库实例)($操作, $路径, $数据);
    }
}
