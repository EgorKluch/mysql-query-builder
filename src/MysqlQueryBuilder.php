<?php
/**
 * @author EgorKluch (EgorKluch@gmail.com)
 * @date: 09.06.2014
 */

namespace EgorKluch;

class MysqlQueryBuilder {
  /**
   * @var MysqlQueryBuilder
   */
  static protected $instance;

  /**
   * @return MysqlQueryBuilder
   * @throws \Exception
   */
  static public function getInstance () {
    if (!MysqlQueryBuilder::$instance) {
      throw new \Exception("MysqlQueryBuilder instance not exist");
    }
    return MysqlQueryBuilder::$instance;
  }

  /**
   * @param array $options
   * @throws \Exception
   */
  public function __construct($options) {
    $options['host'] = ($options['host'] or 'localhost');
    $options['user'] = ($options['user'] or 'root');
    $this->conn = new \mysqli($options['host'], $options['user'], $options['pass'], $options['db']);
    if ($this->conn->connect_errno) {
      $errno = $this->conn->connect_errno;
      $error = $this->conn->connect_error;
      throw new \Exception("Failed to connect to MySQL($errno): $error");
    }
  }

  /**
   * @param string $table
   * @param string|array $where
   * @param string|array $columns
   * @return array
   */
  public function select ($table, $where, $columns = '*') {
    $table = $this->conn->escape_string($table);
    if ($where) {
      $where = ' where ' . $this->_getWhereString($where);
    } else {
      $where = '';
    }

    if (is_array($columns)) {
      $columns = array_map(function ($column) {
        return $this->conn->escape_string($column);
      }, $columns);
      $columns = implode(', ', $columns);
    }

    $query = "select $columns from $table $where";

    return $this->_query($query);
  }

  /**
   * @param string $table
   * @param string|array $where
   * @param string|array $columns
   * @return array|null
   */
  public function one ($table, $where, $columns = '*') {
    $rows = $this->select($table, $where, $columns);
    return $rows[0] or null;
  }

  /**
   * @param string $table
   * @param array $fields
   * @return int
   */
  public function insert ($table, $fields) {
    $table = $this->conn->escape_string($table);
    $query = "insert into $table";

    $tmp = array();
    foreach ($fields as $field => $value) {
      $tmp[] = $this->conn->escape_string($field);
    }
    $query .= '(' . implode(', ', $tmp) . ')';

    $tmp = array();
    foreach ($fields as $value) {
      $tmp[] = "'" . $this->conn->escape_string($value) . "'";
    }
    $query .= ' values(' . implode(', ', $tmp) . ')';

    $this->_query($query);
    return $this->conn->insert_id;
  }

  /**
   * @param string $table
   * @param string|array $values
   * @param string|array $where
   * @return bool
   */
  public function update ($table, $values, $where) {
    $table = $this->conn->escape_string($table);
    $query = "update $table";
    $query .= ' set ' . $this->_getWhereString($values, ',');
    $query .= ' where ' . $this->_getWhereString($where);
    $this->_query($query);
    return true;
  }

  /**
   * @param string $table
   * @param string|array $where
   * @return bool
   */
  public function del ($table, $where) {
    $table = $this->conn->escape_string($table);
    $query = "delete from $table";
    $query .= ' where ' . $this->_getWhereString($where);
    $this->_query($query);
    return true;
  }

  public function assign ($table) {
    return new AssignMysqlQueryBuilder($this->conn, $table);
  }

  /**
   * @param string $query
   * @return array
   */
  protected function _query ($query) {
    $result = $this->conn->query($query);
    $this->_checkError("MysqlQueryBuilder.query");

    if (is_bool($result)) {
      return $result;
    }

    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }

  /**
   * Build where string from where object
   * Example: { a:1, b:2, or: { c: 3, and: { d:4, e:5 } } } transform to
   * a=1 and b=2 and (c=3 or (d=4 and e=5))
   *
   * @param string|array $where
   * @param string $operator
   * @return string
   * @throws \Exception
   */
  protected function _getWhereString ($where, $operator = 'and') {
    if (!$where) return '';
    if (is_string($where)) return $where;
    $result = array();
    foreach ($where as $field => $value) {

      if ('and' === $field or 'or' === $field) {
        $result[] = ' (' . $this->_getWhereString($value, $field) . ') ';
        continue;
      }

      if (!is_array($value)) {
        $result[] = $this->conn->escape_string($field) . "='" . $this->conn->escape_string($value) . "'";
        continue;
      }

      if ('or' !== $operator) {
        throw new \Exception('Parameter $where not correct');
      }
      foreach ($value as $oneValue) {
        $result[] = $this->_getWhereString(array($field => $oneValue));
      }
    }
    return implode(" $operator ", $result);
  }

  /**
   * @param string $errorText
   * @throws \Exception
   */
  protected function _checkError ($errorText = "Mysql error") {
    if ($this->conn->errno) {
      $errno = $this->conn->errno;
      $error = $this->conn->error;
      throw new \Exception("$errorText($errno): $error");
    }
  }

  /**
   * @var \mysqli
   */
  protected $conn;
}

class AssignMysqlQueryBuilder extends MysqlQueryBuilder {
  /**
   * @param \mysqli $conn
   * @param $table
   */
  public function __construct ($conn, $table) {
    $this->conn = $conn;
  }

  public function select ($where, $columns = '*') {
    parent::select($this->table, $where, $columns);
  }

  public function one ($where, $columns = '*') {
    parent::one($this->table, $where, $columns);
  }

  public function insert ($fields) {
    parent::insert($this->table, $fields);
  }

  public function update ($values, $where) {
    parent::update($this->table, $values, $where);
  }

  public function del ($where) {
    parent::del($this->table, $where);
  }

  /**
   * @var \mysqli
   */
  protected $conn;

  /**
   * @var string
   */
  protected $table;
}
