<?php

use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;
use function Swoole\Coroutine\Http\post;

run(function () {
    go(function () {
        
        // 机器人密钥（secret）
        $密钥 = 'M6qbN9wkYND3uldWPJE951ywutttuvx0';
        
        // 模拟QQ官方发送的OP13数据
        $请求数据 = [
            'op' => 13,
            'd' => [
                'plain_token' => 'eP7FyU2uqyybM43t4ug6',
                'event_ts' => 1768695802
            ]
        ];
        
        // 本地计算期望的签名（鉴权算法）
        $本地签名 = 鉴权($请求数据, $密钥);
        echo "本地计算的签名: " . $本地签名 . "\n\n";
        
        // 模拟QQ官方的请求头
        $请求头 = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'QQBot-Callback',
            'X-Bot-Appid' => '102348715',
            'X-Signature-Method' => 'Ed25519',
            'X-Signature-Timestamp' => '1768695802',
            'X-Signature-Ed25519' => '903d9f39134c22b2cd735ee5e16c6b79c110b1ff867a5eda4412e9779e66edcaaa9eeea23fed3f8d8459a3e22966fd27d6f9b317f4d9e679824b34b73133150f'
        ];
        
        echo "发送OP13模拟请求...\n";
        echo "请求体: " . json_encode($请求数据, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        // 发送到你搭建好的服务器
        $响应 = post('https://o.ocoa.cn:8443/', 
            json_encode($请求数据),
            null,
            $请求头
        );
        
        // 解析响应
        $响应体 = json_decode($响应->getBody());
        
        echo "收到响应:\n";
        print_r($响应体);
        
        // 双向验证对比
        if (isset($响应体->plain_token) && isset($响应体->signature)) {
            echo "\n========== 双向验证对比 ==========\n";
            echo "服务器返回的 plain_token: " . $响应体->plain_token . "\n";
            echo "服务器返回的 signature: " . $响应体->signature . "\n";
            echo "本地计算的 signature: " . $本地签名 . "\n";
            
            if ($响应体->signature === $本地签名) {
                echo "\n✅ 双向验证通过！签名一致\n";
            } else {
                echo "\n❌ 双向验证失败！签名不一致\n";
                echo "差异分析:\n";
                echo "- 服务器签名长度: " . strlen($响应体->signature) . "\n";
                echo "- 本地签名长度: " . strlen($本地签名) . "\n";
            }
        } else {
            echo "\n⚠️ 响应格式异常，缺少必要字段\n";
        }
    });
});

// 鉴权算法（Ed25519签名）
function 鉴权($数据, $种子) {
    // 种子扩展到32字节
    while (strlen($种子) < SODIUM_CRYPTO_SIGN_SEEDBYTES) {
        $种子 .= $种子;
    }
    
    // 生成密钥对并提取私钥
    $密钥对 = sodium_crypto_sign_seed_keypair(substr($种子, 0, SODIUM_CRYPTO_SIGN_SEEDBYTES));
    $私钥 = sodium_crypto_sign_secretkey($密钥对);
    
    // 签名数据：event_ts + plain_token
    $签名内容 = $数据['d']['event_ts'] . $数据['d']['plain_token'];
    $签名 = bin2hex(sodium_crypto_sign_detached($签名内容, $私钥));
    
    return $签名;
}
