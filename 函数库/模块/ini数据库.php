<?php
declare(strict_types=1);

class 数据库 
{
    private string $basePath;
    private array $locks = [];
    private int $lockTimeout = 3000;
    
    public function __construct(string $basePath = __DIR__ . '/数据库') 
    {
        $this->basePath = rtrim($basePath, '/');
        if (!is_dir($this->basePath)) {
            @mkdir($this->basePath, 0777, true);
        }
    }
    
    /**
     * 主入口方法 - 支持读写操作
     * @param string $操作 "写" | "读" | "删"
     * @param string $路径 字段路径，如 "交互记录/群号/交互ID"
     * @param mixed $数据 写入时的数据
     * @return mixed 读操作时返回数据或"无数据"，写操作时返回bool
     */
    public function __invoke(string $操作, string $路径, mixed $数据 = null): mixed 
    {
        return match($操作) {
            '写', 'write', 'set' => $this->写($路径, $数据),
            '读', 'read', 'get' => $this->读($路径),
            '删', 'delete', 'del' => $this->删($路径),
            default => false
        };
    }
    
    /** 写入数据 */
    private function 写(string $路径, mixed $数据): bool 
    {
        $parts = $this->解析路径($路径);
        $表名 = array_shift($parts);
        $文件 = $this->获取文件路径($表名);
        $锁 = $this->获取锁($表名);
        
        if (!$锁->lock($this->lockTimeout)) {
            error_log("数据库[$表名]: 获取锁超时");
            return false;
        }
        
        try {
            $内容 = $this->读取文件($文件);
            
            // 递归设置嵌套值
            $当前 = &$内容;
            $最后键 = array_pop($parts);
            foreach ($parts as $键) {
                if (!isset($当前[$键]) || !is_array($当前[$键])) {
                    $当前[$键] = [];
                }
                $当前 = &$当前[$键];
            }
            $当前[$最后键] = $this->编码数据($数据);
            
            return $this->写入文件($文件, $内容);
        } finally {
            $锁->unlock();
        }
    }
    
    /** 读取数据 - 不存在返回 "无数据" */
    private function 读(string $路径): mixed 
    {
        $parts = $this->解析路径($路径);
        $表名 = array_shift($parts);
        $文件 = $this->获取文件路径($表名);
        
        $内容 = $this->读取文件($文件);
        
        // 递归读取嵌套值
        $当前 = $内容;
        foreach ($parts as $键) {
            if (!is_array($当前) || !array_key_exists($键, $当前)) {
                return "无数据";
            }
            $当前 = $当前[$键];
        }
        
        return $this->解码数据($当前);
    }
    
    /** 删除指定路径 */
    private function 删(string $路径): bool 
    {
        $parts = $this->解析路径($路径);
        $表名 = array_shift($parts);
        $文件 = $this->获取文件路径($表名);
        $锁 = $this->获取锁($表名);
        
        if (!$锁->lock($this->lockTimeout)) return false;
        
        try {
            $内容 = $this->读取文件($文件);
            
            // 递归删除嵌套值
            $当前 = &$内容;
            $最后键 = array_pop($parts);
            foreach ($parts as $键) {
                if (!isset($当前[$键]) || !is_array($当前[$键])) {
                    return false; // 路径不存在
                }
                $当前 = &$当前[$键];
            }
            
            if (!array_key_exists($最后键, $当前)) return false;
            unset($当前[$最后键]);
            
            return $this->写入文件($文件, $内容);
        } finally {
            $锁->unlock();
        }
    }
    
    /* ========== 工具方法 ========== */
    
    private function 解析路径(string $路径): array 
    {
        return explode('/', $路径);
    }
    
    private function 获取文件路径(string $表名): string 
    {
        return "{$this->basePath}/{$表名}.json";
    }
    
    private function 获取锁(string $表名): \Swoole\Lock 
    {
        return $this->locks[$表名] ??= new \Swoole\Lock(SWOOLE_MUTEX);
    }
    
    private function 读取文件(string $文件): array 
    {
        if (!file_exists($文件)) return [];
        $内容 = @file_get_contents($文件);
        if (!$内容) return [];
        $数据 = json_decode($内容, true);
        return is_array($数据) ? $数据 : [];
    }
    
    private function 写入文件(string $文件, array $内容): bool 
    {
        $目录 = dirname($文件);
        if (!is_dir($目录)) @mkdir($目录, 0777, true);
        
        $临时文件 = $文件 . '.' . uniqid() . '.tmp';
        $json = json_encode($内容, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        if (file_put_contents($临时文件, $json, LOCK_EX) === false) {
            return false;
        }
        
        return rename($临时文件, $文件);
    }
    
    private function 编码数据(mixed $数据): mixed 
    {
        if ($数据 instanceof \DateTime) {
            return ['__type__' => 'datetime', 'value' => $数据->format('Y-m-d H:i:s')];
        }
        return $数据;
    }
    
    private function 解码数据(mixed $数据): mixed 
    {
        if (is_array($数据) && ($数据['__type__'] ?? null) === 'datetime') {
            return new \DateTime($数据['value']);
        }
        return $数据;
    }
}
