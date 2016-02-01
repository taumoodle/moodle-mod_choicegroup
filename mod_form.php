<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Version information
 *
 * @package    mod
 * @subpackage choicegroup
 * @copyright  2013 Université de Lausanne
 * @author     Nicolas Dunand <Nicolas.Dunand@unil.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
$PAGE->requires->jquery();
$PAGE->requires->js(new moodle_url($CFG->wwwroot . '/mod/choicegroup/js/applylimit.js'));


class mod_choicegroup_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $CHOICEGROUP_SHOWRESULTS, $CHOICEGROUP_PUBLISH, $CHOICEGROUP_DISPLAY, $DB, $COURSE, $PAGE;

        $mform    =& $this->_form;

		//-------------------------------------------------------------------------------
		$mform->addElement('header', 'general', get_string('general', 'form'));

		$mform->addElement('text', 'name', get_string('choicegroupname', 'choicegroup'), array('size' => '64'));
		if (!empty($CFG->formatstringstriptags)) {
			$mform->setType('name', PARAM_TEXT);
		} else {
			$mform->setType('name', PARAM_CLEANHTML);
		}
		$mform->addRule('name', null, 'required', null, 'client');

	        if (method_exists($this, 'standard_intro_elements')) {
	            $this->standard_intro_elements(get_string('description'));
	        } else {
	            $this->add_intro_editor(true, get_string('description'));
	        }

		//-------------------------------------------------------------------------------


		// -------------------------
		// Fetch data from database
		// -------------------------
		$groups = array();
		$db_groups = $DB->get_records('groups', array('courseid' => $COURSE->id));
		foreach ($db_groups as $group) {
			$groups[$group->id] = new stdClass();
			$groups[$group->id]->name = $group->name;
			$groups[$group->id]->mentioned = false;
			$groups[$group->id]->id = $group->id;
		}

		$db_groupings = $DB->get_records('groupings', array('courseid' => $COURSE->id));
        $groupings = array();
        if ($db_groupings) {
            foreach ($db_groupings as $grouping) {
                $groupings[$grouping->id] = new stdClass();
                $groupings[$grouping->id]->name = $grouping->name;
            }

            list($sqlin, $inparams) = $DB->get_in_or_equal(array_keys($groupings));
            $db_groupings_groups = $DB->get_records_select('groupings_groups', 'groupingid '.$sqlin, $inparams);

            foreach ($db_groupings_groups as $grouping_group_link) {
                $groupings[$grouping_group_link->groupingid]->linkedGroupsIDs[] =  $grouping_group_link->groupid;
            }
        }
		// -------------------------
		// -------------------------

		// -------------------------
		// Continue generating form
		// -------------------------
		$mform->addElement('header', 'miscellaneoussettingshdr', get_string('miscellaneoussettings', 'form'));
		$mform->setExpanded('miscellaneoussettingshdr');
		$mform->addElement('checkbox', 'multipleenrollmentspossible', get_string('multipleenrollmentspossible', 'choicegroup'));

		$mform->addElement('select', 'showresults', get_string("publish", "choicegroup"), $CHOICEGROUP_SHOWRESULTS);
		$mform->setDefault('showresults', CHOICEGROUP_SHOWRESULTS_DEFAULT);

		$mform->addElement('select', 'publish', get_string("privacy", "choicegroup"), $CHOICEGROUP_PUBLISH, CHOICEGROUP_PUBLISH_DEFAULT);
		$mform->setDefault('publish', CHOICEGROUP_PUBLISH_DEFAULT);
		$mform->disabledIf('publish', 'showresults', 'eq', 0);

		$mform->addElement('selectyesno', 'allowupdate', get_string("allowupdate", "choicegroup"));

		$mform->addElement('selectyesno', 'showunanswered', get_string("showunanswered", "choicegroup"));
		
		$mform->addElement('selectyesno', 'allowcreategroup', get_string("allowcreategroup", "choicegroup"));
        $mform->setDefault('allowcreategroup', 1);

        $menuoptions = array();
        $menuoptions[0] = get_string('disable');
        $menuoptions[1] = get_string('enable');
        $mform->addElement('select', 'limitanswers', get_string('limitanswers', 'choicegroup'), $menuoptions);
        $mform->addHelpButton('limitanswers', 'limitanswers', 'choicegroup');

        $mform->addElement('text', 'generallimitation', get_string('generallimitation', 'choicegroup'), array('size' => '6'));
        $mform->setType('generallimitation', PARAM_INT);
        $mform->disabledIf('generallimitation', 'limitanswers', 'neq', 1);
        $mform->addRule('generallimitation', get_string('error'), 'numeric', 'extraruledata', 'client', false, false);
        $mform->setDefault('generallimitation', 0);
        $mform->addElement('button', 'setlimit', get_string('applytoallgroups', 'choicegroup'));
        $mform->disabledIf('setlimit', 'limitanswers', 'neq', 1);

		// -------------------------
		// Generate the groups section of the form
		// -------------------------

        $mform->addElement('header', 'groups', get_string('groupsheader', 'choicegroup'));
        $repeatarray = array();
        $repeatarray[] = $mform->createElement('text', 'option', get_string('groupno', 'choicegroup'));
        $repeatarray[] = $mform->createElement('text', 'limit', get_string('set_limit_for_group', 'choicegroup'));
        $repeatarray[] = $mform->createElement('hidden', 'optionid', 0);
        $repeatarray[] = $mform->createElement('hidden', 'groupid', 0);
		
        if ($this->_instance){
            $repeatno = $DB->count_records('choicegroup_options', array('choicegroupid' => $this->_instance));
        } else {
            $repeatno = 5;
        }
		
        $repeateloptions = array();
        $repeateloptions['limit']['default'] = 2;
        $repeateloptions['limit']['disabledif'] = array('limitanswers', 'eq', 0);
        $repeateloptions['limit']['rule'] = 'numeric';
        $repeateloptions['limit']['type'] = PARAM_INT;

        $mform->setType('option', PARAM_CLEANHTML);
		
        $mform->setType('optionid', PARAM_INT);
		
        $this->repeat_elements($repeatarray, $repeatno,$repeateloptions, 'option_repeats', 'option_add_fields', 3, 'add {no} groups', true);
        switch (get_config('choicegroup', 'sortgroupsby')) {
            case CHOICEGROUP_SORTGROUPS_CREATEDATE:
                $systemdefault = array(CHOICEGROUP_SORTGROUPS_SYSTEMDEFAULT => get_string('systemdefault_date', 'choicegroup'));
                break;
            case CHOICEGROUP_SORTGROUPS_NAME:
                $systemdefault = array(CHOICEGROUP_SORTGROUPS_SYSTEMDEFAULT => get_string('systemdefault_name', 'choicegroup'));
                break;
        }

        $options = array_merge($systemdefault, choicegroup_get_sort_options());
        $mform->addElement('select', 'sortgroupsby', get_string('sortgroupsby', 'choicegroup'), $options);
        $mform->setDefault('sortgroupsby', CHOICEGROUP_SORTGROUPS_SYSTEMDEFAULT);

		// -------------------------
		// Go on the with the remainder of the form
		// -------------------------

        $mform->addElement('header', 'timerestricthdr', get_string('timerestrict', 'choicegroup'));
        $mform->addElement('checkbox', 'timerestrict', get_string('timerestrict', 'choicegroup'));

        $mform->addElement('date_time_selector', 'timeopen', get_string("choicegroupopen", "choicegroup"));
        $mform->disabledIf('timeopen', 'timerestrict');

        $mform->addElement('date_time_selector', 'timeclose', get_string("choicegroupclose", "choicegroup"));
        $mform->disabledIf('timeclose', 'timerestrict');

        //-------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
        //-------------------------------------------------------------------------------
        $this->add_action_buttons();
}

function data_preprocessing(&$default_values){
	global $DB;
	if (!empty($this->_instance)) {
				$i = 0;
				
				$sql = "SELECT c.id, c.groupid, c.maxanswers, g.name
                 		FROM {choicegroup_options} c
                 		JOIN {groups} g 
							ON c.groupid = g.id
                 		WHERE c.choicegroupid = :choicegroupid";
				
				$params = array(
						'choicegroupid' => $this->_instance
				);
				
				$groups = $DB->get_records_sql($sql, $params);
				foreach ($groups as $group) {
					$default_values['optionid['.$i.']'] = $group->id;
					$default_values['option['.$i.']'] = $group->name;
					$default_values['limit['.$i.']'] = $group->maxanswers;
					$default_values['groupid['.$i.']'] = $group->groupid;
					$i+=1;
				}
			}

	if (empty($default_values['timeopen'])) {
		$default_values['timerestrict'] = 0;
	} else {
		$default_values['timerestrict'] = 1;
	}

	}

	function get_data() {
		$data = parent::get_data();
		if (!$data) {
			return false;
		}
		// Set up completion section even if checkbox is not ticked.
		if (empty($data->completionsection)) {
			$data->completionsection=0;
		}
		return $data;
	}

	function add_completion_rules() {
		$mform =& $this->_form;

		$mform->addElement('checkbox', 'completionsubmit', '', get_string('completionsubmit', 'choicegroup'));
		return array('completionsubmit');
	}

	function completion_rule_enabled($data) {
		return !empty($data['completionsubmit']);
	}

	public function js_call() {
		global $PAGE;
		$PAGE->requires->yui_module('moodle-mod_choicegroup-form', 'Y.Moodle.mod_choicegroup.form.init');
		foreach (array_keys(get_string_manager()->load_component_strings('choicegroup', current_language())) as $string) {
			$PAGE->requires->string_for_js($string, 'choicegroup');
		}
	}
}
