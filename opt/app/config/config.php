<?php

/**
 * 設定ファイル
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

return [
    'logger' => [
        'level'=> Logger::DEBUG,
    ],
    'cache' => [
        'server' => getenv('REDIS_HOST') ?: 'redis',
        'port'   => getenv('REDIS_PORT') ?: 6379,
        'timeout'=> 4,
        'expire' => 86400,
        'index'  => 1
    ],
];
