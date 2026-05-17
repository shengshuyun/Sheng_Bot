<?php
/**
 * 自动加载指定目录下所有 .php 文件（递归）
 * @param string $dir 绝对路径
 * @return Generator 返回 SplFileInfo
 */
function glob_php(string $dir): Generator
{
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iter as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            yield $file;
        }
    }
}

/*
---------- 使用 ---------- 
foreach (glob_php(__DIR__.'/目录/') as $file) {
    require $file->getRealPath();
}
*/