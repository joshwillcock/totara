<?php

class dp_course_component extends dp_base_component {
    public static $permissions = array(
        'updatecourse' => true,
        'commenton' => false,
        'setpriority' => false,
        'setduedate' => false,
        'setcompletionstatus' => true,
    );
    function __construct($plan) {
        $this->component = 'course';
        parent::__construct($plan);
    }

    function display_course_list() {
        global $CFG;

        $showduedates = ($this->get_setting('duedatemode') == DP_DUEDATES_OPTIONAL ||
            $this->get_setting('duedatemode') == DP_DUEDATES_REQUIRED);
        $showpriorities =
            ($this->get_setting('prioritymode') == DP_PRIORITY_OPTIONAL ||
            $this->get_setting('prioritymode') == DP_PRIORITY_REQUIRED);
        $priorityscaleid = ($this->get_setting('priorityscale')) ? $this->get_setting('priorityscale') : -1;

        $plancompleted = $this->plan->status == DP_PLAN_STATUS_COMPLETE;
        $canapprovecourses = !$plancompleted &&
            $this->get_setting('updatecourse') == DP_PERMISSION_APPROVE;
        $canremovecourses = !$plancompleted &&
            $this->get_setting('updatecourse') == DP_PERMISSION_ALLOW;

        // @todo fix sorting of status column to account for course
        // completion - may need status column in course completions table
        // reenable sorting on progress column when working
        $count = 'SELECT COUNT(*) ';
        $select = 'SELECT ca.*, c.fullname, psv.name ' . sql_as() . ' priorityname ';

        // get courses assigned to this plan
        // and related details
        $from = "FROM {$CFG->prefix}dp_plan_course_assign ca
                LEFT JOIN
                    {$CFG->prefix}course c ON c.id = ca.courseid
                LEFT JOIN
                    {$CFG->prefix}dp_priority_scale_value psv
                    ON (ca.priority = psv.id
                    AND psv.priorityscaleid = $priorityscaleid) ";
        $where = "WHERE ca.planid = {$this->plan->id}";

        $count = count_records_sql($count.$from.$where);
        if (!$count) {
            return get_string('nocourses', 'local_plan');
        }

        $tableheaders = array(
            get_string('name','local_plan'),
            get_string('progress','local_plan'),
        );
        $tablecolumns = array(
            'c.fullname',
            'progress',
        );

        if($showpriorities) {
            $tableheaders[] = get_string('priority', 'local_plan');
            $tablecolumns[] = 'ca.priority';
        }

        if($showduedates) {
            $tableheaders[] = get_string('duedate', 'local_plan');
            $tablecolumns[] = 'ca.duedate';
        }

        if(!$plancompleted) {
            $tableheaders[] = get_string('status','local_plan');
            $tablecolumns[] = 'status';
        }

        if($canremovecourses) {
            $tableheaders[] = get_string('actions', 'local_plan');
            $tablecolumns[] = 'actions';
        }

        $table = new flexible_table('courselist');
        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);

        $table->set_attribute('class', 'logtable generalbox');
        $table->sortable(true);
        $table->no_sorting('progress');
        $table->no_sorting('status');
        $table->no_sorting('actions');
        $table->setup();
        $table->pagesize(20, $count);
        $sort = $table->get_sql_sort();
        $sort = ($sort=='') ? '' : ' ORDER BY ' . $sort;

        // get all course completions for this plan's user
        $completions = completion_info::get_all_courses($this->plan->userid);

        // get the scale values used for courses in this plan
        $priorityvalues = get_records('dp_priority_scale_value',
            'priorityscaleid', $priorityscaleid, 'sortorder', 'id,name,sortorder');

        if($records = get_recordset_sql($select.$from.$where.$sort,
            $table->get_page_start(),
            $table->get_page_size())) {

            while($ca = rs_fetch_next_record($records)) {
                $completionstatus = $this->get_completion_status($ca, $completions);
                $completed = (substr($completionstatus, 0, 8) == 'complete');
                $approved = $ca->approved == DP_APPROVAL_APPROVED;

                $row = array();
                $row[] = $this->display_course_name($ca);

                $row[] = $approved ? $this->display_status_as_progress_bar($ca, $completionstatus) : '';

                if($showpriorities) {
                    $row[] = $this->display_priority($ca, $priorityvalues);
                }

                if($showduedates) {
                    $row[] = $this->display_duedate($ca->id, $ca->duedate);
                }

                if(!$plancompleted) {
                    $status = '';
                    if($approved) {
                        if(!$completed) {
                            $status = $this->display_duedate_highlight_info($ca->duedate);
                        }
                    } else {
                            $status = $this->display_approval($ca, $canapprovecourses);
                    }
                    $row[] = $status;
                }

                if($canremovecourses) {
                    $currenturl = $CFG->wwwroot .
                        '/local/plan/components/course/index.php?id=' .
                        $this->plan->id;
                    $strdelete = get_string('delete', 'local_plan');
                    $row[] = '<a href="'.$currenturl.'&amp;d='.$ca->id.'" title="'.$strdelete.'"><img src="'.$CFG->pixpath.'/t/delete.gif" class="iconsmall" alt="'.$strdelete.'" /></a>';
                }

                $table->add_data($row);
            }

            rs_close($records);

            // return instead of outputing table contents
            ob_start();
            $table->print_html();
            $out = ob_get_contents();
            ob_end_clean();

            return $out;
        }

    }

    function display_linked_courses($list) {
        global $CFG;

        if(!is_array($list)|| count($list) == 0) {
            return false;
        }

        $showduedates = ($this->get_setting('duedatemode') == DP_DUEDATES_OPTIONAL ||
            $this->get_setting('duedatemode') == DP_DUEDATES_REQUIRED);
        $showpriorities =
            ($this->get_setting('prioritymode') == DP_PRIORITY_OPTIONAL ||
            $this->get_setting('prioritymode') == DP_PRIORITY_REQUIRED);
        $priorityscaleid = ($this->get_setting('priorityscale')) ? $this->get_setting('priorityscale') : -1;

        $select = 'SELECT ca.*, c.fullname, psv.name ' . sql_as() . ' priorityname ';

        // get courses assigned to this plan
        // and related details
        $from = "FROM {$CFG->prefix}dp_plan_course_assign ca
                LEFT JOIN
                    {$CFG->prefix}course c ON c.id = ca.courseid
                LEFT JOIN
                    {$CFG->prefix}dp_priority_scale_value psv
                    ON (ca.priority = psv.id
                    AND psv.priorityscaleid = $priorityscaleid) ";
        $where = "WHERE ca.id IN (" . implode(',', $list) . ")
            AND ca.approved = 1 ";

        $sort = "ORDER BY c.fullname";

        $tableheaders = array(
            get_string('name','local_plan'),
            get_string('progress','local_plan'),
        );
        $tablecolumns = array(
            'fullname',
            'progress',
        );

        if($showpriorities) {
            $tableheaders[] = get_string('priority', 'local_plan');
            $tablecolumns[] = 'priority';
        }

        if($showduedates) {
            $tableheaders[] = get_string('duedate', 'local_plan');
            $tablecolumns[] = 'duedate';
        }

        $table = new flexible_table('linkedcourselist');
        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);

        $table->set_attribute('class', 'logtable generalbox');
        $table->setup();

        // get all course completions for this plan's user
        $completions = completion_info::get_all_courses($this->plan->userid);

        // get the scale values used for courses in this plan
        $priorityvalues = get_records('dp_priority_scale_value',
            'priorityscaleid', $priorityscaleid, 'sortorder', 'id,name,sortorder');

        if($records = get_recordset_sql($select.$from.$where.$sort)) {

            while($ca = rs_fetch_next_record($records)) {
                $completionstatus = $this->get_completion_status($ca, $completions);
                $completed = (substr($completionstatus, 0, 8) == 'complete');

                $row = array();
                $row[] = $this->display_course_name($ca);

                $row[] = $this->display_status_as_progress_bar($ca, $completionstatus);

                if($showpriorities) {
                    $row[] = $this->display_priority_as_text($ca->priority, $ca->priorityname, $priorityvalues);
                }

                if($showduedates) {
                    $row[] = $this->display_duedate_as_text($ca->duedate);
                }

                $table->add_data($row);
            }

            rs_close($records);

            // return instead of outputing table contents
            ob_start();
            $table->print_html();
            $out = ob_get_contents();
            ob_end_clean();

            return $out;
        }

    }


    function display_course_name($ca) {
        global $CFG;
        $approved = ($ca->approved == DP_APPROVAL_APPROVED);

        if($approved) {
            $class = '';
            $launch = ' | <a href="' . $CFG->wwwroot .
                '/course/view.php?id=' . $ca->courseid . '">' .
                get_string('launchcourse', 'local_plan') . '</a>';
        } else {
            $class = ' class="dimmed"';
            $launch = '';
        }
        return '<a' . $class .' href="' . $CFG->wwwroot .
            '/local/plan/components/' . $this->component.'/view.php?id=' .
            $this->plan->id . '&amp;itemid=' . $ca->id . '">' . $ca->fullname .
            '</a>'. $launch;
    }

    function display_course_detail($caid) {
        global $CFG;

        $priorityscaleid = ($this->get_setting('priorityscale')) ? $this->get_setting('priorityscale') : -1;

        $sql = 'SELECT ca.*, course.*, psv.name ' . sql_as() . ' priorityname ' .
            "FROM {$CFG->prefix}dp_plan_course_assign ca
                LEFT JOIN {$CFG->prefix}dp_priority_scale_value psv
                    ON (ca.priority = psv.id
                    AND psv.priorityscaleid = {$priorityscaleid})
                LEFT JOIN {$CFG->prefix}course course ON course.id = ca.courseid
            WHERE ca.id = $caid";
        $item = get_record_sql($sql);

        if(!$item) {
            return get_string('coursenotfound', 'local_plan');
        }

        $out = '';

        // get the priority values used for competencies in this plan
        $priorityvalues = get_records('dp_priority_scale_value',
            'priorityscaleid', $priorityscaleid, 'sortorder', 'id,name,sortorder');

        // @todo add course icon
        $icon = "<img class=\"course_icon\" src=\"{$CFG->wwwroot}/local/icon.php?icon={$item->icon}&amp;id={$item->courseid}&amp;size=small&amp;type=course\" alt=\"{$item->fullname}\">";
        $out .= '<h3>' . $icon . $item->fullname . '</h3>';
        $out .= '<table border="0">';
        $out .= '<tr><th>';
        $out .= get_string('priority', 'local_plan') . ':';
        $out .= '</td><th>';
        $out .= $this->display_priority_as_text($item->priority,
            $item->priorityname, $priorityvalues);
        $out .= '</td></tr>';
        $out .= '<tr><th>';
        $out .= get_string('duedate', 'local_plan') . ':';
        $out .= '</th><td>';
        $out .= $this->display_duedate_as_text($item->duedate);
        $out .= '<br />';
        $out .= $this->display_duedate_highlight_info($item->duedate);
        $out .= '</td></tr>';
        $out .= '</table>';
        $out .= '<p>' . $item->summary . '</p>';
        $out .= '<p><a href="' . $CFG->wwwroot . '/course/view.php?id=' . $item->courseid . '">' . get_string('launchcourse', 'local_plan') . '</a></p>';

        return $out;
    }

    function display_status_as_progress_bar($ca, $completionstatus) {
        global $CFG;
        // @todo Move this into the single course page?
        $plancompleted = $this->plan->status == DP_PLAN_STATUS_COMPLETE;
        $canupdatecoursestatus = $this->get_setting('setcompletionstatus') == DP_PERMISSION_ALLOW;
        $out = '';

        // don't print a status bar if there is no completion record
        if($completionstatus !== false) {
            $completionstring = $completionstatus == '' ?
                get_string('notyetstarted','completion') :
                get_string($completionstatus, 'completion');
            $out .= "<span class=\"coursecompletionstatus\">
                <span class=\"completion-{$completionstatus}\" title=\"{$completionstring}\"></span>
                </span>";
        }

        // @todo let users with permission edit the completion status
        // as long as the plan is not complete
        /*
        if(!$plancompleted && $canupdatecoursestatus) {
            $strassess = 'Assess';
            $out .= '<a href="'.$CFG->wwwroot.'/local/plan/components/course/assess.php?id='.$this->plan->id.'&amp;itemid='.$ca->id.'" title="'.$strassess.'"><img src="'.$CFG->pixpath.'/t/edit.gif" class="iconsmall" alt="'.$strassess.'" /></a>';
        }
         */
        return $out;
    }

    function get_completion_status($ca, $completions) {
        // use value stored in dp_plan_course_assign if plan is already complete
        if($this->plan->status == DP_PLAN_STATUS_COMPLETE) {
            return $ca->completionstatus;
        }
        // otherwise look up 'live' value from course completions table
        if(array_key_exists($ca->courseid, $completions)) {
            return completion_completion::get_status($completions[$ca->courseid]);
        } else {
            // no completion record
            return false;
        }
    }

    function process_course_settings_update() {
        // @todo validation notices, including preventing empty due dates
        // if duedatemode is required
        // @todo consider handling differently - currently all updates must
        // work or nothing is changed - is that the best way?
        if (!confirm_sesskey()) {
            return 0;
        }
        $cansetduedates = ($this->get_setting('setduedate') == DP_PERMISSION_ALLOW);
        $cansetpriorities = ($this->get_setting('setpriority') == DP_PERMISSION_ALLOW);
        $canapprovecourses = ($this->get_setting('updatecourse') == DP_PERMISSION_APPROVE);
        $duedates = optional_param('duedate', array(), PARAM_TEXT);
        $priorities = optional_param('priorities', array(), PARAM_TEXT);
        $approvals = optional_param('approve', array(), PARAM_INT);
        $currenturl = qualified_me();
        $stored_records = array();
        if(!empty($duedates) && $cansetduedates) {
            foreach($duedates as $id => $duedate) {
                // allow empty due dates
                if($duedate == '' || $duedate == 'dd/mm/yy') {
                    $duedateout = null;
                } else {
                    $datepattern = '/^(0?[1-9]|[12][0-9]|3[01])\/(0?[1-9]|1[0-2])\/(\d{2})$/';
                    if (preg_match($datepattern, $duedate, $matches) == 0) {
                        // skip badly formatted date strings
                        continue;
                    }
                    $day = $matches[1];
                    $mon = $matches[2];
                    $year = $matches[3];

                    $duedateout = mktime(0, 0, 0, $mon, $day, $year);
                }

                $todb = new object();
                $todb->id = $id;
                $todb->duedate = $duedateout;
                $stored_records[$id] = $todb;
            }
        }

        if(!empty($priorities)) {
            foreach($priorities as $pid => $priority) {
                $priority = (int) $priority;
                if(array_key_exists($pid, $stored_records)) {
                    // add to the existing update object
                    $stored_records[$pid]->priority = $priority;
                } else {
                    // create a new update object
                    $todb = new object();
                    $todb->id = $pid;
                    $todb->priority = $priority;
                    $stored_records[$pid] = $todb;
                }
            }
        }
        if (!empty($approvals) && $canapprovecourses) {
            // Update approvals
            foreach ($approvals as $id => $approval) {
                $approval = (int) $approval;
                if(array_key_exists($id, $stored_records)) {
                    // add to the existing update object
                    $stored_records[$id]->approved = $approval;
                } else {
                    // create a new update object
                    $todb = new object();
                    $todb->id = $id;
                    $todb->approved = $approved;
                    $stored_records[$id] = $todb;
                }
            }
        }
        $status = true;
        begin_sql();
        foreach($stored_records as $itemid => $record) {
            $status = $status & update_record('dp_plan_course_assign', $record);
        }
        if($status) {
            commit_sql();
            totara_set_notification(get_string('coursesupdated','local_plan'), $currenturl, array('style'=>'notifysuccess'));
        } else {
            rollback_sql();
            totara_set_notification(get_string('coursesnotupdated','local_plan'), $currenturl);
        }
        redirect($currenturl);
    }

    function process_action($action) {
        global $CFG;

        switch ($action) {
            case 'approvalremind' :
                $confirm = optional_param('confirm', false, PARAM_BOOL);
                $assignmentid = required_param('assignmentid', PARAM_INT);

                $redirecturl = new moodle_url(strip_querystring(qualified_me()));
                $redirecturl->param('id', $this->plan->id);

                // Get course and assignment details
                $sql = "SELECT c.*, ca.*
                        FROM {$CFG->prefix}dp_plan_course_assign ca
                        INNER JOIN {$CFG->prefix}course c ON ca.courseid = c.id
                        WHERE ca.id = {$assignmentid}";

                $course_details = get_record_sql($sql);

                if (!$confirm) {
                    // Show confirmation message
                    print_header_simple();
                    $remindurl = new moodle_url(qualified_me());
                    $remindurl->param('confirm', 'true');
                    $strdelete = get_string('checksendapprovalreminder', 'local_plan');
                    notice_yesno(
                        "{$strdelete}<br /><br />".format_string($course_details->fullname),
                        $remindurl->out(),
                        $redirecturl->out()
                    );

                    print_footer();
                    exit;
                } else {
                    // Get user's manager(s); email reminder
                    $managers = dp_get_notification_receivers_2(get_context_instance(CONTEXT_USER, $this->plan->userid), 'manager');
                    foreach ($managers as $manager) {
                        // @todo send email
                        //email_to_user($manager, $from, $subject, $bodycopy);
                    }
                    totara_set_notification(get_string('approvalremindersent','local_plan'), $redirecturl->out(), array('style' => 'notifysuccess'));

                    //@todo set event/notification?
                }
                break;
            default:
                break;
        }
    }

    function get_assigned_items() {
        return get_records('dp_plan_course_assign', 'planid', $this->plan->id);
    }

    function initialize_settings(&$settings) {
        if($coursesettings = get_record('dp_course_settings', 'templateid', $this->plan->templateid)) {
            $settings[$this->component.'_duedatemode'] = $coursesettings->duedatemode;
            $settings[$this->component.'_prioritymode'] = $coursesettings->prioritymode;
            $settings[$this->component.'_priorityscale'] = $coursesettings->priorityscale;
        }
    }

    static public function add_settings_form(&$mform, $id) {
        global $CFG, $DP_AVAILABLE_ROLES;

        $mform->addElement('header', 'coursesettings', get_string('coursesettings', 'local_plan'));

        if ($templatesettings = get_record('dp_course_settings', 'templateid', $id)) {
            $defaultduedatesmode = $templatesettings->duedatemode;
            $defaultprioritymode = $templatesettings->prioritymode;
            $defaultpriorityscale = $templatesettings->priorityscale;
        } else {
            $defaultduedatesmode = null;
            $defaultprioritymode = null;
            $defaultpriorityscale = null;
        }
        // due date mode options
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'duedatemode', '', get_string('none', 'local_plan'), DP_DUEDATES_NONE);
        $radiogroup[] =& $mform->createElement('radio', 'duedatemode', '', get_string('optional', 'local_plan'), DP_DUEDATES_OPTIONAL);
        $radiogroup[] =& $mform->createElement('radio', 'duedatemode', '', get_string('required', 'local_plan'), DP_DUEDATES_REQUIRED);
        $mform->addGroup($radiogroup, 'duedategroup', get_string('duedates','local_plan'), '<br />', false);
        $mform->setDefault('duedatemode', $defaultduedatesmode);

        // priorities mode options
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'prioritymode', '', get_string('none', 'local_plan'), DP_PRIORITY_NONE);
        $radiogroup[] =& $mform->createElement('radio', 'prioritymode', '', get_string('optional', 'local_plan'), DP_PRIORITY_OPTIONAL);
        $radiogroup[] =& $mform->createElement('radio', 'prioritymode', '', get_string('required', 'local_plan'), DP_PRIORITY_REQUIRED);
        $mform->addGroup($radiogroup, 'prioritygroup', get_string('priorities','local_plan'), '<br />', false);
        $mform->setDefault('prioritymode', $defaultprioritymode);

        // priority scale selector
        $prioritymenu = array();
        if($priorities = dp_get_priorities()) {
            foreach($priorities as $priority) {
                $prioritymenu[$priority->id] = $priority->name;
            }
        }

        $mform->addElement('select', 'priorityscale', get_string('priorityscale', 'local_plan'), $prioritymenu);
        $mform->disabledIf('priorityscale', 'prioritymode', 'eq', DP_PRIORITY_NONE);
        $mform->setDefault('priorityscale', $defaultpriorityscale);


        $mform->addElement('header', 'coursepermissions', get_string('coursepermissions', 'local_plan'));

        $mform->addElement('html', '<div class="coursepermissionsform"><table><tr>'.
            '<th>'.get_string('action', 'local_plan').'</th>'.
            '<th>'.get_string('learner', 'local_plan').'</th>'.
            '<th>'.get_string('manager', 'local_plan').'</th></tr>');

        foreach(self::$permissions as $action => $requestable) {
            dp_add_permissions_table_row($mform, $action, get_string($action, 'local_plan'), $requestable);
        }

        foreach(self::$permissions as $action => $requestable) {
            foreach($DP_AVAILABLE_ROLES as $role){
                $sql = "SELECT value FROM {$CFG->prefix}dp_permissions WHERE role='$role' AND component='course' AND action='{$action}'";
                $defaultvalue = get_field_sql($sql);
                $mform->setDefault($action.$role, $defaultvalue);
            }
        }


        $mform->addElement('html', '</table></div>');
    }

    static public function process_settings_form($fromform, $id) {
        global $CFG, $DP_AVAILABLE_ROLES;
        $currenturl = $CFG->wwwroot .
            '/local/plan/template/advancedworkflow.php?id=' . $id .
            '&amp;component=course';

        begin_sql();
        $currentworkflow = get_field('dp_template', 'workflow', 'id', $id);
        if($currentworkflow != 'custom') {
            $template_update = new object();
            $template_update->id = $id;
            $template_update->workflow = 'custom';
            if(!update_record('dp_template', $template_update)){
                rollback_sql();
                totara_set_notification(get_string('error:update_competency_settings','local_plan'), $currenturl);
            }
        }

        $todb = new object();
        $todb->templateid = $id;
        $todb->duedatemode = $fromform->duedatemode;
        $todb->prioritymode = $fromform->prioritymode;
        if($fromform->prioritymode != DP_PRIORITY_NONE) {
            $todb->priorityscale = $fromform->priorityscale;
        }
        if($coursesettings = get_record('dp_course_settings', 'templateid', $id)) {
            // update
            $todb->id = $coursesettings->id;
            if(!update_record('dp_course_settings', $todb)) {
                rollback_sql();
                totara_set_notification(get_string('error:update_course_settings','local_plan'), $currenturl);
            }
        } else {
            // insert
            if(!insert_record('dp_course_settings', $todb)) {
                rollback_sql();
                totara_set_notification(get_string('error:update_course_settings','local_plan'), $currenturl);
            }
        }

        foreach(self::$permissions as $action => $requestable) {
            foreach($DP_AVAILABLE_ROLES as $role) {
                $permission_todb = new object();
                $permission_todb->templateid = $id;
                $permission_todb->component = 'course';
                $permission_todb->action = $action;
                $permission_todb->role = $role;
                $temp = $action . $role;
                $permission_todb->value = $fromform->$temp;

                $sql = "SELECT * FROM {$CFG->prefix}dp_permissions WHERE templateid={$id} AND component='course' AND action='{$action}' AND role='{$role}'";

                if($permission_setting = get_record_sql($sql)){
                    //update
                    $permission_todb->id = $permission_setting->id;
                    if(!update_record('dp_permissions', $permission_todb)) {
                        rollback_sql();
                        totara_set_notification(get_string('error:update_course_settings','local_plan'), $currenturl);
                    }
                } else {
                    //insert
                    if(!insert_record('dp_permissions', $permission_todb)) {
                        rollback_sql();
                        totara_set_notification(get_string('error:update_course_settings','local_plan'), $currenturl);
                    }
                }
            }
        }

        commit_sql();
        totara_set_notification(get_string('update_course_settings','local_plan'), $currenturl, array('style' => 'notifysuccess'));
    }

    function remove_course_assignment($caid) {
        $canremovecourse = ($this->get_setting('updatecourse') == DP_PERMISSION_ALLOW);
        // need permission to remove this course
        if(!$canremovecourse) {
            return false;
        }

        return delete_records('dp_plan_course_assign', 'id', $caid);
    }

}