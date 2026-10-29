<?php
namespace Library\ADR;

use Library\ADR\Di;

/**
 * 関数呼び出し処理クラス
 * 
 * Usage example:
 *
 * ```php
 * require '/opt/vendor/autoload.php';
 * use Library\ADR\Caller;
 * 
 * $call = new Caller(
 *   'Controllers', 
 *   APP_PATH.'/backend/controllers'
 * );
 * 
 * // 登録コントローラの直接呼び出し
 * $call->main(
 *   'user',        // ディレクトリ
 *   'register',    // ファイル名 & 関数名
 *   [id=>1]        // 引数
 * );
 * 
 * // 組み込み内部呼び出し
 * $call->embed(
 * 　　'user',      // ファイル名
 * 　　'register',  // 関数名
 * 　　[id=>1]      // 引数
 * );
 * ```
 */
class Caller
{
    /**
     * 接頭語
     * @var string
     */
    private $_prefix;

    /**
     * ディレクトリ
     * @var string
     */
    private $_dir;

    /**
     * コンストラクタ
     *
     * @param  string $prefix ネームスペーストップグループ名
     * @param  string $dir
     * @return void
     */
    public function __construct(string $prefix, string $dir)
    {
        $this->_prefix = $prefix;
        $this->_dir    = $dir;
    }

    /**
     * Action/Controllers main関数の実行
     *
     * @param  string $group  グループ名 /v2/[group]/[action]
     * @param  string $name   アクション名 /v2/[group]/[action]
     * @param  mixed  $params パラメータ　実行関数に渡す引数
     * @return mixed  実行関数の戻り値
     */
    public function main(string $group, string $name, mixed $params = null): mixed
    {
        $ds       = \DIRECTORY_SEPARATOR;
        $filename = $this->_dir . $ds . $group . $ds . $name .'.php';

        return $this->_caller(
            $filename, 
            $group, 
            $name, 
            $params
        );
    }

    /**
     * Embed 内部呼び出しmain関数の実行
     *
     * @param  string $group  グループ名 /v2/[group]/[action]
     * @param  string $name   アクション名 /v2/[group]/[action]
     * @param  mixed  $params パラメータ　実行関数に渡す引数
     * @return mixed  実行関数の戻り値
     */
    public function embed(string $group, string $name, mixed $params = null): mixed
    {
        $filename = $this->_dir . \DIRECTORY_SEPARATOR . $group .'.php';

        return $this->_caller(
            $filename, 
            $group, 
            $name, 
            $params
        );
    }

    /**
     * ユーザー定義関数の実行
     *
     * @access private
     * @param  string $filename   スクリプトファイル名
     * @param  string $group      グループ名 
     * @param  string $name       関数名 
     * @param  mixed  $params     引数
     * @return mixed
     */
    private function _caller(string $filename, string $group, string $name, mixed $params) : mixed
    {
        if (file_exists($filename)) {
            include_once $filename;

            $func = $this->_prefix.'\\'.
                    $this->_normalize($group).'\\'.
                    $this->_normalize($name);
        } else {
            throw new \Exception(
                sprintf('File not found.(%s)', $filename)
            );
        }

        if (is_array($params)) {
            return call_user_func_array($func, $params);
        }

        Di::request()->addRequests($params) ;
        return call_user_func($func, $params);
    }

    /**
     * nameを名前空間形式に変換する
     *
     * @access private
     * @param  string  $name  名前
     * @return string
     */
    private function _normalize(string $name) : string
    {
        $_exploded = explode('/', $name);

        $_element = [];

        foreach ($_exploded as $part) {
            $_element[] = ucfirst($part);
        }
        $output = implode('\\', $_element);
        return $output;
    }
} // class Caller
