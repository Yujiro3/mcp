<?php
namespace Library\ADR;

use Library\ADR\Net\Url;

/**
 * リクエストクラス
 * 
 * Usage example:
 *
 * ```php
 * require '/opt/vendor/autoload.php';
 * use Library\ADR\Di;
 * use Library\ADR\Request;
 * 
 * Di::setInstance();
 * Di::set('request', function () {
 *   return new Request();
 * });
 * 
 * $name = Di::request()->get('name');
 * $num  = Di::request()->get('number', 'int');
 * $user = Di::request()->cookie('user');
 * ```
 */
class Request
{
    /**
     * メソッド
     * @var string
     */
    private $_method;

    /**
     * リクエスト配列
     * @var array<string, mixed>
     */
    private $_requests;

    /**
     * ヘッダーリスト
     * @var array<string, string>
     */
    private $_headers;

    /**
     * コンストラクタ
     *
     * @param  array    $requests
     * @return void
     */
    public function __construct(array $requests = [])
    {
        $this->_headers = [];
        $_method = 'GET';

        if (!empty($requests)) {
            $this->_requests = $requests;
            return ;
        }

        if (isset($_SERVER['REQUEST_METHOD'])) {
            $_method = strtoupper($_SERVER['REQUEST_METHOD']);
        }
        $this->_method = $_method;

        $_requests = [];
        switch ($_method) {
            case 'GET':
                $_requests = $_GET;
                break;
            case 'POST':
                $_requests = $_POST;
                break;
            case 'PUT':
            case 'DELETE':
                $_contents = file_get_contents('php://input');
                $_requests = [];
                parse_str($_contents, $_requests);
                break;
        }
        $this->_requests = $_requests;
    }

    /**
     * AWS-Lambdaリクエストの横取り
     *
     * @param  array    $payload
     * @return void
     */
    public function Intercept(array $payload) : void
    {
        $this->_headers = [];
        $_SERVER['REQUEST_URI'] = $payload['requestContext']['http']['path'] ?? $payload['path'] ?? '';
        $_SERVER['REQUEST_METHOD'] = $payload['requestContext']['http']['method'] ?? $payload['httpMethod'] ?? 'GET';
        $_POST = $_GET = $_COOKIE = $requests =[];

        $headers = $payload['headers'] ?? $payload['multiValueHeaders'];
        if (!empty($headers)) {
            if (is_array($headers)) {
                foreach ($headers as $key=> $header) {
                    $_SERVER[$key] = $header;
                }
            }
        }
        
        if (!empty($payload['body'])) {
            $body = $payload['body'];
            if (!empty($payload['isBase64Encoded'])) {
                $body = base64_decode($body);
            }
            $requests = json_decode($body, true);
            if (empty($requests)) {
                parse_str($body, $requests);
            }
            $_POST = $requests;
            $this->addRequests($requests);
        }

        $params = $payload['queryStringParameters'] ?? $payload['multiValueQueryStringParameters'] ?? [];
        if(!empty($params)) {
            $_GET = $params;
            $this->addRequests($params);
        }
    
        if (!empty($payload['cookies'])) {
            $requests = [];
            parse_str($payload['cookies'], $requests);
            $_COOKIE = $requests;
        }
    }

    /**
     * headerの設定
     *
     * @param  string $header
     * @return void
     */
    public function header(string $header) : void
    {
        $pos = stripos($header, ':');
        if (false === $pos) {
            $key   = $header;
            $value = $header;
        } else {
            $key   = substr($header, 0, $pos);
            $value = substr($header, $pos + 1);
        }
        
        $this->_headers[trim($key)] = trim($value);
        if (php_sapi_name() === 'cli') {
            return ;
        }
        header($header);
    }

    /**
     * headerリストの取得
     *
     * @return array
     */
    public function getHeaders() : array
    {
        return $this->_headers;
    }
    
    /**
     * リクエストデータの設定
     *
     * @param  array    $requests
     * @return void
     */
    public function addRequests(array $requests) : void
    {
        foreach ($requests as $key=> $value) {
            $this->_requests[$key] = $value;
        }
    }

    /**
     * リクエストデータの設定
     *
     * @param  array    $requests
     * @return void
     */
    public function setRequests(mixed $requests) : void
    {
        $this->_requests = $requests;
    }

    /**
     * リクエストデータの取得
     *
     * @param  string  $name
     * @param  integer  $filter
     * @return mixed
     */
    public function get(string $name, string $filter = null) : mixed
    {
        $_filter = $filter;

        $_requests = $this->_requests;
        if (isset($_requests[$name])) {
            $_value = $_requests[$name];
        } else {
            $_value = NULL;
            return $_value;
        }

        if ($this->_empty($_value)) {
            $_value = NULL;
            return $_value;
        }

        if (empty($_filter)) {
            return $_value;
        }

        return $this->_filter($name, $_value, $_filter);
    }

    /**
     * クッキーデータの取得
     *
     * @param  string  $name
     * @param  integer  $filter
     * @return mixed
     */
    public function cookie(string $name, string $filter = null) : mixed
    {
        $_filter = $filter;

        $_cookie = $_COOKIE;
        if (isset($_cookie[$name])) {
            $_value = $_cookie[$name];
        } else {
            $_value = NULL;
            return $_value;
        }

        if (empty($_filter)) {
            return $_value;
        }

        return $this->_filter($name, $_value, $_filter);
    }

    /**
     * クッキーデータの取得
     *
     * @param  string  $name
     * @param  integer  $filter
     * @return mixed
     */
    public function setCookie(string $name, string $value, int $expires = 0, string $path = "", string $domain = "") : void
    {
        $payload = [
            "{$name}={$value};"
        ];

        if (!empty($expires)) {
            $max = $expires - time();
            $format = date('D, d-M-Y H:i:s T', $expires);
            $payload[] = "expires={$format};";
            $payload[] = "Max-Age={$max};";
        }

        if (!empty($path)) {
            $payload[] = "path={$path};";
        }

        if (!empty($domain)) {
            $payload[] = "domain={$domain};";
        }

        $this->header('Set-Cookie: '. implode(' ', $payload));
    }

    /**
     * フィルタリングしたリクエストデータの取得
     *
     * @param  string  $name
     * @param  integer  $filter
     * @param  $   $options
     * @return mixed
     */
    public function filter(string $name, $filter, $options = null) : mixed
    {
        $_requests = $this->_requests;

        if (isset($_requests[$name])) {
            $_value = $_requests[$name];
        } else {
            $_value = NULL;
            return $_value;
        }

        return filter_var($_value, $filter, $options);
    }

    /**
     * メソッドの取得
     *
     * @return string
     */
    public function getMethod() : string
    {
        return $this->_method;
    }

    /**
     * メソッドの取得
     *
     * @return array
     */
    public function getRequests() : mixed
    {
        return $this->_requests;
    }

    /**
     * 入力フォームの有無を確認
     *
     * @param  string  $name
     * @return boolean
     */
    public function isRequest(string $name) : bool
    {
        $_requests = $this->_requests;
        if (!isset($_requests[$name])) {
            return false;
        }
        return true;
    }

    /**
     * 入力内容の有無を確認
     *
     * @param  string  $name
     * @return boolean
     */
    public function isEmpty(string $name) : bool
    {
        $_requests = $this->_requests;
        if (isset($_requests[$name])) {
            return $this->_empty($_requests[$name]);
        }
        return true;
    }

    /**
     * クッキーの有無を確認
     *
     * @param  string  $name
     * @return boolean
     */
    public function isSetCookie(string $name) : bool
    {
        $_cookie = $_COOKIE;
        if (!isset($_cookie[$name])) {
            return false;
        }
        return true;
    }

    /**
     * GETメソッド判定
     *
     * @return boolean
     */
    public function isGet() : bool
    {
        if (0 !== strcmp('GET', $this->_method)) {
            return false;
        }
        return true;
    }

    /**
     * POSTメソッド判定
     *
     * @return boolean
     */
    public function isPost() : bool
    {
        if (0 !== strcmp('POST', $this->_method)) {
            return false;
        }
        return true;
    }

    /**
     * PUTメソッド判定
     *
     * @return boolean
     */
    public function isPut() : bool
    {
        if (0 !== strcmp('PUT', $this->_method)) {
            return false;
        }
        return true;
    }

    /**
     * DELETEメソッド判定
     *
     * @return boolean
     */
    public function isDelete() : bool
    {
        if (0 !== strcmp('DELETE', $this->_method)) {
            return false;
        }
        return true;
    }

        /**
     * フィルタリングしたリクエストデータの取得
     *
     * @access private
     * @param  string   $name
     * @param  $   $value
     * @param  string   $filter
     * @return mixed
     */
    private function _filter(string $name, $value, string $filter) : mixed
    {
        switch (strtolower($filter)) {
            case 'str':
            case 'string':
                $_result = filter_var($value, \FILTER_SANITIZE_SPECIAL_CHARS, \FILTER_NULL_ON_FAILURE);
                break;
            case 'int':
            case 'integer':
                $_result = filter_var($value, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE);
                break;
            case 'bool':
            case 'boolean':
                $_result = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
                break;
            case 'mail':
            case 'email':
                $_result = filter_var($value, \FILTER_VALIDATE_EMAIL, \FILTER_NULL_ON_FAILURE);
                break;
            case 'double':
            case 'float':
                $_result = filter_var($value, \FILTER_VALIDATE_FLOAT, \FILTER_NULL_ON_FAILURE);
                break;
            case 'ip':
                $_result = filter_var($value, \FILTER_VALIDATE_IP, \FILTER_NULL_ON_FAILURE);
                break;
            case 'mac':
                $_result = filter_var($value, \FILTER_VALIDATE_MAC, \FILTER_NULL_ON_FAILURE);
                break;
            case 'url':
                $_result = $this->_verifyUrl($value);
                break;
            case 'date':
                $_result = $this->_verifyDate($value);
                break;
            case 'datetime':
                $_result = $this->_verifyDatetime($value);
                break;
            case 'dates':
                $_result = $this->_sanitizeDate($value);
                break;
            case 'datetimes':
                $_result = $this->_sanitizeDatetime($value);
                break;
            default:
                $_result = $value;
                break;
        }

        return $_result;
    }

    /**
     * 値が有効か判定
     *
     * @access private
     * @param  $   $value
     * @return bool
     */
    private function _empty(mixed $value) : bool
    {
        if (is_string($value)) {
            if (0 == strlen($value)) {
                return true;
            }
        } else {
            if (is_null($value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * URLフィルタリング
     *
     * @access private
     * @param  string   $value
     * @return array
     */
    private function _verifyUrl(mixed $value) : mixed
    {
        $_url = Url::encode($value);
        $_result = filter_var($_url, \FILTER_VALIDATE_URL, \FILTER_NULL_ON_FAILURE);

        return $_result;
    }

    /**
     * 時間のチェック
     *
     * @access private
     * @param  integer  hour
     * @param  integer  min
     * @param  integer  sec
     * @return boolean
     */
    private function _verifyTime($hour, $min, $sec) : bool
    {
        if (23 < $hour) {
            return false;
        }

        if (59 < $min) {
            return false;
        }

        if (59 < $sec) {
            return false;
        }
        return true;
    }

    /**
     * 日付フィルタリング
     *
     * @access private
     * @param  string   $value
     * @return mixed
     */
    private function _verifyDate(mixed $value) : mixed
    {
        $_matches = [];
        if (preg_match('/([0-9]{4})-?([0-9]{1,2})-?([0-9]{1,2})/', $value, $_matches)) {
            $_year  = intval($_matches[1]);
            $_month = intval($_matches[2]);
            $_day   = intval($_matches[3]);

            if (checkdate($_month, $_day, $_year)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * 日付フィルタリング
     *
     * @access private
     * @param  string   $value
     * @return mixed
     */
    private function _verifyDatetime(mixed $value) : mixed
    {
        $_matches = [];
        if (preg_match('/([0-9]{4})-?([0-9]{1,2})-?([0-9]{1,2})\s?([0-9]{1,2}):?([0-9]{1,2}):?([0-9]{1,2})/', $value, $_matches)) {
            $_year  = intval($_matches[1]);
            $_month = intval($_matches[2]);
            $_day   = intval($_matches[3]);
            $_hour  = intval($_matches[4]);
            $_min   = intval($_matches[5]);
            $_sec   = intval($_matches[6]);

            if (checkdate($_month, $_day, $_year)) {
                if ($this->_verifyTime($_hour, $_min, $_sec)) {
                    return $value;
                }
            }
        }
        return null;
    }

    /**
     * 日付フィルタリング
     *
     * @access private
     * @param  string   $value
     * @return array
     */
    private function _sanitizeDate(mixed $value) : mixed
    {
        $_matches = [];
        
        if (preg_match('/([0-9]{4})-?([0-9]{1,2})-?([0-9]{1,2})/', $value, $_matches)) {
            $_year  = intval($_matches[1]);
            $_month = intval($_matches[2]);
            $_day   = intval($_matches[3]);
            if (checkdate($_month, $_day, $_year)) {
                $_result = [];
                $_result['year']  = $_year;
                $_result['month'] = $_month;
                $_result['day']   = $_day;
            }
        }

        return null;
    }

    /**
     * 日付フィルタリング
     *
     * @access private
     * @param  string   $value
     * @return array
     */
    private function _sanitizeDatetime(mixed $value) : mixed
    {
        $_matches = [];
        
        if (preg_match('/([0-9]{4})-?([0-9]{1,2})-?([0-9]{1,2})\s?([0-9]{1,2}):?([0-9]{1,2}):?([0-9]{1,2})/i', $value, $_matches)) {
            $_year  = intval($_matches[1]);
            $_month = intval($_matches[2]);
            $_day   = intval($_matches[3]);
            $_hour  = intval($_matches[4]);
            $_min   = intval($_matches[5]);
            $_sec   = intval($_matches[6]);

            if (checkdate($_month, $_day, $_year)) {
                if ($this->_verifyTime($_hour, $_min, $_sec)) {
                    $_result = [];
                    $_result['year']  = $_year;
                    $_result['month'] = $_month;
                    $_result['day']   = $_day;
                    $_result['hour']  = $_hour;
                    $_result['min']   = $_min;
                    $_result['sec']   = $_sec;
                }
            }
        }

        return null;
    }
} // class Request
