<?php
/**
 * 获取链接内容类
 *
 * @package Z-BlogPHP
 * @subpackage ClassLib/Network 网络连接
 */
class Networkfile_get_contents implements iNetwork {

	private $readyState = 0; #状态
	private $responseBody = NULL; #返回的二进制
	private $responseStream = NULL; #返回的数据流
	private $responseText = ''; #返回的数据
	private $responseXML = NULL; #尝试把responseText格式化为XMLDom
	private $status = 0; #状态码
	private $statusText = ''; #状态码文本
	private $responseVersion = ''; #返回的HTTP版体

	private $option = array();
	private $url = '';
	private $postdata = array();
	private $httpheader = array();
	private $responseHeader = array();
	private $isgzip = false;
	private $maxredirs = 0;
	private $parsed_url = array();

	private $__isBinary = false;
	private $__boundary = '';

	/**
	 * @param $property_name
	 * @param $value
	 * @throws Exception
	 */
	public function __set($property_name, $value) {
		throw new Exception($property_name . ' readonly');
	}

	/**
	 * @param $property_name
	 * @return mixed
	 */
	public function __get($property_name) {
		if (strtolower($property_name) == 'responsexml') {
			$w = new DOMDocument();
			return $w->loadXML($this->responseText);
		} elseif (strtolower($property_name) == 'scheme' ||
			strtolower($property_name) == 'host' ||
			strtolower($property_name) == 'port' ||
			strtolower($property_name) == 'user' ||
			strtolower($property_name) == 'pass' ||
			strtolower($property_name) == 'path' ||
			strtolower($property_name) == 'query' ||
			strtolower($property_name) == 'fragment') {
			if (isset($this->parsed_url[strtolower($property_name)])) {
				return $this->parsed_url[strtolower($property_name)];
			}

		} else {
			return $this->$property_name;
		}
	}

	/**
	 *
	 */
	public function abort() {

	}

	/**
	 * @return string
	 */
	public function getAllResponseHeaders() {
		return implode("\r\n", $this->responseHeader);
	}

	/**
	 * @param $bstrHeader
	 * @return string
	 */
	public function getResponseHeader($bstrHeader) {
		$name = strtolower($bstrHeader);
		foreach ($this->responseHeader as $w) {
			if (strtolower(substr($w, 0, strpos($w, ':'))) == $name) {
				return substr(strstr($w, ': '), 2);
			}
		}
		return '';
	}

	/**
	 * @param $resolveTimeout
	 * @param $connectTimeout
	 * @param $sendTimeout
	 * @param $receiveTimeout
	 */
	public function setTimeOuts($resolveTimeout, $connectTimeout, $sendTimeout, $receiveTimeout) {

	}

	/**
	 * @param $bstrMethod
	 * @param $bstrUrl
	 * @param bool $varAsync
	 * @param string $bstrUser
	 * @param string $bstrPassword
	 * @return bool
	 * @throws Exception
	 */
	public function open($bstrMethod, $bstrUrl, $varAsync = true, $bstrUser = '', $bstrPassword = '') {
		//Async无用
		//初始化变量
		$this->reinit();
		$method = strtoupper($bstrMethod);
		$this->option['method'] = $method;
		$this->parsed_url = parse_url($bstrUrl);

		if (!$this->parsed_url) {
			throw new Exception('URL Syntax Error!');
		} else {
			if ($bstrUser != '') {
				$bstrUrl = substr($bstrUrl, 0, strpos($bstrUrl, ':')) . '://' . $bstrUser . ':' . $bstrPassword . '@' . substr($bstrUrl, strpos($bstrUrl, '/') + 2);
			}
			$this->url = $bstrUrl;
			if (!isset($this->parsed_url['port'])) {
				if ($this->parsed_url['scheme'] == 'https') {
					$this->parsed_url['port'] = 443;
				} else {
					$this->parsed_url['port'] = 80;
				}
			}
		}

		return true;
	}

	/**
	 * @param string $varBody
	 */
	public function send($varBody = '') {
		$data = $varBody;
		if (is_array($data)) {
			$data = http_build_query($data);
		}

		if ($this->option['method'] == 'POST') {

			if ($data == '') {
				$data = $this->__buildPostData(); //http_build_query($this->postdata);
			}
			$this->option['content'] = $data;

			if (!isset($this->httpheader['Content-Type'])) {
				if ($this->__isBinary) {
					$this->httpheader['Content-Type'] = 'Content-Type:  multipart/form-data; boundary=' . $this->__boundary;
				} else {
					$this->httpheader['Content-Type'] = 'Content-Type: application/x-www-form-urlencoded';
				}
			}
			$this->httpheader['Content-Length'] = 'Content-Length: ' . strlen($data);
		}

		$this->option['header'] = implode("\r\n", $this->httpheader);
		//$this->httpheader[] = 'Referer: ' . 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

		if ($this->maxredirs > 0) {
			$this->option['follow_location'] = 1;
			$this->option['max_redirects'] = $this->maxredirs;
		} else {
			$this->option['follow_location'] = 0;
			$this->option['max_redirects'] = 0;
		}

		ZBlogException::SuspendErrorHook();
		$http_response_header = null;
		$this->responseText = file_get_contents(($this->isgzip == true ? 'compress.zlib://' : '') . $this->url, false, stream_context_create(array('http' => $this->option)));
		$this->responseHeader = $http_response_header;
		ZBlogException::ResumeErrorHook();

		if (isset($this->responseHeader[0])) {
			$this->statusText = $this->responseHeader[0];
			$a = explode(' ', $this->statusText);
			if (isset($a[0])) {
				$this->responseVersion = $a[0];
			}

			if (isset($a[1])) {
				$this->status = $a[1];
			}

			unset($this->responseHeader[0]);
		}

	}

	/**
	 * @param $bstrHeader
	 * @param $bstrValue
	 * @param bool $append
	 * @return bool
	 */
	public function setRequestHeader($bstrHeader, $bstrValue, $append = false) {
		if ($append == false) {
			$this->httpheader[$bstrHeader] = $bstrHeader . ': ' . $bstrValue;
		} else {
			if (isset($this->httpheader[$bstrHeader])) {
				$this->httpheader[$bstrHeader] = $this->httpheader[$bstrHeader] . $bstrValue;
			} else {
				$this->httpheader[$bstrHeader] = $bstrHeader . ': ' . $bstrValue;
			}
		}
		return true;
	}

	/**
	 * @param $bstrItem
	 * @param $bstrValue
	 */
	private function add_postdata($bstrItem, $bstrValue) {
		$this->postdata[$bstrItem] = array(
			'data' => $bstrValue,
			'type' => 'text',
		);
	}
	/**
	 * @param string $name
	 * @param string $entity
	 * @return mixed
	 */
	public function addBinary($name, $entity) {
		$this->__isBinary = true;
		$return = array();

		$return['type'] = 'binary';
		if (is_file($entity)) {
			$return['data'] = file_get_contents($entity);
			$return['filename'] = basename($entity);
			if (function_exists('mime_content_type')) {
				$return['mime'] = mime_content_type($entity);
			} else if (function_exists('finfo_open')) {
				$finfo = finfo_open(FILEINFO_MIME);
				$return['mime'] = finfo_file($finfo, $filename);
				finfo_close($finfo);
			} else {
				$return['mime'] = 'application/octet-stream';
			}
		} else {
			$return['data'] = $entity;
			$return['filename'] = $name;
			$return['mime'] = 'application/octet-stream';
		}

		$this->postdata[$name] = $return;
	}

	/**
	 * @param string $name
	 * @param string $entity
	 * @return mixed
	 */
	public function addText($name, $entity) {
		return $this->add_postdata($name, $entity);
	}

	/**
	 * @return string
	 */
	private function __buildPostData() {
		if (!$this->__isBinary) {
			$array = array();
			foreach ($this->postdata as $name => $value) {
				$array[$name] = $value['data'];
			}
			return http_build_query($array);
		}
		$this->__buildBoundary();
		$boundary = $this->__boundary;
		$data = '';

		foreach ($this->postdata as $name => $value) {
			$content = $value['data'];
			$data .= "--{$boundary}\r\n";
			$data .= "Content-Disposition: form-data; ";
			if ($value['type'] == 'text') {
				$data .= 'name="' . $name . '"' . "\r\n\r\n";
				$data .= $content . "\r\n";
				$data .= "--{$boundary}\r\n";
			} else {
				$filename = $value['filename'];
				$mime = $value['mime'];
				$data .= 'name="' . $name . '"; filename="' . $filename . '"' . "\r\n";
				$data .= "Content-Type: $mime\r\n";
				$data .= "\r\n$content\r\n";
				$data .= "--{$boundary}\r\n";
			}
		}

		return $data;
	}

	/**
	 * Build Boundary
	 */
	private function __buildBoundary() {
		$boundary = 'ZBLOGPHP_BOUNDARY';
		$boundary .= substr(md5(time()), 8, 16);
		$this->__boundary = $boundary;
	}
	/**
	 *
	 */
	private function reinit() {
		global $zbp;
		$this->readyState = 0; #状态
		$this->responseBody = NULL; #返回的二进制
		$this->responseStream = NULL; #返回的数据流
		$this->responseText = ''; #返回的数据
		$this->responseXML = NULL; #尝试把responseText格式化为XMLDom
		$this->status = 0; #状态码
		$this->statusText = ''; #状态码文本

		$this->__isBinary = false;
		$this->__boundary = '';

		$this->option = array();
		$this->url = '';
		$this->postdata = array();
		$this->httpheader = array();
		$this->responseHeader = array();
		$this->setRequestHeader('User-Agent', 'Mozilla/5.0 (' . $zbp->cache->system_environment . ') Z-BlogPHP/' . ZC_BLOG_VERSION);
		$this->setMaxRedirs(1);
	}

	/**
	 * 启用Gzip
	 */
	public function enableGzip() {
		if (extension_loaded('zlib')) {
			$this->isgzip = true;
		}
	}

	/**
	 * @param int $n
	 */
	public function setMaxRedirs($n = 0) {
		$this->maxredirs = $n;
	}

}
