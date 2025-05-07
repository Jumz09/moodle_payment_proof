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
 * Event observers for enrol_paymentproof.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paymentproof;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/paymentproof/locallib.php');

/**
 * Event observer class.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Observer for submission approved event.
     * Enrolls the user in the course when a payment proof is approved.
     *
     * @param \enrol_paymentproof\event\submission_approved $event The event.
     * @return bool True on success.
     */
    public static function submission_approved(\enrol_paymentproof\event\submission_approved $event) {
        global $DB;
        
        $submission = $DB->get_record('enrol_paymentproof_submissions', array('id' => $event->objectid));
        if (!$submission) {
            return false;
        }
        
        // Get the enrolment instance.
        $instance = $DB->get_record('enrol', array('courseid' => $submission->courseid, 'enrol' => 'paymentproof', 'status' => ENROL_INSTANCE_ENABLED));
        if (!$instance) {
            return false;
        }
        
        // Enroll the user.
        $plugin = enrol_get_plugin('paymentproof');
        if (!$plugin) {
            return false;
        }
        
        // Check if user is already enrolled.
        $context = \context_course::instance($submission->courseid);
        if (is_enrolled($context, $submission->userid)) {
            return true; // User is already enrolled.
        }
        
        // Enrol the user.
        $timestart = time();
        $timeend = 0;
        
        // If there's a duration set in the enrolment instance, calculate end time.
        if ($instance->enrolperiod) {
            $timeend = $timestart + $instance->enrolperiod;
        }
        
        $plugin->enrol_user($instance, $submission->userid, $instance->roleid, $timestart, $timeend);
        
        // Trigger event for user enrolled via payment proof.
        $params = array(
            'context' => $context,
            'objectid' => $instance->id,
            'courseid' => $submission->courseid,
            'relateduserid' => $submission->userid,
        );
        $event = \core\event\user_enrolment_created::create($params);
        $event->trigger();
        
        // Send enrollment notification if enabled.
        if ($instance->customint1) { // customint1 represents notification setting
            $plugin->send_enrollment_notification($instance, $submission->userid);
        }
        
        return true;
    }
    
    /**
     * Observer for submission rejected event.
     * Handles any necessary actions when a payment proof is rejected.
     *
     * @param \enrol_paymentproof\event\submission_rejected $event The event.
     * @return bool True on success.
     */
    public static function submission_rejected(\enrol_paymentproof\event\submission_rejected $event) {
        global $DB;
        
        $submission = $DB->get_record('enrol_paymentproof_submissions', array('id' => $event->objectid));
        if (!$submission) {
            return false;
        }
        
        // Get the enrolment instance.
        $instance = $DB->get_record('enrol', array('courseid' => $submission->courseid, 'enrol' => 'paymentproof', 'status' => ENROL_INSTANCE_ENABLED));
        if (!$instance) {
            return false;
        }
        
        // Send rejection notification if enabled.
        if ($instance->customint2) { // customint2 represents rejection notification setting
            $plugin = enrol_get_plugin('paymentproof');
            $plugin->send_rejection_notification($instance, $submission->userid, $submission->feedback);
        }
        
        return true;
    }
    
    /**
     * Observer for course deleted event.
     * Cleans up payment proof submissions when a course is deleted.
     *
     * @param \core\event\course_deleted $event The event.
     * @return bool True on success.
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;
        
        // Delete all payment proof submissions for this course.
        $DB->delete_records('enrol_paymentproof_submissions', array('courseid' => $event->objectid));
        
        return true;
    }
}