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
 * Payment proof enrollment plugin local library.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/paymentproof/lib.php');

/**
 * Create a new payment proof submission.
 *
 * @param stdClass $data The submission data
 * @param object $file The uploaded file
 * @return int The ID of the new submission
 */
function enrol_paymentproof_create_submission($data, $file) {
    global $DB, $USER;

    // Prepare submission record
    $submission = new stdClass();
    $submission->courseid = $data->courseid;
    $submission->instanceid = $data->instanceid;
    $submission->userid = $USER->id;
    $submission->paymenttype = $data->paymenttype;
    $submission->paymentamount = $data->paymentamount;
    $submission->paymentdate = $data->paymentdate;
    $submission->paymentref = $data->paymentref;
    $submission->notes = $data->notes;
    $submission->status = ENROL_PAYMENTPROOF_STATUS_PENDING;
    $submission->timecreated = time();
    $submission->timemodified = time();
    
    // Save submission to database
    $submission->id = $DB->insert_record('enrol_paymentproof_submissions', $submission);
    
    // Save the file
    if ($file) {
        $context = context_course::instance($data->courseid);
        $fs = get_file_storage();
        
        // Prepare file record
        $fileinfo = array(
            'contextid' => $context->id,
            'component' => 'enrol_paymentproof',
            'filearea' => 'paymentproof',
            'itemid' => $submission->id,
            'filepath' => '/',
            'filename' => $file->get_filename(),
        );
        
        // Create file from uploaded file
        $fs->create_file_from_storedfile($fileinfo, $file);
    }
    
    // Send notification if enabled
    $instance = $DB->get_record('enrol', array('id' => $data->instanceid));
    if (!empty($instance->customint4)) { // customint4 = notification setting
        enrol_paymentproof_send_notification('submission', $submission);
    }
    
    return $submission->id;
}

/**
 * Update a payment proof submission status.
 *
 * @param int $submissionid The submission ID
 * @param int $status The new status
 * @param string $feedback Optional feedback message
 * @param int $reviewerid The reviewer's user ID
 * @return bool Success/failure
 */
function enrol_paymentproof_update_submission_status($submissionid, $status, $feedback = '', $reviewerid = 0) {
    global $DB, $USER;
    
    // Get submission
    $submission = $DB->get_record('enrol_paymentproof_submissions', array('id' => $submissionid), '*', MUST_EXIST);
    
    // Update submission
    $submission->status = $status;
    $submission->feedback = $feedback;
    $submission->reviewerid = $reviewerid ? $reviewerid : $USER->id;
    $submission->timemodified = time();
    $submission->timereviewed = time();
    
    $result = $DB->update_record('enrol_paymentproof_submissions', $submission);
    
    if ($result) {
        // Handle enrollment if approved
        if ($status == ENROL_PAYMENTPROOF_STATUS_APPROVED) {
            $instance = $DB->get_record('enrol', array('id' => $submission->instanceid));
            if ($instance) {
                $plugin = enrol_get_plugin('paymentproof');
                $plugin->enrol_user($instance, $submission->userid);
            }
            
            // Send approval notification
            $instance = $DB->get_record('enrol', array('id' => $submission->instanceid));
            if ($instance->customint4) { // customint4 = notification setting
                enrol_paymentproof_send_notification('approval', $submission, $feedback);
            }
        } else if ($status == ENROL_PAYMENTPROOF_STATUS_REJECTED) {
            // Send rejection notification
            $instance = $DB->get_record('enrol', array('id' => $submission->instanceid));
            if ($instance->customint4) { // customint4 = notification setting
                enrol_paymentproof_send_notification('rejection', $submission, $feedback);
            }
        }
    }
    
    return $result;
}

/**
 * Get submission details including user and course information.
 *
 * @param int $submissionid The submission ID
 * @return object|false The submission details or false if not found
 */
function enrol_paymentproof_get_submission_details($submissionid) {
    global $DB;
    
    $sql = "SELECT s.*, u.firstname, u.lastname, u.email, u.picture, u.imagealt, 
                   c.fullname as coursename, c.shortname as courseshortname,
                   r.firstname as reviewerfirstname, r.lastname as reviewerlastname
            FROM {enrol_paymentproof_submissions} s
            JOIN {user} u ON s.userid = u.id
            JOIN {course} c ON s.courseid = c.id
            LEFT JOIN {user} r ON s.reviewerid = r.id
            WHERE s.id = :submissionid";
    
    return $DB->get_record_sql($sql, array('submissionid' => $submissionid));
}

/**
 * Check if user has pending submissions for a course.
 *
 * @param int $userid The user ID
 * @param int $courseid The course ID
 * @return bool True if user has pending submissions
 */
function enrol_paymentproof_has_pending_submission($userid, $courseid) {
    global $DB;
    
    $params = array(
        'userid' => $userid,
        'courseid' => $courseid,
        'status' => ENROL_PAYMENTPROOF_STATUS_PENDING
    );
    
    return $DB->record_exists('enrol_paymentproof_submissions', $params);
}

/**
 * Get payment type options.
 *
 * @return array Array of payment type options
 */
function enrol_paymentproof_get_payment_types() {
    return array(
        'bank' => get_string('paymenttype_bank', 'enrol_paymentproof'),
        'cash' => get_string('paymenttype_cash', 'enrol_paymentproof'),
        'check' => get_string('paymenttype_check', 'enrol_paymentproof'),
        'credit' => get_string('paymenttype_credit', 'enrol_paymentproof'),
        'other' => get_string('paymenttype_other', 'enrol_paymentproof')
    );
}

/**
 * Count submissions by status for a course.
 *
 * @param int $courseid The course ID
 * @return object Object with counts for each status
 */
function enrol_paymentproof_count_submissions_by_status($courseid) {
    global $DB;
    
    $counts = new stdClass();
    $counts->pending = 0;
    $counts->approved = 0;
    $counts->rejected = 0;
    $counts->total = 0;
    
    $sql = "SELECT status, COUNT(*) as count
            FROM {enrol_paymentproof_submissions}
            WHERE courseid = :courseid
            GROUP BY status";
    
    $results = $DB->get_records_sql($sql, array('courseid' => $courseid));
    
    foreach ($results as $result) {
        switch ($result->status) {
            case ENROL_PAYMENTPROOF_STATUS_PENDING:
                $counts->pending = $result->count;
                break;
            case ENROL_PAYMENTPROOF_STATUS_APPROVED:
                $counts->approved = $result->count;
                break;
            case ENROL_PAYMENTPROOF_STATUS_REJECTED:
                $counts->rejected = $result->count;
                break;
        }
        $counts->total += $result->count;
    }
    
    return $counts;
}

/**
 * Delete a submission and its associated files.
 *
 * @param int $submissionid The submission ID
 * @return bool Success/failure
 */
function enrol_paymentproof_delete_submission($submissionid) {
    global $DB;
    
    $submission = $DB->get_record('enrol_paymentproof_submissions', array('id' => $submissionid), '*', MUST_EXIST);
    $context = context_course::instance($submission->courseid);
    
    // Delete associated files
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'enrol_paymentproof', 'paymentproof', $submissionid);
    
    // Delete submission record
    return $DB->delete_records('enrol_paymentproof_submissions', array('id' => $submissionid));
}