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
 * Payment proof upload page.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once('locallib.php');
require_once($CFG->dirroot . '/enrol/paymentproof/classes/form/upload_form.php');

$id = required_param('id', PARAM_INT); // Course ID
$instanceid = optional_param('instanceid', 0, PARAM_INT); // Enrollment instance ID

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$context = context_course::instance($course->id);

// Find enrolment instance
if ($instanceid) {
    $instance = $DB->get_record('enrol', array('id' => $instanceid, 'enrol' => 'paymentproof', 'status' => 0), '*', MUST_EXIST);
} else {
    $instances = $DB->get_records('enrol', array('courseid' => $course->id, 'enrol' => 'paymentproof', 'status' => 0));
    $instance = reset($instances);
    if (!$instance) {
        redirect(new moodle_url('/course/view.php', array('id' => $course->id)),
            get_string('paymentproof_not_enabled', 'enrol_paymentproof'),
            null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

require_login($course);

// Check if user is already enrolled
if (is_enrolled($context, null, '', true)) {
    redirect(new moodle_url('/course/view.php', array('id' => $course->id)),
        get_string('already_enrolled', 'enrol_paymentproof'),
        null,
        \core\output\notification::NOTIFY_INFO);
}

// Check if user has pending submission
$existing_submission = $DB->get_record('enrol_paymentproof_submissions', array(
    'userid' => $USER->id,
    'courseid' => $course->id,
    'instanceid' => $instance->id,
    'status' => 0 // Pending
));

if ($existing_submission) {
    redirect(new moodle_url('/enrol/paymentproof/view.php', array(
        'id' => $course->id,
        'submissionid' => $existing_submission->id
    )),
        get_string('submission_pending', 'enrol_paymentproof'),
        null,
        \core\output\notification::NOTIFY_INFO);
}

// Set up page
$PAGE->set_context($context);
$PAGE->set_url('/enrol/paymentproof/upload.php', array('id' => $course->id, 'instanceid' => $instance->id));
$PAGE->set_title($course->shortname . ': ' . get_string('pluginname', 'enrol_paymentproof'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('course');

// Create form
$customdata = array(
    'instance' => $instance,
    'course' => $course,
    'context' => $context
);
$mform = new \enrol_paymentproof\form\upload_form(null, $customdata);

// Form processing
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', array('id' => $course->id)));
} else if ($data = $mform->get_data()) {
    // Process form submission
    $submission = new stdClass();
    $submission->instanceid = $instance->id;
    $submission->courseid = $course->id;
    $submission->userid = $USER->id;
    $submission->paymentamount = $data->paymentamount;
    $submission->paymentdate = $data->paymentdate;
    $submission->paymentmethod = $data->paymentmethod;
    $submission->transactionid = $data->transactionid;
    $submission->notes = $data->notes;
    $submission->timecreated = time();
    $submission->timemodified = time();
    $submission->status = 0; // 0 = pending
    
    // Save the submission
    $submissionid = $DB->insert_record('enrol_paymentproof_submissions', $submission);
    
    // Process uploaded file
    if ($mform->get_file_content('paymentproof')) {
        file_save_draft_area_files(
            $data->paymentproof,
            $context->id,
            'enrol_paymentproof',
            'paymentproof',
            $submissionid,
            array('subdirs' => 0, 'maxfiles' => 1)
        );
    }
    
    // Trigger event
    $event = \enrol_paymentproof\event\submission_created::create(array(
        'objectid' => $submissionid,
        'context' => $context,
        'courseid' => $course->id,
        'userid' => $USER->id,
        'other' => array(
            'instanceid' => $instance->id
        )
    ));
    $event->trigger();
    
    // Send notification to teachers/managers
    enrol_paymentproof_notify_managers($course, $USER, $submissionid);
    
    // Redirect to view page
    redirect(new moodle_url('/enrol/paymentproof/view.php', array(
        'id' => $course->id,
        'submissionid' => $submissionid
    )),
        get_string('submission_success', 'enrol_paymentproof'),
        null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Output starts here
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('upload_payment_proof', 'enrol_paymentproof'));

// Display payment instructions if set
$instructions = !empty($instance->customtext1) ? $instance->customtext1 : get_string('default_payment_instructions', 'enrol_paymentproof');
echo html_writer::div($instructions, 'enrol_paymentproof_instructions');

// Display form
$mform->display();

echo $OUTPUT->footer();