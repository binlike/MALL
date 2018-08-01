<?php
	/* *
	 * 功能：支付宝手机网站alipay.trade (统一下单交易查询关闭接口)业务参数封装
	 * 版本：2.0
	 * 修改日期：2016-11-01
	 * 说明：
	 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
	 */
	namespace alipay;
	require_once (EXTEND_PATH . 'alipay/aop/AopClient.php');

	class alipay {

		//应用id
		private $appid;

		//商户私钥
		private $private_key;

		//支付宝公钥
		private $alipay_public_key;

		//编码格式
		private $charset = "UTF-8";

		//签名方式
		private $signtype = "RSA";

		//支付宝网关地址
		private $gateway_url = "https://openapi.alipay.com/gateway.do";

		//返回数据格式
		private $format = "json";

		//支付宝账号
		private $seller_id = "";

		//返回数据格式
		private $partner = "";

		private $aop;

		/**
		 * 架构函数
		 * @access public
		 */
		function __construct($appid, $private_key, $alipay_public_key, $signtype, $seller_id = '', $partner = '') {
			$this -> appid = $appid;
			$this -> private_key = file_get_contents($private_key);
			$this -> alipay_public_key = file_get_contents($alipay_public_key);
			$this -> signtype = $signtype;
			$this -> seller_id = $seller_id;
			$this -> partner = $partner;

			$this -> aop = new \AopClient();
			$this -> aop -> gatewayUrl = $this -> gateway_url;
			$this -> aop -> appId = $appid;
			$this -> aop -> rsaPrivateKey = file_get_contents($private_key);
			$this -> aop -> format = $this -> format;
			$this -> aop -> charset = $this -> charset;
			$this -> aop -> signType = $signtype;
			$this -> aop -> alipayrsaPublicKey = file_get_contents($alipay_public_key);
		}

		/**
		 * 创建APP支付订单
		 *
		 * @param string $body 对一笔交易的具体描述信息。
		 * @param string $subject 商品的标题/交易标题/订单标题/订单关键字等。
		 * @param string $order_sn 商户网站唯一订单号
		 * @return array 返回订单信息
		 */
		function tradeAppPay($out_trade_no, $subject, $total_amount, $body = "商品描述", $timeout_express, $return_url, $notify_url) {
			require_once (EXTEND_PATH . 'alipay/aop/request/AlipayTradeAppPayRequest.php');
			//实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
			$request = new \AlipayTradeAppPayRequest();
			//SDK已经封装掉了公共参数，这里只需要传入业务参数
			$bizcontent = array(
				'body' => $body,
				'subject' => $subject,
				'out_trade_no' => $out_trade_no,
				'timeout_express' => $timeout_express, //失效时间
				'total_amount' => $total_amount, //价格
				'product_code' => 'QUICK_MSECURITY_PAY',
			);
			//打印业务参数
			$this -> writeLog($bizcontent);
			//商户外网可以访问的异步地址 (异步回掉地址，根据自己需求写)
			$request -> setNotifyUrl($notify_url);
			$request -> setBizContent(json_encode($bizcontent));
			//这里和普通的接口调用不同，使用的是sdkExecute
			$response = $this -> aop -> sdkExecute($request);
			return $response;
		}

		/**
		 * alipay.trade.wap.pay
		 * @param $builder 业务参数，使用buildmodel中的对象生成。
		 * @param $return_url 同步跳转地址，公网可访问
		 * @param $notify_url 异步通知地址，公网可以访问
		 * @return $response 支付宝返回的信息
		 */
		function tradeWapPay($out_trade_no, $subject, $total_amount, $body = "商品描述", $timeout_express, $return_url, $notify_url) {
			require_once (EXTEND_PATH . 'alipay/aop/request/AlipayTradeWapPayRequest.php');
			//实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.wap.pay
			$request = new \AlipayTradeWapPayRequest();
			//SDK已经封装掉了公共参数，这里只需要传入业务参数
			$bizcontent = array(
				'body' => $body,
				'subject' => $subject,
				'out_trade_no' => $out_trade_no,
				'timeout_express' => $timeout_express, //失效时间
				'total_amount' => $total_amount, //价格
				'product_code' => 'QUICK_WAP_PAY',
			);
			//打印业务参数
			$this -> writeLog($bizcontent);

			$request -> setNotifyUrl($notify_url);
			$request -> setReturnUrl($return_url);
			$request -> setBizContent(json_encode($bizcontent));

			// 首先调用支付api
			$response = $this -> aopclientRequestExecute($request, true);
			return $response;
		}

		function aopclientRequestExecute($request, $ispage = false) {

			// 开启页面信息输出
			$this -> aop -> debugInfo = false;
			if ($ispage) {
				$result = $this -> aop -> pageExecute($request, "post");
				if (is_weixin()) {
					//微信浏览器，需要复制链接到手机浏览器支付
					return 'wx_notice';
				}
				else {
					echo $result;
				}
			}
			else {
				$result = $this -> aop -> Execute($request);
			}

			//打开后，将报文写入log文件
			$this -> writeLog("response: " . var_export($result, true));
			return $result;
		}

		/**
		 * alipay.trade.query (统一收单线下交易查询)
		 * @param $builder 业务参数，使用buildmodel中的对象生成。
		 * @return $response 支付宝返回的信息
		 */
		function Query($builder) {
			$biz_content = $builder -> getBizContent();
			//打印业务参数
			$this -> writeLog($biz_content);
			$request = new AlipayTradeQueryRequest();
			$request -> setBizContent($biz_content);

			// 首先调用支付api
			$response = $this -> aopclientRequestExecute($request);
			$response = $response -> alipay_trade_query_response;
			var_dump($response);
			return $response;
		}

		/**
		 * alipay.trade.refund (统一收单交易退款接口)
		 * @param $builder 业务参数，使用buildmodel中的对象生成。
		 * @return $response 支付宝返回的信息
		 */
		function Refund($out_trade_no, $out_request_no, $refund_fee) {
			require_once (EXTEND_PATH . 'alipay/aop/request/AlipayTradeRefundRequest.php');
			//实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
			$request = new \AlipayTradeRefundRequest();
			//SDK已经封装掉了公共参数，这里只需要传入业务参数
			$bizcontent = array(
				'out_trade_no' => $out_trade_no,
				'refund_amount' => $refund_fee,
				'refund_reason' => '',
				'out_request_no' => $out_request_no
			);
			//打印业务参数
			$this -> writeLog($bizcontent);

			$request -> setBizContent(json_encode($bizcontent));
			// 首先调用支付api
			$response = $this -> aopclientRequestExecute($request);
			$response = $response -> alipay_trade_refund_response;
			return $response;
		}

		/**
		 * alipay.trade.close (统一收单交易关闭接口)
		 * @param $builder 业务参数，使用buildmodel中的对象生成。
		 * @return $response 支付宝返回的信息
		 */
		function Close($builder) {
			$biz_content = $builder -> getBizContent();
			//打印业务参数
			$this -> writeLog($biz_content);
			$request = new AlipayTradeCloseRequest();
			$request -> setBizContent($biz_content);

			// 首先调用支付api
			$response = $this -> aopclientRequestExecute($request);
			$response = $response -> alipay_trade_close_response;
			var_dump($response);
			return $response;
		}

		/**
		 * 验签方法
		 * @param $arr 验签支付宝返回的信息，使用支付宝公钥。
		 * @return boolean
		 */
		function check($arr) {
			return $this -> aop -> rsaCheckV1($arr, $this -> alipay_public_key, $this -> signtype);
		}

		//请确保项目文件有可写权限，不然打印不了日志。
		function writeLog($s) {
			if (!is_string($s)) {
				$s = json_encode($s, JSON_UNESCAPED_UNICODE);
			}
			$fp = fopen(ROOT_PATH . "paylog" . DS . "nalipaylog_" . strftime("%Y%m%d", time()) . ".txt", "a");
			flock($fp, LOCK_EX);
			fwrite($fp, "\r\n执行日期：" . strftime("%Y%m%d%H%M%S", time()) . $s);
			flock($fp, LOCK_UN);
			fclose($fp);
		}

		/** *利用google api生成二维码图片
		 * $content：二维码内容参数
		 * $size：生成二维码的尺寸，宽度和高度的值
		 * $lev：可选参数，纠错等级
		 * $margin：生成的二维码离边框的距离
		 */
		function create_erweima($content, $size = '200', $lev = 'L', $margin = '0') {
			$content = urlencode($content);
			$image = '<img src="http://chart.apis.google.com/chart?chs=' . $size . 'x' . $size . '&amp;cht=qr&chld=' . $lev . '|' . $margin . '&amp;chl=' . $content . '"  widht="' . $size . '" height="' . $size . '" />';
			return $image;
		}

		/**
		 * alipay.trade.page.pay
		 * @param $builder 业务参数，使用buildmodel中的对象生成。
		 * @param $return_url 同步跳转地址，公网可以访问
		 * @param $notify_url 异步通知地址，公网可以访问
		 * @return $response 支付宝返回的信息
		 */
		function pagePay($out_trade_no, $subject, $total_amount, $body = "商品描述", $timeout_express, $return_url, $notify_url) {
			require_once (EXTEND_PATH . 'alipay/aop/request/AlipayTradePagePayRequest.php');
			//实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.wap.pay
			$request = new \AlipayTradePagePayRequest();
			//SDK已经封装掉了公共参数，这里只需要传入业务参数
			$bizcontent = array(
				'body' => $body,
				'subject' => $subject,
				'out_trade_no' => $out_trade_no,
				'timeout_express' => $timeout_express, //失效时间
				'total_amount' => $total_amount, //价格
				'product_code' => 'FAST_INSTANT_TRADE_PAY',
			);
			//打印业务参数
			$this -> writeLog($bizcontent);

			$request -> setNotifyUrl($notify_url);
			$request -> setReturnUrl($return_url);
			$request -> setBizContent(json_encode($bizcontent));

			// 首先调用支付api
			$response = $this -> aopclientRequestExecute($request, true);
			return $response;
		}
		
		function getOauthUserInfo($code){
			require_once (EXTEND_PATH . 'alipay/aop/request/AlipaySystemOauthTokenRequest.php');
			//实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
			$request = new \AlipaySystemOauthTokenRequest();
			//SDK已经封装掉了公共参数，这里只需要传入业务参数
			$bizcontent = array(
				'grant_type' => 'authorization_code',
				'code' => $code
			);
			//打印业务参数
			$this -> writeLog($bizcontent);
			$request -> setCode($code);
			$request -> setGrantType('authorization_code');
			// 首先调用支付api
			$response = $this -> aopclientRequestExecute($request, false);
			return $response;
		}
	}
?>