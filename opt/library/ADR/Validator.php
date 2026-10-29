<?php
namespace Library\ADR;

/**
 * リクエストクラス
 * 
 * Usage example:
 *
 * ```php
 * require '/opt/vendor/autoload.php';
 * use Library\ADR\Di;
 * use Library\ADR\Validator;
 * 
 * Di::set('validator', function () {
 *   return new Validator();
 * });
 * 
 * // HTTPリクエストされた内容を精査
 * $result = Di::validator()->validate('number', '$required [1,3]');
 * if ($result) {
 *   echo "正常に取得できました。";
 * } else {
 *   echo "必須項目です。";
 * }
 * ```
 */
class Validator 
{
    /**
     * 型
     * @var array<string, mixed>
     */
    private $_types;

    /**
     * 必須項目
     * @var array<string, mixed>
     */
    private $_requireds;

    /**
     * 入力範囲
     * @var array<string, mixed>
     */
    private $_ranges;


    /**
     * 証明結果一覧
     * @var array<string, mixed>
     */
    private $_verifies;

    /**
     * 値一覧
     * @var array<string, mixed>
     */
    private $_values;

    /**
     * タイトル一覧
     * @var array<string, mixed>
     */
    private $_titles;

    /**
     * エラーメッセージ原文
     * @var array<string, string>
     */
    private $_messages;

    /**
     * エラーメッセージ
     * @var array<string, string>
     */
    private $_errors;

    /**
     * コンストラクタ
     *
     * @param array $messages   エラーメッセージ
     * @return void
     */
    public function __construct(array $messages = null)
    {
        $this->_types     = [];
        $this->_requireds = [];
        $this->_ranges    = [];

        if (empty($messages)) {
            $this->_messages = $this->_getDefaultMessages();
        } else {
            $this->_messages = $messages;
        }
    }

    /**
     * エラーメッセージの設定
     *
     * @param string $key       エラーメッセージキー
     * @param array  $message   エラーメッセージ
     * @return boolean
     */
    public function setMessage(string $key, string $message) : bool
    {
        if (!isset($this->_messages[$key])) {
            return false;
        }

        $this->_messages[$key] = $message;
        return true;
    }

    /**
     * エラーメッセージの設定
     *
     * @param string $key       エラーメッセージキー
     * @param array  $message   エラーメッセージ
     * @return array|string
     */
    public function getError(string $key = null) : mixed
    {
        if (empty($key)) {
            return $this->_errors;
        }
        return $this->_errors[$key];
    }

    /**
     * フォームからの値の正当性を確認
     *
     * Example:
     * <code><pre>
     * $object->validates([
     *     '$title'  => 'str required [1-36]',
     *     'url'    => 'url',
     *     'email'  => 'email required',
     *     'passwd' => 'required str'
     * ]);
     * </pre></code>
     *
     * @param array $rules 条件配列
     * @return boolean
     */
    public function validation(array $rules)
    {
        $_validated = true;

        foreach ($rules as $_key=>$_row) {
            if (!is_array($_row)) {
                $_rule  = $_row[0];
                $_title = $_row[1];
            } else {
                $_rule  = $_row;
                $_title = $_key;
            }

            if (!$this->validate($_key, $_rule, $_title)) {
                $_validated = false;
            }
        }

        return $_validated;
    }

    /**
     * 正当性結果の取得
     *
     * @return boolean
     */
    public function validated() : bool
    {
        foreach ($this->_verifies as $_verifies) {
            if (!$_verifies['type'] || !$_verifies['range'] || !$_verifies['required']) {
                return false;
            }
        }

        return true;
    }

    /**
     * データの取得
     *
     * @return array
     */
    public function payload() : mixed
    {
        return $this->_values;
    }

    /**
     * 判定結果の取得
     *
     * @return array
     */
    public function verifies() : mixed
    {
        return $this->_verifies;
    }

    /**
     * フォームからの値の正当性を確認
     *
     * Example:
     * <code><pre>
     * $object->validate('number', '$required [1,3]');
     * </pre></code>
     *
     * @param string $key   確認対象情報
     * @param string $rule  実行条件
     * @param string $title タイトル
     * @return boolean
     */
    public function validate(string $key, string $rule, string $title = null)
    {
        $_validated = true;
        $_verifies = [];

        $this->_types[$key]     = $this->_parseType($rule);
        $this->_requireds[$key] = $this->_parseRequired($rule);
        $this->_ranges[$key]    = $this->_parseRange($rule, $this->_types[$key]);
        $this->_titles[$key]    = empty($title) ? $key : $title;

        $_verifies['type']     = true;
        $_verifies['range']    = true;
        $_verifies['required'] = true;

        $_isempty = Di::get('request')->isEmpty($key);
        if ($_isempty) {
            if ($this->_requireds[$key]) {
                $_verifies['required'] = false;
                $this->_setMessage($key, '__required');
            }
            $_value = null;
        } else {
            $_value = Di::get('request')->get($key, $this->_types[$key]);
            if ($_value == null) {
                $_verifies['type'] = false;
                $this->_setMessage($key, $this->_types[$key]);
            } else {
                if (!$this->_verifyRange($key, $_value, $this->_types[$key], $this->_ranges[$key])) {
                    $_verifies['range'] = false;
                    $_value             = null;
                }
            }
        }
        
        if (!$_verifies['type'] || !$_verifies['range'] || !$_verifies['required']) {
            $_validated = false;
        }

        $this->_verifies[$key] = $_verifies;
        $this->_values[$key]   = $_value;

        return $_validated;
    }

    /**
     * タイプの取得
     *
     * @access public
     * @param string    $rule   実行条件
     * @return string
     */
    private function _parseType(string $rule) : string
    {
        $_matches = [];

        if (!preg_match('/([a-z]+)/i', $rule, $_matches)) {
            return 'str';
        }

        if (strcasecmp($_matches[1], 'required') == 0) {
            $_rule = str_replace($_matches[1], '', $rule);

            if (!preg_match('/([a-z]+)/i', $_rule, $_matches)) {
                return 'str';
            }
        }

        return $_matches[1];
    }

    /**
     * 必須フラグの取得
     *
     * @access public
     * @param array     $rule   実行条件
     * @return boolean
     */
    private function _parseRequired(string $rule) : bool
    {
        if (false === strpos($rule, 'required')) {
            return false;
        }
        return true;
    }

    /**
     * 範囲の取得
     *
     * @access public
     * @param string    $rule   実行条件
     * @param string    $type   タイプ
     * @return array
     */
    private function _parseRange(string $rule, string $type) : array
    {
        $_matches = [];
        $_renges  = [];
        $_result  = [];
        
        $_result['min'] = 0;
        $_result['max'] = $this->_size($type);

        if (!preg_match('/\[([^\]]+)\]/i', $rule, $_matches)) {
            return $_result;
        }

        $_range = $_matches[1];
        if (!preg_match('/(-?[0-9\.]*),?(-?[0-9\.]*)/i', $_range, $_renges)) {
            return $_result;
        }

        if (isset($_renges[1])) {
            $_result['min'] = $_renges[1];
        }

        if (isset($_renges[2])) {
            $_result['max'] = $_renges[2];
        }

        return $_result;
    }

    /**
     * 範囲判定
     *
     * @access public
     * @param string    $key    キー
     * @param string    $rule   実行条件
     * @param string    $type   タイプ
     * @param array     $ranges 範囲
     * @return array
     */
    private function _verifyRange(string $key, $value, string $type, array $ranges) : bool
    {
        switch (strtolower($type)) {
            case 'int':
            case 'integer':
            case 'double':
            case 'float':
                if ($value < $ranges['min']) {
                    $this->_setMessage($key, '__number', $ranges);
                    return false;
                }
                if ($value > $ranges['max']) {
                    $this->_setMessage($key, '__number', $ranges);
                    return false;
                }
                break;

            case 'str':
            case 'string':
            case 'mail':
            case 'email':
            case 'ip':
            case 'mac':
            case 'url':

            case 'date':
            case 'datetime':
            case 'dates':
            case 'datetimes':
                if (strlen($value) < $ranges['min']) {
                    $this->_setMessage($key, '__string', $ranges);
                    return false;
                }
                if (strlen($value) > $ranges['max']) {
                    $this->_setMessage($key, '__string', $ranges);
                    return false;
                }
                break;
        }

        return true;
    }

    /**
     * 型サイズの取得
     *
     * @access private
     * @param  string   $type
     * @return integer
     */
    private function _size(string $type) : int
    {
        switch (strtolower($type)) {
            case 'str':
            case 'string':
                $_length = 256;
                break;
            case 'int':
            case 'integer':
                $_length = 4294967295;
                break;
            case 'bool':
            case 'boolean':
                $_length = 1;
                break;
            case 'mail':
            case 'email':
                $_length = 256;
                break;
            case 'double':
            case 'float':
                $_length = 4294967295;
                break;
            case 'ip':
                $_length = 40;
                break;
            case 'mac':
                $_length = 18;
                break;
            case 'url':
                $_length = 1028;
                break;
            case 'date':
                $_length = 16;
                break;
            case 'datetime':
                $_length = 32;
                break;
            case 'dates':
                $_length = 32;
                break;
            case 'datetimes':
                $_length = 32;
                break;
            default:
                $_length = 4294967295;
                break;
        }

        return $_length;
    }

    /**
     * エラーメッセージの設定
     *
     * @access private
     * @param  string   $key
     * @param  string   $type
     * @param  array    $ranges
     * @return void
     */
    private function _setMessage(string $key, string $type, array $ranges = null) : void
    {
        switch (strtolower($type)) {
            case 'str':
            case 'string':
                $_type = 'string';
                break;
            case 'int':
            case 'integer':
                $_type = 'integer';
                break;
            case 'bool':
            case 'boolean':
                $_type = 'boolean';
                break;
            case 'mail':
            case 'email':
                $_type = 'email';
                break;
            case 'double':
            case 'float':
                $_type = 'double';
                break;
            case 'date':
            case 'dates':
                $_type = 'date';
                break;
            case 'datetime':
            case 'datetimes':
                $_type = 'datetime';
                break;
            default :
                $_type = $type;
        }

        switch (strtolower($_type)) {
            case 'string':
            case 'integer':
            case 'boolean':
            case 'email':
            case 'double':
            case 'ip':
            case 'mac':
            case 'url':
            case 'date':
            case 'datetime':
                $this->_errors[$key] = sprintf(
                    $this->_messages[$_type], 
                    $this->_titles[$key]
                );
                break;
            case '__required':
                $this->_errors[$key] = sprintf(
                    $this->_messages['required'], 
                    $this->_titles[$key]
                );
                break;
            case '__string':
                if (empty($ranges['min'])) {
                    $this->_errors[$key] = sprintf(
                        $this->_messages['string_range_max'], 
                        $this->_titles[$key],
                        $ranges['max']
                    );
                } else {
                    $this->_errors[$key] = sprintf(
                        $this->_messages['string_range'], 
                        $this->_titles[$key],
                        $ranges['min'],
                        $ranges['max']
                    );
                }
                break;
            case '__number':
                if (empty($ranges['min'])) {
                    $this->_errors[$key] = sprintf(
                        $this->_messages['number_range_max'], 
                        $this->_titles[$key],
                        $ranges['max']
                    );
                } else {
                    $this->_errors[$key] = sprintf(
                        $this->_messages['number_range'], 
                        $this->_titles[$key],
                        $ranges['min'],
                        $ranges['max']
                    );
                }
                break;
            default:
                break;
        }
    }

    /**
     * デフォルトエラーメッセージの取得
     *
     * @access private
     * @return array
     */
    private function _getDefaultMessages() : array
    {
        return [
            'string'            => '%sは文字列を入力してください。',
            'integer'           => '%sは数値を入力してください。',
            'boolean'           => '%sは真偽値を入力してください。',
            'email'             => '%sはメールアドレスを入力してください。',
            'double'            => '%sは数値を入力してください。',
            'ip'                => '%sはIPアドレスを入力してください。',
            'mac'               => '%sはMACアドレスを入力してください。',
            'url'               => '%sは正しいURLを入力してください。',
            'date'              => '%sは正しい日付を入力してください。',
            'datetime'          => '%sは正しい日時を入力してください。',
            'required'          => '%sは必須項目です。',
            'string_range_max'  => '%1$sは%2$d文字以内で入力してください。',
            'string_range'      => '%1$sは%2$d以上、%3$d文字以内で入力してください。',
            'number_range_max'  => '%1$sは%2$d以内の数値を入力してください。',
            'number_range'      => '%1$sは%2$d以上、%3$d以内の数値を入力してください。'
        ];
    }
} // class Validator 
