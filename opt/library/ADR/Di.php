<?php
namespace Library\ADR;

/**
 * DIコンテナ風クラス
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
 * $agree = Di::request()->get('agree');
 * ```
 */
class Di
{
    /**
     * Singleton用インスタンス
     * @var object
     */
    private static $_instance;

    /**
     * 関数リスト
     * @var array<string, mixed>
     */
    private $_services = [];

    /**
     * オブジェクトリスト
     * @var array<string, mixed>
     */
    private $_container = [];

    /**
     * インスタンスの作成
     *
     * @access public
     * @return this
     */
    public static function setInstance()
    {
        $di = self::$_instance;
        if (!$di) {
            self::$_instance = new self();
        }
    }

    /**
     * サービスの登録
     *
     * @access public
     * @param  string $name 呼び出しに使うキー
     * @param  mixed  $service サービスまたはデータ
     * @return this
     */
    public static function set(string $name, mixed $service) : Di
    {
        self::$_instance->_setService($name, $service);
        return self::$_instance;
    }

    /**
     * サービスの呼び出し
     *
     * @access public
     * @param  string $name 呼び出しキー
     * @return Object
     */
    public static function get(string $name): mixed
    {
        return self::$_instance->_getService($name);
    }

    /**
     * 保持インスタンスの開放
     *
     * @access public
     * @param  string $name 呼び出しキー
     * @return void
     */
    public static function purge(string $name): void
    {
        self::$_instance->_purge($name);
    }
    
    /**
     * サービスの呼び出し(マジックメソッド)
     *
     * @access public
     * @param  string $name      呼び出しキー
     * @param  mixed  $arguments 未使用
     * @return mixed
     *
     * Usage example:
     *
     * ```php
     * Di::setInstance();
     * Di::set('request', function () {
     *   return new Request();
     * });
     *
     * // マジックメソッドによる呼び出し
     * $agree = Di::request()->get('agree');
     * ```
     */
    public static function __callStatic(string $name, mixed $args=null): mixed
    {
        return self::$_instance->_getService($name);
    }

    /**
     * サービスの登録
     *
     * @access private
     * @param  string $name 呼び出しに使うキー
     * @param  mixed  $service サービスまたはデータ
     * @return void
     */
    private function _setService(string $name, mixed $service): void
    {
        $this->_services[$name] = $service;
    }

    /**
     * サービスの呼び出し
     *
     * @access private
     * @param  string $name 呼び出しに使うキー
     * @return mixed  オブジェクトまたはデータ
     */
    private function _getService(string $name): mixed
    {
        if (!isset($this->_container[$name])) {
            $returnObj = call_user_func($this->_services[$name]);
            $this->_container[$name] = $returnObj;
        } else {
            $returnObj = $this->_container[$name];
        }
        return $returnObj;
    }

    /**
     * 保持インスタンスの開放
     *
     * @access public
     * @param  string $name 呼び出しキー
     * @return void
     */
    public function _purge(string $name): void
    {
        if (!isset($this->_container[$name])) {
            return ;
        }
        /** PDOなどのデストラクタ処理の即時起動 */
        $this->_container[$name] = null;
        
        unset($this->_container[$name]);
    }
} // class Di
