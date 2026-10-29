<?php

/**
 * Redisに保存された描画命令をもとに最終出力用画像を生成する
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
 * 描画命令を参照して最終出力用画像を生成する MCP Tool
 *
 * preview との違い:
 *  - デフォルトキャンバスサイズが高解像度 (1024x1024)
 *  - 背景色を指定可能
 *  - filename を指定することでダウンロード用ファイル名を返却
 */
function Download(
    string $id,
    array  $canvas   = [1024, 1024],
    string $format   = 'png',
    string $bg       = 'white',
    string $filename = ''
): array {
    [$width, $height] = $canvas;

    $result = render($id, $width, $height, $format, $bg);

    if ($filename === '') {
        $filename = sprintf('imagick-%s.%s', $id, $format);
    }

    return array_merge($result, [
        'filename' => $filename,
        'width'    => $width,
        'height'   => $height,
    ]);
}

