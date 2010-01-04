<?php
/**
 * We made this at my job, but this is a modified version with stuff stripped
 * out.  You can replace or extend it as you like.
 */
class databaseMachine {
    var $dbLink = null;

    /* just the queries for debugging, no result caching */
    var $queries = array();

    function __construct($target = '', $singularity = false) {
        if (!$singularity) {
            trigger_error('STOP! Use: $db = databaseMachine::getDatabaseMachine();', E_USER_NOTICE);
            die();
        }
        $this->queries = array();
        $this->dbLink = getDataBaseConnection();
        if ($this->dbLink) {
            if ( !@mysql_select_db($target, $this->dbLink) ) {
                trigger_error('Could not select database target: '.$target, E_USER_NOTICE);
                @mysql_close($this->dbLink);
                $this->dbLink = null;
            }
        } else {
            trigger_error('Could not connect to database server.', E_USER_NOTICE);
        }
    }
    static function &getDatabaseMachine($target = false) {
        static $database_machines = array();
        if (empty($database_machines[$target])) {
            $database_machines[$target] = new databaseMachine($target, true);
        }
        return $database_machines[$target];
    }
    public function isValid() {
        return ($this->dbLink && is_resource($this->dbLink));
    }

    /**
     * On success, returns a resource,
     * an integer > 0 representing the auto_increment insert id, or true.
     * On failure, returns false and throws a php error.
     */
    function query($query) {
        $start = microtime();
        $result = mysql_query($query, $this->dbLink);
        $end = microtime();
        $start = array_sum(explode(' ', $start, 2));
        $end = array_sum(explode(' ', $end, 2));
        $this->queries[] = compact('query', 'start', 'end');
        if (is_resource($result)) {
            return $result;
        } else if ($result === true) {
            $id = mysql_insert_id($this->dbLink);
            if (!empty($id)) {
                return $id;
            } else {
                return true;
            }
            /* all other returns from mysql_query are failure cases */
        } else {
            if (mysql_errno($this->dbLink)) {
                $error = 'mysql error (' . mysql_errno($this->dbLink) . "): \n"
                        . mysql_error($this->dbLink) . "\n"
                        . "Query was: \n"
                        . '<pre>'
                        . $query
                        . '</pre>';
            } else {
                $error = 'query returned: ' . var_export($result, true) . "\n"
                        . "Query was: \n"
                        . '<pre>'
                        . $query
                        . '</pre>';
            }
            trigger_error($error, E_USER_NOTICE);
            return false;
        }
    }

    /**
     * On success, returns an array of rows which may be empty
     * On failure, returns false
     */
    function getRows($query, $indexColumn = false) {
        $data = false;
        $result = $this->query($query);
        if ($result && is_resource($result)) {
            $data = array();
            if ($indexColumn) {
                while ($row = mysql_fetch_assoc($result)) {
                    $data[$row[$indexColumn]] = $row;
                }
            } else {
                while ($row = mysql_fetch_assoc($result)) {
                    $data[] = $row;
                }
            }
            mysql_free_result($result);
        }
        return $data;
    }

    /**
     * On success, returns a row as an associative array or an empty array
     * if there are no results for the query
     * On failure, returns false
     */
    function getRow($query) {
        $data = false;
        $result = $this->query($query);
        if ($result && is_resource($result)) {
            if ($row = mysql_fetch_assoc($result)) {
                $data = $row;
            }
            mysql_free_result($result);
        }
        return $data;
    }

    /**
     * Returns one row from a query but doesn't close the result.  Most of the
     * time you don't want to use this, but every so often you have to consider memory
     */
    function getNextRow($result) {
        $data = false;
        if ($result && is_resource($result)) {
            if ($row = mysql_fetch_assoc($result)) {
                $data = $row;
            }
        }
        return $data;
    }

    /**
     * On success, returns an array of column values or an empty array
     * if there are no results for the query
     * On failure, returns false
     */
    function getCol($query) {
        $data = false;
        $result = $this->query($query);
        if ($result && is_resource($result)) {
            $data = array();
            while ($row = mysql_fetch_row($result)) {
                $data[] = $row[0];
            }
            mysql_free_result($result);
        }
        return $data;
    }


    /**
     * On success, returns a column value or null if there are no results for the query; however, if your column can store null, maybe this method isn't for you?
     * On failure, return false
     *
     */
    function getVal($query) {
        $data = false;
        $result = $this->query($query);
        if ($result && is_resource($result)) {
            $data = null;
            if ($row = mysql_fetch_row($result)) {
                $data = $row[0];
            }
            mysql_free_result($result);
        }
        return $data;
    }
}
