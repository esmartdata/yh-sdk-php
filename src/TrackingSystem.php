<?php
define('SDK_VERSION', '0.0.1');
//define('QA_URL', 'http://localhost:80/collection/i');
define('QA_URL', 'https://tsapiqa.escase.cn');
define('PROD_URL', 'https://tsapi.escase.cn');
define('TS_PATH', '/collection/i');


class TrackingSystem {
    public $_config;
    public $_super_properties;
    public $_consumer;
    public $_tracking_utils;
    public $page_info;
    private $ts_php;

    /**
     * TrackingSystem constructor.
     * @param $config
     */
    public function __construct($config) {
        // 初始化TS工具类
        $this->_tracking_utils = new TrackingUtils();
        // 获取TS缓存对象
        $ts_php = $this->_tracking_utils->getSafeArrValue($_COOKIE, "ts_php", "");

        $this->ts_php = json_decode($ts_php, false);

        // 注册tracking缓存
        $this->registerTrackingCache();

        // 初始化公共属性
        $this->initSuperProperties($config);

        // 初始化页面信息
        $this->page_info = $this->initPage();

        // 不支持 Windows，因为 Windows 版本的 PHP 都不支持 long
        if (strtoupper(substr(PHP_OS, 0, 3)) == "WIN") {
            $this->_is_win = true;
        } else {
            $this->_is_win = false;
        }

        $appKey = $this->getAppKey($config);
        $serverUrl = $this->getServerUrl($config);
        $this->_consumer = new BatchConsumer($appKey, $serverUrl);
    }

    private function getAppKey($config) {
        $appKey = $this->_tracking_utils->getSafeArrValue($config, "app_key", "");
        return $appKey ? $appKey : "";
    }

    private function getServerUrl($config) {
        $serverUrl = $this->_tracking_utils->getSafeArrValue($config, "server_url", null);
        if(!$serverUrl) {
            if(strpos($config["app_key"], "qa")  !== false) {
                $serverUrl = QA_URL;
            } else {
                $serverUrl = PROD_URL;
            }
        }
        $serverUrl = $serverUrl.TS_PATH;
        return $serverUrl;
    }

    /**
     * 获取设备ID
     * @return string 设备ID
     */
    private function getDeviceId() {
        $device_id = "TS_".md5(uniqid(mt_rand(), true));
        return $device_id;
    }

    private function getSessionId() {
        $session_id = md5(uniqid(mt_rand(), true));
        return $session_id;
    }

    /**
     * 更新缓存
     */
    private function updateTrackingCache() {
        $jsonStr = json_encode($this->ts_php);
        setcookie("ts_php", $jsonStr, time() + 99 * 365 * 24 * 3600);
    }

    /**
     * 注册tracking缓存
     */
    private function registerTrackingCache() {
        if($this->ts_php) {
            // 获取设备ID
            $device_id = $this->ts_php->device_id;
            if(!$device_id) {
                $this->ts_php->device_id = $this->getDeviceId();
                $this->ts_php->device_id = $device_id;
                // 更新缓存
                $this->updateTrackingCache();
            }
        } else { // 注册设备ID
            $this->ts_php = new stdClass();
            $this->ts_php->device_id = $this->getDeviceId();
            // 更新缓存
            $this->updateTrackingCache();
        }
    }

    private function getPageQuery() {
        $query_string = $_SERVER['QUERY_STRING'];
        if(!$query_string) {
            return "";
        }
        $queryArr = array();
        $query_string_arr = explode("&", $_SERVER['QUERY_STRING']);
        foreach ($query_string_arr as $value) {
            $objArr = explode("=", $value);
            $queryArr[$objArr[0]] = $objArr[1];
        }
//        echo "page_query：";
//        print_r($_SERVER['QUERY_STRING']);
        return json_encode($queryArr);
    }

    private function initPage() {
//        print_r("<pre>");
//        print_r($_SERVER);
        // 页面信息
        $page_info = array(
            "device_id"=>$this->ts_php->device_id,
            'current_path'=>$_SERVER['SCRIPT_NAME'],
            'page_url'=>$_SERVER['PHP_SELF'],
        );
//        print_r("<br/>页面信息：<br/>");
//        print_r($page_info);
        return $page_info;
    }

    public function setUserInfo($userInfo) {
        $defaultUser = array(
            "app_key"=>$this->_super_properties["app_key"],
            "device_id"=>"",
            "open_id"=>"",
            "union_id"=>"",
            "real_name"=>"",
            "nick_name"=>"",
            "age"=>"",
            "account"=>"",
            "birthday"=>"",
            "gender"=>"",
            "country"=>"",
            "province"=>"",
            "city"=>"",
            "key"=>"user",
            "timestamp"=>$this->getTimestamp(),
        );
        $userInfo = array_merge($defaultUser, $userInfo);
        $this->_consumer->send($userInfo);
    }

    public function pageview() {
        $pageData = $this->processData(array("key"=>"pageview"));
        $this->_consumer->send($pageData);
    }

    /**
     * 删除所有已设置的事件公共属性
     */
    public function clear_super_properties() {
    }

    private function sendRequest($url, $data) {
        $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
//        echo $data;
//        echo "<br>";
        $ch = curl_init();
        $params[CURLOPT_URL] = $url;    //请求url地址
        $params[CURLOPT_HEADER] = FALSE; //是否返回响应头信息
        $params[CURLOPT_HTTPHEADER] = array("Content-type: application/json"); //是否返回响应头信息
        $params[CURLOPT_SSL_VERIFYPEER] = false;
        $params[CURLOPT_SSL_VERIFYHOST] = false;
        $params[CURLOPT_RETURNTRANSFER] = true; //是否将结果返回
        $params[CURLOPT_POST] = true;
        $params[CURLOPT_POSTFIELDS] = $data;
        curl_setopt_array($ch, $params); //传入curl参数
        $content = curl_exec($ch); //执行
        curl_close($ch); //关闭连接
//        echo $content;

    }

    //返回当前的毫秒时间戳
    private function getTimestamp() {
        return intval(microtime(1) * 1000);
    }

    private function initSuperProperties($config) {
        $this->_config = $config;
        $osInfo = $this-> _tracking_utils->get_os();
        $this->_super_properties = array(
            //SDK信息
            'sdk_name' => 'php_track',//sdk名称
            'sdk_version' => SDK_VERSION,//sdk版本
            'sdk_type' => 'php',//sdk类型

            'platform' => 'php',

            //用户信息
            'guid' => '',//guid

            // 设备信息
            'device_system' => strtolower($osInfo[0]),
            'device_system_version' => $osInfo[count($osInfo) - 1],

            //渠道参数
            'business_channel' => 'default',
        );

        $this->_super_properties = array_merge($config, $this->_super_properties);
    }

    public function setSuperProperties($superProperties) {
        if ($superProperties) {
            $this->_super_properties = array_merge($this->_super_properties, $superProperties);
        }
    }

    public function setBusinessChannel($businessChannel) {
        if ($businessChannel) {
            $this->_super_properties['business_channel'] = $businessChannel;
        }

    }

    private function processData($data) {
        $data["timestamp"] = $this->getTimestamp();
//        $newData = array_merge($this->_config, $this->_super_properties, $this->page_info, $data);
        $newData = array_merge($this->_super_properties, $this->page_info, $data);

//        print_r("processData<br/>");
//        print_r($this->_super_properties);
//        print_r($newData);
        return $newData;
    }

    public function event($data) {
        if (!$data['event_name']) {
//            echo "事件名称不能为空";
            return;
        }

//        //发起请求的13位时间戳
//        $data['timestamp'] = $this->getTimestamp();
        //整合公共属性
//        $event_data = array_merge($this->_config, $this->_super_properties, $data);
        $data["key"] = "event";
        $event_data = $this->processData($data);

//        print_r("event_data================<br/>");
//        print_r($event_data);

        //发送事件请求
//        $this->sendRequest($this->_config['server_url'], $event_data);
        $this->_consumer->send($event_data);
    }

    public function batchEvent($data) {
        foreach ($data as &$eventList) {
            foreach ($eventList as &$event) {
                //发起请求的13位时间戳
                $event['timestamp'] = $this->getTimestamp();
                //整合公共属性
                $event = array_merge($this->_config, $this->_super_properties, $event);
            }

        }
//            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '<br />';
        //发送事件请求
        $this->sendRequest($this->_config['batch_url'], $data);
    }

}

abstract class AbstractConsumer {

    /**
     * 发送一条消息。
     *
     * @param string $msg 发送的消息体
     * @return bool
     */
    public abstract function send($msg);

    /**
     * 立即发送所有未发出的数据。
     *
     * @return bool
     */
    public function flush() {
    }

    /**
     * 关闭 Consumer 并释放资源。
     *
     * @return bool
     */
    public function  close() {
    }
}

class BatchConsumer extends AbstractConsumer {
    private $_app_key;
    private $_buffers;
    private $_max_size;
    private $_url_prefix;
    private $_request_timeout;
    private $file_handler;

    /**
     * @param string $app_key Tracking app_key。
     * @param string $server_url 服务器的 URL 地址。
     * @param int $max_size 批量发送的阈值。
     * @param int $request_timeout 请求服务器的超时时间，单位毫秒。
     * @param boolean $response_info 发送数据请求是否返回详情 默认 false。
     * @param string $filename 发送数据请求的返回状态及数据落盘记录，必须同时 $response_info 为 ture 时，才会记录。
     */
    public function __construct($app_key, $server_url, $max_size = 1, $request_timeout = 1000, $response_info = false, $filename = false) {
        $this->_app_key = $app_key;
        $this->_buffers = array();
        $this->_max_size = $max_size;
        $this->_url_prefix = $server_url;
        $this->_request_timeout = $request_timeout;
        $this->_response_info = $response_info;
        try {
            if($filename !== false && $this->_response_info !== false) {
                $this->file_handler = fopen($filename, 'a+');
            }
        } catch (\Exception $e) {
//            echo $e;
        }
    }


    public function send($msg) {
        $this->_buffers[] = $msg;
        if (count($this->_buffers) >= $this->_max_size) {
            return $this->flush();
        } else {
//            echo "请求队列已更新！";
        }
        // data into cache buffers，back some log
        if($this->_response_info) {
            $result = array(
                "ret_content" => "data into cache buffers",
                "ret_origin_data" => "",
                "ret_code" => 900,
            );
            if ($this->file_handler !== null) {
                // need to write log
                fwrite($this->file_handler, stripslashes(json_encode($result)) . "\n");
            }
            return $result;
        }
        return true;
    }

    public function flush() {
        if (empty($this->_buffers)) {
            $ret = false;
        } else {
            $ret = $this->_do_request($this->_buffers);
        }
        if ($ret) {
            $this->_buffers = array();
        }
        return $ret;
    }

    /**
     * 发送数据包给远程服务器。
     *
     * @param array $data
     * @return bool 请求是否成功
     */
    protected function _do_request($data) {
        $params = array(
            "app_key"=>$this->_app_key,
            "content"=>"",
        );
//        foreach ($data as $key => $value) {
//            $params[] = $key . '=' . urlencode($value);
//        }
        // 批量发送
        if(count($data) > 1) {
            $params["content"] = $data;
        } else {
            $params["content"] = base64_encode(json_encode($data[0]));
        }

//        print_r("_do_request================<br/>");
//        print_r($this->_url_prefix."<br/>");
//        print_r($params);
        $params = json_encode($data[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
//        print_r($params);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->_url_prefix);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->_request_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->_request_timeout);
        curl_setopt($ch, CURLOPT_POST, 1);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $params));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_USERAGENT, "PHP SDK");

        //judge https
        $pos = strpos($this->_url_prefix, "https");
        if ($pos === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        $ret = curl_exec($ch);

//        print_r("请求返回================<br/>");
//        print_r($ret);
        // judge back detail response
        if($this->_response_info){
            $result = array(
                "ret_content" => $ret,
                "ret_code" => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            );
            if ($this->file_handler !== null) {
                // need to write log
                fwrite($this->file_handler, stripslashes(json_encode($result)) . "\n");
            }
            curl_close($ch);
            return $result;
        }
        if (false === $ret) {
            curl_close($ch);
            return false;
        } else {
            curl_close($ch);
            return true;
        }
    }

    /**
     * 对待发送的数据进行编码
     *
     * @param array $msg_list
     * @return string
     */
    private function _encode_msg_list($msg_list) {
        return base64_encode($this->_gzip_string("[" . implode(",", $msg_list) . "]"));
    }

    /**
     * GZIP 压缩一个字符串
     *
     * @param string $data
     * @return string
     */
    private function _gzip_string($data) {
        return gzencode($data);
    }

    public function close() {
        $closeResult = $this->flush();
        if ($this->file_handler !== null) {
            fclose($this->file_handler);
        }
        return $closeResult;
    }
}

class TrackingUtils {

    /**
     * 安全的获取数组中的元素
     * @param $arr
     * @param $key
     * @param null $default
     * @return null
     */
    function getSafeArrValue(&$arr,$key,$default=null){
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /**
     * 获取客户端操作系统信息包括win10
     * @param  null
     * @return string
     */
    function get_os() {
        $agent = $this->getSafeArrValue($_SERVER, "HTTP_USER_AGENT", "");
        $os = false;

        if (preg_match('/win/i', $agent) && strpos($agent, '95'))
        {
            $os = 'Windows 95';
        }
        else if (preg_match('/win 9x/i', $agent) && strpos($agent, '4.90'))
        {
            $os = 'Windows ME';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/98/i', $agent))
        {
            $os = 'Windows 98';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/nt 6.0/i', $agent))
        {
            $os = 'Windows Vista';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/nt 6.1/i', $agent))
        {
            $os = 'Windows 7';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/nt 6.2/i', $agent))
        {
            $os = 'Windows 8';
        }else if(preg_match('/win/i', $agent) && preg_match('/nt 10.0/i', $agent))
        {
            $os = 'Windows 10';#添加win10判断
        }else if (preg_match('/win/i', $agent) && preg_match('/nt 5.1/i', $agent))
        {
            $os = 'Windows XP';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/nt 5/i', $agent))
        {
            $os = 'Windows 2000';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/nt/i', $agent))
        {
            $os = 'Windows NT';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/32/i', $agent))
        {
            $os = 'Windows 32';
        }
        else if (preg_match('/linux/i', $agent))
        {
            $os = 'Linux';
        }
        else if (preg_match('/unix/i', $agent))
        {
            $os = 'Unix';
        }
        else if (preg_match('/sun/i', $agent) && preg_match('/os/i', $agent))
        {
            $os = 'SunOS';
        }
        else if (preg_match('/ibm/i', $agent) && preg_match('/os/i', $agent))
        {
            $os = 'IBM OS/2';
        }
        else if (preg_match('/Mac/i', $agent) && preg_match('/PC/i', $agent))
        {
            $os = 'Macintosh';
        }
        else if (preg_match('/PowerPC/i', $agent))
        {
            $os = 'PowerPC';
        }
        else if (preg_match('/AIX/i', $agent))
        {
            $os = 'AIX';
        }
        else if (preg_match('/HPUX/i', $agent))
        {
            $os = 'HPUX';
        }
        else if (preg_match('/NetBSD/i', $agent))
        {
            $os = 'NetBSD';
        }
        else if (preg_match('/BSD/i', $agent))
        {
            $os = 'BSD';
        }
        else if (preg_match('/OSF1/i', $agent))
        {
            $os = 'OSF1';
        }
        else if (preg_match('/IRIX/i', $agent))
        {
            $os = 'IRIX';
        }
        else if (preg_match('/FreeBSD/i', $agent))
        {
            $os = 'FreeBSD';
        }
        else if (preg_match('/teleport/i', $agent))
        {
            $os = 'teleport';
        }
        else if (preg_match('/flashget/i', $agent))
        {
            $os = 'flashget';
        }
        else if (preg_match('/webzip/i', $agent))
        {
            $os = 'webzip';
        }
        else if (preg_match('/offline/i', $agent))
        {
            $os = 'offline';
        }
        else
        {
            $os = '未知操作系统';
        }
        return explode(" ", $os);
    }
}

//1、引入SDK并初始化配置
//2、设置公共属性（如用户信息）
//3、调用事件发送事件请求
//4、批量发送事件
//5、设置渠道参数
