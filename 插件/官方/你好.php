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

if ($this->用户信息 == "原生") {
    $this->发送("md", null, "# 标题 \n## 简介很开心 \n内容[🔗腾讯](https://www.qq.com)");
}