<?php

/**
 * Redisに保存された描画命令をもとにImagickクラスの実行
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

include_once __DIR__ . '/common.php';

/**
 * 描画命令を参照してプレビュー画像を返す MCP Tool
 */
function Preview(
    string $id,
    array  $canvas = [32, 32],
    string $format = 'png'
): array {
    [$width, $height] = $canvas;
    return render($id, $width, $height, $format);
}
