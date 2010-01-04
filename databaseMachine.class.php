<?php
require_once('databaseConnection.php');
/**
 * $Revision: 1.7 $
 * $Date: 2007/09/19 20:33:05 $
 */

class databaseMachine {
    /* just the queries for debugging, no result caching */
    static private $_queries = array();
    static private $_instances = array();
    static private $_query_index = 0;
    private $_target;
    private $_dbLink;
    private $_logging = true;
    public function __construct($target, $singularity = false) {
        if (!$singularity) {
            trigger_error('STOP! Use: $db = databaseMachine::getDatabaseMachine();', E_USER_NOTICE);
            exit(1);
        }
        $this->_target = $target;
        if (!isset($this->_dbLink)) {
            $this->_dbLink = getDatabaseConnection();
        }
        if ($this->_dbLink) {
            if (!@mysql_select_db($this->_target, $this->_dbLink)) {
                trigger_error('Could not select database target: ' . $this->_target, E_USER_NOTICE);
                @mysql_close($this->_dbLink);
                $this->_dbLink = null;
            }
        } else {
            trigger_error('Could not connect to database server.', E_USER_NOTICE);
        }
    }

    /**
     * Static Method
     * Don't forget to assign it by reference
     * $db = databaseMachine::getDatabaseMachine();
     */
    static function getDatabaseMachine($target = false) {
        if (!$target || !is_string($target)) {
            trigger_error('No target was specified when getting the database machine',
                          E_USER_NOTICE);
            exit(1);
        }
        if (empty(self::$_instances[$target])) {
            self::$_instances[$target] = new databaseMachine($target, true);
            if (!self::$_instances[$target]->isValid()) {
                unset(self::$_instances[$target]);
                return false;
            }
        }
        return self::$_instances[$target];
    }

    public function isValid() {
        return $this->_target && $this->_dbLink;
    }

    public function setLogging($now_logging) {
        $was_logging = $this->_logging;
        $this->_logging = $now_logging;
        return $was_logging;
    }
    /**
     * On success, returns a resource, an integer > 0 representing the auto_increment insert id, or true.
     * On failure, returns false and throws a php error.
     */
    public function query($query) {
        $start = microtime();
        $result = mysql_query($query, $this->_dbLink);
        self::$_query_index++;
        $end = microtime();
        $start = array_sum(explode(' ', $start, 2));
        $end = array_sum(explode(' ', $end, 2));
        if ($this->_logging) {
            self::$_queries[self::$_query_index] = array();
            self::$_queries[self::$_query_index][] = array('target' => $this->_target,
                                                           'query' => $query,
                                                           'start' => $start,
                                                           'end' => $end,
                                                           'explain' => true);
        }
        if (is_resource($result)) {
            return $result;
        } else if ($result === true) {
            $id = mysql_insert_id($this->_dbLink);
            if (!empty($id)) {
                return $id;
            } else {
                $affected_rows = mysql_affected_rows($this->_dbLink);
                if ($affected_rows) {
                    return $affected_rows;
                } else {
                    return true;
                }
            }
            /* all other returns from mysql_query are failure cases */
        } else {
            if (mysql_errno($this->_dbLink)) {
                $error = 'mysql error (' . mysql_errno($this->_dbLink) . "): \n"
                        . mysql_error($this->_dbLink) . "\n"
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

    static public function fetchAssoc($resource) {
        return mysql_fetch_assoc($resource);
    }


    static public function numRows($resource) {
        return mysql_num_rows($resource);
    }


    /**
     * On success, returns an array of rows which may be empty
     * On failure, returns false
     */
    public function getRows($query, $indexColumn = false) {
        $start = microtime();
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
        $end = microtime();
        $start = array_sum(explode(' ', $start, 2));
        $end = array_sum(explode(' ', $end, 2));
        if ($this->_logging) {
            self::$_queries[self::$_query_index][] = array('target' => $this->_target,
                                                           'query' => $query,
                                                           'start' => $start,
                                                           'end' => $end);
        }
        return $data;
    }

    /**
     * On success, returns a row as an associative array or an empty array if there are no results for the query
     * On failure, returns false
     */
    public function getRow($query) {
        $start = microtime();
        $data = false;
        $result = $this->query($query);
        if ($result && is_resource($result)) {
            $data = array();
            if ($row = $this->fetchAssoc($result)) {
                $data = $row;
            }
            mysql_free_result($result);
        }
        $end = microtime();
        $start = array_sum(explode(' ', $start, 2));
        $end = array_sum(explode(' ', $end, 2));
        if ($this->_logging) {
            self::$_queries[self::$_query_index][] = array('target' => $this->_target,
                                                           'query' => $query,
                                                           'start' => $start,
                                                           'end' => $end);
        }
        return $data;
    }

    /**
     * Returns one row from a query but doesn't close the result.  Most of the
     * time you don't want to use this, but every so often you have to consider
     * memory
     */
    static public function getNextRow($result) {
        $data = false;
        if ($result && is_resource($result)) {
            if ($row = mysql_fetch_assoc($result)) {
                $data = $row;
            }
        }
        return $data;
    }

    /**
     * On success, returns an array of column values or an empty array if there are no results for the query
     * On failure, returns false
     */
    public function getCol($query) {
        $start = microtime();
        $data = false;
        $result = $this->query($query);
        if ($result && is_resource($result)) {
            $data = array();
            while ($row = mysql_fetch_row($result)) {
                $data[] = $row[0];
            }
            mysql_free_result($result);
        }
        $end = microtime();
        $start = array_sum(explode(' ', $start, 2));
        $end = array_sum(explode(' ', $end, 2));
        if ($this->_logging) {
            self::$_queries[self::$_query_index][] = array('target' => $this->_target,
                                                           'query' => $query,
                                                           'start' => $start,
                                                           'end' => $end);
        }
        return $data;
    }


    /**
     * On success, returns a column value or null if there are no results for the query; however, if your column can store null, maybe this method isn't for you?
     * On failure, return false
     *
     */
    public function getVal($query) {
        $start = microtime();
        $data = false;
        $result = $this->query($query);
        if ($result && is_resource($result)) {
            $data = null;
            if ($row = mysql_fetch_row($result)) {
                $data = $row[0];
            }
            mysql_free_result($result);
        }
        $end = microtime();
        $start = array_sum(explode(' ', $start, 2));
        $end = array_sum(explode(' ', $end, 2));
        if ($this->_logging) {
            self::$_queries[self::$_query_index][] = array('target' => $this->_target,
                                                           'query' => $query,
                                                           'start' => $start,
                                                           'end' => $end);
        }
        return $data;
    }

    public function escape($value) {
        if (is_string($value)) {
            return mysql_real_escape_string($value, $this->_dbLink);
        } else if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->escape($val);
            }
        }
        return $value;
    }

    public function report() {
        $output = '';
        if (is_jesse()) {
            if (self::$_queries) {
                $table = array();
                $table[] = '<h1>Queries Run</h1>';
                $table[] = '<table class="queryDump">';
                $table[] = '<thead>';
                $table[] = '<tr>';
                $table[] = '<th>Id</th>';
                $table[] = '<th>Time<br />(seconds)</th>';
                $table[] = '<th>Server</th>';
                $table[] = '<th>Query</th>';
                /* time, target, query */
                $table[] = '</tr>';
                $table[] = '</thead>';
                $total_time = 0;
                $query_count = 0;
                foreach (self::$_queries as $query_id => $logs) {
                    $query_count++;
                    $max_time = 0;
                    foreach ($logs as $data) {
                        $explanation = '';
                        $target = $data['target'];
                        $query = $data['query'];
                        if (!empty($data['explain'])
                            && !preg_match('/\s*explain /i', $query)
			    && !preg_match('/\s*show/i', $query)) {
                            $dbm = self::getDatabaseMachine($target);
                            $explanation_query = 'EXPLAIN EXTENDED ' . $query;
                            $was_logging = $dbm->setLogging(false);
                            $explanation = $dbm->getRows($explanation_query);
                            $dbm->setLogging($was_logging);
                        }
                        $time = round($data['end'] - $data['start'], 4);
                        $max_time = max($max_time, $time);
                        $table[] = '<tr>';
                        $table[] = '<td class="numeric" style="vertical-align: top">';
                        $table[] = $query_id;
                        $table[] = '</td>';
                        $table[] = '<td class="numeric" style="vertical-align: top">';
                        $table[] = $time;
                        $table[] = '</td>';
                        $table[] = '<td style="vertical-align: top">';
                        $table[] = $data['target'];
                        $table[] = '</td>';
                        $table[] = '<td style="vertical-align: top"><pre>';
                        $table[] = $data['query'];
                        if ($explanation) {
                            $table[] = "\n\n";
                            $table[] = varDump($explanation, '$explanation', false);
                        }
                        $table[] = '</pre>';
                        $table[] = '</td>';
                        $table[] = '</tr>';
                    }
                    $total_time += $max_time;
                }
                if ($query_count) {
                    $table[] = '<tr>';
                    $table[] = '<td colspan="3">';
                    $table[] = '<p>';
                    $table[] = $query_count;
                    if (1 == $query_count) {
                        $table[] = ' query';
                    } else {
                        $table[] = ' queries';
                    }
                    $table[] = '</p>';
                    $table[] = '<p>';
                    $table[] = $total_time;
                    $table[] = ' total second(s) of query time';
                    $table[] = '</p>';
                    $table[] = '<p>';
                    $table[] = round($total_time / $query_count, 4);
                    $table[] = ' average second(s) per query';
                    $table[] = '</p>';
                    $table[] = '</td>';
                    $table[] = '</tr>';
                }
                $table[] = '</table>';
                $output .= join("\n", $table);
            } else {
                $output .= '<p>No queries were run.</p>';
            }
        }
        return $output;
    }

}
