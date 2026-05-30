<?php
/**
 * 撤回示例插件
 * 
 * 指令：
 *   撤回 - 发送消息后自动撤回
 *   闪现 - 发送消息后3秒自动撤回
 *   召回测试 - 发送互动召回消息（仅单聊）
 */

// 只处理群聊和单聊消息
if (!in_array($this->事件类型 ?? '', ['GROUP_AT_MESSAGE_CREATE', 'C2C_MESSAGE_CREATE', 'GROUP_MESSAGE_CREATE'])) {
    return;
}

$消息 = trim($this->用户信息 ?? '');
if (empty($消息)) return;

// 撤回：发送后1秒撤回
if ($消息 === '撤回') {
    $id = $this->发送('文本', '✨ 这条消息将被撤回');
    if ($id) {
        $this->定时器('延迟', 1000, function() use ($id) {
            $this->撤回($id);
        });
    }
    return;
}

// 闪现：发送后3秒撤回
if ($消息 === '闪现') {
    $id = $this->发送('文本', '✨ 这条消息将在3秒后消失...');
    if ($id) {
        $this->定时器('延迟', 3000, function() use ($id) {
            $this->撤回($id);
        });
    }
    return;
}

// 召回测试（仅单聊）
if ($消息 === '召回测试') {
    if ($this->事件类型 !== 'C2C_MESSAGE_CREATE') {
        $this->发送('文本', '召回功能仅支持单聊场景');
        return;
    }
    $this->发送召回('这是一条召回消息，用于提醒用户回来互动');
    return;
}
