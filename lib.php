<?php
defined('MOODLE_INTERNAL') || die();

function assignsubmission_formulacheck_validate_range($value, $opts) {
    if (!is_scalar($value)) { return false; }
    $v = trim((string)$value);
    if ($v === '') { return false; }
    $v = (float)str_replace(',', '.', $v);
    if ($opts['min'] !== '' && $v < (float)$opts['min']) { return false; }
    if ($opts['max'] !== '' && $v > (float)$opts['max']) { return false; }
    return true;
}
