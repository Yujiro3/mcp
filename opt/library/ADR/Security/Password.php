<?php
namespace Library\ADR\Security;

/**
 * パスワードクラス
 */
class Password
{
    /**
     * パスワード
     * @const integer
     */
    const COST = 12;

    /**
     * パスワードハッシュの生成
     *
     * @access public
     * @param  string  passwd
     * @return string
     */
    public static function hash(string $passwd) : string
    {
        $_options = [
            'cost' => self::COST
        ];
        
        return password_hash($passwd, \PASSWORD_DEFAULT, $_options);
    }

    /**
     * パスワードがハッシュにマッチするかどうかを調べる
     *
     * @access public
     * @param  string  $passwd
     * @param  string  $hash
     * @return string
     */
    public static function verify(string $passwd, string $hash) : bool
    {        
        return password_verify($passwd, $hash);
    }
} // class Password
