<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'assignsubmission_formulacheck/default',
        get_string('default', 'assignsubmission_formulacheck'),
        get_string('default_help', 'assignsubmission_formulacheck'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_formulacheck/defaulttolerance',
        get_string('defaulttolerance', 'assignsubmission_formulacheck'),
        '',
        '0.001', PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configselect(
        'assignsubmission_formulacheck/defaulttolerancetype',
        get_string('defaulttolerancetype', 'assignsubmission_formulacheck'),
        '',
        'nominal',
        [
            'nominal' => get_string('tolerance_nominal', 'assignsubmission_formulacheck'),
            'relative' => get_string('tolerance_relative', 'assignsubmission_formulacheck')
        ]
    ));
}
