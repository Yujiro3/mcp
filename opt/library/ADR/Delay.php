<?php
namespace Library\ADR;

/**
 * 遅延処理クラス
 * 
 * Usage example:
 *
 * ```php
 * require '/opt/vendor/autoload.php';
 * 
 * use Library\ADR\Di;
 * use Library\ADR\App;
 * use Library\ADR\Delay;
 * 
 * $config = include '/opt/config.php';
 * $app    = new \Library\App($config);
 * 
 * Di::set('delay', function () {
 *   return new Delay();
 * });
 * 
 * // リクエスト時の処理
 * 
 * Di::delay()->finishRequest();
 * Di::delay()->call();
 * ```
 */
class Delay
{
    /**
     * 関数リスト
     * @var array<string, callable>
     */
    private $_delays = [];

    /**
     * 関数に渡す引数
     * @var array<string, mixed>
     */
    private $_parameters = [];

    /**
     * 格納用配列のキー番号
     * @var integer
     */
    private $_no = 0;

    /**
     * 遅延実行する関数の登録
     *
     * @access public
     * @param  string $function   登録する関数
     * @param  string  $parameters 可変長の引数
     * @return this
     */
    public function add(mixed $func, $param = null)
    {
        $this->_delays[$this->_no]     = $func;
        $this->_parameters[$this->_no] = $param;
        $this->_no++;

        return $this;
    }
    
    /**
     * 遅延実行する関数の実行
     *
     * @access public
     * @return this
     * @example location Di::get('delay')->finishRequest();
     * @example location Di::get('delay')->call(); // 遅延処理の実行
     */
    public function call()
    { 
        foreach ($this->_parameters as $key=> $param) {
            if (is_array($param)) {
                call_user_func_array($this->_delays[$key], $param);
            } else {
                call_user_func($this->_delays[$key], $param);
            }
        }

        return $this;
    }

    /**
     * リクエスト結果をブラウザへ帰す
     *
     * @access public
     * @return void
     * @example location Di::get('delay')->finishRequest();
     */
    public function finishRequest()
    {
        $pos = strpos(\PHP_SAPI, 'fpm');
        if (false !== $pos) {
            fastcgi_finish_request();
        }
    }
} // class Delay
