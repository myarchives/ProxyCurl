<?php
return [
	'enable' => env('PROXY_CURL_ENABLE'),

	/**
	 * 芝麻HTTP代理
	 * @linnk http://h.zhimaruanjian.com/getapi/#obtain_ip
	 */
	'pack' => env('PROXY_CURL_PACK'), // 用户套餐ID
	// 稳定时长
	// 1 : "05分钟至25分钟【0.04芝麻币/个】"
	// 2 : "05分钟至25分钟【0.04芝麻币/个】"
	// 3 : "03小时至06小时【0.2芝麻币/个】"
	// 4 : "06小时至12小时【0.5芝麻币/个】"
	// 7 : "48小时至72小时【5芝麻币/个】"
	'time' => env('PROXY_CURL_TIME', 1),

	/**
	 * IP限速间隔时间
	 * 当大于零时，间隔时间内发起的请求会强制申请新IP
	 */
	'interval' => env('PROXY_CURL_INTERVAL', 0),

	/**
	 * 非严格模式下，当指定城市没有代理时，将使用全国随机IP
	 */
	'strict' => env('PROXY_CURL_STRICT', false),
];