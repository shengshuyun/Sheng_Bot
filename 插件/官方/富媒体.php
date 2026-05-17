<?php

if ($this->用户信息 == "图片") {
    $this->发送("图片", "https://api.komll.com/images");
}

if ($this->用户信息 == "图文") {
    $this->发送("图片", "https://api.komll.com/images", "这是附带的文本");
}

if ($this->用户信息 == "视频") {
    $this->发送("视频", "https://www.learningcontainer.com/wp-content/uploads/2020/05/sample-mp4-file.mp4");
}

if ($this->用户信息 == "语音") {
    $this->发送("文本", "你看我长得像不像语音？");
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