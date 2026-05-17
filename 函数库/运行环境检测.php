<?php
/*
 * 运行前必要的检测
 */

if (version_compare(PHP_VERSION, '8.4.0', '<')) {
    throw new RuntimeException('PHP版本需要8.4或更高，当前版本：' . PHP_VERSION);
}

if (!extension_loaded('swoole')) {
    throw new RuntimeException('Swoole扩展未安装');
}

$configFile = __DIR__ . '/../config.json';

if (!file_exists($configFile)) {
    throw new RuntimeException("配置文件不存在: {$configFile}");
}

$配置 = json_decode(file_get_contents($configFile), true);
if (!$配置) {
    throw new RuntimeException("配置 JSON 解析失败");
}

if (!empty($配置['ssl证书'])) {
    $certFile = dirname($configFile) . '/' . $配置['ssl证书'];
    if (!file_exists($certFile)) {
        throw new RuntimeException("SSL证书文件不存在: {$certFile}");
    }
    
    $cert = openssl_x509_parse(file_get_contents($certFile));
    if (!$cert) {
        throw new RuntimeException("SSL证书文件解析失败");
    }

    $remainingSeconds = $cert['validTo_time_t'] - time();
    $days = (int)($remainingSeconds / 86400);
    
    if ($remainingSeconds < 0) {
        // 已过期
        $expiredDays = abs($days);
        throw new RuntimeException("SSL证书已过期 {$expiredDays} 天，请立即更新");
    } elseif ($days <= 7) {
        echo "⚠️ 警告：SSL证书仅剩 {$days} 天过期" . PHP_EOL;
    } else {
        echo "✅ SSL证书有效，剩余 {$days} 天" . PHP_EOL;
    }
}

echo '✅ 所有前置检查通过，开始运行...' . PHP_EOL;
