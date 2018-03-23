<?php
/**
* iCMS - i Content Management System
* Copyright (c) 2007-2017 iCMSdev.com. All rights reserved.
*
* @author icmsdev <master@icmsdev.com>
* @site https://www.icmsdev.com
* @licence https://www.icmsdev.com/LICENSE.html
*/
defined('iPHP') OR exit('What are you doing?');

class iCMS {
    public static $config    = array();

    public static function init(){
        self::config();

        define('iCMS_URL',       self::$config['router']['url']);
        define('iCMS_PUBLIC_URL',self::$config['router']['public']);
        define('iCMS_USER_URL',  self::$config['router']['user']);
        define('iCMS_FS_URL',    self::$config['FS']['url']);
        define('iCMS_API',       iCMS_PUBLIC_URL.'/api.php');
        define('iCMS_API_URL',   iCMS_API.'?app=');

        self::set_tpl_const();
        self::send_access_control();
        self::assign_site();
    }
    /**
     * [config 对框架各系统进行配置]
     * @return [type] [description]
     */
	public static function config(){
        iPHP::$callback['config']['apps'] = array('apps','default_config');
        //获取配置
        $config = iPHP::config();
        //多终端适配
        iDevice::init($config['template'],array(
            'redirect' => $config['router']['redirect'],
        ));
        //终端URL一致性
        iDevice::identity($config['router']);
        iDevice::identity($config['FS']);
        //文件系统
        iFS::init($config['FS']);
        //缓存系统
        iCache::init($config['cache']);
        //路由系统
        iURL::init($config['router'],array(
            'user_url' => $config['router']['user'],
            'api_url'  => $config['router']['public'],
            'tag'      => $config['tag'],//标签配置
            'iurl'     => $config['iurl'],//应用路由定义
            'callback'=> array(
                "domain" => array('categoryApp','domain'),//绑定域名回调
                'device' => array('iDevice','urls'),//设备网址
            )
        ));
        //是否移动设设备
        define('iPHP_MOBILE', iDevice::$IS_MOBILE);
        //设备标识
        define('iPHP_DEVICE', iDevice::$device_name);
        //模板系统
        iView::init(array(
            'template' => array(
                'device' => iDevice::$device_name,  //设备
                'dir'    => iDevice::$device_tpl,   //模板名
                'index'  => iDevice::$device_index, //模板首页
            ),
            'define' => array(
                'apps' => $config['apps'],
                'func' => 'content',
            )
        ));
        //UI
        iUI::set_dialog('title',$config['site']['name']);

        self::$config = $config;
	}
    /**
     * 运行应用程序
     * @param string $app 应用程序名称
     * @param string $do 动作名称
     */
    public static function run($app = NULL,$do = NULL,$args = NULL,$prefix="do_") {
        iPHP::$callback['run']['begin'][] = function(){
            iView::set_iVARS(array(
                "MOBILE" => iPHP_MOBILE,
                'COOKIE_PRE' => iPHP_COOKIE_PRE,
                'REFER' => iPHP_REFERER,
                "APP" => array(
                    'NAME' => iPHP::$app_name,
                    'DO' => iPHP::$app_do,
                    'METHOD' => iPHP::$app_method,
                )
            ));
            iView::set_iVARS(iPHP::$app_name,'SAPI',true);
        };
        return iPHP::run($app,$do,$args,$prefix);
    }

    public static function API($app = NULL,$do = NULL,$args = NULL) {
        $app OR $app = iSecurity::escapeStr($_GET['app']);
        return self::run($app,$do,$args,'API_');
    }
    public static function send_access_control() {
        @header("Access-Control-Allow-Origin: " . iCMS_URL);
        @header('Access-Control-Allow-Headers: X-Requested-With,X_Requested_With');
    }
    public static function assign_site(){
        $site          = self::$config['site'];
        $site['title'] = $site['name'];
        $site['404']   = iPHP_URL_404;
        $site['url']   = iCMS_URL;
        $site['murl']  = self::$config['template']['mobile']['domain'];
        $site['tpl']   = iView::$config['template']['dir'];
        $site['page']  = isset($_GET['p'])?(int)$_GET['p']:(int)$_GET['page'];
        $site['urls']  = array(
            "template" => iCMS_URL.'/template',
            "tpl"      => iCMS_URL.'/template/'.iView::$config['template']['dir'],
            "public"   => iCMS_PUBLIC_URL,
            "user"     => iCMS_USER_URL,
            "res"      => iCMS_FS_URL,
            "ui"       => iCMS_PUBLIC_URL.'/ui',
            "avatar"   => iCMS_FS_URL.'avatar/',
            "mobile"   => $site['murl'],
            "desktop"  => self::$config['template']['desktop']['domain'],
        );
        iDevice::domain($site['urls']);
        iView::assign('site',$site);
    }
    //向下兼容[暂时保留]
    public static function check_view_html($tpl,$C,$key) {
        return appsApp::is_html($tpl,$C,$key);
    }
    //向下兼容[暂时保留]
    public static function redirect_html($iurl) {
        return appsApp::redirect_html($iurl);
    }
    //分页数缓存
    public static function page_total_cache($sql, $type = null,$cachetime=3600) {
        $total = (int) $_GET['total_num'];
        if($type=="G"){
            empty($total) && $total = iDB::value($sql);
        }else{
            $cache_key = 'page_total/'.substr(md5($sql), 8, 16);
            if(empty($total)){
                if (!isset($_GET['page_total_cache'])|| $type === 'nocache'||!$cachetime) {
                    $total = iDB::value($sql);
                    $type === null && iCache::set($cache_key,$total,$cachetime);
                }else{
                    $total = iCache::get($cache_key);
                }
            }
        }
        return (int)$total;
    }

    public static function set_tpl_const() {
        $APPID = array();
        foreach ((array)self::$config['apps'] as $_app => $_appid) {
            $APPID[strtoupper($_app)] = $_appid;
        }
        iView::set_iVARS(array(
            'VERSION' => iCMS_VERSION,
            'API'     => iCMS_API,
            'SAPI'    => iCMS_API_URL,
            'DEVICE'  => iPHP_DEVICE,
            'CONFIG'  => self::$config,
            'APPID'   => $APPID
        ));
    }
}
