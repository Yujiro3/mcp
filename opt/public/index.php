<?php

/**
 * フロントコントローラー
 *
 * PHP versions 8.x
 *
 *      Copyright (c) 2026 Yujiro Takahashi All Rights Reserved.
 *
 * @copyright       Copyright (c) 2026 Yujiro Takahashi.
 * @filesource
 */

require_once '/opt/vendor/autoload.php';

date_default_timezone_set('Asia/Tokyo');
// アプリケーションPATHの指定
define('APP_PATH', realpath('/opt'));

use Library\ADR\Di;

$config = include APP_PATH . '/app/config/config.php';
include APP_PATH . '/app/config/services.php';

/** ルーティング定義 */
$response = Di::get('router')->map([
    '/mcp'  => 'mcp.php',
    ''      => 'browser.php'
], true);

if (!empty($response['headers']) && is_array($response['headers'])) {
    foreach ($response['headers'] as $key=> $header) {
        header("{$key}: {$header}");
    }
} // if (!empty($response['headers']) && is_array($response['headers']))

echo $response['body'];
