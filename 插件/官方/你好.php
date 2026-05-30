<?php
use function Swoole\Coroutine\Http\get;

if ($this->用户信息 == "你好") {
    $code = <<<PHP
<?php\rclass 机器人 {\r    public function 打招呼() {\r        return "你好，！";\r    }\r}\r = new 机器人();\recho ("世界");
PHP;
    $img = json_decode(get("https://cyapi.top/API/acg_h.php")->getBody(), true);
    $md = [
        ['key' => 'headurl', 'values' => ["https://q.qlogo.cn/qqapp/".$this->当前账号["appid"]."/{$this->用户ID}/640"]],
        ['key' => 'text1', 'values' => ["<@$this->用户ID>"]],
        ['key' => 'imagesize', 'values' => ["喵喵喵 #{$img['width']} #{$img['height']}"]],
        ['key' => 'imageurl', 'values' => [$img["url"]]],
        ['key' => 'dmk', 'values' => ["php\r{$code}"]],
        ['key' => 'text2', 'values' => ["奇奇怪怪"]]
    ];
    $this->发送('md', '101996091_1768812174', $md);
}

if ($this->用户信息 == "测试") {
$img1 = '![text #208px #320px]';
$img2 = '(https://resource5-1255303497.cos.ap-guangzhou.myqcloud.com/abcmouse_word_watch/markdown/building.png)';
    $mdParams = [
        ['key' => 'text', 'values' => ["<@{$this->用户ID}> \r >你好\r# 一级标题\r## 二级标题\r### 三级标题\r#### 四级标题\r##### 五级标题\r###### 六级标题\r\r**加粗文本**（关键信息）\r*斜体文本*（强调或注释）\r***加粗斜体文本***（强强调）\r> 引用文本（比如引用他人观点、文献内容）\r> 多行引用可继续加>\r\r---\r（分割线，三个及以上短横线，前后空行更美观）\r\r| 表头1 | 表头2 | 表头3 |\r| --- | --- | --- |\r| 内容1 | 内容2 | 内容3 |\r| 内容4 | 内容5 | 内容6 |"]]
    ];
    $this->发送('md', '101996091_1768555988', $mdParams);

}

if ($this->用户信息 == "按钮") {
    $数组 = [[
        "key" => "text",
        "values" => array("> 不要顺便点\r---")
    ]];
    $this->发送("md", "101996091_1768555988", $数组, "101996091_1746167833");
}


if ($this->用户信息 == "当前账号") {
    $this->发送('文本', "当前appid: " . $this->当前账号["appid"]);
}

if ($this->用户信息 == "ID") {
    $this->发送('文本', $this->用户ID);
}

if ($this->用户信息 == "原生") {
    $附加2 = [
        "style" => [
            "font_size" => "small"
        ],
        "rows" => [
            [
                "buttons" => [
                    [
                        "render_data" => [
                            "label" => "点我",
                            "visited_label" => "成功！",
                            "style" => 1
                        ],
                        "action" => [
                            "type" => 1,
                            "permission" => [
                                "type" => 0,
                                "specify_user_ids" => ["{$this->用户ID}"]
                            ],
                            "unsupport_tips" => "当前版本不支持该操作",
                            "data" => "点击测试"
                        ],
                        "id" => "btn_fd4flyc"
                    ],
                    [
                        "render_data" => [
                            "label" => "别点",
                            "visited_label" => "失败！",
                            "style" => 1
                        ],
                        "action" => [
                            "type" => 1,
                            "permission" => [
                                "type" => 0,
                                "specify_user_ids" => ["{$this->用户ID}"]
                            ],
                            "unsupport_tips" => "当前版本不支持该操作",
                            "data" => "点击失败测试"
                        ],
                        "id" => "btn_fd4flyy"
                    ]
                ]
            ]
        ]
    ];

    $this->发送("md", null, 
    '$\textcolor{red}{红}$$\textcolor{orange}{橙}$$\textcolor{yellow}{黄}$$\textcolor{green}{绿}$$\textcolor{blue}{蓝}$$\textcolor{purple}{紫}$' . "\n" . 
    '$\textcolor{#FF6B6B}{珊瑚红}$
$\textcolor{#4ECDC4}{薄荷绿}$
$\textcolor{#45B7D1}{天蓝色}$
$\textcolor{#F9CA24}{柠檬黄}$' . "\n" . 
    '![图片 scheme="http://tucdn.wpon.cn/api-girl/index.php?wpon=302" #1920px #862px](https://multimedia.nt.qq.com.cn/download?appid=1407&fileid=EhSsLjUzF4rxQHy8CTYDBj-ZDr93shjB1wkg_wooiZWi3JLUlAMyBHByb2RQgL2jAVoQs0893f7fKSCQxuLGN1X1mXoCHVyCAQJneg&spec=0&rkey=CAISMGjRK20_AfMLC8wadYOocoFH5yLl2rrSxbN4WPkBgWbm8yxoLbQ87QvpIpI5gcQguQ)' . "\n***\n" . '点击上方图片看视频', 
$附加2);

}

if ($this->按钮数据 == "点击测试" && $this->按钮ID == "btn_fd4flyc") {
    $this->发送("文本", "按钮测试成功");
}

if ($this->按钮数据 == "点击失败测试" && $this->按钮ID == "btn_fd4flyy") {
    $this->发送("文本", "都说了别点！");
}


if ($this->用户信息 == "事件") {
    $this->发送('文本', "当前事件类型: " . $this->事件类型);
}