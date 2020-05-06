<?php

namespace WxPay;

use WxPay\Request\WxPayRefund;
use WxPay\WxPayApi;
use WxPay\WxPayConfigInterface;
use WxPay\Request\WxPayResults;
use WxPay\Request\WxPayNotifyReply;
use WxPay\Request\WxPayUnifiedOrder;

class Helpers
{

    private $wxConfig;
    private $site_url;      //当前域名
    private $pay_subject;   //当前项目主体

    function __construct(\stdClass $wxConfig)
    {

        $this->wxConfig = new WxPayConfigInterface();
        $this->wxConfig->SetAppId($wxConfig->appID);
        $this->wxConfig->SetMerchantId($wxConfig->payID);
        $this->wxConfig->SetKey($wxConfig->payKey);

        $this->site_url = config('app.url');
        $this->pay_subject = config('app.name');
    }

    /**
     * 统一支付
     * out_trade_no、out_request_no、out_biz_no 业务订单号均可由平台后端控制
     * @param string $order_sn
     * @param float $order_amount
     * @throws WxPayException
     */
    function AppPay(string $order_sn, float $order_amount)
    {
        //支付订单参数
        $data = [
            "out_trade_no" => $order_sn . rand(1000, 9999),
            //商户订单号，避开微信限制，随机4位单号
            //注：微信同一订单号支付未成功前，不能重复申请预支付，额外补充4位随机
            "total_amount" => $order_amount,
            //订单价格，单位：元￥
            "subject"      => $this->subject,
            //订单名称 可以中文
        ];

        //统一下单
        $input = new WxPayUnifiedOrder();
        $input->SetBody($data['subject']);
        $input->SetOut_trade_no($data['out_trade_no']);
        $input->SetTotal_fee($data['total_amount'] * 100);

        //设置回调函数接口
        $input->SetNotify_url(SITE_URL . 'Notify');
        $input->SetTrade_type($type);
        //JSAPI支付需额外设置OPENID
        //$input->SetOpenid("OPENID");
        $wx_result = WxPayApi::unifiedOrder($this->wxConfig, $input);

        if ($wx_result['return_code'] == 'SUCCESS' && $wx_result['result_code'] == 'SUCCESS') {
            $result['nonce_str'] = $wx_result['nonce_str'];
            $result['prepay_id'] = $wx_result['prepay_id'];
            $result['partnerid'] = $wx_result['mch_id'];
            $result['appid'] = $wx_result['appid'];
            $result['package'] = 'Sign=WXPay';
            $result['time'] = time();

            $signData = new WxPayResults();
            $signData->SetData('appid', $result['appid']);
            $signData->SetData('timestamp', $result['time']);
            $signData->SetData('package', $result['package']);
            $signData->SetData('noncestr', $result['nonce_str']);
            $signData->SetData('partnerid', $result['partnerid']);

            //预支付订单唯一凭证，两小时有效，不能再次获取 == 缓特么得存
            $signData->SetData('prepayid', $result['prepay_id']);

            $result['sign'] = $signData->SetSign($wxConfig);
            return $result;
        }

        return FALSE;
    }

//    /**
//     * 二维码、JS支付仅限公众号型商户号可用
//     */
//    function NativePay(string $order_sn, float $order_amount)
//    {
//        return $this->PayIndex($order_sn, $order_amount, 'NATIVE');
//    }

    /**
     * 退款
     * @param string $order_sn 平台生成退款订单号
     * @param string $trade_no 已支付的支付流水号，支付成功后返回
     * @param float $amount 本次退款金额
     * @return integer
     */
    function Refund(string $order_sn, string $trade_no, float $amount){
        //回水
        $input = new WxPayRefund();
        $input->SetOut_refund_no($order_sn);
        $input->SetTransaction_id($trade_no);
        $input->SetRefund_fee($amount);
        $input->SetOp_user_id($this->wxConfig->GetMerchantId());

        $wx_result = WxPayApi::refund($this->wxConfig, $input);
        if ($wx_result['return_code'] == 'SUCCESS' && $wx_result['result_code'] == 'SUCCESS')
            return TRUE;
        return FALSE;
    }

    /**
     * @param mixed $callback 回调函数，接收post数据，对象方法用数组传递，具体参照call_user_func()
     * @param array $request 支付宝方请求参数，参照文档
     * @throws WxPayException
     */
    function Notify($callback)
    {
        $msg = "OK";
        //初始化配置类，微信支付参数配置
        $wxConfig = new WxPayConfigInterface();
        $wxConfig->SetAppId($this->wx_appID);
        $wxConfig->SetMerchantId($this->wx_payID);
        $wxConfig->SetKey($this->wx_payKey);

        //直接回调函数使用方法: notify(you_function);
        //回调类成员函数方法:notify(array($this, you_function))，这里指updateOrderPay方法
        $result = WxPayApi::notify($wxConfig, $callback, $msg);

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

    /**
     * 回调参数例子
     * {
     * "appid"          : "",
     * "bank_type"      : "",
     * "fee_type"       : "",
     * "is_subscribe"   : "",
     * "mch_id"         : "",
     * "nonce_str"      : "",
     * "result_code"    : "SUCCESS",
     * "return_code"    : "SUCCESS",
     * "sign"           : "",
     *
     * //支付时间
     * "time_end"       : "0",
     * //现金支付金额，单位均为分
     * "cash_fee"       : "1",
     * //支付金额
     * "total_fee"      : "1",
     * //支付类型
     * "trade_type"     : "APP",
     * //平台提交的订单号
     * "out_trade_no"   : "",
     * //微信生成支付单号，建议保存
     * "transaction_id" : "",
     * //支付人在平台对应OPENID
     * "openid"         : ""
     * }
     */
}