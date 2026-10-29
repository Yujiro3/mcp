<?php

/**
 * サービス登録
 *
 * PHP versions 8.x
 *
 *      mcp-server : MCPサーバー
 *      Copyright (c) 2026 Yujiro Takahashi All Rights Reserved.
 *
 * @package         mcp-server
 * @copyright       Copyright (c) 2026 Yujiro Takahashi.
 * @filesource
 */

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use Library\ADR\Di;
use Library\ADR\Router;
use Library\ADR\Request;

/**
 * Autoloaderの設定
 */
spl_autoload_register(static function(string $className):bool {
    $ds = \DIRECTORY_SEPARATOR;
    $file = str_replace('\\', $ds, $className);

    $filenames = [
        APP_PATH . $ds . 'app' . $ds . $file . '.php',
        APP_PATH . $ds . 'app' . $ds . lcfirst($file) . '.php',
        APP_PATH . $ds . $file . '.php',
        APP_PATH . $ds . lcfirst($file) . '.php'
    ];
    foreach ($filenames as $filename) {
        if (file_exists($filename)) {
            include_once $filename;
            return true;
        } // if (file_exists($filename))
    } // foreach ($filenames as $filename)
    return false;
});

/**
 * DI設定
 */
Di::setInstance();

/**
 * 定義設定
 */
Di::set('config', static fn () => $config);

/**
 * ログ設定
 */
Di::set('log', static function () use ($config) {
    $logger = new Logger('TXGN');
    $logger->pushHandler(
        new StreamHandler('php://stdout', Logger::DEBUG)
    );
    return $logger;
});

/**
 * Redis::キャッシュ
 */
Di::set('redis', static function () use ($config) {
    $client = new \Redis();
    $client->connect($config['cache']['server'], $config['cache']['port'], 2.5);
    $client->select(intVal($config['cache']['index']));
    $client->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_IGBINARY);
    return $client;
});

/**
 * ルーター設定
 */
Di::set('router', static fn () => new Router(
    '', APP_PATH . '/app/responder'
));

/**
 * リクエスト設定
 */
Di::set('request', static fn () =>  new Request());
