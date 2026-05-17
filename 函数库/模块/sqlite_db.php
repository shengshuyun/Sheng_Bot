<?php
require_once __DIR__ . '/../../admin/数据库.php';

class 数据库
{
    private $db;
    private $dbPath;

    public function __construct($dbPath = null)
    {
        $this->db = new SQLiteDatabase();
        $this->dbPath = $dbPath;
    }

    public function __invoke($操作, $路径, $数据 = null)
    {
        if ($操作 === '写' || $操作 === 'write' || $操作 === 'set') {
            return $this->写($路径, $数据);
        } elseif ($操作 === '读' || $操作 === 'read' || $操作 === 'get') {
            return $this->读($路径);
        } elseif ($操作 === '删' || $操作 === 'delete' || $操作 === 'del') {
            return $this->删($路径);
        }
        return false;
    }

    private function 写($路径, $数据)
    {
        return $this->db->setKV($路径, $数据);
    }

    private function 读($路径)
    {
        $result = $this->db->getKV($路径);
        return $result !== null ? $result : '无数据';
    }

    private function 删($路径)
    {
        return $this->db->deleteKV($路径);
    }
}
