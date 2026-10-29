<?php
namespace Library\ADR;

use Library\ADR\Di;

/**
 * ビュークラス
 * 
 * Usage example:
 *
 * ```php
 * require '/opt/vendor/autoload.php';
 * use Library\ADR\Di;
 * use Library\ADR\View;
 * 
 * Di::set('view', function () {
 *   $view = new View(
 *     APP_PATH.'/back-end/template',
 *     'tpl',
 *     'main/index'
 *   );
 *   $view->setFrame('general');
 *   return $view;
 * });
 * 
 * Di::view()->render('index', 'front', ['hash' => 'aabbccdd']);
 * 
 * ?>
 * <html lang="ja">
 *   <body>
 *     <script src="/js/front.js?hash=<?=$hash?>"></script>
 *   </body>
 * </html>
 * ```
 */
class View
{
    /**
     * ディレクトリ
     * @var string
     */
    private $_dir;

    /**
     * ファイルの拡張子
     * @var string
     */
    private $_extension;

    /**
     * デフォルトファイル名
     * @var string
     */
    private $_notfound;

    /**
     * フレーム使用フラグ
     * @var boolean
     */
    private $_isframe;

    /**use Library\Di;
     * フレームファイル名
     * @var string
     */
    private $_frame;

    /**
     * パラメータ
     * @var array
     */
    private $_mixeds;

    /**
     * Partialエリア
     * @var array
     */
    private $_partials;

    /**
     * コンストラクタ
     *
     * @param  string $dir
     * @param  string $extension
     * @param  string $notfound
     * @return void
     */
    public function __construct(string $dir, string $extension, string $notfound)
    {
        $this->_dir       = $dir;
        $this->_extension = $extension;
        $this->_notfound  = $notfound;
        $this->_mixeds      = [];
        $this->_isframe   = false;
        $this->_partials  = [];
    }

    /**
     * フレームの設定
     *
     * @param  string $filename
     * @return void
     */
    public function setFrame(string $filename)
    {
        if (empty($filename)) {
            $this->_isframe = false;
        } else {
            $this->_isframe = true;
            $this->_frame   = $filename;
        }
    }

    /**
     * 変数の設定
     *
     * @param  string $key
     * @param  $ $value
     * @return void
     */
    public function setmixed(string $key, $value) : void
    {
        $this->_mixeds[$key] = $value;
    }

    /**
     * 変数の取得
     *
     * @param string $key
     * @return mixed
     */
    public function getmixed(string $key) : mixed
    {
        return $this->_getmixeds($this->_mixeds, $key);
    }

    /**
     * self::getmixed() のエイリアス
     *
     * @param  string $key
     * @return mixed
     */
    public function v(string $key) : mixed
    {
        return $this->getmixed($key);
    }

    /**
     * View変数をエスケープして取得
     *
     * @param  string $key
     * @return mixed
     */
    public function esc(string $key) : mixed
    {
        return htmlspecialchars($this->getmixed($key));
    }

    /**
     * self::esc() のエイリアス
     *
     * @param  string $key
     * @return mixed
     */
    public function e(string $key) : mixed
    {
        return htmlspecialchars($this->getmixed($key));
    }

    /**
     * レンダリング
     *
     * @param  string  $group
     * @param  string  $name
     * @param  array   $params
     * @return void
     */
    public function render(string $group, string $name, $params) : void
    {
        $ds = \DIRECTORY_SEPARATOR;
        $_group = $group;
        $_name  = $name;
        $_path  = $this->_dir . $ds . $_group . $ds . $_name . '.' . $this->_extension;

        if (!file_exists($_path)) {
            $_exploded = explode($ds, $this->_notfound);

            $_group = strtolower($_exploded[0]);
            $_name  = strtolower($_exploded[1]);
            $_path  = $this->_dir . $ds . $_group . $ds . $_name . '.' . $this->_extension;
        }

        $this->_mixeds = array_merge($this->_mixeds, $params);

        if ($this->_isframe) {
            $this->_mixeds['_template'] = $this->_dir . $ds . $_group . $ds . $_name . '.' . $this->_extension;
            extract($this->_mixeds);
            include $this->_dir . $ds . $this->_frame . '.' . $this->_extension;
            return ;
        }

        extract($this->_mixeds);
        include $_path;
    }

    /**
     * レンダリングの取得
     *
     * @param  string $group
     * @param  string $name
     * @param  array   $params
     * @return string
     */
    public function getRender(string $group, string $name, $params) : string
    {
        ob_start();
        $this->render($group, $name, $params);
        return ob_get_clean();
    }

    /**
     * json出力
     *
     * @param  array   $params
     * @return void
     */
    public function jsonRender(mixed $params) : void
    {
        $_get = $_GET;

        if (isset($_get['callback'])) {
            echo $_get['callback'].'(' . json_encode($params) . ')';
        } else {
            echo json_encode($params);
        }
    }

    /**
     * json出力
     *
     * @param  array   $params
     * @return void
     */
    public function getJsonRender(mixed $params) : string
    {
        $_get = $_GET;

        if (isset($_get['callback'])) {
            return $_get['callback'].'(' . json_encode($params) . ')';
        } else {
            return json_encode($params);
        }
    }

    /**
     * テンプレート処理
     *
     * @param  string $template
     * @param  array  $val
     * @return string
     */
    public static function Template(string $template, $val) : string
    {
        $_len = mb_strlen($template);
        $_source = '<?php $arg = json_decode("'.json_encode($val).'", true); ?>';
        $_val = '';
        $_cmd = '';
        $_isCopy = true;
        $_isLoop = false;

        $_cur = 0;
        while(1) {
            $_target = mb_substr($template, $_cur, 1);
            if ($_target === '{') {
                $_isCopy = false;
                $_cmd = '';
            } else {
                if ($_target === '}') {
                    $_isCopy = true;
                    if (false !== strpos($_cmd, '/')) {
                        $_source .= '<?php } ?>';
                        $_isLoop = false;
                    } else {
                        if (false !== strpos($_cmd, 'IS')) {
                            $_val = trim(substr($_cmd, 2));
                            $_source .= '<?php if (!empty($arg["'. $_val .'"])) { ?>';
                        } else {
                            if (false !== strpos($_cmd, 'LOOP')) {
                                $_val = trim(substr($_cmd, 4));
                                $_source .= '<?php foreach($arg["'. $_val . '"] as $_row) { ?>';
                                $_isLoop = true;
                            } else {
                                $_source .= $_isLoop ? '<?=$_row["'. $_cmd .'"]?>' : '<?=$arg["'. $_cmd .'"]?>';
                                if (mb_substr($template, $_cur+1, 1) == "\n") {
                                    $_source .= "\n";
                                }
                            }
                        }
                    }
                } else {
                    if ($_isCopy) {
                        $_source .= $_target;
                    } else {
                        $_cmd .= $_target;
                    }
                }
            }

            $_cur++;
            if ($_len == $_cur) {
                break;
            }
        }

        ob_start();
        eval('?>'. $_source ."\n");
        $_replaced = ob_get_contents();
        ob_end_clean();

        return $_replaced;
    }

    /**
     * パーシャル
     *
     * @param  $$filename
     * @param  array  $argv
     * @return void
     */
    public function partial(string $filename, $argv = null)
    {
        $_ds = \DIRECTORY_SEPARATOR;
        $_filename = $this->_dir . $_ds . $filename . '.' . $this->_extension;

        if (file_exists ($_filename)) {
            if (is_array($argv)) {
                $this->_mixeds = array_merge($this->_mixeds, $argv);
            }

            include $_filename;
        }
    }

    /**
     * パーシャルエリア開始
     *
     * @param  $$key
     * @return void
     */
    public function partialStart(string $key)
    {
        $this->_partials[$key] = [];
        ob_start();
    }

    /**
     * パーシャルエリア終了
     *
     * @param  $$key
     * @return void
     */
    public function partialEnd(string $key)
    {
        $buff = ob_get_clean();
        $this->_partials[$key][] = $buff;
    }

    /**
     * パーシャルデータの展開
     *
     * @param  $$key
     * @return void
     */
    public function deployPartial(string $key)
    {
        if (isset($this->_partials[$key])) {
            foreach ($this->_partials[$key] as $_tpl) {
                echo $_tpl;
            }
        }
    }

    /**
     * 変数の取得
     *
     * @access public
     * @param  array   $mixeds
     * @param  string  $key
     * @return mixed
     */
    private function _getmixeds(mixed $mixeds, string $key) : mixed
    {
        $_keys = explode('.', $key);

        if (!isset($mixeds[$_keys[0]])) {
            $result = null;
            return $result;
        }

        if (!isset($_keys[1])) {
            return $mixeds[$_keys[0]];
        }

        $_key = substr($key, strpos($key, '.') + 1);

        return $this->_getmixeds($mixeds[$_keys[0]], $_key);
    }
} // class View
