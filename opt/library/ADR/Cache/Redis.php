<?php

namespace Library\ADR\Cache;

use Library\ADR\Di;

/**
 * キャッシュクラス
 * 
 * Usage example:
 *
 * ```php
 * require '/opt/vendor/autoload.php';
 * use Library\ADR\Cache\Redis;
 * 
 * $cache = new Redis(
 *   '192.168.0.2', 
 *   6379,
 *   '1'
 * );
 * 
 * $cache->set('id', 'aabbcc');
 * echo $cache->get('id');
 * ```
 */
class Redis
{
    const TIME_OUT = 2.5;

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
     * @param string $index  DBインデックス
     */
    public function __construct(string $host, string $port, string $index)
    {
        $this->client = new \Redis();
        $this->client->connect($host, $port, self::TIME_OUT);
        $this->client->select(intVal($index));
        $this->client->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_IGBINARY);
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
        if (!empty($expiration)) {
            return $this->client->setEx($key, $expiration, $value);
        }
        return $this->client->set($key, $value);
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
        return $this->client->del($key);
    }
}
