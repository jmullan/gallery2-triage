<?php
class miscHelper {
    static function intArrayToRanges($ints) {
	$last_value = null;
	$first_value = null;
	$string = '';
	$print_last_value = false;
	$is_very_first = true;
	$previous_to_last_value = null;
	foreach ($ints as $value) {
	    if (!empty($last_value) && 1 < ($value - $last_value)) {
		if ($last_value != $first_value) {
		    if (1 <  $last_value - $previous_to_last_value) {
			$string .= ' - ' . $last_value;
		    } else {
			$string .= ', ' . $last_value;
		    }
		}
		$first_value = $value;
		$string .= ', ' . $value;
		$print_last_value = false;
	    } elseif (!empty($last_value) && 1 == ($value - $last_value)) {
		$print_last_value = true;
	    } elseif (empty($last_value)) {
		$first_value = $value;
		$string .= $value;
	    }
	    $previous_to_last_value = $last_value;
	    $last_value = $value;
	    $is_very_first = false;
	}
	if (!empty($print_last_value)) {
	    $string .= ' - ' . $last_value;
	}
	return $string;
    }
}