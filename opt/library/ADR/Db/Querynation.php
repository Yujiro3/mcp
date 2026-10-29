<?php
namespace Library\ADR\Db;

use Library\ADR\Di;
use Library\ADR\Request;

/**
 * クエリネーション
 * 
 * Example:
 * <code>
 * $query = new Querynation([
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
 *     'SELECT `id`, `name` FROM `users` GROUP BY `client_id` ORDER BY `created`'
 * );
 * if (!empty($status)) {
 *     $query->addWhere('`usr`.`status` = :status');
 *     $query->addBind(':status', $status, \PDO::PARAM_INT);
 * }
 * $list = $query->getList();
 * print_r($list);
 * </code>
 */
class Querynation 
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
    protected $sqls;

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
     * ステートメント
     * @$PDOStatement
     */
    protected $stmt;

    /**
     * コンストラクタ
     *
     * @param  array   $conditions
     * @return void
     */
    public function __construct(array $rules, string $sql, array $requests = [])
    {
        if (empty($requests)) {
            $this->request = Di::get('request');
        } else {
            $this->request = new Request($requests);
        }

        $this->db     = Di::get('db');
        $this->rules  = $rules;
        $this->wheres = [];
        $this->binds  = [];
        $this->sqls   = [];

        $this->_parseSQL($sql);
    }

    /**
     * WHERE句の追加
     *
     * @param  string $where
     * @return this
     */
    public function addWhere(string $where) : Querynation
    {
        $this->wheres[] = $where;
        return $this;
    }

    /**
     * バインドデータの追加
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  integer $type
     * @return this
     */
    public function addBind(string $key, mixed $value, int $type = null) : Querynation
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
     * 一覧データの取得
     *
     * @return array
     */
    public function getList() : array
    {
        $this->_setCondition();
        $this->_execute();

        $_result = $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($_result)) {
            return [];
        }

        return $_result;
    }

    /**
     * 一行データの取得
     *
     * @return array
     */
    public function getRow() : array
    {
        $this->_setCondition();
        $this->_execute();

        $_result = $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($_result)) {
            return [];
        }

        return $_result[0];
    }

    /**
     * １項目データの取得
     *
     * @param  integer  $number
     * @return mixed
     */
    public function getColumn(mixed $number = null) : mixed
    {
        $this->_setCondition();
        $this->_execute();

        return $this->stmt->fetchColumn($number);
    }

    /**
     * PagerQueryの内容を取得
     *
     * @param  array    $columns
     * @param  string  $format
     * @return array
     */
    public function dumpQuery() : array
    {
        $this->_setCondition();

        $_result['sql']   = $this->_buildQuery();
        $_result['binds'] = [];
        foreach ($this->binds as $_key=>$_bind) {
            $_result['binds'][$_key] = $_bind['value'];
        }

        return $_result;
    }

    /**
     * PagerQueryのSQLを取得
     *
     * @param  array    $columns
     * @param  string  $format
     * @return array
     */
    public function dumpSQL() : string
    {
        $this->_setCondition();

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
     * @return void
     */
    private function _parseSQL(mixed $sql) : void
    {
        if (preg_match('/GROUP\s+BY/i', $sql)) {
            $this->sqls = preg_split('/GROUP\s+BY/i', $sql);
            $this->sqls[1] = 'GROUP BY' .  $this->sqls[1];
        } else {
            if (preg_match('/HAVING/i', $sql)) {
                $this->sqls = preg_split('/HAVING/i', $sql);
                $this->sqls[1] = 'HAVING' .  $this->sqls[1];
            } else {
                if (preg_match('/ORDER\s+BY/i', $sql)) {
                    $this->sqls = preg_split('/ORDER\s+BY/i', $sql);
                    $this->sqls[1] = 'ORDER BY' .  $this->sqls[1];
                } else {
                    if (preg_match('/LIMIT/i', $sql)) {
                        $this->sqls = preg_split('/LIMIT/i', $sql);
                        $this->sqls[1] = 'LIMIT' . $this->sqls[1]. ' ';
                    } else {
                        $this->sqls[0] = $sql;
                        $this->sqls[1] = '';
                    }
                }
            }
        } // if preg_match('/GROUP\s+BY/i', sql)
    }

    /**
     * SQLの成型
     *
     * @access private
     * @return string
     */
    private function _buildQuery() : string
    {
        $_sql = $this->sqls[0];

        if (!empty($this->where)) {
            $_sql .= ' WHERE '. $this->where;
        }
        
        return $_sql . ' '. $this->sqls[1];
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

            if (0 !== $_len) {
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
     * 一覧の取得
     *
     * @access private
     * @return array
     */
    private function _execute()
    {        
        $_sql = $this->_buildQuery();
        $this->stmt = $this->db->prepare($_sql);

        foreach ($this->binds as $_key=>$_bind) {
            $this->stmt->bindValue($_key, $_bind['value'], $_bind['type']);
        }
        $this->stmt->execute();
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
} // class Querynation
