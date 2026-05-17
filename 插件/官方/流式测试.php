<?php

if ($this->用户信息 == "流式测试") {
    // 第一条：开启流式，index=0，id=null
    $流ID = $this->流式(1, "正在\n", null, 0);
    
    // 模拟延迟，展示打字效果
    $this->定时器('延迟', 500, function() use ($流ID) {
        // 续接：使用返回的流ID，index递增
        $新流ID = $this->流式(1, "正在思考\n", $流ID, 1);
        
        $this->定时器('延迟', 800, function() use ($新流ID) {
            // 继续续接
            $this->流式(1, "正在思考中\n", $新流ID, 2);
            
            $this->定时器('延迟', 600, function() use ($新流ID) {
                // 最后一条：state=10 表示结束
                $this->流式(10, "正在思考中...\n结束", $新流ID, 3);
            });
        });
    });
}

if ($this->事件类型 == "C2C_MESSAGE_CREATE" && trim($this->用户信息) == "流式重置") {
    go(function() {
        $流ID = $this->流式(1, "下面开始胡言乱语\n", null, 0);
        Co\System::sleep(1);
        $this->流式(1, "你好，我是机器人", $流ID, 1);
        Co\System::sleep(1);
        $this->流式(1, "不对，重新说，我是你爸爸", $流ID, 1, true);
        Co\System::sleep(1);
        $this->流式(1, "不对不对，重新说，我是AI助手", $流ID, 1, true);
        Co\System::sleep(1);
        $this->流式(10, "对了，我是AI助手！！😊🎉", $流ID, 1, true);
    });
}
