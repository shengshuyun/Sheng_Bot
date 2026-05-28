<?php
/**
 * 插件：获取回复消息原始PB数据
 */
if ($this->事件类型 == '群消息' && $this->消息内容->纯文本 == '取') {
    $json = array(
        "message_id"=>$this->消息内容->回复消息ID,
        "raw" => true
    );
    $详情 = $this->调用API("get_msg", $json);
    if ($详情 === null) return;
    $real_seq = $详情["data"]["real_seq"] ?? null;
    if ($real_seq === null) return;
    $packet = [
        "1" => [
            "1" => $this->来源ID,
            "2" => $real_seq,
            "3" => $real_seq
        ],
        "2" => 1
    ];
    $发包结果 = $this->发包("trpc.msg.register_proxy.RegisterProxy.SsoGetGroupMsg", $packet);
    $发包 = json_decode($发包结果, true);
    if ($发包 === null) return;
    $发包 = json_encode($发包, 480);
    $用户1 = $this->伪造(10001, "pony", $this->消息段("文本", $发包));
    $this->群伪造($this->来源ID, $用户1);
}
if ($this->事件类型 == '群消息' && $this->消息内容->纯文本 == '取按钮') {
    $json = array(
        "message_id"=>$this->消息内容->回复消息ID,
        "raw" => true
    );
    $详情 = $this->调用API("get_msg", $json);
    if ($详情 === null) return;
    $real_seq = $详情["data"]["real_seq"] ?? null;
    if ($real_seq === null) return;
    $packet = [
        "1" => [
            "1" => $this->来源ID,
            "2" => $real_seq,
            "3" => $real_seq
        ],
        "2" => 1
    ];
    $发包结果 = $this->发包("trpc.msg.register_proxy.RegisterProxy.SsoGetGroupMsg", $packet);
    $发包 = json_decode($发包结果, true);
    if ($发包 === null) return;
    $按钮数据 = $发包[3][6][3][1][2][5] ?? null;
    if ($按钮数据 === null) return;
    $按钮id = $按钮数据[53][2][1][1][1][1] ?? '';
    $按钮data = $按钮数据[53][2][1][1][1][3][5] ?? '';
    $官机id = $按钮数据[53][2][1][2] ?? '';
    $发包 = json_encode($发包, 480);
    $用户1 = $this->伪造(10001, "pony", $this->消息段("文本", "按钮id：\n" . $按钮id));
    $用户2 = $this->伪造(10001, "pony", $this->消息段("文本", "按钮data：\n" . $按钮data));
    $用户3 = $this->伪造(10001, "pony", $this->消息段("文本", "官机appid：\n" . $官机id));
    $用户4 = $this->伪造(10001, "pony", $this->消息段("文本", $发包));
    $this->数据库("写", "无限主动配置/{$this->来源ID}/botid", $官机id);
    $this->数据库("写", "无限主动配置/{$this->来源ID}/按钮id", $按钮id);
    $this->数据库("写", "无限主动配置/{$this->来源ID}/按钮数据", $按钮data);
    $this->群伪造($this->来源ID, $用户1,$用户2,$用户3,$用户4);
}

if ($this->用户ID == 2657595205) {
    $指令 = explode(" ", $this->消息内容->纯文本);
    switch ($指令[0]) {
        case "记录群号":
            $this->数据库("写", "无限主动配置/{$this->来源ID}/官方群号", $指令[1]);
            $this->回复("已记录本群官方ID:\n{$指令[1]}");
            break;
    }
}
