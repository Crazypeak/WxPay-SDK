<?php

class Demo {
    private $order_sn, $order_amount, $notify_url, $payCode;
    private $body = '微开云支付测试订单';

    function index(){
        $this->order_sn = time().rand(1000,9999);
        $this->order_amount = 0.01;
        $this->notify_url = 'juhuiyanfa.com/pay.php';


        $wx_appID  = 'wx0173f7911a035faf';
        $wx_payID  = '1546454401';
        $wx_payKey = "111111111111111111111111111111We";

        $wxConfig = new \WxPay\WxPayConfigInterface();
        $wxConfig->SetAppId($wx_appID);
        $wxConfig->SetMerchantId($wx_payID);
        $wxConfig->SetKey($wx_payKey);

        //统一下单
        $input = new \WxPay\Request\WxPayUnifiedOrder();
        $input->SetBody($this->body);
        $input->SetOut_trade_no($this->order_sn);
        $input->SetTotal_fee($this->order_amount*100);
        $input->SetNotify_url($this->notify_url);
        $input->SetTrade_type("APP");
        $result = \WxPay\WxPayApi::unifiedOrder($wxConfig, $input);

        var_dump($result);die;
    }
}

$t = new Demo();
$t->index();