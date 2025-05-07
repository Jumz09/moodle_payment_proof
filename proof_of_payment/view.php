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
 * Payment proof submission details view page.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once('locallib.php');
require_once($CFG->dirroot . '/enrol/paymentproof/classes/form/review_form.php');

$id = required_param('id', PARAM_INT); // Course ID
$submissionid = required_param('submissionid', PARAM_INT); // Submission ID

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$context = context_course::instance($course->id);

// Get submission record
$submission = $DB->get_record('enrol_paymentproof_submissions', array('id' => $submissionid), '*', MUST_EXIST);

// Security checks
require_login($course, false);

// Get enrolment instance
$instance = $DB->get_record('enrol', array('id' => $submission->instanceid, 'enrol' => 'paymentproof'), '*', MUST_EXIST);

// Check if user has permission to view this submission
$canreview = has_capability('enrol/paymentproof:manage', $context);
$isowner = ($USER->id == $submission->userid);

if (!$canreview && !$isowner) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('cannot_view_submission', 'enrol_paymentproof'));
}

// Set up page
$PAGE->set_context($context);
$PAGE->set_url('/enrol/paymentproof/view.php', array('id' => $course->id, 'submissionid' => $submissionid));
$PAGE->set_title($course->shortname . ': ' . get_string('submission_details', 'enrol_paymentproof'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('course');

// Adding the navigation nodes
$coursenode = $PAGE->navigation->find($course->id, navigation_node::TYPE_COURSE);
if ($coursenode) {
    $url = new moodle_url('/enrol/paymentproof/manage.php', array('id' => $course->id));
    $paymentnode = $coursenode->add(get_string('payment_submissions', 'enrol_paymentproof'), $url);
    $paymentnode->make_active();
}

// Create review form if user has permissions
$mform = null;
if ($canreview && $submission->status == 0) { // Only allow reviews for pending submissions
    $customdata = array(
        'submission' => $submission,
        'course' => $course,
        'context' => $context
    );
    $mform = new \enrol_paymentproof\form\review_form(null, $customdata);
    
    // Form processing
    if ($mform->is_cancelled()) {
        redirect(new moodle_url('/enrol/paymentproof/manage.php', array('id' => $course->id)));
    } else if ($data = $mform->get_data()) {
        // Process the form submission
        $submission->status = $data->status; // 1 = approved, 2 = rejected
        $submission->reviewerid = $USER->id;
        $submission->reviewcomment = $data->reviewcomment;
        $submission->timemodified = time();
        $submission->timereviewed = time();
        
        // Update the submission
        $DB->update_record('enrol_paymentproof_submissions', $submission);
        
        // If approved, enrol the user
        if ($data->status == 1) {
            $enrolment = new stdClass();
            $enrolment->enrol = 'paymentproof';
            $enrolment->status = 0; // active
            $enrolment->courseid = $course->id;
            $enrolment->userid = $submission->userid;
            
            $plugin = enrol_get_plugin('paymentproof');
            $plugin->enrol_user($instance, $submission->userid, $instance->roleid, time(), 0, 0);
            
            // Trigger event
            $event = \enrol_paymentproof\event\submission_approved::create(array(
                'objectid' => $submissionid,
                'context' => $context,
                'courseid' => $course->id,
                'userid' => $USER->id,
                'relateduserid' => $submission->userid,
                'other' => array(
                    'instanceid' => $instance->id
                )
            ));
            $event->trigger();
        } else {
            // Trigger event for rejection
            $event = \enrol_paymentproof\event\submission_rejected::create(array(
                'objectid' => $submissionid,
                'context' => $context,
                'courseid' => $course->id,
                'userid' => $USER->id,
                'relateduserid' => $submission->userid,
                'other' => array(
                    'instanceid' => $instance->id
                )
            ));
            $event->trigger();
        }
        
        // Send notification to the student
        enrol_paymentproof_notify_student($submission->userid, $course, $submissionid, $submission->status);
        
        // Redirect back to the management page
        redirect(new moodle_url('/enrol/paymentproof/manage.php', array('id' => $course->id)),
            get_string('submission_updated', 'enrol_paymentproof'),
            null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Get user info
$submitter = $DB->get_record('user', array('id' => $submission->userid), '*', MUST_EXIST);

// Get reviewer info if submission has been reviewed
$reviewer = null;
if (!empty($submission->reviewerid)) {
    $reviewer = $DB->get_record('user', array('id' => $submission->reviewerid));
}

// Prepare file storage for attachment
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'enrol_paymentproof', 'paymentproof', $submissionid, 'timemodified', false);
$fileurl = '';
if ($files) {
    $file = reset($files);
    $fileurl = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    );
}

// Output starts here
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('submission_details', 'enrol_paymentproof'));

// Display submission details
echo html_writer::start_div('enrol_paymentproof_submission');

// Status indicator
$statuslabel = '';
$statusclass = '';
switch ($submission->status) {
    case 0:
        $statuslabel = get_string('status_pending', 'enrol_paymentproof');
        $statusclass = 'status-pending';
        break;
    case 1:
        $statuslabel = get_string('status_approved', 'enrol_paymentproof');
        $statusclass = 'status-approved';
        break;
    case 2:
        $statuslabel = get_string('status_rejected', 'enrol_paymentproof');
        $statusclass = 'status-rejected';
        break;
}

echo html_writer::div($statuslabel, 'enrol_paymentproof_status ' . $statusclass);

// Submission details table
echo html_writer::start_tag('table', array('class' => 'enrol_paymentproof_details'));

// Course
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('course'));
echo html_writer::tag('td', $course->fullname);
echo html_writer::end_tag('tr');

// Submitter
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('submitter', 'enrol_paymentproof'));
echo html_writer::tag('td', fullname($submitter));
echo html_writer::end_tag('tr');

// Submission date
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('submission_date', 'enrol_paymentproof'));
echo html_writer::tag('td', userdate($submission->timecreated));
echo html_writer::end_tag('tr');

// Payment amount
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('payment_amount', 'enrol_paymentproof'));
echo html_writer::tag('td', $submission->paymentamount);
echo html_writer::end_tag('tr');

// Payment date
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('payment_date', 'enrol_paymentproof'));
echo html_writer::tag('td', userdate($submission->paymentdate));
echo html_writer::end_tag('tr');

// Payment method
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('payment_method', 'enrol_paymentproof'));
echo html_writer::tag('td', $submission->paymentmethod);
echo html_writer::end_tag('tr');

// Transaction ID
if (!empty($submission->transactionid)) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('transaction_id', 'enrol_paymentproof'));
    echo html_writer::tag('td', $submission->transactionid);
    echo html_writer::end_tag('tr');
}

// Notes
if (!empty($submission->notes)) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('notes', 'enrol_paymentproof'));
    echo html_writer::tag('td', format_text($submission->notes, FORMAT_MOODLE));
    echo html_writer::end_tag('tr');
}

// Payment proof attachment
if ($fileurl) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('payment_proof', 'enrol_paymentproof'));
    echo html_writer::tag('td', html_writer::link($fileurl, get_string('view_attachment', 'enrol_paymentproof'), 
        array('class' => 'btn btn-secondary', 'target' => '_blank')));
    echo html_writer::end_tag('tr');
}

// Review details if submission has been reviewed
if ($submission->status > 0) {
    // Reviewer
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('reviewer', 'enrol_paymentproof'));
    echo html_writer::tag('td', fullname($reviewer));
    echo html_writer::end_tag('tr');
    
    // Review date
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('review_date', 'enrol_paymentproof'));
    echo html_writer::tag('td', userdate($submission->timereviewed));
    echo html_writer::end_tag('tr');
    
    // Review comments
    if (!empty($submission->reviewcomment)) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('review_comments', 'enrol_paymentproof'));
        echo html_writer::tag('td', format_text($submission->reviewcomment, FORMAT_MOODLE));
        echo html_writer::end_tag('tr');
    }
}

echo html_writer::end_tag('table');
echo html_writer::end_div();

// Display review form if user has permission and submission is pending
if ($canreview && $submission->status == 0) {
    echo html_writer::start_div('enrol_paymentproof_review_form');
    echo html_writer::tag('h3', get_string('review_submission', 'enrol_paymentproof'));
    $mform->display();
    echo html_writer::end_div();
}

// Back buttons
echo html_writer::start_div('enrol_paymentproof_buttons');
if ($canreview) {
    echo html_writer::link(
        new moodle_url('/enrol/paymentproof/manage.php', array('id' => $course->id)),
        get_string('back_to_submissions', 'enrol_paymentproof'),
        array('class' => 'btn btn-secondary')
    );
} else {
    echo html_writer::link(
        new moodle_url('/course/view.php', array('id' => $course->id)),
        get_string('back_to_course', 'enrol_paymentproof'),
        array('class' => 'btn btn-secondary')
    );
}
echo html_writer::end_div();

echo $OUTPUT->footer();