<?php
//require_once 'PHPUnit/Autoload.php';
//引入SDK
require_once("TrackingSystem.php");

//初始化sdk
$tsConfig = array(
    'app_key' => 'qa1576468657xxx', // 必须
    'ts_app' => '测试应用', // 非必须
    'ts_ext' => '{"name": "张三", "age": 18}' // 非必须
);
$ts = new TrackingSystem($tsConfig);

//设置公共属性
//$sp = array(
//    'guid' => 'distinct_id',
//    'open_id' => 'oieCW5DQKyWKLgEHATyK2MZjcpoE'
//);
//$ts->setSuperProperties($sp);

//设置渠道参数
$ts->setBusinessChannel("testChannel");

//自定义事件A
$eventA = array(
    'event_name' => 'testEvent',
    'event_param' => array(
        'name' => '张三',
        'age' => '25',
    )
);
//自定义事件B
$eventB = array(
    'event_name' => 'testEvent',
    'event_param' => array(
        'name' => '李四',
        'age' => '26',
    )
);

//定义事件列表
$eventArray = array('dataList' => array($eventA, $eventB));

//发送单个事件
$ts->event($eventA);
$ts->event($eventB);
//发送事件列表
//$ts->batchEvent($eventArray);
