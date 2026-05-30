<?php
/**
 * 系统管理插件
 * 
 * 指令（仅超级管理员可用）：
 *   重启 - 重启框架主进程
 *   重载 - 重启框架主进程（同重启）
 */

// 只处理群聊和单聊消息
if (!in_array($this->事件类型 ?? '', ['GROUP_AT_MESSAGE_CREATE', 'C2C_MESSAGE_CREATE', 'GROUP_MESSAGE_CREATE'])) {
    return;
}

$消息 = trim($this->用户信息 ?? '');
if (empty($消息)) return;

// 检查是否是管理指令
if (!in_array($消息, ['重启', '重载'])) {
    return;
}

// 权限判定：只有超级管理员才能执行
if (!$this->是管理员()) {
    $this->发送('文本', '❌ 权限不足，仅超级管理员可执行此操作');
    return;
}

// 执行重启
$this->发送('文本', '✅ 正在重启框架...');
$this->logger->info("[系统管理] 收到重启请求，正在重启...");

// server.php 已经捕获 Swoole\ExitException，所以 exit() 可以正常退出
throw new \Swoole\ExitException(0);
