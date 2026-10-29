<?php

/**
 * レスポンダー：ブラウザ
 *
 * PHP versions 8.x
 *
 *      Copyright (c) 2026 Yujiro Takahashi All Rights Reserved.
 *
 * @package         MCP
 * @copyright       Copyright (c) 2026 Yujiro Takahashi.
 * @filesource
 */

use Library\ADR\Di;

Di::request()->header("Content-Type: text/html; charset=utf-8");

return file_get_contents('/opt/public/index.html');