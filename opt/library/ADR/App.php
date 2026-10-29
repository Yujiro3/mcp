<?php
namespace Library\ADR;

use Library\ADR\Di;

/**
 * アプリケーションクラス
 * 
 * Usage example:
 *
 * ```php
 * require '/opt/vendor/autoload.php';
 * use Library\ADR\App;
 * 
 * App([
 *   'app_dir'    => APP_PATH.'/back-end',
 *   'lib_dir'    => APP_PATH.'/back-end/lib',
 *   'model_dir'  => APP_PATH.'/back-end/model',
 *   'vendor_dir' => APP_PATH.'/back-end/'
 * ]);
 * ```
 */
class App
{
    /**
     * アプリケーションディレクトリ
     * @var string
     */
    private $_appdir;

    /**
     * ライブラリディレクトリ
     * @var string
     */
    private $_libdir;

    /**
     * モデルディレクトリ
     * @var string
     */
    private $_modeldir;

    /**
     * ベンダーディレクトリ
     * @var string
     */
    private $_vendordir;

    /**
     * コンストラクタ
     *
     * @param  array $config アプリケーション設定
     */
    public function __construct(array $config)
    {
        $ds = \DIRECTORY_SEPARATOR;

        $this->_appdir = $config['app_dir'];
        $this->_libdir = $config['lib_dir'];
        $this->_modeldir = '';
        $this->_vendordir = $config['vendor_dir'];

        if (isset($config['model_dir'])) {
            $this->_modeldir = $config['model_dir'];
        }

        Di::setInstance();

        spl_autoload_register([$this, 'autoLoad']);

        if (file_exists($this->_appdir. $ds .'vendor'. $ds .'autoload.php')) {
            include_once $this->_appdir. $ds .'vendor'. $ds .'autoload.php';
        }
        if (file_exists($this->_vendordir. $ds .'autoload.php')) {
            include_once $this->_vendordir . $ds.'autoload.php';
        }
    }

    /**
     * オートローダー
     *
     * @param  string $className クラス名
     * @return boolean
     * @example location $app->autoLoad('Libraries\\Cache')
     */
    public function autoLoad(string $className): bool
    {
        $ds = \DIRECTORY_SEPARATOR;

        $file = str_replace('\\', $ds, $className);

        if (file_exists($this->_libdir.$ds.$file.'.php')) {
            include_once $this->_libdir.$ds.$file.'.php';
            return true;
        }
        if (file_exists($this->_modeldir.$ds.$file.'.php')) {
            include_once $this->_modeldir.$ds.$file.'.php';
            return true;
        }
        if (file_exists($this->_appdir.$ds.$file.'.php')) {
            include $this->_appdir.$ds.$file.'.php';
            return true;
        }

        $file = lcfirst($file);
        if (file_exists($this->_libdir.$ds.$file.'.php')) {
            include_once $this->_libdir.$ds.$file.'.php';
            return true;
        }
        if (file_exists($this->_modeldir.$ds.$file.'.php')) {
            include_once $this->_modeldir.$ds.$file.'.php';
            return true;
        }
        if (file_exists($this->_appdir.$ds.$file.'.php')) {
            include_once $this->_appdir.$ds.$file.'.php';
            return true;
        }

        $file = strtolower($file);
        if (file_exists($this->_libdir.$ds.$file.'.php')) {
            include_once $this->_libdir.$ds.$file.'.php';
            return true;
        }
        if (file_exists($this->_modeldir.$ds.$file.'.php')) {
            include_once $this->_modeldir.$ds.$file.'.php';
            return true;
        }
        if (file_exists($this->_appdir.$ds.$file.'.php')) {
            include_once $this->_appdir.$ds.$file.'.php';
            return true;
        }

        return false;
    }
} // class App