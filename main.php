<?php
if (empty($database_name)) {
    exit(1);
}

/*
 * The rest is not really intended to be edited except to fix bugs and extend
 * or add features
 */
error_reporting(E_ALL | E_STRICT);
if (!version_compare(PHP_VERSION, '5.2.0', '>=')) {
    die('I need PHP 5.2.x or higher to run. You are currently running PHP ' . PHP_VERSION . '.');
}

if (!class_exists('databaseMachine')) {
    require_once('databaseMachine.class.php');
}
require_once('stringWrapper.class.php');
require_once('gallery2Fixer.class.php');

$li_wrapper = new stringWrapper('<li>', '</li>');
$error_text = '';
$success_text = '';
$item_text = '';
$table_descriptions = array();
$table_descriptions_string = '';
$fixer = new gallery2Fixer($database_name, $table_prefix, $field_prefix, !empty($explain_queries));
$query_explanations = '';

if (isset($_GET['id_of_interest']) && (0 <= ($id_of_interest = intval($_GET['id_of_interest'])))) {
    $item_text = $fixer->interrogateItem($id_of_interest);
} else {
    $fixer->runChecks();
    if ($fixer->foreignKeyDeletes) {
	if (false && INTEGRITY_ALLOW_CHANGES) {
	    foreach ($fixer->foreignKeyDeletes as $query) {
		$fixer->databaseMachine->query($query);
		exit();
	    }
	}
    }
    if (false) {
	foreach ($fixer->tableDescriptions as $table => $description) {
	    $columns = array();
	    $table = substr($table, strlen($fixer->tablePrefix));
	    foreach ($description as $column) {
		$field = substr($column['Field'], strlen($fixer->fieldPrefix));
		$description = "$table.$field";
		if (preg_match('/Id$/i', $field)
		    && !in_array($description, $fixer->foreignKeyChecks)) {
		    $type = $column['Type'];
		    $primary = (!empty($column['Key']) && 'PRI' == $column['Key']);
		    $table_descriptions[] = $description;
		}
	    }
	}
	if (!empty($table_descriptions)) {
	    $table_descriptions_string = '<ul>'
		    . join("\n", array_map(array($li_wrapper, 'wrap_string'),
					   $table_descriptions))
		    . '</ul>';
	}
    }

    if ($fixer->foreignKeySuccesses) {
	$fixer->successes[] = 'Foreign keys okay: <ul>'
		. join("\n", array_map(array($li_wrapper, 'wrap_string'),
				       $fixer->foreignKeySuccesses))
		. '</ul>';
    }
    if ($fixer->foreignKeyFailures) {
	$fixer->errors[] = 'Foreign Key Problems: <ul>'
		. join("\n", array_map(array($li_wrapper, 'wrap_string'),
				       $fixer->foreignKeyFailures))
		. '</ul>';
    }
    if ($fixer->errors) {
	$error_text = '<ul class="errors">'
		. join("\n", array_map(array($li_wrapper, 'wrap_string'),
				       $fixer->errors))
		. '</ul>';
    }
    if ($fixer->successes) {
	$success_text = '<ul class="successes">'
		. join("\n", array_map(array($li_wrapper,
					     'wrap_string'),
				       $fixer->successes))
		. '</ul>';
    }
    if ($fixer->explainQueries) {
	$query_explanations = $fixer->getQueryExplanations();
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<title>Gallery Integrity Checking</title>

<style type="text/css">
.errors {
    color: red;
    background-color: #ffeeee;
}
.successes {
    background-color: #eeffee;
}
.dump {
 margin: 0em;
 border-collapse: collapse;
}
.dump td, .dump th {
    font-size: 10px;
 border: 1px solid #333333;
 padding: 0.5em;
 margin: 0em;
}
</style>
</head>
<body>
<?php
echo  '<h1>Gallery Integrity Checking</h1>';
if ($item_text) {
    echo $item_text;
}
if ($error_text || $success_text || $table_descriptions_string) {
    echo  '<h2>General statistics</h2>';
    echo  '<dl>';
    if ($error_text) {
	echo  '<dt>Errors</dt>';
	echo  '<dd>' . $error_text . '</dd>';
    }
    if ($success_text) {
	echo  '<dt>Successes</dt>';
	echo  '<dd>' . $success_text . '</dd>';
    }
    if ($table_descriptions_string) {
	echo  '<dt>Possible foreign keys to check</dt>';
	echo  '<dd>' . $table_descriptions_string . '</dd>';
    }
    if ($query_explanations) {
	echo '<dt>Queries</dt>';
	echo '<dd>' . $query_explanations . '</dt>';
    }
    echo  '</dl>';
}
?>
</body>
</html>