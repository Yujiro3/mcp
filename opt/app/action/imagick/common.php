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


/**
 * ImagickPixel に変換が必要な ImagickDraw メソッド名（小文字）
 */
$COLOR_METHODS = [
    'setfillcolor',
    'setstrokecolor',
    'settextundercolor',
    'setbordercolor',
];

/**
 * Redis から命令列を取得して Imagick blob を生成する
 *
 * @param  string $id      キャッシュID
 * @param  int    $width   キャンバス幅 (px)
 * @param  int    $height  キャンバス高さ (px)
 * @param  string $format  出力フォーマット (png/jpeg/gif/webp)
 * @param  string $bg      背景色（デフォルト: transparent）
 * @return array{ mime: string, blob: string }
 */
function render(
    string $id,
    int $width,
    int $height,
    string $format,
    string $bg = 'transparent'
): array {
    $raw      = Di::redis()->get($id . ':transforms');
    $commands = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($commands)) {
        $commands = [];
    }

    $draw = new \ImagickDraw();
    foreach ($commands as $cmd) {
        // Support both tuple format [method, args] and object format {method, args}
        $method  = is_array($cmd) ? ($cmd[0] ?? $cmd['method'] ?? null) : ($cmd->method ?? null);
        $rawArgs = is_array($cmd) ? ($cmd[1] ?? $cmd['args'] ?? null) : ($cmd->args ?? null);

        if (!$method) {
            continue;
        }

        $args = is_array($rawArgs)
            ? $rawArgs
            : ($rawArgs !== null ? [$rawArgs] : []);

        if (
            in_array(strtolower($method), $COLOR_METHODS, true)
            && isset($args[0])
            && is_string($args[0])
        ) {
            $args[0] = new \ImagickPixel($args[0]);
        }

        $draw->$method(...$args);
    }

    $imagick = new \Imagick();
    $imagick->newImage($width, $height, new \ImagickPixel($bg));
    $imagick->setImageFormat($format);
    $imagick->drawImage($draw);

    // ダウンロード向けに圧縮品質を最大に
    if (in_array($format, ['jpeg', 'jpg'], true)) {
        $imagick->setImageCompressionQuality(100);
    }

    $blob = $imagick->getImageBlob();
    $imagick->clear();
    $draw->clear();

    return [
        'mime' => 'image/' . $format,
        'blob' => base64_encode($blob),
    ];
}
