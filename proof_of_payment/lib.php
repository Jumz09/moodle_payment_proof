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
 * Payment proof enrollment plugin library.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Submission status constants
define('ENROL_PAYMENTPROOF_STATUS_PENDING', 0);
define('ENROL_PAYMENTPROOF_STATUS_APPROVED', 1);
define('ENROL_PAYMENTPROOF_STATUS_REJECTED', 2);

/**
 * Map status codes to language strings.
 *
 * @param int $status The status code
 * @return string The language string key
 */
function enrol_paymentproof_get_status_string($status) {
    switch ($status) {
        case ENROL_PAYMENTPROOF_STATUS_PENDING:
            return 'status_pending';
        case ENROL_PAYMENTPROOF_STATUS_APPROVED:
            return 'status_approved';
        case ENROL_PAYMENTPROOF_STATUS_REJECTED:
            return 'status_rejected';
        default:
            return 'status_unknown';
    }
}

/**
 * Send email notification to users.
 *
 * @param string $type The type of notification (submission, approval, rejection)
 * @param stdClass $submission The submission record
 * @param string $message Optional message to include
 * @return bool Success/failure
 */
function enrol_paymentproof_send_notification($type, $submission, $message = '') {
    global $DB, $CFG;

    $user = $DB->get_record('user', array('id' => $submission->userid));
    if (!$user) {
        return false;
    }

    $course = $DB->get_record('course', array('id' => $submission->courseid));
    if (!$course) {
        return false;
    }

    $instance = $DB->get_record('enrol', array('id' => $submission->instanceid));
    if (!$instance) {
        return false;
    }

    $site = get_site();
    $subject = '';
    $messagetext = '';
    $messagehtml = '';

    switch ($type) {
        case 'submission':
            // Notification to teachers/admins
            $subject = get_string('notification_submission_subject', 'enrol_paymentproof', 
                array('course' => format_string($course->fullname), 'site' => format_string($site->fullname)));
            
            $messagetext = get_string('notification_submission_text', 'enrol_paymentproof', 
                array(
                    'course' => format_string($course->fullname),
                    'site' => format_string($site->fullname),
                    'user' => fullname($user),
                    'link' => $CFG->wwwroot . '/enrol/paymentproof/view.php?id=' . $submission->id
                ));
            
            $messagehtml = get_string('notification_submission_html', 'enrol_paymentproof', 
                array(
                    'course' => format_string($course->fullname),
                    'site' => format_string($site->fullname),
                    'user' => fullname($user),
                    'link' => $CFG->wwwroot . '/enrol/paymentproof/view.php?id=' . $submission->id
                ));
            
            // Send to all users who can review submissions
            $context = context_course::instance($course->id);
            $users = get_enrolled_users($context, 'enrol/paymentproof:manage');
            
            foreach ($users as $recipient) {
                email_to_user($recipient, $user, $subject, $messagetext, $messagehtml);
            }
            break;
            
        case 'approval':
            // Notification to student
            $subject = get_string('notification_approval_subject', 'enrol_paymentproof', 
                array('course' => format_string($course->fullname), 'site' => format_string($site->fullname)));
            
            $messagetext = get_string('notification_approval_text', 'enrol_paymentproof', 
                array(
                    'course' => format_string($course->fullname),
                    'site' => format_string($site->fullname),
                    'message' => $message,
                    'link' => $CFG->wwwroot . '/course/view.php?id=' . $course->id
                ));
            
            $messagehtml = get_string('notification_approval_html', 'enrol_paymentproof', 
                array(
                    'course' => format_string($course->fullname),
                    'site' => format_string($site->fullname),
                    'message' => $message,
                    'link' => $CFG->wwwroot . '/course/view.php?id=' . $course->id
                ));
            
            email_to_user($user, core_user::get_noreply_user(), $subject, $messagetext, $messagehtml);
            break;
            
        case 'rejection':
            // Notification to student
            $subject = get_string('notification_rejection_subject', 'enrol_paymentproof', 
                array('course' => format_string($course->fullname), 'site' => format_string($site->fullname)));
            
            $messagetext = get_string('notification_rejection_text', 'enrol_paymentproof', 
                array(
                    'course' => format_string($course->fullname),
                    'site' => format_string($site->fullname),
                    'message' => $message,
                    'link' => $CFG->wwwroot . '/enrol/paymentproof/upload.php?courseid=' . $course->id . '&instanceid=' . $instance->id
                ));
            
            $messagehtml = get_string('notification_rejection_html', 'enrol_paymentproof', 
                array(
                    'course' => format_string($course->fullname),
                    'site' => format_string($site->fullname),
                    'message' => $message,
                    'link' => $CFG->wwwroot . '/enrol/paymentproof/upload.php?courseid=' . $course->id . '&instanceid=' . $instance->id
                ));
            
            email_to_user($user, core_user::get_noreply_user(), $subject, $messagetext, $messagehtml);
            break;
    }

    return true;
}

/**
 * Get file storage object for payment proof files.
 *
 * @param int $contextid The context ID
 * @param int $submissionid The submission ID
 * @return stored_file|false The file object or false if not found
 */
function enrol_paymentproof_get_file($contextid, $submissionid) {
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $contextid,
        'enrol_paymentproof',
        'paymentproof',
        $submissionid,
        'id DESC',
        false
    );
    
    if ($files) {
        return reset($files);
    }
    
    return false;
}

/**
 * Get submissions for a course.
 *
 * @param int $courseid The course ID
 * @param int $status Optional status filter
 * @return array Array of submission records
 */
function enrol_paymentproof_get_submissions($courseid, $status = null) {
    global $DB;
    
    $params = array('courseid' => $courseid);
    if ($status !== null) {
        $params['status'] = $status;
    }
    
    $sql = "SELECT s.*, u.firstname, u.lastname, u.email
            FROM {enrol_paymentproof_submissions} s
            JOIN {user} u ON s.userid = u.id
            WHERE s.courseid = :courseid";
    
    if ($status !== null) {
        $sql .= " AND s.status = :status";
    }
    
    $sql .= " ORDER BY s.timecreated DESC";
    
    return $DB->get_records_sql($sql, $params);
}

/**
 * Serves payment proof files.
 *
 * @param stdClass $course The course object
 * @param stdClass $cm The course module
 * @param stdClass $context The context
 * @param string $filearea The file area
 * @param array $args Extra arguments
 * @param bool $forcedownload Whether or not to force download
 * @param array $options Additional options
 * @return bool
 */
function enrol_paymentproof_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $DB, $CFG, $USER;

    if ($context->contextlevel != CONTEXT_COURSE) {
        return false;
    }

    if ($filearea !== 'paymentproof') {
        return false;
    }

    require_login($course);

    $submissionid = (int)array_shift($args);
    $submission = $DB->get_record('enrol_paymentproof_submissions', array('id' => $submissionid), '*', MUST_EXIST);

    // Check if user can access this file
    $canview = false;
    
    // Owner can view
    if ($submission->userid == $USER->id) {
        $canview = true;
    }
    
    // Users with manage capability can view
    if (has_capability('enrol/paymentproof:manage', $context)) {
        $canview = true;
    }
    
    if (!$canview) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/enrol_paymentproof/$filearea/$submissionid/$relativepath";
    $file = $fs->get_file_by_hash(sha1($fullpath));
    
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}