<?php
namespace Library\ADR\Net;

use Library\ADR\Net\Punycode;

/**
 * URLクラス
 */
class Url
{
    /**
     * URLのエンコード
     *
     * @access private
     * @param  string   $url
     * @return string
     */
    public static function encode(string $url) : string
    {
        $punycode = new Punycode();
        $parsed   = parse_url($url);
        
        if (!isset($parsed["host"])) {
            return $url;
        }
        $host   = $punycode->encode($parsed["host"]);
        $result = $parsed["scheme"]."://".$host;
        
        /* https://example.com/[** path **]/ */
        if (isset($parsed["path"])) {
            $paths = explode("/", $parsed["path"]);
            $paths = array_map(function ($node) {
                return urlencode($node);
            }, $paths);

            $path    = implode("/", $paths);
            $result .= $path;
        }

        /* https://example.com/path/[** ?query=test **] */
        if (isset($parsed["query"])) {
            $queries  = explode("&", $parsed["query"]);
            $queries = array_map(function ($node) {
                if (false !== strpos($node, "=")) {
                    $param = explode("=", $node);
                    return urlencode($param[0]). "=" .urlencode($param[1]);
                }
                return urlencode($node);
            }, $queries);

            $query   = implode("&", $queries);
            $result .= "?".$query;
        }

        return $result;
    }

    /**
     * URLのデコード
     *
     * @access private
     * @param  string   $url
     * @return string
     */
    public static function decode(string $url) : string
    {
        $punycode = new Punycode();
        $parsed   = parse_url($url);

        if (!isset($parsed["host"])) {
            return $url;
        }
        $host   = $punycode->decode($parsed["host"]);
        $result = $parsed["scheme"]."://".$host;

        /* https://example.com/[** path **]/ */
        if (isset($parsed["path"])) {
            $paths = explode("/", $parsed["path"]);
            $paths = array_map(function ($node) {
                return urldecode($node);
            }, $paths);

            $path    = implode("/", $paths);
            $result .= $path;
        }

        /* https://example.com/path/[** ?query=test **] */
        if (isset($parsed["query"])) {
            $queries = explode("&", $parsed["query"]);
            $queries = array_map(function ($node) {
                if (false !== strpos($node, "=")) {
                    $$param = explode("=", $node);
                    return urldecode($param[0]). "=" .urldecode($param[1]);
                }
                return urldecode($node);
            }, $queries);

            $query   = implode("&", $queries);
            $result .= "?".$query;
        }

        return $result;
    }

    /**
     * URLの制御下チェック
     *
     * @access public
     * @param string $url
     * @param string $referer
     * @return boolean
     */
    public static function isUnderControl(string $url, string $referer) : bool
    {
        if (empty($url) || empty($referer)) {
            return false;
        }
        $needle = preg_replace("~^(?:http|s):/~i", "", $url);
        if (false === strpos($referer, $needle)) {
            return false;
        }
        return true;
    }

    /**
     * URL中のハッシュタグ以降を消去
     *
     * @access public
     * @param string $url
     * @return string
     */
    public static function trimAHT(string $url) : string
    {
        $pos = strpos($url, "#");
        if (false === $pos) {
            return $url;
        }
        
        return substr($url, 0, $pos);
    }
} // class URL
