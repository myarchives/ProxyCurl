<?php

namespace Latrell\ProxyCurl;

use Curl\Curl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Latrell\ProxyCurl\Exception as ProxyCurlException;

class ProxyCurl
{
	protected $pack;

	/**
	 * @var Curl
	 */
	protected $curl;

	/**
	 * @var string IP城市代码，默认全国。
	 */
	protected $city_code;

	/**
	 * @var bool 当地理位置没有代理时，是否使用全国随机IP
	 */
	protected $strict;

	public function __construct($pack, $time, $strict)
	{
		$this->pack = $pack;
		$this->time = $time;
		$this->strict = $strict;
	}

	/**
	 * Close the connection when the Curl object will be destroyed.
	 */
	public function __destruct()
	{
		$this->close();
	}

	/**
	 * 初始化CURL
	 *
	 * @return $this
	 */
	public function init()
	{
		if ($this->curl) {
			$this->curl->reset();
		} else {
			$this->curl = new Curl;
		}

		$this->setOpt(CURLOPT_SSL_VERIFYPEER, false);
		$this->setOpt(CURLOPT_SSL_VERIFYHOST, false);

		return $this;
	}

	/**
	 * 设置IP城市代码
	 *
	 * @param string $city_code
	 *
	 * @return $this
	 */
	public function setCity($city_code)
	{
		$this->city_code = $city_code;
		return $this;
	}

	/**
	 * 设置当地理位置没有代理时，是否使用全国随机IP
	 *
	 * @param bool $strict
	 *
	 * @return $this
	 */
	public function setStrict($strict)
	{
		$this->strict = $strict;
		return $this;
	}

	/**
	 * 设置UserAgent
	 *
	 * @param string $user_agent
	 *
	 * @return $this
	 */
	public function setUserAgent($user_agent)
	{
		$this->curl->setUserAgent($user_agent);
		return $this;
	}

	/**
	 * 设置来路
	 *
	 * @param string $referer
	 *
	 * @return $this
	 */
	public function setReferer($referer)
	{
		$this->curl->setReferer($referer);
		return $this;
	}

	/**
	 * 设置请求头
	 *
	 * @param string $key
	 * @param string $value
	 *
	 * @return $this
	 */
	public function setHeader($key, $value)
	{
		$this->curl->setHeader($key, $value);
		return $this;
	}

	/**
	 * 设置CURL选项
	 *
	 * @param $option
	 * @param $value
	 *
	 * @return $this
	 */
	public function setOpt($option, $value)
	{
		$this->curl->setOpt($option, $value);
		return $this;
	}

	/**
	 * 获取代理IP
	 *
	 * @param bool $force 是否跳过缓存强制获取
	 *
	 * @return ProxyModel
	 * @throws ProxyCurlException
	 */
	public function getShortS5Proxy($force = false)
	{
		$proxy = null;
		$cache_key = 'ShortS5Proxy@city:' . $this->city_code;
		if ($force || ! ($proxy = Cache::get($cache_key))) {

			// 请求频率限制2秒一次，这里限制5秒，因为HTTP请求可能需要占用3秒。
			Cache::lock('ShortS5Proxy', 5)->block(10);

			$params = [
				'num' => '1', // 提取IP数量
				'pro' => '', // 省份，默认全国
				'city' => '', // 城市，默认全国
				'regions' => '', // 全国混拨地区
				'yys' => '0', // 运营商：0:不限，100026:联通，100017:电信
				'port' => '2', // IP协议：1:HTTP，2:SOCK5，11:HTTPS
				'time' => $this->time, // 稳定时长
				'type' => '2', // 数据格式：1:TXT，2:JSON，3:html
				'pack' => $this->pack, // 用户套餐ID
				'ts' => '1', // 是否显示IP过期时间：1:显示，2:不显示
				'ys' => '1', // 是否显示IP运营商：1:显示
				'cs' => '1', // 否显示位置：1:显示
				'lb' => '1', // 分隔符：1:\r\n，2:/br，3:\r，4:\n，5:\t，6:自定义
				'sb' => '', // 自定义分隔符
				'mr' => '1',// 去重选择：1:360天去重,2:单日去重,3:不去重
				'pb' => '45', // 端口位数：4:4位端口，5:5位端口
			];
			if ($this->city_code) {
				$params['pro'] = substr($this->city_code, 0, 2) . '0000';
				$params['city'] = $this->city_code;
			}
			$curl = new Curl;
			$curl->setOpt(CURLOPT_TIMEOUT, 3);
			$curl->get('http://webapi.http.zhimacangku.com/getip', $params);
			$curl->close();
			if ($curl->error) {
				throw new ProxyCurlException($curl->error_message, $curl->error_code);
			}
			$json = json_decode($curl->response);
			if (! isset($json->success)) {
				throw new ProxyCurlException('Unexpected data structure: ' . $curl->response);
			}
			if (! $json->success) {
				throw new ProxyCurlException($json->msg, $json->code);
			}
			$proxy = new ProxyModel;
			$proxy->ip = $json->data[0]->ip;
			$proxy->port = $json->data[0]->port;
			$proxy->address = $json->data[0]->city;
			$proxy->isp = $json->data[0]->isp;
			$proxy->timeout = Carbon::parse($json->data[0]->expire_time);
			Cache::put($cache_key, $proxy, $proxy->timeout->subSeconds(5));
		}
		return $proxy;
	}

	/**
	 * 发起 GET 请求
	 *
	 * @param string $url
	 * @param array $data
	 *
	 * @return $this
	 * @throws ProxyCurlException
	 */
	public function get($url, $data = [])
	{
		$this->setProxy();
		$this->curl->get($url, $data);
		return $this;
	}

	/**
	 * 发起 POST 请求
	 *
	 * @param string $url
	 * @param array $data
	 *
	 * @return $this
	 * @throws ProxyCurlException
	 */
	public function post($url, $data = [])
	{
		$this->setProxy();
		$this->curl->post($url, $data);
		return $this;
	}

	/**
	 * 获取响应头
	 *
	 * @param string $header_key Optional key to get from the array.
	 *
	 * @return bool|string|array
	 */
	public function getResponseHeaders($header_key = null)
	{
		if ($header_key) {
			return $this->curl->getResponseHeaders($header_key);
		}
		return $this->curl->response_headers;
	}

	/**
	 * 释放资源
	 *
	 * @return $this
	 */
	public function close()
	{
		if ($this->curl) {
			$this->curl->close();
		}
		return $this;
	}

	public function __get($name)
	{
		if ($this->curl) {
			return $this->curl->$name;
		}
	}

	/**
	 * 设置使用的代理
	 *
	 * @throws Exception
	 */
	protected function setProxy()
	{
		$proxy = null;
		try {
			$proxy = $this->getShortS5Proxy();
		} catch (ProxyCurlException $e) {
			if ($this->strict || $e->getCode() != 115) {
				throw $e;
			} else {
				$city_code = $this->city_code;
				$this->city_code = null;
				$proxy = $this->getShortS5Proxy();
				$this->city_code = $city_code;
			}
		}
		if ($proxy) {
			$this->setOpt(CURLOPT_PROXY, $proxy->ip);
			$this->setOpt(CURLOPT_PROXYPORT, $proxy->port);
			$this->setOpt(CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
			$this->setOpt(CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
		}
	}
}
