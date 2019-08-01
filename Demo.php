<?php

namespace app\index\controller;

use WxPay\WxPayApi;
use WxPay\WxPayConfigInterface;
use WxPay\Request\WxPayResults;
use WxPay\Request\WxPayNotifyReply;
use WxPay\Request\WxPayUnifiedOrder;

class Demo {

    function __construct() {

        define('SITE_URL', 'http://127.0.0.1');
    }

    function index($order_sn = 'O00000000000000R0000', $order_amount = 0.01) {
        $order_sn = 'O' . date('YmdHis') . 'R' . rand(1000, 9999);
        //out_trade_no、out_request_no、out_biz_no 业务订单号均可由平台后端控制

        //初始化配置类，微信支付参数配置
        $wxConfig = new WxPayConfigInterface();
        $wxConfig->SetAppId($this->wx_appID);
        $wxConfig->SetMerchantId($this->wx_payID);
        $wxConfig->SetKey($this->wx_payKey);

        //支付订单参数
        $data = [
            "out_trade_no" => $order_sn . rand(1000, 9999),
            //商户订单号，避开微信限制，随机4位单号
            "total_amount" => 0.10,
            //订单价格，单位：元￥
            "subject"      => '支付测试订单',
            //订单名称 可以中文
        ];

        //统一下单
        $input = new WxPayUnifiedOrder();
        $input->SetBody($data['subject']);
        $input->SetOut_trade_no($data['out_trade_no']);
        $input->SetTotal_fee($data['total_amount'] * 100);

        //设置回调函数接口
        $input->SetNotify_url(SITE_URL . 'Notify');

        //APP    -APP支付
        //NATIVE -扫码支付
        //JSAPI  -公众号、小程序支付
        $input->SetTrade_type("APP");
        //JSAPI支付需个别设置OPENID
//            $input->SetOpenid("OPENID");
        $wx_result = WxPayApi::unifiedOrder($wxConfig, $input);

        if ($wx_result['return_code'] == 'SUCCESS' && $wx_result['result_code'] == 'SUCCESS') {
            $result['nonce_str'] = $wx_result['nonce_str'];
            $result['prepay_id'] = $wx_result['prepay_id'];
            $result['partnerid'] = $wx_result['mch_id'];
            $result['appid']     = $wx_result['appid'];
            $result['package']   = 'Sign=WXPay';
            $result['time']      = time();

            $signData = new WxPayResults();
            $signData->SetData('appid', $result['appid']);
            $signData->SetData('timestamp', $result['time']);
            $signData->SetData('package', $result['package']);
            $signData->SetData('noncestr', $result['nonce_str']);
            $signData->SetData('partnerid', $result['partnerid']);

            //预支付订单唯一凭证，两小时有效，不能再次获取 == 缓特么得存
            $signData->SetData('prepayid', $result['prepay_id']);

            $result['sign'] = $signData->SetSign($wxConfig);
        }

        $this->result($result, $wx_result['result_code'] == 'SUCCESS' ? 1 : 0, 1, 'json');
    }

    function Notify() {
        $msg = "OK";
        //初始化配置类，微信支付参数配置
        $wxConfig = new WxPayConfigInterface();
        $wxConfig->SetAppId($this->wx_appID);
        $wxConfig->SetMerchantId($this->wx_payID);
        $wxConfig->SetKey($this->wx_payKey);

        //直接回调函数使用方法: notify(you_function);
        //回调类成员函数方法:notify(array($this, you_function))，这里指updateOrderPay方法
        $result = WxPayApi::notify($wxConfig, [$this, 'updateOrderPay'], $msg);

        //根据业务方法返回值判断是否处理正确
        $resultObj = new WxPayNotifyReply();
        if ($result == TRUE) {
            $resultObj->SetReturn_code("SUCCESS");
            $resultObj->SetReturn_msg("OK");
            $resultObj->SetSign($wxConfig);
        } else {
            $resultObj->SetReturn_code("FAIL");
            $resultObj->SetReturn_msg($msg);
        }

        $xml = $resultObj->ToXml();
        WxpayApi::replyNotify($xml);
    }

    function updateOrderPay($request) {
        $request = $request->GetValues();
        //除去单号随机值
        $request['out_trade_no'] = substr($request['out_trade_no'], 0, -4);

        $request = [
            "appid"          => "",
            "bank_type"      => "",
            "fee_type"       => "",
            "is_subscribe"   => "",
            "mch_id"         => "",
            "nonce_str"      => "",
            "result_code"    => "SUCCESS",
            "return_code"    => "SUCCESS",
            "sign"           => "",

            //支付时间
            "time_end"       => "20190801164312",
            //现金支付金额，单位均为分
            "cash_fee"       => "1",
            //支付金额
            "total_fee"      => "1",
            //支付类型
            "trade_type"     => "APP",
            //平台提交的订单号
            "out_trade_no"   => "O00000000000000R0000",
            //微信生成支付单号，建议保存
            "transaction_id" => "",
            //支付人在平台对应OPENID
            "openid"         => "",
        ];

        //业务逻辑处理,并返回布尔值
        return TRUE || FALSE;
    }

    //密钥配置
    private $wx_appID  = '';   //对应应用appid，小程序、APP、公众号各不相同
    private $wx_payID  = '';   //微信商户号，应用关联
    private $wx_payKey = '';   //微信商户号，支付密钥key
}