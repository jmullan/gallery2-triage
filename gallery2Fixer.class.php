<?php
require_once('miscHelper.class.php');
class gallery2Fixer {
    var $databaseName;
    var $tablePrefix;
    var $fieldPrefix;
    var $databaseMachine;
    var $entityTypes;
    var $tablesSetUp;
    var $tables;
    var $tableDescriptions;
    var $tablesWithEntityIds;
    var $tablesWithGroupIds;
    var $tablesWithUserIds;
    var $tablesWithUserOrGroupIds;
    var $tablesWithItemIds;
    var $foreignKeysSetup;
    var $pluginParameters;
    var $autoForeignKeys;
    var $forcedForeignKeys;
    var $foreignKeyChecks;
    var $foreignKeyFailures;
    var $foreignKeySuccesses;
    var $foreignKeyDeletes;
    var $schemaParents;
    var $successes;
    var $errors;
    var $explainQueries;
    
    function __construct($databaseName, $table_prefix, $field_prefix, $explain_queries = false) {
	$this->databaseName = $databaseName;
	$this->tablePrefix = $table_prefix;
	$this->fieldPrefix = $field_prefix;
	$this->databaseMachine = databaseMachine::getDatabaseMachine($this->databaseName);
	if (!$this->databaseMachine->isValid()) {
	    trigger_error('Could not get database connection', E_USER_NOTICE);
	    die();
	}
	
	$this->explainQueries = $explain_queries;
	$this->databaseMachine->setLogging($this->explainQueries);
	
	$this->tablesSetUp = false;
	$this->tables = array();
	$this->tableDescriptions = array();
	$this->tablesWithEntityIds = array();
	$this->tablesWithGroupIds = array();
	$this->tablesWithUserIds = array();
	$this->tablesWithUserOrGroupIds = array();
	$this->tablesWithItemIds = array();
	$this->pluginParametersSetUp = false;
	$this->pluginParameters = array();
	$this->foreignKeysSetup = false;
	$this->autoForeignKeys = array();
	$this->forcedForeignKeys = array();
	$this->foreignKeyChecks = array();
	$this->foreignKeyFailures = array();
	$this->foreignKeySuccesses = array();
	$this->foreignKeyDeletes = array();
	$this->schemaParents = array();
	$this->successes = array();
	$this->errors = array();
	$this->setupTables();
	$this->setUpPluginParameters();
	$this->setUpEntityTypes();
	$this->setUpSchemas();
	$this->setUpForeignKeys();
    }
    function setupTables() {
	$query_tables = "SHOW TABLES";
	$this->tables = $this->databaseMachine->getCol($query_tables);
	foreach ($this->tables as $table) {
	    if ($this->tablePrefix == substr($table, 0, strlen($this->tablePrefix))
		&& "{$this->tablePrefix}EventLogMap" != $table
		&& "{$this->tablePrefix}PluginParameterMap" != $table) {
		$query_describe = "SHOW COLUMNS FROM $table";
		$this->tableDescriptions[$table]
			= $this->databaseMachine->getRows($query_describe);
		foreach ($this->tableDescriptions[$table] as $d) {
		    if ($this->fieldPrefix . 'id' == $d['Field']) {
			$this->tablesWithEntityIds[] = $table;
		    } elseif ($this->fieldPrefix . 'userId' == $d['Field']) {
			$this->tablesWithUserIds[] = $table;
		    } elseif ($this->fieldPrefix . 'itemId' == $d['Field']) {
			$this->tablesWithItemIds[] = $table;
		    } elseif ($this->fieldPrefix . 'groupId' == $d['Field']) {
			$this->tablesWithGroupIds[] = $table;
		    } elseif ($this->fieldPrefix . 'userOrGroupId' == $d['Field']) {
			$this->tablesWithUserOrGroupIds[] = $table;
		    }
		}
	    }
	}
	$this->tablesSetUp = true;
    }
    function setUpSchemas() {
	$query = '# ' . __LINE__ . "
SELECT
    `{$this->fieldPrefix}name` AS `_name`,
    `{$this->fieldPrefix}info` AS `_info`
FROM
    `{$this->tablePrefix}Schema`
WHERE
    `{$this->fieldPrefix}type` = 'entity'
";
	$rows = $this->databaseMachine->getRows($query);
	foreach ($rows as $row) {
	    $name = $row['_name'];
	    $raw_data = $row['_info'];
	    if ($raw_data) {
		$cooked_data = unserialize($raw_data);
		$schema = array_pop($cooked_data);
		$parent = str_replace('Gallery', '', $schema['parent']);
		if ($parent) {
		    $this->schemaParents[$name] = $parent;
		}
	    }
	}
    }
    function setUpPluginParameters() {
	$plugin_parameters_query = '# ' . __LINE__ . "
SELECT
    *
FROM
    `{$this->tablePrefix}PluginParameterMap`
";
	$plugin_parameters_raw = $this->databaseMachine->getRows($plugin_parameters_query);
	$plugin_parameter_tree = array("{$this->fieldPrefix}pluginType",
				       "{$this->fieldPrefix}pluginId");
	foreach ($plugin_parameters_raw as $row) {
	    $tree =& $this->pluginParameters;
	    foreach ($plugin_parameter_tree as $step) {
		$level = $row[$step];
		if (!isset($tree[$level])) {
		    $tree[$level] = array();
		}
		$tree =& $tree[$level];
	    }
	    $name = $row["{$this->fieldPrefix}parameterName"];
	    $tree[$name] = $row["{$this->fieldPrefix}parameterValue"];
	}
    }
    function setUpEntityTypes() {
	$entity_types_query = '# ' . __LINE__ . "
SELECT DISTINCT
    `{$this->fieldPrefix}entityType`
FROM
    `{$this->tablePrefix}Entity`
ORDER BY
    `{$this->fieldPrefix}entityType` ASC
";
	$this->entityTypes = $this->databaseMachine->getCol($entity_types_query);
    }
    function setupForeignKeys() {
	if (!$this->tablesSetUp) {
	    $this->setupTables();
	}
	$this->autoForeignKeys = array();
	$this->forcedForeignKeys = array(array('AccessSubscriberMap.accessListId',
					       'AccessMap.accessListId'),
					 
					 array('AlbumItem.theme', 'PluginMap.pluginId'),
					 array('ChildEntity.parentId', 'Entity.id'),
					 array('ChildEntity.parentId', 'Item.id'),
					 array('Comment.commenterId', 'User.id'),
					 array('Derivative.derivativeSourceId', 'Entity.id'),
					 array('Entity.linkId', 'Entity.id'),
					 array('ExternalIdMap.entityId', 'Entity.id'),
					 array('FileSystemEntity.id', 'ChildEntity.id'),
					 array('Item.ownerId', 'User.id'),
					 array('Lock.readEntityId', 'Entity.id'),
					 array('Lock.writeEntityId', 'Entity.id'),
					 array('PermalinksMap.destId', 'Entity.id'),
					 array('RssMap.ownerId', 'User.id'),
					 array('WatermarkImage.ownerId', 'User.id'));
	foreach ($this->tablesWithEntityIds as $table) {
	    $table = substr($table, strlen($this->tablePrefix));
	    if (!in_array($table, array('SessionMap', 'EventLogMap'))) {
		$this->autoForeignKeys[] = array("{$table}.id", 'Entity.id');
	    }
	}
	foreach ($this->tablesWithGroupIds as $table) {
	    $table = substr($table, strlen($this->tablePrefix));
	    $this->autoForeignKeys[] = array("{$table}.groupId", 'Group.id');
	}
	foreach ($this->tablesWithUserIds as $table) {
	    $table = substr($table, strlen($this->tablePrefix));
	    $this->autoForeignKeys[] = array("{$table}.userId", 'User.id');
	}
	foreach ($this->tablesWithItemIds as $table) {
	    $table = substr($table, strlen($this->tablePrefix));
	    $this->autoForeignKeys[] = array("{$table}.itemId", 'Item.id');
	}
	foreach ($this->schemaParents as $from => $to) {
	    $this->autoForeignKeys[] = array($from . '.id', $to . '.id');
	}
    }
    function checkForeignKeys() {
	if (!$this->foreignKeysSetup) {
	    $this->setupForeignKeys();
	}
	$success = true;
	$checked_relationships = array();
	foreach (array($this->autoForeignKeys, $this->forcedForeignKeys) as $foreign_keys) {
	    foreach ($foreign_keys as $relationship) {
		if (!in_array($relationship, $checked_relationships)) {
		    $here = $relationship[0];
		    $there = $relationship[1];
		    $checked_relationships[] = $relationship;
		    $this->foreignKeyChecks[] = $here;
		    list($here_table, $here_field) = explode('.', $here, 2);
		    list($there_table, $there_field) = explode('.', $there, 2);
		    if (in_array($this->tablePrefix . $here_table, $this->tables)
			&& in_array($this->tablePrefix . $there_table, $this->tables)) {
			$query_checker = '# ' . __LINE__ . "
SELECT DISTINCT
    `here`.`{$this->fieldPrefix}{$here_field}`
FROM
    `{$this->tablePrefix}{$here_table}` AS `here`
LEFT JOIN
    `{$this->tablePrefix}{$there_table}` AS `there`
    ON `here`.`{$this->fieldPrefix}{$here_field}`
        = `there`.`{$this->fieldPrefix}{$there_field}`
WHERE
    `here`.`{$this->fieldPrefix}{$here_field}` IS NOT NULL
    AND `there`.`{$this->fieldPrefix}{$there_field}` IS NULL
    AND `here`.`{$this->fieldPrefix}{$here_field}` <> 0
ORDER BY
    `here`.`{$this->fieldPrefix}{$here_field}` ASC

";
			if ($fails = $this->databaseMachine->getCol($query_checker)) {
			    $success = false;
			    $this->foreignKeyFailures[] = "$here not found in $there: "
				    . count($fails)
				    . " failures ("
				    . miscHelper::intArrayToRanges($fails) . ')';
			    $this->foreignKeyDeletes["$here to $there"] = '# ' . __LINE__ . "
DELETE FROM
    `{$this->tablePrefix}{$here_table}`
WHERE
    `{$this->tablePrefix}{$here_table}`.`{$this->fieldPrefix}{$here_field}` IN (
" . join(',', $fails) . "
)";
			} else {
			    $this->foreignKeySuccesses[] = "$here to $there";
			}
		    }
		}
	    }
	}
	foreach ($this->tablesWithUserOrGroupIds as $table) {
	    $here_table = substr($table, strlen($this->tablePrefix));
	    $here_field = 'userOrGroupId';
	    $here = $here_table . '.' . $here_field;
	    $this->foreignKeyChecks[] = $here;
	    $there_field = 'id';
	    $there = 'User.id OR Group.id';
	    $query_checker = '# ' . __LINE__ . "
SELECT DISTINCT
    `here`.`{$this->fieldPrefix}{$here_field}`
FROM
    `{$this->tablePrefix}{$here_table}` AS `here`
LEFT JOIN
    `{$this->tablePrefix}User` AS `users`
    ON `here`.`{$this->fieldPrefix}{$here_field}`
        = `users`.`{$this->fieldPrefix}{$there_field}`
LEFT JOIN
    `{$this->tablePrefix}Group` AS `groups`
    ON `here`.`{$this->fieldPrefix}{$here_field}`
        = `groups`.`{$this->fieldPrefix}{$there_field}`
WHERE
    `here`.`{$this->fieldPrefix}{$here_field}` IS NOT NULL
    AND `users`.`{$this->fieldPrefix}{$there_field}` IS NULL
    AND `groups`.`{$this->fieldPrefix}{$there_field}` IS NULL
    AND `here`.`{$this->fieldPrefix}{$here_field}` <> 0
ORDER BY
    `here`.`{$this->fieldPrefix}{$here_field}` ASC
";
	    if ($fails = $this->databaseMachine->getCol($query_checker)) {
		$success = false;
		$this->foreignKeyFailures[] = "$here not found in $there: "
			. count($fails)
			. " ("
			. miscHelper::intArrayToRanges($fails) . ')';
		$this->foreignKeyDeletes["$here to $there"] = '# ' . __LINE__ . "
DELETE FROM
    `{$this->tablePrefix}{$here_table}`
WHERE
    `{$this->tablePrefix}{$here_table}`.`{$this->fieldPrefix}{$here_field}` IN (
" . join(',', $fails) . "
)";
	    } else {
		$this->foreignKeySuccesses[] = "$here to $there";
	    }
	    $this->autoForeignKeys[] = array("{$table}.userOrGroupId", 'User.id');
	}
	sort($this->foreignKeySuccesses);
	sort($this->foreignKeyFailures);
    }
    function checkEntityTypes() {
	$types = array('GalleryAlbumItem',
		       'GalleryComment',
		       'GalleryDerivativeImage',
		       'GalleryGroup',
		       'GalleryMovieItem',
		       'GalleryPhotoItem',
		       'GalleryUnknownItem',
		       'GalleryUser',
		       'ThumbnailImage',
		       'WatermarkImage');
	foreach ($types as $type) {
	    $table = str_replace('Gallery', '', $type);
	    $query = '# ' . __LINE__ . "
SELECT
    `{$this->tablePrefix}$table`.`{$this->fieldPrefix}id`
FROM
    `{$this->tablePrefix}$table`
LEFT JOIN
    `{$this->tablePrefix}Entity`
    ON `{$this->tablePrefix}$table`.`{$this->fieldPrefix}id`
       = `{$this->tablePrefix}Entity`.`{$this->fieldPrefix}id`
WHERE
    `{$this->tablePrefix}Entity`.`{$this->fieldPrefix}EntityType` <> '$type'
";
	    $broken_results = $this->databaseMachine->getCol($query);
	    if ($broken_results) {
		$this->errors[] = "Found broken {$type}s: " . join(',', $broken_results);
	    } else {
		$this->successes[] = "Checked {$type}s";
	    }
	}
    }
    function checkTree() {
	$query = '# ' . __LINE__ . "
SELECT DISTINCT
    `Relationship`.`{$this->fieldPrefix}parentId` AS `RelationshipParentId`,
    `Relationship`.`{$this->fieldPrefix}id` AS `RelationshipChildId`,
    `Father`.`{$this->fieldPrefix}entityType` AS `FatherType`,
    `Child`.`{$this->fieldPrefix}entityType` AS `ChildType`
FROM
    `{$this->tablePrefix}ChildEntity` AS `Relationship`
LEFT JOIN
    `{$this->tablePrefix}Item` AS `Mother`
    ON `Relationship`.`{$this->fieldPrefix}parentId`
        = `Mother`.`{$this->fieldPrefix}id`
LEFT JOIN
    `{$this->tablePrefix}Entity` AS `Father`
    ON `Relationship`.`{$this->fieldPrefix}parentId`
        = `Father`.`{$this->fieldPrefix}id`
LEFT JOIN
    `{$this->tablePrefix}Entity` AS `Child`
    ON `Relationship`.`{$this->fieldPrefix}id`
        = `Child`.`{$this->fieldPrefix}id`
WHERE
    `Mother`.`{$this->fieldPrefix}canContainChildren` = 0
    AND `Father`.`{$this->fieldPrefix}entityType` NOT IN(
       'GalleryPhotoItem',
       'GalleryMovieItem',
       'GalleryUnknownItem'
    )
    AND `Child`.`{$this->fieldPrefix}entityType` <> 'GalleryDerivativeItem'
ORDER BY
    `RelationshipParentId` ASC
";
	$bad_parents = $this->databaseMachine->getRows($query);
	if ($bad_parents) {
	    $bad_parent_lines = array();
	    foreach ($bad_parents as $row) {
		$bad_parent_lines[] = join(':', $row);
	    }
	    $this->errors[] = 'Found bad parents: '
		    . join('<br />', $bad_parent_lines);
	} else {
	    $this->successes[] = 'All parents are reasonable entities';
	}

	$query = '# ' . __LINE__ . "
SELECT DISTINCT
    `Relationship`.`{$this->fieldPrefix}id` AS `RelationshipChildId`
FROM
    `{$this->tablePrefix}ChildEntity` AS `Relationship`
LEFT JOIN
    `{$this->tablePrefix}Item` AS `Mother`
    ON `Relationship`.`{$this->fieldPrefix}id` = `Mother`.`{$this->fieldPrefix}id`
LEFT JOIN
    `{$this->tablePrefix}Derivative` AS `Father`
    ON `Relationship`.`{$this->fieldPrefix}id` = `Father`.`{$this->fieldPrefix}id`
WHERE (
    `Mother`.`{$this->fieldPrefix}id` IS NULL
    AND `Father`.`{$this->fieldPrefix}id` IS NULL
)
ORDER BY
    `Relationship`.`{$this->fieldPrefix}parentId` ASC
";
	$bad_children = $this->databaseMachine->getCol($query);
	if ($bad_children) {
	    $this->errors[] = 'Found bad Children (NULL parents): '
		    . join('<br />', $bad_children);
	} else {
	    $this->successes[] = 'All children are items or derivatives';
	}
	$query = '# ' . __LINE__ . "
SELECT DISTINCT
    `Relationship`.`{$this->fieldPrefix}id` AS `RelationshipChildId`
FROM
    `{$this->tablePrefix}ChildEntity` AS `Relationship`
WHERE
    `Relationship`.`{$this->fieldPrefix}id` = 0
";
	$bad_children = $this->databaseMachine->getCol($query);
	if ($bad_children) {
	    $this->errors[] = 'Found bad Children (ids not above zero): '
		    . join('<br />', $bad_children);
	} else {
	    $this->successes[] = 'All children have ids above zero';
	}
	$query = '# ' . __LINE__ . "
SELECT
    `Relationship`.`{$this->fieldPrefix}id` AS `child_id`
FROM
    `{$this->tablePrefix}ChildEntity` AS `Relationship`
GROUP BY
    `Relationship`.`{$this->fieldPrefix}id`
HAVING
    count(`Relationship`.`{$this->fieldPrefix}id`) > 1
";
	$duplicate_child_ids = $this->databaseMachine->getCol($query);
	if ($duplicate_child_ids) {
	    $this->errors[] = 'Duplicate child ids: '
		    . miscHelper::intArrayToRanges($duplicate_child_ids);
	} else {
	    $this->successes[] = 'No duplicate child ids';
	}
	$query = '# ' . __LINE__ . "
SELECT
    `Relationship`.`{$this->fieldPrefix}id`
FROM
    `{$this->tablePrefix}ChildEntity` AS `Relationship`
WHERE
    `Relationship`.`{$this->fieldPrefix}id` = `Relationship`.`{$this->fieldPrefix}parentId`
";
	$self_referentials = $this->databaseMachine->getCol($query);
	if ($self_referentials) {
	    $this->errors[] = 'Children who are their own parents: '
		    . miscHelper::intArrayToRanges($self_referentials);
	} else {
	    $this->successes[] = 'No children are their own parents';
	}
	$query = '# ' . __LINE__ . "
SELECT
    `Relationship`.`{$this->fieldPrefix}parentId` AS `parent`,
    `Relationship`.`{$this->fieldPrefix}id` AS `child`
FROM
    `{$this->tablePrefix}ChildEntity` AS `Relationship`
WHERE
    `Relationship`.`{$this->fieldPrefix}id` <> `Relationship`.`{$this->fieldPrefix}parentId`
ORDER BY
    `parent` ASC,
    `child` ASC
";
	$relationships = $this->databaseMachine->query($query);
	if (!$relationships) {
	    $this->errors[] = 'Could not look for relationships';
	}
	$roots = array();
	$found_cycle = false;
	while (!$found_cycle
	       && $relationships
	       && $row = $this->databaseMachine->getNextRow($relationships)) {
	    $parent = $row['parent'];
	    $child = $row['child'];
	    if (isset($roots[$parent])) {
		$roots[$child] =& $roots[$parent];
	    } else {
		if (isset($roots[$child])) {
		    $roots[$child] = $parent;
		    $roots[$parent] =& $roots[$child];
		} else {
		    $roots[$parent] = $parent;
		    $roots[$child] =& $roots[$parent];
		}
	    }
	    if ($roots[$parent] == $child) {
		$found_cycle = true;
		$this->errors[] = 'Found cycle with id: ' . $child;
	    }
	}
	if (!$found_cycle) {
	    $this->successes[] = 'No cycles found';
	}
    }
    function fixSequence() {
	$check_sequence = true;
	$found_errors = false;
	$sequence_limit = 5;
	while ($check_sequence && (0 < $sequence_limit--)) {
	    $check_sequence = false;
	    $query1 = '# ' . __LINE__ . "
SELECT
    count(`id`) AS `how_many`,
    max(`id`) AS `next_id`
FROM
    `{$this->tablePrefix}SequenceId`
";
	    $data = $this->databaseMachine->getRow($query1);
	    $how_many = intval($data['how_many']);
	    $next_id = intval($data['next_id']);
	    $query2 = '# ' . __LINE__ . "
SELECT
    max(`{$this->fieldPrefix}id`)
FROM
    `{$this->tablePrefix}Entity`
";
	    $max_id = intval($this->databaseMachine->getVal($query2));
	    $new_id = $max_id + 1;
	    $fixed_sequence_count = 0;
	    if (1 < $how_many) {
		$this->errors[] = "Found $how_many in sequence table";
		$found_errors = true;
		if (INTEGRITY_ALLOW_CHANGES) {
		    $fix_limit = $how_many - 1;
		    $fix_sequence_query = '# ' . __LINE__ . "
DELETE FROM
    `{$this->tablePrefix}SequenceId`
WHERE
    `id` <= $next_id
LIMIT
    `$fix_limit`
";
		    $fixed_sequence_count = $this->databaseMachine->query($fix_sequence_query);
		    $check_sequence = true;
		}
	    }
	    if (0 == $how_many) {
		$this->errors[] = 'No sequence id was found in sequence table';
		$fix_sequence_query = '# ' . __LINE__ . "
INSERT INTO
    `{$this->tablePrefix}SequenceId`
VALUES
    `id` = $new_id
";
		$fixed_sequence_count = $this->databaseMachine->query($fix_sequence_query);
		$check_sequence = true;
		$found_errors = true;
	    }
	    if ($next_id < $max_id && 0 < $how_many) {
		$this->errors[] = 'Sequence id was lower than max id:'
			. $next_id . ' versus ' . $max_id;
		$found_errors = true;
		if (INTEGRITY_ALLOW_CHANGES) {
		    $fix_sequence_query = '# ' . __LINE__ . "
UPDATE
    `{$this->tablePrefix}SequenceId`
SET
    `id` = $new_id
WHERE
    `id` < $new_id
";
		    $fixed_sequence_count = $this->databaseMachine->query($fix_sequence_query);
		    $check_sequence = true;
		}
	    }
	}
	if (1 > $sequence_limit) {
	    $this->errors[] = 'Ran out of sequence fixing steps';
	}
	if (!$found_errors) {
	    $this->successes[] = 'Sequence ids';
	}
    }
    function findSuspiciousIds($timestamp = null, $threshold_id = null) {
	$query3 = '# ' . __LINE__ . "
SELECT
    `{$this->fieldPrefix}id`
FROM
    `{$this->tablePrefix}Entity`
WHERE
    `{$this->fieldPrefix}creationTimestamp` >= $timestamp
    AND `{$this->fieldPrefix}id` < $threshold_id
ORDER BY
    `{$this->fieldPrefix}id` ASC
";
	$overwritten_ids_Entity = array_map('intval', $this->databaseMachine->getCol($query3));
	$query4 = '# ' . __LINE__ . "
SELECT
    `{$this->fieldPrefix}id`
FROM
    `{$this->tablePrefix}Item`
WHERE
    `{$this->fieldPrefix}viewedSinceTimestamp` >= 1191218472
    AND `{$this->fieldPrefix}id < $threshold_id`
ORDER BY
    `{$this->fieldPrefix}id` ASC
";
	$overwritten_ids_Item = array_map('intval', $this->databaseMachine->getCol($query4));


	$overwritten_ids = array_unique(array_merge($overwritten_ids_Entity,
						    $overwritten_ids_Item));
	sort($overwritten_ids);
    }
    function checkRootId() {
	if (empty($this->pluginParameters['module']['core']['id.rootAlbum'])) {
	    $this->errors[] = 'No root album id is set in the plugin parameters';
	    return;
	} else {
	    $expected_root_id = intval($this->pluginParameters['module']['core']['id.rootAlbum']);
	}
	$query = '# ' . __LINE__ . "
SELECT
    `{$this->tablePrefix}ChildEntity`.`{$this->fieldPrefix}id`
FROM
    `{$this->tablePrefix}ChildEntity`
LEFT JOIN
    `{$this->tablePrefix}FileSystemEntity`
    ON `{$this->tablePrefix}ChildEntity`.`{$this->fieldPrefix}id`
       = `{$this->tablePrefix}FileSystemEntity`.`{$this->fieldPrefix}id`
WHERE
    `{$this->tablePrefix}ChildEntity`.`{$this->fieldPrefix}id` IS NOT NULL
    AND `{$this->tablePrefix}ChildEntity`.`{$this->fieldPrefix}parentId` IS NOT NULL
    AND `{$this->tablePrefix}ChildEntity`.`{$this->fieldPrefix}parentId` = 0
ORDER BY
    `{$this->tablePrefix}ChildEntity`.`{$this->fieldPrefix}id` ASC
";
	$root_ids = $this->databaseMachine->getCol($query);
	$root_id_count = count($root_ids);
	if (empty($root_ids)) {
	    $this->errors[] = 'No root id found in the entity and filesystementity tables';
	} elseif (1 < count($root_ids)) {
	    $this->errors[] = 'Too many root ids found in the entity and filesystementity tables: "'
		    . miscHelper::intArrayToRanges($root_ids)
		    . '" instead of "'
		    . $expected_root_id . '"';
	} elseif (1 == count($root_ids)) {
	    $test_id = intval(array_pop($root_ids));
	    if ($test_id != $expected_root_id) {
		$this->errors[] = 'Wrong root id found in the entity and filesystementity tables: "'
			. $test_id
			. '" instead of "'
			. $expected_root_id
			. '"';
	    }
	}
    }
    function checkForMissingDerivatives() {
	$query = '# ' . __LINE__ . "
SELECT
    `Images`.`{$this->fieldPrefix}id`
FROM
    `{$this->tablePrefix}PhotoItem` AS `Images`
LEFT JOIN
    `{$this->tablePrefix}Derivative` AS `Derivatives`
    ON `Derivatives`.`{$this->fieldPrefix}derivativeSourceId` = `Images`.`{$this->fieldPrefix}id`
LEFT JOIN
    `{$this->tablePrefix}DerivativePrefsMap` AS `Prefs`
    ON `Prefs`.`{$this->fieldPrefix}itemId` = `Images`.`{$this->fieldPrefix}id`
WHERE
    `Derivatives`.`{$this->fieldPrefix}id` IS NULL
    AND `Prefs`.`{$this->fieldPrefix}itemId` IS NOT NULL
";
	$missingDerivatives = $this->databaseMachine->getCol($query);
	if ($missingDerivatives) {
	    $this->errors[] = 'No derivatives found for items: ('
		    . miscHelper::intArrayToRanges($missingDerivatives);
	} else {
	    $this->successes[] = 'Derivatives found for all items with derivative preferences';
	}
    }
    function checkMisc() {
	if (empty($this->pluginParameters['module']['core'])) {
	    $this->errors[] = 'Serious error: core preferences are not set';
	} else {
	    $core =& $this->pluginParameters['module']['core'];
	    if (empty($core['id.everybodyGroup'])) {
		$this->errors[] = 'Missing everybodyGroup';
	    } else {
		$this->successes[] = 'Everybody Group exists';
		$everybodyGroup = $core['id.everybodyGroup'];
		if (empty($core['id.anonymousUser'])) {
		    $this->errors[] = 'Missing anonymousUser';
		} else {
		    $this->successes[] = 'Anonymous User is defined';
		    $anonymousUser = $core['id.anonymousUser'];
		}
		$query = '# ' . __LINE__ . "
SELECT
    `{$this->tablePrefix}UserGroupMap`.`{$this->fieldPrefix}groupId`
FROM
    `{$this->tablePrefix}UserGroupMap`
WHERE
    `{$this->tablePrefix}UserGroupMap`.`{$this->fieldPrefix}userId` = $anonymousUser
";
		$anonymousUserInGroups = $this->databaseMachine->getCol($query);
		if (0 == count($anonymousUserInGroups)) {
		    $this->errors[] = 'Anonymous user is not part of any groups';
		} elseif (1 == count($anonymousUserInGroups)) {
		    $test_group = array_pop($anonymousUserInGroups);
		    if ($test_group != $everybodyGroup) {
			$this->errors[] = 'Anonymous user is part of the wrong user group: '
				. $test_group
				. ' instead of '
				. $everybodyGroup;
		    } else {
			$this->successes[] = 'Anonymous user is in only the correct group';
		    }
		} else {
		    $this->errors[] = 'Anonymous user is part of more than one group.'
			    . ' Expected ' . $everybodyGroup
			    . ' but is ' . miscHelper::intArrayToRanges($anonymousUserInGroups);
		}
	    }
	}
    }
    function runChecks() {
	$this->fixSequence();
	$this->checkRootId();
	$this->checkMisc();
	$this->checkForeignKeys();
	$this->checkEntityTypes();
	$this->checkTree();
	$this->checkForMissingDerivatives();
    }
    function interrogateItem($id_of_interest) {
	$id_of_interest = intval($id_of_interest);
	$wheres = array();
	foreach ($this->tablesWithEntityIds as $table_here) {
	    $table = substr($table_here, strlen($this->tablePrefix));
	    if (!isset($wheres[$table])) {
		$wheres[$table] = array();
	    }
	    $wheres[$table][] = "`{$this->fieldPrefix}id` = '{$id_of_interest}'";
	}
	foreach ($this->tablesWithGroupIds as $table_here) {
	    $table = substr($table_here, strlen($this->tablePrefix));
	    if (!isset($wheres[$table])) {
		$wheres[$table] = array();
	    }
	    $wheres[$table][] = "`{$this->fieldPrefix}groupId` = '{$id_of_interest}'";
	}
	foreach ($this->tablesWithUserIds as $table_here) {
	    $table = substr($table_here, strlen($this->tablePrefix));
	    if (!isset($wheres[$table])) {
		$wheres[$table] = array();
	    }
	    $wheres[$table][] = "`{$this->fieldPrefix}userId` = '{$id_of_interest}'";
	}
	foreach ($this->tablesWithItemIds as $table_here) {
	    $table = substr($table_here, strlen($this->tablePrefix));
	    if (!isset($wheres[$table])) {
		$wheres[$table] = array();
	    }
	    $wheres[$table][] = "`{$this->fieldPrefix}itemId` = '{$id_of_interest}'";
	}
	foreach ($this->tablesWithUserOrGroupIds as $table_here) {
	    $table = substr($table_here, strlen($this->tablePrefix));
	    if (!isset($wheres[$table])) {
		$wheres[$table] = array();
	    }
	    $wheres[$table][] = "`{$this->fieldPrefix}userOrGroupId` = '{$id_of_interest}'";
	}
	foreach ($this->forcedForeignKeys as $mapping) {
	    foreach ($mapping as $field) {
		list($table, $fieldname) = explode('.', $field);
		if (!isset($wheres[$table])) {
		    $wheres[$table] = array();
		}
		$wheres[$table][] = "`{$this->fieldPrefix}{$fieldname}` = '{$id_of_interest}'";
	    }
	}
	$queries = array();
	foreach ($wheres as $table => $criteria) {
	    if ($criteria) {
		$criteria = array_unique($criteria);
		$queries[] = '# ' . __LINE__ . "
SELECT
    *
FROM
    `{$this->tablePrefix}{$table}`
WHERE (
    " . join("\n    OR ", $criteria) . "
)";
	    }
	}
	$datas = array();
	foreach ($queries as $query) {
	    $data = $this->databaseMachine->getRows($query);
	    if ($data) {
		$datas[] = $query . '<br />' . $this->rowsToHTML($data) . '<br />';;
	    }
	}
	return join("\n", $datas);
    }
    function rowsToHTML($rows) {
	$texts[] = '<table class="dump">';
	$texts[] = '<thead>';
	foreach ($rows as $row_values) {
	    $texts[] = '<tr>';
	    foreach ($row_values as $key => $value) {
		$texts[] = '<th>' . htmlspecialchars($key) . '</th>';
	    }
	    $texts[] = '</tr>';
	    break;
	}
	$texts[] = '</thead>';
	$texts[] = '<tbody>';
	foreach ($rows as $row_values) {
	    $texts[] = '<tr>';
	    foreach ($row_values as $value) {
		$texts[] = '<td>' . htmlspecialchars($value) . '</td>';
	    }
	    $texts[] = '</tr>';
	}
	$texts[] = '</tbody>';
	$texts[] = '</table>';
	return join("\n", $texts);
    }
    function getQueryExplanations() {
	return $this->databaseMachine->report();
    }
}