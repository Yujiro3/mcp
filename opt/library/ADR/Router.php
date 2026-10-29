<?php
namespace Library\ADR;
use Library\ADR\Di;

/**
 * ルータークラス
 * 
 * Usage example:
 *
 * ```php
 * require '/opt/vendor/autoload.php';
 * use Library\ADR\Di;
 * use Library\ADR\Router;
 * 
 * Di::set('router', function () {
 *   return new Router(
 *     '',
 *     APP_PATH.'/back-end/responder'
 *   );
 * });
 * 
 * Di::router()->map([
 *   '/api/v1/bizid/:name'   => 'bizid-api.php',
 *   '/api/v1/user/register' => 'register.php',
 *   ''                      => 'front.php'
 * ]);
 * ```
 */
class Router
{
    /**
     * メソッド
     * @var string
     */
    private $_method;

    /**
     * 接頭語
     * @var string
     */
    private $_prefix;

    /**
     * ディレクトリ
     * @var string
     */
    private $_dir;

    /**
     * パラメーター
     * @var array
     */
    private $_params;

    /**
     * その他の引数
     * @var array
     */
    private $_args;

    /**
     * リクエスト結果をブラウザへ帰す
     *
     * @param  $prefix
     * @param  $path
     * @return void
     */
    public function __construct(string $prefix, string $dir)
    {
        $this->_prefix = $prefix;
        $this->_dir    = $dir;
    }

    /**
     * メソッドの取得
     *
     * @return string
     */
    public function getMethod() : string
    {
        return strtoupper($this->_method);
    }

    /**
     * URL内変数の取得
     *
     * @return array
     */
    public function getParams() : mixed
    {
        $_params = $this->_params;

        $_result = [];
        if (!is_array($_params)) {
            $_result['group'] = '';
            $_result['name'] = '';
        } else {
            foreach ($_params as $_key=>$_value) {
                $_result[$_key] = filter_var(
                    $_value, 
                    \FILTER_SANITIZE_SPECIAL_CHARS, 
                    \FILTER_NULL_ON_FAILURE
                );
            }
        }
        
        return $_result;
    }

    /**
     * URL内変数の取得
     *
     * @return array
     */
    public function getArgs() : mixed
    {
        $_args = $this->_args;
        $_result = [];
        if (!is_array($_args)) {
            $_result[] = $this->_args;
        } else {
            foreach ($_args as $_key=>$_value) {
                $_result[$_key] = filter_var(
                    $_value, 
                    \FILTER_SANITIZE_SPECIAL_CHARS, 
                    \FILTER_NULL_ON_FAILURE
                );
            }
        }
        
        return $_result;
    }

    /**
     * Router:map
     *
     * Usage example:
     *
     * ```php
     * ::map([
     *     ''        => 'simple.php',
     *     'usr/:id' => [
     *         function ($id) {
     *             echo 'usr:{$id}\n';
     *         }, 'get'
     *     ]
     * ]);
     * ```
     * @param  array $routing
     * @param  bool  $returned
     * @return mixed
     */
    public function map(mixed $routing, bool $returned=false)
    {
        $this->_method = 'get';
        if (!empty($_SERVER['REQUEST_METHOD'])) {
            $this->_method = strtolower($_SERVER['REQUEST_METHOD']);
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        if (!$returned) {
            /** 下位互換用 */
            $this->_map($routing, $uri);
            return ;
        }        

        $data = $this->_map($routing, $uri);
        $headers = Di::request()->getHeaders();
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'text/html; charset=utf-8';

        return [
            'statusCode'=> 200,
            'headers'   => $headers,
            'body'      => $data
        ];
    }

    /**
     * Router:run
     *
     * Usage example:
     *
     * ```php
     * ::run([
     *     ''        => 'simple.php',
     *     'usr/:id' => [
     *         function ($id) {
     *             echo 'usr:{$id}\n';
     *         }, 'get'
     *     ]
     * ]);
     * ```
     * @param  mixed  $routing
     * @param  string $method
     * @param  string $path
     * @return mixed
     */
    public function run(mixed $routing, string $method, string $path)
    {
        $this->_method = strtolower($method);
        return $this->_map($routing, $path);
    }

    /**
     * Router:map
     *
     * @param  mixed  $routing
     * @param  string $requestUri
     * @return mixed
     */
    private function _map(mixed $routing, string $requestUri): mixed
    {
        $_url = $requestUri;
        if (\PHP_SAPI === 'cli-server') {
            $_pos = strpos($_url, '?');
            $_offset = strpos($_url, '&', $_pos + 1);
            if (!empty($_offset)) {
                $_url = substr($_url, $_pos + 1, $_offset - $_pos);
            } else {
                $_url = substr($_url, $_pos + 1);
            }
        } else {
            $_pos = strrpos($_url, '?');
            if (!empty($_pos)) {
                $_url = substr($_url, 0, $_pos);
            }
        }
        $_url = substr($_url, strlen($this->_prefix));

        /* 完全一致検索 */
        if (isset($routing[$_url])) {
            if (is_array($routing[$_url])) {
                if (0 === strcasecmp($this->_method, $routing[$_url][1])) {
                    if (is_callable($routing[$_url][0])) {
                        return call_user_func($routing[$_url][0]);
                    } else {
                        return include $this->_dir . DIRECTORY_SEPARATOR . $routing[$_url][0];
                    }
                } else {
                    if (0 === strcasecmp($this->_method, $routing[$_url][0])) {
                        if (is_callable($routing[$_url][1])) {
                            return call_user_func($routing[$_url][1]);
                        } else {
                            return include $this->_dir . DIRECTORY_SEPARATOR . $routing[$_url][1];
                        }
                    }
                }
            } else {
                if (is_callable($routing[$_url])) {
                    return call_user_func($routing[$_url]);
                } else {
                    return include $this->_dir . DIRECTORY_SEPARATOR . $routing[$_url];
                }
            }
        }

        /* パターンマッチ検索 */
        foreach ($routing as $_pattern=>$_route) {
            if (!empty($_pattern)) {
                $_overrided = strtolower($_pattern);
                $_overrided = preg_replace('~:([^/]+)~', '(?P<\\1>[^/]+)', $_overrided);
                $_overrided = sprintf('~^%s~', $_overrided);

                if (preg_match($_overrided, $_url, $_matches)) {
                    $this->_setArgs($_url, $_matches[0]);

                    foreach ($_matches as $_key=>$_value) {
                        if (is_numeric($_key)) {
                            unset($_matches[$_key]);
                        }
                    }
                    $this->_params = $_matches;

                    return $this->_call($_route);
                }
            }
        } // for _pattern, _route in routing

        /* 該当なしデフォルト実行 */
        if (is_callable($routing[''])) {
            return call_user_func($routing['']);
        } else {
            return include $this->_dir . DIRECTORY_SEPARATOR . $routing[''];
        }
    }

    /**
     * 関数呼び出し
     *
     * @access private
     * @param  $   $route
     * @return mixed
     */
    private function _call(mixed $route) : mixed
    {
        if (is_callable($route)) {
            return call_user_func($route, $this->_params);
        } else {
            if (is_string($route)) {
                return include $this->_dir . DIRECTORY_SEPARATOR . $route;
            } else {
                if (0 === strcasecmp($this->_method, $route[1])) {
                    if (is_callable($route[0])) {
                        return call_user_func($route[0], $this->_params);
                    } else {
                        return include $this->_dir . DIRECTORY_SEPARATOR . $route[0];
                    }
                } else {
                    if (0 === strcasecmp($this->_method, $route[0])) {
                        if (is_callable($route[1])) {
                            return call_user_func($route[1], $this->_params);
                        } else {
                            return include $this->_dir . DIRECTORY_SEPARATOR . $route[1];
                        }
                    }
                }
            }
        } // if is_callable(route)

        return null;
    }

    /**
     * 引数の設定
     *
     * @access private
     * @param  string   $url
     * @param  string   $match
     * @return void
     */
    private function _setArgs(string $url, string $match) : void
    {
        $_url = substr($url, strlen($match));
        $_url = trim($_url, '/');

        if (!empty($_url)) {
            $this->_args = explode('/', $_url);
        }
    }
} // class Router
