<?php
namespace Library\ADR;

use Library\ADR\Di;
use Library\ADR\Security\Random;

/**
 * セッションクラス
 * 
 * Usage example:
 *
 * ```php
 * require '/opt/vendor/autoload.php';
 * use Library\ADR\Di;
 * use Library\ADR\Request;
 * use Library\ADR\Session;
 * 
 * Di::setInstance();
 * Di::set('request', function () {
 *   return new Request();
 * });
 * Di::set('session', function () use ($config) {
 *   return new Session([
 *     'name'     => 'cache',
 *     'lifetime' => 86400,
 *     'expire'   => 86400,
 *     'server'   => '192.168.0.2',
 *     'port'     => 11211,
 *   ]);
 * });
 * 
 * Di::session()->start();
 * 
 * $account = Di::session()->get('account');
 * Di::session()->set('account', $account);
 * ```
 */
class Session
{
    /**
     * 外部入力キー
     * @var string
     */
    private $_name;

    /**
     * セッションキー
     * @var string
     */
    private $_key;

    /**
     * 接頭語
     * @var string
     */
    private $_prefix;

    /**
     * 有効期限
     * @var integer
     */
    private $_expire;

    /**
     * クッキー有効期限
     * @var integer
     */
    private $_lifetime;

    /**
     * 保存フラグ
     * @var object
     */
    private $_abort;

    /**
     * Memcached
     * @var object
     */
    private $_mem;

    /**
     * Memcached
     * @var object
     */
    private $_session;

    /**
     * クッキーパス
     * @var string
     */
    private $_path;

    /**
     * クッキードメイン名
     * @var string
     */
    private $_domain;

    /**
     * コンストラクタ
     *
     * @param  array    $config
     * @return void
     */
    public function __construct(mixed $config)
    {
        $_port = 11211;

        $this->_mem = new \Memcached();
        $this->_abort = false;

        if (isset($config['name'])) {
            $this->_name = $config['name'];
        } else {
            $this->_name = 'PHPSESSID';
        }

        if (isset($config['expire'])) {
            $this->_expire = $config['expire'];
        } else {
            $this->_expire = 0;
        }

        if (isset($config['lifetime'])) {
            $this->_lifetime = $config['lifetime'];
        } else {
            $this->_lifetime = 0;
        }

        if (isset($config['prefix'])) {
            $this->_prefix = $config['prefix'];
        } else {
            $this->_prefix = null;
        }

        if (isset($config['port'])) {
            $_port = $config['port'];
        }

        if (isset($config['client_mode'])) {
            $this->_mem->setOption(\Memcached::OPT_CLIENT_MODE, $config['client_mode']);
        } else {
            $this->_mem->setOption(\Memcached::OPT_CLIENT_MODE, \Memcached::DYNAMIC_CLIENT_MODE);
        }

        if (isset($config['serializer'])) {
            $this->_mem->setOption(\Memcached::OPT_SERIALIZER, $config['serializer']);
        }


        if (isset($config['server'])) {
            $this->_mem->addServer($config['server'], $_port);
        }

        if (isset($config['servers'])) {
            $this->_mem->addServers($config['servers']);
        }

        if (isset($config['path'])) {
            $this->_path = $config['path'];
        } else {
            $this->_path = '/';
        }

        if (isset($config['domain'])) {
            $this->_domain = $config['domain'];
        } else {
            $this->_domain = '';
        }
    }

    /**
     * デストラクタ
     *
     * @return void
     */
    public function __destruct()
    {
        if (!$this->_abort) {
            $this->_save();
        }
        $this->_mem->quit();
    }

    /**
     * 新しいセッションを開始、あるいは既存のセッションを再開する
     *
     * @return boolean
     */
    public function start() : bool
    {
        $_cookie = $_COOKIE;

        if (isset($_cookie[$this->_name])) {
            $this->_key = $_cookie[$this->_name];
        }
        if (empty($this->_key)) {
            $this->regenerateId();
        }
        $this->_load();

        Di::request()->setCookie($this->_name, $this->_key, time() + $this->_lifetime, $this->_path, $this->_domain);
        return true;
    }

    /**
     * session 配列の変更を破棄してセッションを終了します
     *
     * @return void
     */
    public function abort() : void
    {
        $this->_abort = true;
    }

    /**
     * セッションデータを書き込んでセッションを終了する
     *
     * @return void
     */
    public function commit() : void
    {
        $this->_save();
    }

    /**
     * 全てのセッション変数を開放する
     *
     * @return void
     */
    public function release() : void
    {
        $this->_session = [];
        $this->_remove();
        $this->abort();
    }

    /**
     * 現在のセッションIDを指定したIDと置き換える
     *
     * @return string
     */
    public function sessionId(mixed $new_session = null) : string
    {
        if (!empty($new_session)) {
            $this->_remove();
            $this->_key = $new_session;
            Di::request()->setCookie($this->_name, $this->_key, time() + $this->_lifetime, $this->_path, $this->_domain);
        }
        return $this->_key;
    }

    /**
     * 現在のセッションIDを新しく生成したものと置き換える
     *
     * @return string
     */
    public function regenerateId() : string
    {
        $this->_remove();

        $this->_key = Random::uuid();
        Di::request()->setCookie($this->_name, $this->_key, time() + $this->_lifetime, $this->_path, $this->_domain);
        return $this->_key;
    }


    /**
     * セッションデータの設定
     *
     * @param  string $key
     * @param  $  $value
     * @return boolean
     */
    public function set(string $key, $value) : bool
    {
        $this->_session[$key] = $value;
        return true;
    }

    /**
     * セッションデータの取得
     *
     * @param  string $key
     * @return mixed
     */
    public function get(string $key) : mixed
    {
        $_result = null;
        if (isset($this->_session[$key])) {
            $_result = $this->_session[$key];
        }
        return $_result;
    }

    /**
     * セッション用サーバリストの取得
     *
     * @return mixed
     */
    public function getServerList() : mixed
    {
        return $this->_mem->getServerList();
    }

    /**
     * 保存用キーの取得
     *
     * @access private
     * @return string
     */
    private function _getKey() : string
    {
        $_key = '';
        if (empty($this->_prefix)) {
            $_key = $this->_key;
        } else {
            $_key = $this->_prefix . ':' .$this->_key;
        }

        return $_key;
    }

    /**
     * ストレージ上のセッションデータを取得
     *
     * @access private
     * @return void
     */
    private function _load() : void
    {
        $_key = $this->_getKey();
        $this->_session = $this->_mem->get($_key);
    }

    /**
     * ストレージ上にセッションデータを保存
     *
     * @access private
     * @return mixed
     */
    private function _save() : void
    {
        $_key = $this->_getKey();

        if (!empty($this->_session)) {
            $this->_mem->set($_key, $this->_session, $this->_expire);
        }
    }

    /**
     * ストレージ上のセッションデータを破棄
     *
     * @access private
     * @return mixed
     */
    private function _remove() : void
    {
        $_key = $this->_getKey();
        $this->_mem->delete($_key);
    }
} // class Session
