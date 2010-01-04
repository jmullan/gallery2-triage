<?php
/*
 * TODO:
 * move to gallery2/lib/support/
 * use adodb / g2's db api
 * 
 */
define('INTEGRITY_ALLOW_CHANGES', false);
/* You are responsible for these parts */
$database_name = 'db2276_gallery2';
$table_prefix = 'g2_';
$field_prefix = 'g_';
$explain_queries = true;

/*
 * I have a function named like this included into every page.  You might not,
 * so you can use this one.
 */
if (!function_exists('getDataBaseConnection')) {
    $GLOBALS['INTEGRITY']['db_server'] = 'localhost';
    $GLOBALS['INTEGRITY']['db_username'] = '';
    $GLOBALS['INTEGRITY']['db_password'] = '';
    

    function getDataBaseConnection($newConnection = false) {
	return @mysql_pconnect($GLOBALS['INTEGRITY']['db_server'],
			       $GLOBALS['INTEGRITY']['db_username'],
			       $GLOBALS['INTEGRITY']['db_password'],
			       $newConnection);
    }
}
require('main.php');