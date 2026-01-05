<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/assign/submissionplugin.php');
use assignsubmission_formulacheck\local\evaluator;

class assign_submission_formulacheck extends assign_submission_plugin {

    public function get_name() { return get_string('pluginname', 'assignsubmission_formulacheck'); }

     public function get_settings(MoodleQuickForm $mform) {
        global $PAGE; 
        $PAGE->requires->css('/mod/assign/submission/formulacheck/styles/custom.css');

        $mform->addElement('header', 'assignsubmission_formulacheck_params_header', get_string('paramlabel', 'assignsubmission_formulacheck'));
        //$mform->addHelpButton('assignsubmission_formulacheck_params_header', 'paramlabel', 'assignsubmission_formulacheck');
        
        $mform->addElement('text', 'assignsubmission_formulacheck_formula', get_string('teacherformula', 'assignsubmission_formulacheck'));
        $mform->addHelpButton('assignsubmission_formulacheck_formula', 'teacherformula', 'assignsubmission_formulacheck');
        $mform->setType('assignsubmission_formulacheck_formula', PARAM_RAW_TRIMMED);
        // FIX: Ensure saved formula loads on edit.
        $mform->setDefault('assignsubmission_formulacheck_formula', $this->get_config('formula')); 

        // Change 1: Loop parameters p1 through p10 for labels and min/max settings,
        // organized into form groups for single-row display.
        $params = ['p1','p2','p3','p4','p5','p6','p7','p8','p9','p10'];

        $headerhtml = html_writer::start_div('fitem formulacheck-param-header-wrapper') .
                      // Inner div to hold the column names and apply flex layout
                      html_writer::start_div('felement formulacheck-param-header-columns') .
                      html_writer::div(get_string('paramlabel', 'assignsubmission_formulacheck'), 'header-label') .
                      html_writer::div(get_string('min', 'assignsubmission_formulacheck'), 'header-min') .
                      html_writer::div(get_string('max', 'assignsubmission_formulacheck'), 'header-max') .
                      html_writer::end_div() .
                      html_writer::end_div();
        $mform->addElement('html', $headerhtml);
        
        // --- Start of new structure for parameters ---
        //$mform->addElement('header', 'assignsubmission_formulacheck_params_header', get_string('paramlabel', 'assignsubmission_formulacheck'));

        foreach ($params as $k) {
            $label_key = "assignsubmission_formulacheck_label_{$k}";
            $min_key = "assignsubmission_formulacheck_min_{$k}";
            $max_key = "assignsubmission_formulacheck_max_{$k}";
            
            // 1a. Create the elements individually
            $mform->addElement('text', $label_key, get_string("label_{$k}", 'assignsubmission_formulacheck'));
            $mform->setType($label_key, PARAM_TEXT);
            $mform->setDefault($label_key, $this->get_config("label_{$k}"));
            
            $mform->addElement('text', $min_key, get_string('min', 'assignsubmission_formulacheck')); // Using generic 'Min' string
            $mform->setType($min_key, PARAM_RAW_TRIMMED);
            $mform->setDefault($min_key, $this->get_config("min_{$k}"));
            
            $mform->addElement('text', $max_key, get_string('max', 'assignsubmission_formulacheck')); // Using generic 'Max' string
            $mform->setType($max_key, PARAM_RAW_TRIMMED);
            $mform->setDefault($max_key, $this->get_config("max_{$k}"));
            
            // 1b. Group the elements for a single-row display (without a specific group label)
            // Use 'assignsubmission_formulacheck_param_group_' . $k as the group name
            $group_elements = [$label_key, $min_key, $max_key];
            $group_label = get_string("label_{$k}", 'assignsubmission_formulacheck') . ' (' . $k . ')';
            
            // The first element in the group will typically display its label, 
            // so we set the label for the group to be empty and use the label for the first element.
            // A more robust Moodle approach is to create a group and then set its label
            
            $mform->removeElement($label_key); // Remove the element so it can be added to the group
            $mform->removeElement($min_key);
            $mform->removeElement($max_key);

            $mform->addElement('group', 'assignsubmission_formulacheck_param_group_' . $k, get_string("label_{$k}", 'assignsubmission_formulacheck'), [
                $mform->createElement('text', $label_key, null, ['size' => 25]), // Param Label
                $mform->createElement('text', $min_key, get_string('min', 'assignsubmission_formulacheck'), ['size' => 10]), // Min
                $mform->createElement('text', $max_key, get_string('max', 'assignsubmission_formulacheck'), ['size' => 10])  // Max
            ], ' ', false); // ' ' is the separator, false means not required

            // Re-apply types and defaults as they were lost when recreating elements for the group
            $mform->setType($label_key, PARAM_TEXT);
            $mform->setDefault($label_key, $this->get_config("label_{$k}"));

            $mform->setType($min_key, PARAM_RAW_TRIMMED);
            $mform->setDefault($min_key, $this->get_config("min_{$k}"));

            $mform->setType($max_key, PARAM_RAW_TRIMMED);
            $mform->setDefault($max_key, $this->get_config("max_{$k}"));
        }
        // --- End of new structure for parameters ---

        // The rest of the settings remain below the parameter settings
        $mform->addElement('text', 'assignsubmission_formulacheck_label_result', get_string('label_result', 'assignsubmission_formulacheck'));
        $mform->setType('assignsubmission_formulacheck_label_result', PARAM_TEXT);
        // FIX: Ensure saved result label loads on edit.
        $mform->setDefault('assignsubmission_formulacheck_label_result', $this->get_config('label_result'));

        $mform->addElement('text', 'assignsubmission_formulacheck_tolerance', get_string('tolerance', 'assignsubmission_formulacheck'));
        $mform->setType('assignsubmission_formulacheck_tolerance', PARAM_FLOAT);
        // FIX: Ensure saved tolerance loads first, falling back to global default.
        $mform->setDefault('assignsubmission_formulacheck_tolerance', $this->get_config('tolerance') ?? get_config('assignsubmission_formulacheck', 'defaulttolerance'));

        $mform->addElement('select', 'assignsubmission_formulacheck_tolerancetype', get_string('tolerancetype', 'assignsubmission_formulacheck'),
            ['nominal' => get_string('tolerance_nominal', 'assignsubmission_formulacheck'), 'relative' => get_string('tolerance_relative', 'assignsubmission_formulacheck')]);
        // FIX: Ensure saved tolerance type loads first, falling back to global default.
        $mform->setDefault('assignsubmission_formulacheck_tolerancetype', $this->get_config('tolerancetype') ?? get_config('assignsubmission_formulacheck', 'defaulttolerancetype'));

        $mform->addElement('advcheckbox', 'assignsubmission_formulacheck_showformula', get_string('showformula', 'assignsubmission_formulacheck'), null, [], [0,1]);
        // FIX: Ensure saved checkbox value loads on edit.
        $mform->setDefault('assignsubmission_formulacheck_showformula', $this->get_config('showformula'));

        $mform->addElement('text', 'assignsubmission_formulacheck_decimalplaces', get_string('decimalplaces', 'assignsubmission_formulacheck'));
        $mform->setType('assignsubmission_formulacheck_decimalplaces', PARAM_INT);
        // FIX: Ensure saved decimal places loads first, falling back to hardcoded default (3).
        $mform->setDefault('assignsubmission_formulacheck_decimalplaces', $this->get_config('decimalplaces') ?? 3);

        $mform->addElement('advcheckbox', 'assignsubmission_formulacheck_blockonsave', get_string('blockonsave', 'assignsubmission_formulacheck'), null, [], [0,1]);
        // FIX: Ensure saved checkbox value loads on edit.
        $mform->setDefault('assignsubmission_formulacheck_blockonsave', $this->get_config('blockonsave'));

        // Removed the original Change 2: Loop parameters p1 through p10 for min/max settings
        // as they are now integrated into the single loop above.
    }


    public function save_settings(stdClass $data) {
        // Change 3: Extend the map to include labels for p6 through p10
        $map = [
            'formula' => 'assignsubmission_formulacheck_formula',
            'label_p1' => 'assignsubmission_formulacheck_label_p1',
            'label_p2' => 'assignsubmission_formulacheck_label_p2',
            'label_p3' => 'assignsubmission_formulacheck_label_p3',
            'label_p4' => 'assignsubmission_formulacheck_label_p4',
            'label_p5' => 'assignsubmission_formulacheck_label_p5',
            'label_p6' => 'assignsubmission_formulacheck_label_p6',
            'label_p7' => 'assignsubmission_formulacheck_label_p7',
            'label_p8' => 'assignsubmission_formulacheck_label_p8',
            'label_p9' => 'assignsubmission_formulacheck_label_p9',
            'label_p10' => 'assignsubmission_formulacheck_label_p10',
            'label_result' => 'assignsubmission_formulacheck_label_result',
            'tolerance' => 'assignsubmission_formulacheck_tolerance',
            'tolerancetype' => 'assignsubmission_formulacheck_tolerancetype',
            'showformula' => 'assignsubmission_formulacheck_showformula',
            'decimalplaces' => 'assignsubmission_formulacheck_decimalplaces',
            'blockonsave' => 'assignsubmission_formulacheck_blockonsave',
        ];
        foreach ($map as $cfg => $formkey) { $this->set_config($cfg, $data->$formkey ?? null); }

        // Change 4: Loop parameters p1 through p10 for min/max
        $params = ['p1','p2','p3','p4','p5','p6','p7','p8','p9','p10'];
        foreach ($params as $k) {
            $this->set_config("min_{$k}", $data->{"assignsubmission_formulacheck_min_{$k}"} ?? null);
            $this->set_config("max_{$k}", $data->{"assignsubmission_formulacheck_max_{$k}"} ?? null);
        }
        return true;
    }


    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        global $PAGE; 
        $PAGE->requires->css('/mod/assign/submission/formulacheck/styles/custom.css');
        $labels = $this->get_param_labels();
        $params = ['p1','p2','p3','p4','p5','p6','p7','p8','p9','p10']; // Define local params list
        $param_ranges = []; // New: Collect ranges to pass to JS

        $formula = trim((string)$this->get_config('formula'));
        if ((int)$this->get_config('showformula') && $formula!=='') {
            $mform->addElement('static', 'assignsubmission_formulacheck_formula_display', get_string('teacherformula', 'assignsubmission_formulacheck'),
                s($formula) . '<br><small>Use dot for decimals; ^ is exponent; radians for trig.</small>');
        }

        // Change 5: Loop parameters p1 through p10 for student input fields
        foreach ($params as $k) {
            $name = 'assignsubmission_formulacheck_' . $k;
            // Check if the teacher has configured a non-empty custom label.
            $customlabel = $this->get_config('label_'.$k);
            if (!empty(trim($customlabel))) {

                    $min = trim((string)$this->get_config("min_{$k}"));
                    $max = trim((string)$this->get_config("max_{$k}"));

                    // Plain text fields; store desired bounds as data-* for our passive JS.
                    $attrs = ['size'=>12, 'data-min'=>($min!==''?$min:''), 'data-max'=>($max!==''?$max:'')];
                    
                    // New: Collect ranges for the random button logic
                    $param_ranges[$k] = [
                        'min' => $min !== '' ? $min : null,
                        'max' => $max !== '' ? $max : null,
                        'field_name' => $name
                    ];

                    $mform->addElement('text', $name, $labels[$k], $attrs);
                    $mform->setType($name, PARAM_RAW_TRIMMED);

                    // Client-side built-ins for required & numeric.
                    $mform->addRule($name, null, 'required', null, 'client');
                    $mform->addRule($name, null, 'numeric',  null, 'client');

                    // Server-side range rule for authoritative check + inline error after submit.
                    if ($min !== '' || $max !== '') {
                        $a = (object)['min' => ($min!==''?$min:'−∞'), 'max' => ($max!==''?$max:'+∞'), 'label'=>$labels[$k]];
                        $errmsg = get_string('range_violation','assignsubmission_formulacheck',$a);
                        $mform->addRule($name, $errmsg, 'callback', ['assignsubmission_formulacheck_validate_range', ['min'=>$min, 'max'=>$max]], 'server');
                        $mform->addElement('static', $name.'_hint', '', get_string('rangehint','assignsubmission_formulacheck',(object)['min'=>$a->min,'max'=>$a->max]));
                    }
            }
        }

        $mform->addElement('text', 'assignsubmission_formulacheck_result', $labels['result']);
        $mform->setType('assignsubmission_formulacheck_result', PARAM_RAW_TRIMMED);
        $mform->addRule('assignsubmission_formulacheck_result', null, 'required', null, 'client');
        $mform->addRule('assignsubmission_formulacheck_result', null, 'numeric',  null, 'client');

        // New: Add the "Generate" button below the input fields
        if (!empty($param_ranges)) {
            $mform->addElement('button', 'assignsubmission_formulacheck_generate_random', get_string('generate_random_params', 'assignsubmission_formulacheck'), [
               // 'class' => 'btn btn-secondary',
                'data-ranges' => json_encode($param_ranges), // Pass the ranges to the JS
            ]);
        }
        
        // Passive JS: gentle hints while typing + submit-time prevention only for out-of-range.
        // This selector works for p1 through p10
        $PAGE->requires->js_call_amd('assignsubmission_formulacheck/range', 'init', ['input[name^=assignsubmission_formulacheck_p]', 'assignsubmission_formulacheck_generate_random']);

        return true;
    }

    

    public function is_empty(stdClass $submission) { global $DB; return !$DB->record_exists('assignsubmission_formulacheck', ['submission'=>$submission->id]); }

    public function save(stdClass $submission, stdClass $data) {
        global $DB; $vals=[];
        $params = ['p1','p2','p3','p4','p5','p6','p7','p8','p9','p10']; // Define local params list
        
        // Change 6: Loop parameters p1 through p10 for validation and collecting values
        foreach ($params as $k) {
            $customlabel = $this->get_config('label_'.$k);
                if (!empty(trim($customlabel))) {
                        $field='assignsubmission_formulacheck_' . $k;
                        if (!isset($data->$field) || !is_numeric($data->$field)) { throw new \moodle_exception('invaliddata'); }
                        $vals[$k]=(float)$data->$field;
                        $min = trim((string)$this->get_config("min_{$k}"));
                        $max = trim((string)$this->get_config("max_{$k}"));
                        if ($min !== '' && $vals[$k] < (float)$min) { $this->throw_range_violation($k, ($min!=='')?(float)$min:null, ($max!=='')?(float)$max:null); }
                        if ($max !== '' && $vals[$k] > (float)$max) { $this->throw_range_violation($k, ($min!=='')?(float)$min:null, ($max!=='')?(float)$max:null); }
                }
        }
        $studentresult=(float)($data->assignsubmission_formulacheck_result ?? 0);
        $formula=trim((string)$this->get_config('formula'));
        // echo $formula + '<br>';
        // echo '<pre>' . print_r($vals, true) . '</pre>';
        // die();
        $expected=evaluator::evaluate($formula, $vals);
        $isvalid=0;
        if ($expected !== null) { 
            $tol=(float)$this->get_config('tolerance'); 
            $tt=(string)$this->get_config('tolerancetype'); 
            $isvalid=$this->within_tolerance($studentresult,$expected,$tol,$tt)?1:0; 
        }



        $rec=$DB->get_record('assignsubmission_formulacheck',['submission'=>$submission->id]); $now=time();
        
        // Prepare parameter fields for insertion/update
        $param_fields = [];
        foreach ($params as $k) {
            $customlabel = $this->get_config('label_'.$k);
                if (!empty(trim($customlabel))) {
                        $param_fields[$k] = $vals[$k];
                }
        }
        
        if ($rec) {
            // Change 7a: Update record fields for p1 through p10
            foreach ($param_fields as $k => $v) { $rec->$k = $v; }
            $rec->studentresult=$studentresult; $rec->expectedresult=$expected; $rec->isvalid=$isvalid; $rec->timemodified=$now;
            $DB->update_record('assignsubmission_formulacheck',$rec);
        } else {
            // Change 7b: Insert record fields for p1 through p10
            $rec = (object) array_merge([
                'assignment' => $this->assignment->get_instance()->id,
                'submission' => $submission->id,
                'studentresult' => $studentresult,
                'expectedresult' => $expected,
                'isvalid' => $isvalid,
                'timemodified' => $now
            ], $param_fields);
            
            $DB->insert_record('assignsubmission_formulacheck',$rec);
        }



        // ✅ Move grading logic AFTER DB operations
        // $instance = $this->assignment->get_instance();
        // if ($instance->grade > 0) {
        //     $userid = !empty($submission->userid)
        //         ? $submission->userid
        //         : $DB->get_field('assign_submission', 'userid', ['id' => $submission->id]);

        //     if ($userid) {
        //         $grade = $isvalid ? $instance->grade : 0;
        //         $this->assignment->update_grade($userid, $grade);
        //     }
        // }



        if ((int)$this->get_config('blockonsave')===1 && $isvalid===0) { throw new \moodle_exception('validation_failed','assignsubmission_formulacheck'); }
        return true;
    }

    private function throw_range_violation(string $k, ?float $min, ?float $max) {
        $labels = $this->get_param_labels();
        $a=(object)['label'=>$labels[$k],'min'=>($min!==null?$min:'−∞'),'max'=>($max!==null?$max:'+∞')];
        throw new \moodle_exception('range_violation','assignsubmission_formulacheck','',$a);
    }

    public function view_summary(stdClass $submission, &$showviewlink) {
        global $DB; $showviewlink=false; $rec=$DB->get_record('assignsubmission_formulacheck',['submission'=>$submission->id]); if(!$rec){return '';}        
        $dp=(int)$this->get_config('decimalplaces'); $labels=$this->get_param_labels(); $out='';

        // Change 8: Loop parameters p1 through p10 for summary view
        $params = ['p1','p2','p3','p4','p5','p6','p7','p8','p9','p10'];
        foreach($params as $k)
            { 
                $customlabel = $this->get_config('label_'.$k);
                if (!empty(trim($customlabel))) {
                    $out .= html_writer::div(s($labels[$k]).': '.format_float($rec->$k,$dp)); 
                }
            
            }
        
        $out .= html_writer::div(s($labels['result']).': '.format_float($rec->studentresult,$dp));
        if ($rec->expectedresult !== null) { $out .= html_writer::div('Expected: '.format_float($rec->expectedresult,$dp)); }
        $out .= html_writer::div($rec->isvalid?get_string('summary_ok','assignsubmission_formulacheck'):get_string('summary_bad','assignsubmission_formulacheck'), '', ['style'=>'font-weight:bold;color:'.($rec->isvalid?'green':'red')]);
        return $out;
    }

    private function within_tolerance(float $student, float $expected, float $tol, string $type): bool {
        if (!is_finite($student) || !is_finite($expected)) return false;
        if ($type==='relative') { if ($expected==0.0) return abs($student-$expected)<= $tol; return abs($student-$expected)/abs($expected) <= $tol; }
        return abs($student-$expected) <= $tol;
    }

    private function get_param_labels(): array {
        // Change 9: Include labels for p6 through p10
        return [
            'p1' => $this->get_config('label_p1') ?: get_string('label_p1', 'assignsubmission_formulacheck'),
            'p2' => $this->get_config('label_p2') ?: get_string('label_p2', 'assignsubmission_formulacheck'),
            'p3' => $this->get_config('label_p3') ?: get_string('label_p3', 'assignsubmission_formulacheck'),
            'p4' => $this->get_config('label_p4') ?: get_string('label_p4', 'assignsubmission_formulacheck'),
            'p5' => $this->get_config('label_p5') ?: get_string('label_p5', 'assignsubmission_formulacheck'),
            'p6' => $this->get_config('label_p6') ?: get_string('label_p6', 'assignsubmission_formulacheck'),
            'p7' => $this->get_config('label_p7') ?: get_string('label_p7', 'assignsubmission_formulacheck'),
            'p8' => $this->get_config('label_p8') ?: get_string('label_p8', 'assignsubmission_formulacheck'),
            'p9' => $this->get_config('label_p9') ?: get_string('label_p9', 'assignsubmission_formulacheck'),
            'p10' => $this->get_config('label_p10') ?: get_string('label_p10', 'assignsubmission_formulacheck'),
            'result' => $this->get_config('label_result') ?: get_string('label_result', 'assignsubmission_formulacheck'),
        ];
    }
}
