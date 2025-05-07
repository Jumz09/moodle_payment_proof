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
 * Manage payment proof submissions.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/enrol/paymentproof/locallib.php');
require_once($CFG->libdir.'/tablelib.php');

$courseid = required_param('courseid', PARAM_INT);
$status = optional_param('status', -1, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('enrol/paymentproof:manage', $context);

$PAGE->set_url('/enrol/paymentproof/manage.php', array('courseid' => $courseid));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managesubmissions', 'enrol_paymentproof'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('managesubmissions', 'enrol_paymentproof'));

// Process bulk actions if any
$bulkaction = optional_param('bulkaction', '', PARAM_ALPHA);
$submissionids = optional_param_array('submissionid', array(), PARAM_INT);

if ($bulkaction && !empty($submissionids) && confirm_sesskey()) {
    switch ($bulkaction) {
        case 'approve':
            foreach ($submissionids as $submissionid) {
                enrol_paymentproof_update_submission_status($submissionid, ENROL_PAYMENTPROOF_STATUS_APPROVED);
            }
            redirect($PAGE->url, get_string('bulkapproved', 'enrol_paymentproof'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
            
        case 'reject':
            foreach ($submissionids as $submissionid) {
                enrol_paymentproof_update_submission_status($submissionid, ENROL_PAYMENTPROOF_STATUS_REJECTED);
            }
            redirect($PAGE->url, get_string('bulkrejected', 'enrol_paymentproof'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
            
        case 'delete':
            foreach ($submissionids as $submissionid) {
                enrol_paymentproof_delete_submission($submissionid);
            }
            redirect($PAGE->url, get_string('bulkdeleted', 'enrol_paymentproof'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
    }
}

// Get statistics
$counts = enrol_paymentproof_count_submissions_by_status($courseid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managesubmissions', 'enrol_paymentproof'));

// Display tabs for different statuses
$tabs = array();
$tabs[] = new tabobject('all', new moodle_url('/enrol/paymentproof/manage.php', array('courseid' => $courseid, 'status' => -1)),
    get_string('all') . ' (' . $counts->total . ')');
$tabs[] = new tabobject('pending', new moodle_url('/enrol/paymentproof/manage.php', array('courseid' => $courseid, 'status' => ENROL_PAYMENTPROOF_STATUS_PENDING)),
    get_string('status_pending', 'enrol_paymentproof') . ' (' . $counts->pending . ')');
$tabs[] = new tabobject('approved', new moodle_url('/enrol/paymentproof/manage.php', array('courseid' => $courseid, 'status' => ENROL_PAYMENTPROOF_STATUS_APPROVED)),
    get_string('status_approved', 'enrol_paymentproof') . ' (' . $counts->approved . ')');
$tabs[] = new tabobject('rejected', new moodle_url('/enrol/paymentproof/manage.php', array('courseid' => $courseid, 'status' => ENROL_PAYMENTPROOF_STATUS_REJECTED)),
    get_string('status_rejected', 'enrol_paymentproof') . ' (' . $counts->rejected . ')');

echo $OUTPUT->tabtree($tabs, $status == -1 ? 'all' : ($status == ENROL_PAYMENTPROOF_STATUS_PENDING ? 'pending' : 
    ($status == ENROL_PAYMENTPROOF_STATUS_APPROVED ? 'approved' : 'rejected')));

// Get submissions based on selected status
$params = array('courseid' => $courseid);
$statussql = '';
if ($status != -1) {
    $statussql = " AND s.status = :status";
    $params['status'] = $status;
}

$sql = "SELECT s.*, u.firstname, u.lastname, u.email
        FROM {enrol_paymentproof_submissions} s
        JOIN {user} u ON s.userid = u.id
        WHERE s.courseid = :courseid $statussql
        ORDER BY s.timecreated DESC";

$submissions = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
$totalcount = $DB->count_records_sql("SELECT COUNT(*) FROM {enrol_paymentproof_submissions} s WHERE s.courseid = :courseid $statussql", $params);

if (empty($submissions)) {
    echo $OUTPUT->notification(get_string('nosubmissions', 'enrol_paymentproof'), 'info');
} else {
    // Start form for bulk actions
    echo html_writer::start_tag('form', array('id' => 'submissionsform', 'method' => 'post', 'action' => $PAGE->url));
    echo html_writer::input_hidden_params($PAGE->url);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));

    // Create table
    $table = new html_table();
    $table->id = 'submissions';
    $table->attributes['class'] = 'generaltable';
    $table->head = array(
        html_writer::checkbox('selectall', 1, false, '', array('id' => 'selectall')),
        get_string('student', 'enrol_paymentproof'),
        get_string('paymenttype', 'enrol_paymentproof'),
        get_string('paymentamount', 'enrol_paymentproof'),
        get_string('paymentdate', 'enrol_paymentproof'),
        get_string('timecreated', 'enrol_paymentproof'),
        get_string('status', 'enrol_paymentproof'),
        get_string('actions', 'enrol_paymentproof')
    );
    $table->colclasses = array('center', '', '', '', '', '', '', 'actions');

    foreach ($submissions as $submission) {
        $viewurl = new moodle_url('/enrol/paymentproof/view.php', array('id' => $submission->id));
        $studentname = html_writer::link($viewurl, fullname($submission));
        
        // Status cell with appropriate color
        $statusclass = '';
        switch ($submission->status) {
            case ENROL_PAYMENTPROOF_STATUS_PENDING:
                $statusclass = 'badge badge-warning';
                break;
            case ENROL_PAYMENTPROOF_STATUS_APPROVED:
                $statusclass = 'badge badge-success';
                break;
            case ENROL_PAYMENTPROOF_STATUS_REJECTED:
                $statusclass = 'badge badge-danger';
                break;
        }
        $status = html_writer::span(get_string(enrol_paymentproof_get_status_string($submission->status), 'enrol_paymentproof'), $statusclass);
        
        // Actions
        $actions = html_writer::link($viewurl, $OUTPUT->pix_icon('t/preview', get_string('view')));
        
        if ($submission->status == ENROL_PAYMENTPROOF_STATUS_PENDING) {
            $approveurl = new moodle_url($PAGE->url, array(
                'submissionid[]' => $submission->id,
                'bulkaction' => 'approve',
                'sesskey' => sesskey()
            ));
            $rejecturl = new moodle_url($PAGE->url, array(
                'submissionid[]' => $submission->id,
                'bulkaction' => 'reject',
                'sesskey' => sesskey()
            ));
            
            $actions .= html_writer::link($approveurl, $OUTPUT->pix_icon('t/approve', get_string('approve', 'enrol_paymentproof')), 
                array('title' => get_string('approve', 'enrol_paymentproof'), 'class' => 'action-icon'));
            $actions .= html_writer::link($rejecturl, $OUTPUT->pix_icon('t/delete', get_string('reject', 'enrol_paymentproof')), 
                array('title' => get_string('reject', 'enrol_paymentproof'), 'class' => 'action-icon'));
        }
        
        $paymentTypeString = get_string('paymenttype_' . $submission->paymenttype, 'enrol_paymentproof');
        
        $checkbox = html_writer::checkbox('submissionid[]', $submission->id, false);
        
        $row = array(
            $checkbox,
            $studentname,
            $paymentTypeString,
            format_float($submission->paymentamount, 2),
            userdate($submission->paymentdate),
            userdate($submission->timecreated),
            $status,
            $actions
        );
        
        $table->data[] = $row;
    }

    echo html_writer::table($table);
    
    // Add pagination
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url);
    
    // Add bulk actions
    echo html_writer::start_div('bulk-actions mt-3');
    echo html_writer::label(get_string('withselected', 'enrol_paymentproof'), 'bulkaction');
    echo html_writer::select(array(
        '' => get_string('choose'),
        'approve' => get_string('approve', 'enrol_paymentproof'),
        'reject' => get_string('reject', 'enrol_paymentproof'),
        'delete' => get_string('delete')
    ), 'bulkaction', '', array('' => get_string('choose')));
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('go'), 'class' => 'btn btn-secondary'));
    echo html_writer::end_div();
    
    echo html_writer::end_tag('form');
    
    // Add some JavaScript for bulk selection
    $PAGE->requires->js_amd_inline("
        require(['jquery'], function($) {
            $('#selectall').click(function() {
                var checked = $(this).prop('checked');
                $('input[name=\"submissionid[]\"]').prop('checked', checked);
            });
        });
    ");
}

echo $OUTPUT->footer();