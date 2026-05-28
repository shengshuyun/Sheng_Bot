<?php

if ($this->用户信息 == "图片") {
    $this->发送("图片", "https://www.dmoe.cc/random.php");
}

if ($this->用户信息 == "图文") {
    $this->发送("图片", "https://www.dmoe.cc/random.php", "这是附带的文本");
}

if ($this->用户信息 == "失败图文") {
    $this->发送("图片", "https://api.komll.com/images", "这是附带的文本");
}

if ($this->用户信息 == "视频") {
    $this->发送("视频", "https://www.learningcontainer.com/wp-content/uploads/2020/05/sample-mp4-file.mp4");
}

if ($this->用户信息 == "文件") {
    $this->发送("文件", "https://github.com/shengshuyun/Sheng_Bot/archive/refs/heads/main.zip");
}

if ($this->用户信息 == "语音") {
    $this->发送("语音", "http://music.163.com/song/media/outer/url?id=31649312.mp3");
}

if ($this->用户信息 == "文本") {
    $this->发送("文本", "文本1");
    $this->发送("文本", "文本2");
    $this->发送("文本", "文本3");
    $this->发送("文本", "文本4");
    $this->发送("文本", "文本5");
}

if ($this->用户信息 == "群号") {
    $this->发送("文本", $this->来源ID);
}