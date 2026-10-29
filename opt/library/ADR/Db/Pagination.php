<?php
namespace Library\ADR\Db;

use Library\ADR\Di;
use Library\ADR\Request;

/**
 * ページネーション
 *
 * @example location 
 * <code>
 * $args = [...];
 * $page = new Pagination([
 *         'id' => [
 *             'variable' => 'int',
 *             'where'    => '`usr`.`id` = :id',
 *             'format'   => '%d',
 *             'type'     => \PDO::PARAM_INT
 *         ],
 *         'name' => [
 *             'variable' => 'string',
 *             'where'    => '`usr`.`name` LIKE :name',
 *             'format'   => '%%%s%%',
 *             'type'     => \PDO::PARAM_STR
 *         ]
 *     ],
 *     'SELECT `id`, `name` FROM `users`', 
 *     'SELECT COUNT(*) FROM `users`',
 *    $args
 * );
 * $orderList = ['id', 'name', 'created_at'];
 * $list = $page->getPaginate($orderList);
 * print_r($list);
 * </code>
 */
class Pagination
{
    /**
     * リクエストオブジェクト
     * @$Library\Request
     */
    protected $request;

    /**
     * PDO
     * @$PDO
     */
    protected $db;

    /**
     * 条件ルール
     * @$array
     */
    protected $rules;

    /**
     * SQL
     * @$array
     */
    protected $sql;

    /**
     * カウント用SQL
     * @$array
     */
    protected $countSql;

    /**
     * 条件句配列
     * @$array
     */
    protected $wheres;

    /**
     * 条件句
     * @$string
     */
    protected $where;

    /**
     * バインドデータ
     * @$array
     */
    protected $binds;

    /**
     * 並べ替えfield
     * @$string
     */
    protected $field;

    /**
     * ソート方向
     * @$string
     */
    protected $sequence;

    /**
     * グループ句
     * @$string
     */
    protected $group;

    /**
     * 結果条件句
     * @$string
     */
    protected $having;

    /**
     * オーダー句
     * @$string
     */
    protected $order;

    /**
     * デフォルトソート方向
     * @$string
     */
    protected $defaultSequence;

    /**
     * 取得可能な最大数
     * @$integer
     */
    protected $max;

    /**
     * 上限
     * @$integer
     */
    protected $limit;

    /**
     * オフセット
     * @$integer
     */
    protected $offset;

    /**
     * コンストラクタ
     * 
     * @param  string  $rules POSTデータルール
     * @param  string  $sql SQL文
     * @param  string  $cntsql 結果数の取得SQL
     * @param  array   $requests リクエストデータ
     * @return void
     */
    public function __construct(array $rules, string $sql, string $cntsql = null, array $requests = [])
    {
        $_parsed = [];

        if (empty($requests)) {
            $this->request = Di::get('request');
        } else {
            $this->request = new Request($requests);
        }

        $this->db              = Di::get('db');
        $this->rules           = $rules;
        $this->wheres          = [];
        $this->binds           = [];
        $this->limit           = 20;
        $this->defaultSequence = 'DESC';

        $_parsed      = $this->_parseSQL($sql);
        $this->sql    = $_parsed['sql'];
        $this->order  = $_parsed['order'];
        $this->having = $_parsed['having'];
        $this->group  = $_parsed['group'];
        
        if (!empty($cntsql)) {
            $this->countSql = $cntsql;
        } else {
            $this->countSql = preg_replace('/^.+(?=FROM)/i', 'SELECT COUNT(*) ', $this->sql);
        }
    }

    /**
     * デフォルト上限の設定
     *
     * @param  integer $limit 表示上限数
     * @return this
     */
    public function setLimit(int $limit) : Pagination
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * デフォルトオーダー項目の設定
     *
     * @param  string $field
     * @return this
     */
    public function setOrder(string $field) : Pagination
    {
        $this->field = $field;
        return $this;
    }

    /**
     * デフォルトソート方向の設定
     *
     * @param  string $sequence
     * @return this
     */
    public function setSequence(string $sequence) : Pagination
    {
        $this->defaultSequence = $sequence;
        return $this;
    }

    /**
     * WHERE句の追加
     *
     * @param  string $where
     * @return this
     */
    public function addWhere(string $where) : Pagination
    {
        $this->wheres[] = $where;
        return $this;
    }

    /**
     * バインドデータの追加
     *
     * @param  string  $key キー
     * @param  mixed   $value 値
     * @param  integer $type 値の型
     * @return this
     */
    public function addBind(string $key, mixed $value, int $type = null) : Pagination
    {
        $this->binds[$key]['value'] = $value;
        if (null === $type) {
            $this->binds[$key]['type'] = \PDO::PARAM_STR;
        } else {
            $this->binds[$key]['type'] = $type;
        }
        return $this;
    }

    /**
     * Pagerデータの取得
     * 
     * @param  string  $columns
     * @param  string  $format
     * @return array
     */
    public function getPaginate(array $columns = null, string $format = null) : array
    {
        $_result = [];
        $this->_setCondition();
        $this->_setCount();
        $this->_setRange();
        $this->_setOrder($columns, $format);

        return [
            'total' => $this->max,
            'items' => $this->_getList()
        ];
    }

    /**
     * PagerQueryの内容を取得
     *
     * @param  string  $columns
     * @param  string  $format
     * @return array
     */
    public function dumpQuery(array $columns = null, string $format = null) : array
    {
        $_result = [];

        $this->_setCondition();
        $this->_setCount();
        $this->_setRange();
        $this->_setOrder($columns, $format);

        $_result['sql'] = $this->_buildQuery();

        $_result['binds'] = [];
        foreach ($this->binds as $_key=> $_bind) {
            $_result['binds'][$_key] = $_bind['value'];
        }

        return $_result;
    }

    /**
     * PagerQueryのSQLを取得
     *
     * @param  string   $columns
     * @param  string  $format
     * @return string
     */
    public function dumpSQL(array $columns = null, string $format = null) : string
    {
        $this->_setCondition();
        $this->_setCount();
        $this->_setRange();
        $this->_setOrder($columns, $format);

        $_result = $this->_buildQuery();

        foreach ($this->binds as $_key=>$_bind) {
            if ($_bind['type'] == \PDO::PARAM_INT) {
                $_result = str_replace($_key, $_bind['value'], $_result);
            } else {
                if (is_null($_bind['value'])) {
                    $_result = str_replace($_key, 'NULL', $_result);
                } else {
                    $_result = str_replace($_key, $_bind['value'], "'".$_result."'");
                }
            }
        }

        return $_result;
    }

    /**
     * SQLの分解
     *
     * @access private
     * @param  string   $sql
     * @return array
     */
    private function _parseSQL(mixed $sql) : array
    {
        $_result = $_sqls = [];

        $_result['sql']    = '';
        $_result['order']  = '';
        $_result['having'] = '';
        $_result['group']  = '';

        if (preg_match('/GROUP\s+BY/i', $sql)) {
            $_sqls = preg_split('/GROUP\s+BY/i', $sql);
            $_sqls[1] = 'GROUP BY' . $_sqls[1];
        } else {
            if (preg_match('/HAVING/i', $sql)) {
                $_sqls = preg_split('/HAVING/i', $sql);
                $_sqls[1] = 'HAVING' . $_sqls[1];
            } else {
                if (preg_match('/ORDER\s+BY/i', $sql)) {
                    $_sqls = preg_split('/ORDER\s+BY/i', $sql);
                    $_sqls[1] = 'ORDER BY' . $_sqls[1];
                } else {
                    $_sqls[0] = $sql;
                    $_sqls[1] = '';
                }
            }
        } // if preg_match('/GROUP\s+BY/i', sql)

        $_result['sql'] = $_sqls[0];
        $_subject  = $_sqls[1];

        if (preg_match('/ORDER\s+BY/i', $_subject)) {
            $_sqls = preg_split('/ORDER\s+BY/i', $_subject);
            $_subject         = $_sqls[0];
            $_result['order'] = $_sqls[1];
        }
        if (preg_match('/HAVING/i', $_subject)) {
            $_sqls = preg_split('/HAVING/i', $_subject);
            $_subject          = $_sqls[0];
            $_result['having'] = $_sqls[1];
        }
        if (preg_match('/GROUP\s+BY/i', $_subject)) {
            $_sqls = preg_split('/GROUP\s+BY/i', $_subject);
            $_subject         = $_sqls[0];
            $_result['group'] = $_sqls[1];
        }

        return $_result;
    }

    /**
     * SQLの成型
     *
     * @access private
     * @return string
     */
    private function _buildQuery() : string
    {
        $_sql = $this->sql;

        if (!empty($this->where)) {
            $_sql .= ' WHERE '. $this->where;
        }

        if (!empty($this->group)) {
            $_sql .= ' GROUP BY'.$this->group;
        }

        if (!empty($this->having)) {
            $_sql .= ' HAVING'.$this->having;
        }

        if (empty($this->field)) {
            if (!empty($this->order)) {
                $_sql .= ' ORDER BY'.$this->order;
            }
        } else {
            $_sql .= ' ORDER BY '.
                         $this->field.' '.$this->sequence.' ';
        }

        $_sql .= ' LIMIT '.$this->limit;
        $_sql .= ' OFFSET '.$this->offset;
        
        return $_sql;
    }

    /**
     * 条件の取得
     *
     * @access private
     * @return void
     */
    private function _setCondition() : void
    {
        foreach ($this->rules as $_key=>$_curr) {
            if (is_array($_curr)) {
                $_where    = $_curr['where'];
                $_variable = isset($_curr['variable']) ?  $_curr['variable'] : 'string';
                $_format   = isset($_curr['format']) ? $_curr['format'] : '%s';
                $_type     = isset($_curr['type']) ? $_curr['type'] : $this->_getParamType($_variable);
            } else {
                $_where    = $_curr;
                $_variable = 'string';
                $_format   = '%s';
                $_type     = \PDO::PARAM_STR;
            }
            $_value =  $this->request->get($_key, $_variable);
            $_len = strlen($_value);

            if (0 != $_value && 0 !== $_len) {
                $this->wheres[] = $_where;
                $this->binds[':'.$_key]['value'] = sprintf($_format, $_value);
                $this->binds[':'.$_key]['type'] = $_type;
            }
        } // for _key, _curr in $this->rules


        if (0 !== count($this->wheres)) {
            $this->where = implode(' AND ', $this->wheres);
        }
    }

    /**
     * 並び順の取得
     *
     * @access public
     * @param  array    $columns
     * @param  string   $format
     * @return void
     */
    private function _setOrder(array $columns = null, string $format = null) : void
    {        
        if (empty($columns)) {
            return ;
        }

        $_order    = $this->request->get('order', 'string');
        $_sequence = $this->request->get('sequence', 'string');

        if (empty($_order) && empty($_sequence)) {
            return ;
        }

        if (in_array($_order, $columns)) {
            $_field = $_order;
        } else {
            $_field = current($columns);
        }

        if (empty($format)) {
            $format = '`%s`';
        }
        $this->field = sprintf($format, $_field);

        if (0 === strcasecmp('ASC', $_sequence)) {
            $this->sequence = 'ASC';
        } else {
            if (0 === strcasecmp('DESC', $_sequence)) {
                $this->sequence = 'DESC';
            } else {
                $this->sequence = $this->defaultSequence;
            }
        }
    }

    /**
     * 表示範囲の取得
     *
     * @access public
     * @return void
     */
    private function _setRange() : void
    {
        $limit  = $this->request->get('limit', 'int');
        $offset = $this->request->get('offset', 'int');

        $this->limit = empty($limit) ? $this->limit : intval($limit);
        $this->offset = empty($offset) ? 0  : intval($offset);

        if ($this->max <= $this->offset) {
            $this->offset = $this->max - $this->limit;
        }
        if (0 >= $this->offset) {
            $this->offset = 0;
        }
    }

    /**
     * 条件のヒット数を取得
     *
     * @access private
     * @param  array   $condition
     * @return void
     */
    private function _setCount() : void
    {
        $_parsed = $this->_parseSQL($this->countSql);

        if (empty($this->where)) {
            $_sql = $_parsed['sql'];
        } else {
            $_sql = $_parsed['sql'] .' WHERE '. $this->where;
        }

        if (!empty($_parsed['group'])) {
            $_sql = $_sql . ' GROUP BY ' . $_parsed['group'];
        }

        $_stmt  = $this->db->prepare($_sql);
        foreach ($this->binds as $_key=>$_bind) {
            $_stmt->bindValue($_key, $_bind['value'], $_bind['type']);
        }
        $_stmt->execute();

        $this->max = $_stmt->fetchColumn();        
        if (empty($this->max)) {
            $this->max = 0;
        }
    }

    /**
     * 一覧の取得
     *
     * @access private
     * @return array
     */
    private function _getList()
    {        
        $_sql  = $this->_buildQuery();
        $_stmt = $this->db->prepare($_sql);

        foreach ($this->binds as $_key=>$_bind) {
            $_stmt->bindValue($_key, $_bind['value'], $_bind['type']);
        }
        $_stmt->execute();

        $_result = $_stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($_result)) {
            return [];
        }

        return $_result;
    }

    /**
     * PDO引数型の取得
     *
     * @access private
     * @param  string   variable
     * @return int
     */
    private function _getParamType(string $variable)
    {
        switch (strtolower($variable)) {
            case 'string':
            case 'str':
            case 'mail':
            case 'email':
            case 'double':
            case 'float':
            case 'ip':
            case 'mac':
            case 'url':
            case 'date':
            case 'datetime':
            case 'dates':
            case 'datetimes':
                return \PDO::PARAM_STR;
            case 'int':
            case 'integer':
                return \PDO::PARAM_INT;
            case 'bool':
            case 'boolean':
                return \PDO::PARAM_BOOL;
        }

        return \PDO::PARAM_STR;
    }
} // class Pagination
