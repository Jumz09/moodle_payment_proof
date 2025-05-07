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
 * Task to process pending enrollments for payment proof plugin.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paymentproof\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Task to process pending enrollments.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_enrollments extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('processpendingenrollments', 'enrol_paymentproof');
    }

    /**
     * Process pending submissions and enrollments.
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/enrol/paymentproof/locallib.php');

        $plugin = enrol_get_plugin('paymentproof');
        if (!$plugin) {
            mtrace('Payment proof plugin not found');
            return;
        }

        mtrace('Starting processing payment proof enrollments...');

        // Check for expired pending submissions.
        $this->process_expired_submissions();

        // Check for auto-approve if that setting is enabled.
        $this->process_auto_approve_submissions();

        // Send reminders for pending submissions.
        $this->send_pending_reminders();

        mtrace('Finished processing payment proof enrollments.');
    }

    /**
     * Process submissions that have been waiting too long for review.
     */
    protected function process_expired_submissions() {
        global $DB;

        // Get global setting for submission expiration (in days).
        $expirydays = get_config('enrol_paymentproof', 'submissionexpiry');
        
        if (!$expirydays) {
            return; // Feature disabled.
        }

        $expirydate = time() - ($expirydays * DAYSECS);
        
        // Find submissions that are pending and older than the expiry date.
        $sql = "SELECT ps.*, e.id as enrolid, e.customint3 as expireaction
                FROM {enrol_paymentproof_submissions} ps
                JOIN {enrol} e ON e.courseid = ps.courseid AND e.enrol = 'paymentproof'
                WHERE ps.status = 'pending'
                AND ps.timecreated < :expirydate";
                
        $params = array('expirydate' => $expirydate);
        $submissions = $DB->get_records_sql($sql, $params);
        
        if (!$submissions) {
            mtrace('- No expired submissions found');
            return;
        }
        
        mtrace('- Found ' . count($submissions) . ' expired submissions');
        
        foreach ($submissions as $submission) {
            // Check what action to take based on instance setting.
            // customint3: 0 = do nothing, 1 = auto-approve, 2 = auto-reject
            switch ($submission->expireaction) {
                case 1: // Auto-approve
                    mtrace('  - Auto-approving expired submission #' . $submission->id . ' for course ' . $submission->courseid);
                    $submission->status = 'approved';
                    $submission->feedback = get_string('autoapproved', 'enrol_paymentproof');
                    $submission->reviewerid = 0; // System
                    $submission->timereview = time();
                    $DB->update_record('enrol_paymentproof_submissions', $submission);
                    
                    // Trigger approval event.
                    $context = \context_course::instance($submission->courseid);
                    $event = \enrol_paymentproof\event\submission_approved::create(array(
                        'objectid' => $submission->id,
                        'context' => $context,
                        'courseid' => $submission->courseid,
                        'relateduserid' => $submission->userid,
                    ));
                    $event->trigger();
                    break;
                    
                case 2: // Auto-reject
                    mtrace('  - Auto-rejecting expired submission #' . $submission->id . ' for course ' . $submission->courseid);
                    $submission->status = 'rejected';
                    $submission->feedback = get_string('autorejected', 'enrol_paymentproof');
                    $submission->reviewerid = 0; // System
                    $submission->timereview = time();
                    $DB->update_record('enrol_paymentproof_submissions', $submission);
                    
                    // Trigger rejection event.
                    $context = \context_course::instance($submission->courseid);
                    $event = \enrol_paymentproof\event\submission_rejected::create(array(
                        'objectid' => $submission->id,
                        'context' => $context,
                        'courseid' => $submission->courseid,
                        'relateduserid' => $submission->userid,
                    ));
                    $event->trigger();
                    break;
                    
                default: // Do nothing
                    mtrace('  - No action configured for expired submission #' . $submission->id);
                    break;
            }
        }
    }

    /**
     * Process submissions that should be auto-approved.
     */
    protected function process_auto_approve_submissions() {
        global $DB;
        
        // Check for instances with auto-approve enabled (customint4 = 1).
        $sql = "SELECT ps.*, e.id as enrolid
                FROM {enrol_paymentproof_submissions} ps
                JOIN {enrol} e ON e.courseid = ps.courseid AND e.enrol = 'paymentproof'
                WHERE ps.status = 'pending'
                AND e.customint4 = 1";
                
        $submissions = $DB->get_records_sql($sql);
        
        if (!$submissions) {
            mtrace('- No submissions for auto-approval');
            return;
        }
        
        mtrace('- Found ' . count($submissions) . ' submissions for auto-approval');
        
        foreach ($submissions as $submission) {
            mtrace('  - Auto-approving submission #' . $submission->id . ' for course ' . $submission->courseid);
            
            $submission->status = 'approved';
            $submission->feedback = get_string('autoapproved', 'enrol_paymentproof');
            $submission->reviewerid = 0; // System
            $submission->timereview = time();
            $DB->update_record('enrol_paymentproof_submissions', $submission);
            
            // Trigger approval event.
            $context = \context_course::instance($submission->courseid);
            $event = \enrol_paymentproof\event\submission_approved::create(array(
                'objectid' => $submission->id,
                'context' => $context,
                'courseid' => $submission->courseid,
                'relateduserid' => $submission->userid,
            ));
            $event->trigger();
        }
    }

    /**
     * Send reminders for pending submissions.
     */
    protected function send_pending_reminders() {
        global $DB;
        
        // Check if reminder feature is enabled.
        $reminderenabled = get_config('enrol_paymentproof', 'reminderenabled');
        if (!$reminderenabled) {
            return;
        }
        
        // Get reminder threshold (in hours).
        $reminderthreshold = get_config('enrol_paymentproof', 'reminderthreshold');
        if (!$reminderthreshold) {
            $reminderthreshold = 24; // Default: 24 hours
        }
        
        $thresholdtime = time() - ($reminderthreshold * HOURSECS);
        
        // Find courses with pending submissions older than the threshold.
        $sql = "SELECT DISTINCT e.courseid, c.fullname as coursename, e.customint5 as notification_recipient
                FROM {enrol_paymentproof_submissions} ps
                JOIN {enrol} e ON e.courseid = ps.courseid AND e.enrol = 'paymentproof'
                JOIN {course} c ON c.id = e.courseid
                WHERE ps.status = 'pending'
                AND ps.timecreated < :thresholdtime
                AND (ps.timereminder IS NULL OR ps.timereminder < :remindertime)";
                
        // Don't send more than one reminder per day.
        $remindertime = time() - DAYSECS;
        
        $params = array(
            'thresholdtime' => $thresholdtime,
            'remindertime' => $remindertime
        );
        
        $courses = $DB->get_records_sql($sql, $params);
        
        if (!$courses) {
            mtrace('- No courses with pending submissions requiring reminders');
            return;
        }
        
        mtrace('- Found ' . count($courses) . ' courses with pending submissions requiring reminders');
        
        foreach ($courses as $course) {
            mtrace('  - Sending reminders for course: ' . $course->coursename);
            
            // Get pending submissions for this course.
            $submissions = $DB->get_records('enrol_paymentproof_submissions', array(
                'courseid' => $course->courseid,
                'status' => 'pending'
            ));
            
            // Send reminder to teachers.
            $this->send_teacher_reminder($course, $submissions);
            
            // Update reminder time on submissions.
            foreach ($submissions as $submission) {
                $DB->set_field('enrol_paymentproof_submissions', 'timereminder', time(), array('id' => $submission->id));
            }
        }
    }

    /**
     * Send reminder to teachers about pending submissions.
     *
     * @param \stdClass $course Course object with additional fields.
     * @param array $submissions Array of submission records.
     */
    protected function send_teacher_reminder($course, $submissions) {
        global $DB;
        
        // Determine notification recipients based on setting.
        // customint5: 0 = course teachers, 1 = specific user(s), 2 = both
        $recipients = array();
        
        // Add course teachers if needed.
        if ($course->notification_recipient == 0 || $course->notification_recipient == 2) {
            $context = \context_course::instance($course->courseid);
            $teachers = get_enrolled_users($context, 'enrol/paymentproof:manage');
            foreach ($teachers as $teacher) {
                $recipients[$teacher->id] = $teacher;
            }
        }
        
        // Add specific users if needed.
        if ($course->notification_recipient == 1 || $course->notification_recipient == 2) {
            $instance = $DB->get_record('enrol', array('courseid' => $course->courseid, 'enrol' => 'paymentproof'));
            if (!empty($instance->customtext2)) { // Specific user IDs in customtext2
                $userids = explode(',', $instance->customtext2);
                foreach ($userids as $userid) {
                    $userid = trim($userid);
                    if (is_numeric($userid)) {
                        $user = $DB->get_record('user', array('id' => $userid));
                        if ($user) {
                            $recipients[$user->id] = $user;
                        }
                    }
                }
            }
        }
        
        if (empty($recipients)) {
            mtrace('    - No recipients found for notifications');
            return;
        }
        
        mtrace('    - Sending notifications to ' . count($recipients) . ' recipients');
        
        // Build the message.
        $subject = get_string('remindersubject', 'enrol_paymentproof', $course->coursename);
        
        // Create a list of pending students for the message body.
        $submissionlist = '';
        foreach ($submissions as $submission) {
            $student = $DB->get_record('user', array('id' => $submission->userid));
            $submissiondate = userdate($submission->timecreated);
            $submissionlist .= get_string('remindersubmissionitem', 'enrol_paymentproof', array(
                'user' => fullname($student),
                'date' => $submissiondate
            ));
        }
        
        // Send the message to each recipient.
        foreach ($recipients as $recipient) {
            $messagedata = new \stdClass();
            $messagedata->component = 'enrol_paymentproof';
            $messagedata->name = 'submission_reminder';
            $messagedata->userfrom = \core_user::get_noreply_user();
            $messagedata->userto = $recipient;
            $messagedata->subject = $subject;
            $messagedata->fullmessage = get_string('remindertext', 'enrol_paymentproof', array(
                'course' => $course->coursename,
                'count' => count($submissions),
                'submissions' => $submissionlist,
                'url' => new \moodle_url('/enrol/paymentproof/manage.php', array('id' => $course->courseid))
            ));
            $messagedata->fullmessageformat = FORMAT_HTML;
            $messagedata->fullmessagehtml = get_string('remindertexthtml', 'enrol_paymentproof', array(
                'course' => $course->coursename,
                'count' => count($submissions),
                'submissions' => $submissionlist,
                'url' => new \moodle_url('/enrol/paymentproof/manage.php', array('id' => $course->courseid))
            ));
            $messagedata->smallmessage = get_string('remindersmall', 'enrol_paymentproof', array(
                'course' => $course->coursename, 
                'count' => count($submissions)
            ));
            $messagedata->notification = 1;
            $messagedata->contexturl = new \moodle_url('/enrol/paymentproof/manage.php', array('id' => $course->courseid));
            $messagedata->contexturlname = get_string('managesubmissions', 'enrol_paymentproof');
            
            message_send($messagedata);
        }
    }
}