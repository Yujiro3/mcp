<?php
namespace Library\ADR;

use Library\ADR\Di;

/**
 * キャッシュクラス
 * 
 * Usage example:
 *
 * ```php
 * require '/opt/vendor/autoload.php';
 * use Library\ADR\Cache;
 * 
 * $cache = new Cache(
 *   '192.168.0.2', 
 *   11211,
 *   'cache'
 * );
 * 
 * $cache->set('id', 'aabbcc');
 * echo $cache->get('id');
 * ```
 */
class Cache
{
    const ENV_STRING = "APPLICATION_ENV";

    /**
     * クライアント
     * @var object
     */
    private $client;

    /**
     * コンストラクタ
     * 
     * @param string $host   サーバーアドレス
     * @param string $port   サーバーポート
     * @param string $prefix キー接頭語
     */
    public function __construct(string $host, string $port, string $prefix)
    {
        $this->client = new \Memcached();

        $env = getenv(self::ENV_STRING) ?? 'local';
        switch ($env) {
            case 'production':
                $this->client->setOption(\Memcached::OPT_CLIENT_MODE, \Memcached::DYNAMIC_CLIENT_MODE);
                break;
            default:
                $this->client->setOption(\Memcached::OPT_CLIENT_MODE, \Memcached::STATIC_CLIENT_MODE);
                break;
        }
        $this->client->setOption(\Memcached::OPT_TCP_NODELAY, true);
        $this->client->setOption(\Memcached::OPT_PREFIX_KEY, $prefix);

        $this->client->addServer($host, $port);
    }

    /**
     * 値の格納
     * 
     * @param string  $key        値の格納先のキー
     * @param mixed   $value      格納する値
     * @param integer $expiration 期限切れとなるまでの時間
     * @return bool
     */
    public function set($key, $value, $expiration=0)
    {
        return $this->client->set($key, $value, $expiration);
    }

    /**
     * 値の取得
     * 
     * @param  string $key 取得したいアイテムのキー
     * @return mixed       キャッシュに格納された値
     */
    public function get(string $key): mixed
    {
        return $this->client->get($key);
    }

    /**
     * 値の削除
     * 
     * @param  string $key 削除したいアイテムのキー
     * @return bool
     */
    public function delete($key): bool
    {
        return $this->client->delete($key);
    }
}
