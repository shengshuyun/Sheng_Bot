<?php

use function Swoole\Coroutine\batch;
use function Swoole\Coroutine\Http\post;

$请求数据 = [
    "op" => 0,
    "d" => [
        "user_id" => $this->用户ID,
        "message" => $this->消息内容->纯文本,
        "group_id" => $this->数据库("读", "无限主动配置/{$this->来源ID}/官方群号")
    ],
    "t" => "ShengBot_MSG"
];
$请求头 = ["x-bot-appid" => 101996091];
$地址 = 'https://sheng.luoxiaohei.art/';
$发送请求 = fn() => post($地址, json_encode($请求数据), null, $请求头);

$结果集 = batch([$发送请求]);

foreach ($结果集 as $i => $响应) {
    if ($响应) {
        $数据 = json_decode($响应->getBody(), true);
        
        if (str_contains($数据["msg"] ?? "", "event_id")) {
            $json = [
                "group_id" => $this->来源ID,
                "bot_appid" => $this->数据库("读", "无限主动配置/{$this->来源ID}/botid"),
                "button_id" => $this->数据库("读", "无限主动配置/{$this->来源ID}/按钮id"),
                "callback_data" => $this->数据库("读", "无限主动配置/{$this->来源ID}/按钮数据"),
                "msg_seq" => rand(1, 100)
            ];
            $this->调用API("click_inline_keyboard_button", $json);
            
            // 延迟1.5秒后重发
            echo "检测到过期，1.5秒后重发请求...\n";
            Swoole\Coroutine\System::sleep(1.5);
            
            $新响应 = $发送请求();
            
            // 处理重试结果
            if ($新响应) {
                $新数据 = json_decode($新响应->getBody(), true);
                echo "重发结果：{$新数据['msg']}\n";
            } else {
                echo "重发请求失败\n";
            }
        }
    } else {
        echo "请求{$i}失败\n";
    }
}