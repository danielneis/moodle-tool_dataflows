<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_dataflows\local\step;

use tool_dataflows\step;
use tool_dataflows\local\scheduler;
use tool_dataflows\local\execution\engine_step;


/**
 * CRON trigger class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trigger_cron extends trigger_step {

    protected static function form_define_fields(): array {
        return [
            'minute' => ['type' => PARAM_TEXT],
            'hour' => ['type' => PARAM_TEXT],
            'day' => ['type' => PARAM_TEXT],
            'month' => ['type' => PARAM_TEXT],
            'dayofweek' => ['type' => PARAM_TEXT],
            'disabled' => ['type' => PARAM_TEXT],
        ];
    }

    /**
     * Get the default data.
     *
     * @return stdClass
     */
    public function form_get_default_data(&$data) {
        parent::form_get_default_data($data);
        $fields = array('minute', 'hour', 'day', 'month', 'dayofweek');
        foreach ($fields as $field) {
            if (!isset($data->{"config_$field"})) {
                $data->{"config_$field"} = '*';
            }
        }
    }

    /**
     * Allows each step type to determine a list of optional/required form
     * inputs for their configuration
     *
     * It's recommended you prefix the additional config related fields to avoid
     * conflicts with any existing fields.
     *
     * @param \MoodleQuickForm &$mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        $mform->addElement('static', 'schedule_header', '', 'Schedule');

        if ($this->stepdef) {
            $times = scheduler::get_scheduled_times($this->stepdef->dataflowid);
            if (!(bool)$this->stepdef->dataflow->enabled) {
                $nextrun = get_string('trigger_cron:flow_disabled', 'tool_dataflows');
            } else if ($times->nextruntime > time()) {
                $nextrun = userdate($times->nextruntime);
            } else {
                $nextrun = get_string('asap', 'tool_task');
            }

            $mform->addElement('static', 'lastrun', get_string('lastruntime', 'tool_task'), $times->lastruntime ? userdate($times->lastruntime) : get_string('never'));
            $mform->addElement('static', 'nextrun', get_string('nextruntime', 'tool_task'), $nextrun);
        }

        $crontab = [];

        $element = $mform->createElement('text', 'config_minute', get_string('taskscheduleminute', 'tool_task'), ['size' => '5']);
        $element->setType(PARAM_RAW);
        $crontab[] = $element;

        $element = $mform->createElement('text', 'config_hour', get_string('taskschedulehour', 'tool_task'), ['size' => '5']);
        $element->setType(PARAM_RAW);
        $crontab[] = $element;

        $element = $mform->createElement('text', 'config_day', get_string('taskscheduleday', 'tool_task'), ['size' => '5']);
        $element->setType(PARAM_RAW);
        $crontab[] = $element;

        $element = $mform->createElement('text', 'config_month', get_string('taskschedulemonth', 'tool_task'), ['size' => '5']);
        $element->setType(PARAM_RAW);
        $crontab[] = $element;

        $element = $mform->createElement('text', 'config_dayofweek', get_string('taskscheduledayofweek', 'tool_task'), ['size' => '5']);
        $element->setType(PARAM_RAW);
        $crontab[] = $element;

        $mform->addGroup($crontab, 'crontab', get_string('trigger_cron:crontab', 'tool_dataflows'), '&nbsp;', false);
        $mform->addElement('static', 'crontab_desc', '', get_string('trigger_cron:crontab_desc', 'tool_dataflows'));
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];

        $fields = array('minute', 'hour', 'day', 'month', 'dayofweek');
        foreach ($fields as $field) {
            if (!\tool_task_edit_scheduled_task_form::validate_fields($field, $config->$field)) {
                $errors['config_' . $field] = get_string('trigger_cron:invalid', 'tool_dataflows', '', true);
            }
        }
        return empty($errors) ? true : $errors;
    }

    /**
     * Hook function that gets called when a step has been saved.
     *
     * @param step $stepdef
     */
    public function on_save() {
        $config = $this->stepdef->config;
        $config->classname = 'tool_dataflows\task\process_dataflows';
        $times = scheduler::get_scheduled_times($this->stepdef->dataflowid);
        if ($times === false) {
            $config->lastruntime = 0;
            $config->nextruntime = 0;
        } else {
            $config = (object) array_merge((array) $config, (array) $times);
        }

        $task = \core\task\manager::scheduled_task_from_record($config);
        $newtime = $task->get_next_scheduled_time();

        scheduler::set_scheduled_times(
            $this->stepdef->dataflowid,
            $newtime
        );
    }

    /**
     * Hook function that gets called when a step has been saved.
     *
     * @param step $stepdef
     */
    public function on_delete() {
        global $DB;

        $DB->delete_records(scheduler::TABLE, ['dataflowid' => $this->stepdef->dataflowid]);
    }

    /**
     * Hook function that gets called when an engine step has been finalised.
     *
     * @throws \dml_exception
     */
    public function on_finalise() {
        if (!$this->enginestep->engine->isdryrun) {
            $dataflowid = $this->enginestep->stepdef->dataflowid;

            $config = $this->stepdef->config;
            $config->classname = 'tool_dataflows\task\process_dataflows';
            $times = scheduler::get_scheduled_times($this->stepdef->dataflowid);
            if ($times === false) {
                $config->lastruntime = 0;
                $config->nextruntime = 0;
            } else {
                $config = (object) array_merge((array) $config, (array) $times);
            }

            $task = \core\task\manager::scheduled_task_from_record($config);
            $newtime = $task->get_next_scheduled_time();

            scheduler::set_scheduled_times(
                $dataflowid,
                $newtime,
                $config->nextruntime
            );
        }
    }
}
