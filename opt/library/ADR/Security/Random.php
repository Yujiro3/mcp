<?php
namespace Library\ADR\Security;

/**
 * 乱数クラス
 */
class Random
{
    /**
     * ランダム文字列文字数
     * @const integer
     */
    const LENGTH = 16;

    /**
     * パスワード
     * @const integer
     */
    const COST = 10;

    /**
     * UUIDの生成
     *
     * @access public
     * @return string
     */
    public static function uuid() : string
    {
        $_try = self::COST;
        while ($_try) {
            $_data = openssl_random_pseudo_bytes(self::LENGTH, $_cstrong);
            if ($_cstrong) {
                break;
            }
            $_try -= 1;
        }

        $_ary = array_values(unpack('N1a/n1b/n1c/n1d/n1e/N1f', $_data));
        $_ary[2] = ($_ary[2] & 0x0fff) | 0x4000;
        $_ary[3] = ($_ary[3] & 0x3fff) | 0x8000;

        array_unshift($_ary, '%08x-%04x-%04x-%04x-%04x%08x');

        return call_user_func_array('sprintf', $_ary);
    }
} // class Random
