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
 * Payment proof enrollment plugin.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Payment proof enrolment plugin implementation.
 */
class enrol_paymentproof_plugin extends enrol_plugin {
    /**
     * Returns optional enrolment information icons.
     *
     * @param array $instances all enrol instances available for current user
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances) {
        $icons = array();
        foreach ($instances as $instance) {
            if ($instance->status != ENROL_INSTANCE_ENABLED) {
                continue;
            }
            $icons[] = new pix_icon('icon', get_string('pluginname', 'enrol_paymentproof'), 'enrol_paymentproof');
        }
        return $icons;
    }

    /**
     * Returns localised name of enrol instance.
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance->name)) {
            if (!empty($instance->roleid) and $role = $DB->get_record('role', array('id' => $instance->roleid))) {
                $role = ' (' . role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING)) . ')';
            } else {
                $role = '';
            }
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_' . $enrol) . $role;
        } else {
            return format_string($instance->name);
        }
    }

    /**
     * Returns true if we can add a new instance to this course.
     *
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        return has_capability('moodle/course:enrolconfig', $context) && has_capability('enrol/paymentproof:config', $context);
    }

    /**
     * Add new instance of enrol plugin with default settings.
     *
     * @param stdClass $course
     * @return int id of new instance
     */
    public function add_default_instance($course) {
        $fields = $this->get_instance_defaults();
        return $this->add_instance($course, $fields);
    }

    /**
     * Returns defaults for new instances.
     *
     * @return array
     */
    public function get_instance_defaults() {
        $expirynotify = $this->get_config('expirynotify', 0);
        $expirythreshold = $this->get_config('expirythreshold', 86400);

        $fields = array();
        $fields['status']          = ENROL_INSTANCE_ENABLED;
        $fields['roleid']          = $this->get_config('roleid', 0);
        $fields['enrolperiod']     = $this->get_config('enrolperiod', 0);
        $fields['expirynotify']    = $expirynotify;
        $fields['expirythreshold'] = $expirythreshold;
        $fields['customint1']      = $this->get_config('groupkey', 0);
        $fields['customint2']      = $this->get_config('longtimenosee', 0);
        $fields['customtext1']     = $this->get_config('paymentinstructions', '');
        
        return $fields;
    }

    /**
     * Creates course enrol form, checks if form submitted and enrols user if necessary.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    public function enrol_page_hook(stdClass $instance) {
        global $CFG, $USER, $OUTPUT, $PAGE;

        require_once("$CFG->dirroot/enrol/paymentproof/locallib.php");

        $enrolstatus = $this->can_user_enrol($instance);

        if ($enrolstatus) {
            // Redirect to upload page.
            $url = new moodle_url('/enrol/paymentproof/upload.php', array('courseid' => $instance->courseid, 'instanceid' => $instance->id));
            return $OUTPUT->box(get_string('uploadpaymentproof', 'enrol_paymentproof', $url->out(false)), 'generalbox');
        }

        return null;
    }

    /**
     * Returns edit icons for the page with list of instances.
     *
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        $context = context_course::instance($instance->courseid);
        $icons = array();

        if (has_capability('enrol/paymentproof:config', $context)) {
            $editlink = new moodle_url("/enrol/paymentproof/manage.php", array('courseid' => $instance->courseid, 'id' => $instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core', array('class' => 'iconsmall')));
        }

        return $icons;
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     *
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) || !has_capability('enrol/paymentproof:config', $context)) {
            return null;
        }

        return new moodle_url('/enrol/paymentproof/edit.php', array('courseid' => $courseid));
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/paymentproof:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/paymentproof:config', $context);
    }

    /**
     * Check if user can self enrol.
     *
     * @param stdClass $instance enrolment instance
     * @return bool|string true if user can self enrol, error message if not
     */
    public function can_user_enrol(stdClass $instance) {
        global $DB, $USER;

        if (!$instance->status) {
            return get_string('canntenrol', 'enrol_paymentproof');
        }

        // Check if user is already enrolled.
        if ($DB->record_exists('user_enrolments', array('userid' => $USER->id, 'enrolid' => $instance->id))) {
            return get_string('alreadyenroled', 'enrol_paymentproof');
        }

        // Check if user already has a pending submission.
        if ($DB->record_exists('enrol_paymentproof_submissions', array('userid' => $USER->id, 'instanceid' => $instance->id, 'status' => ENROL_PAYMENTPROOF_STATUS_PENDING))) {
            return get_string('submissionpending', 'enrol_paymentproof');
        }

        return true;
    }

    /**
     * Return an array of valid options for the status.
     *
     * @return array
     */
    protected function get_status_options() {
        $options = array(
            ENROL_INSTANCE_ENABLED  => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        );
        return $options;
    }

    /**
     * Enrol user into course via payment proof submission.
     *
     * @param stdClass $instance
     * @param int $userid
     * @return bool
     */
    public function enrol_user(stdClass $instance, $userid) {
        global $DB, $USER, $CFG;

        $timestart = time();
        $timeend = 0;

        if ($instance->enrolperiod) {
            $timeend = $timestart + $instance->enrolperiod;
        }

        $this->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE, $timestart, $timeend);

        // Send welcome message.
        if ($instance->customint3) {
            $this->email_welcome_message($instance, $DB->get_record('user', array('id' => $userid)));
        }

        return true;
    }

    /**
     * Gets a list of roles that this user can assign for the course.
     *
     * @param context $context
     * @return array
     */
    public function get_assignable_roles($context) {
        global $DB;

        $roles = get_assignable_roles($context);
        
        // Filter it down to the roles selected in the plugin settings.
        if ($this->get_config('roleid')) {
            $roleid = $this->get_config('roleid');
            if (isset($roles[$roleid])) {
                $roles = array($roleid => $roles[$roleid]);
            }
        }
        
        return $roles;
    }

    /**
     * Defines if user can be unenrolled.
     *
     * @param stdClass $instance enrolment instance
     * @param stdClass $ue record from user_enrolments table
     * @return bool
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/paymentproof:unenrol', $context);
    }
}