<?php

/**
 * Redisに描画用の命令を保存する
 *
 * PHP versions 8.x
 *
 *      Copyright (c) 2026 Yujiro Takahashi All Rights Reserved.
 *
 * @package         imagick
 * @copyright       Copyright (c) 2026 Yujiro Takahashi.
 * @filesource
 */
namespace Action\Imagick;

use Library\ADR\Di;

/**
 * 描画命令を保存する MCP Tool
 */
function Transforms(mixed $id = null, mixed $draw): array
{
    if (empty($id)) {
        $id = 'transforms:' . bin2hex(random_bytes(8));
    }

    if (!empty($draw)) {
        Di::redis()->set($id . ':transforms', json_encode($draw, JSON_UNESCAPED_UNICODE));
    }

    return [
        'id' => $id,
    ];
}
