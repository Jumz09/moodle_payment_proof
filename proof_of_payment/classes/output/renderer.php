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
 * Output renderer for the payment proof enrollment plugin.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paymentproof\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;
use renderable;
use templatable;
use moodle_url;
use context_course;
use html_writer;
use stdClass;

/**
 * Output renderer for the payment proof enrollment plugin.
 */
class renderer extends plugin_renderer_base {

    /**
     * Renders the submission list page.
     *
     * @param array $submissions Array of submission records.
     * @param int $courseid The course ID.
     * @return string HTML content.
     */
    public function render_submission_list($submissions, $courseid) {
        global $CFG, $OUTPUT;
        
        $data = new stdClass();
        $data->submissions = array();
        $data->courseurl = new moodle_url('/course/view.php', array('id' => $courseid));
        $data->manageurl = new moodle_url('/enrol/paymentproof/manage.php', array('id' => $courseid));
        $data->hassubmissions = !empty($submissions);
        
        foreach ($submissions as $submission) {
            $submissiondata = new stdClass();
            $submissiondata->id = $submission->id;
            $submissiondata->userid = $submission->userid;
            $submissiondata->username = fullname($submission);
            $submissiondata->timecreated = userdate($submission->timecreated);
            $submissiondata->status = $this->get_status_string($submission->status);
            $submissiondata->statusclass = $this->get_status_class($submission->status);
            
            // Add action URLs
            $submissiondata->viewurl = new moodle_url('/enrol/paymentproof/view.php', 
                array('id' => $submission->id, 'courseid' => $courseid));
            
            if ($submission->status == 0) { // Pending
                $submissiondata->approveurl = new moodle_url('/enrol/paymentproof/manage.php', 
                    array('action' => 'approve', 'id' => $submission->id, 'courseid' => $courseid, 
                    'sesskey' => sesskey()));
                $submissiondata->rejecturl = new moodle_url('/enrol/paymentproof/manage.php', 
                    array('action' => 'reject', 'id' => $submission->id, 'courseid' => $courseid, 
                    'sesskey' => sesskey()));
                $submissiondata->canaction = true;
            } else {
                $submissiondata->canaction = false;
            }
            
            $data->submissions[] = $submissiondata;
        }
        
        return $this->render_from_template('enrol_paymentproof/submission_list', $data);
    }
    
    /**
     * Renders the upload form for payment proof.
     *
     * @param \enrol_paymentproof\form\upload_form $form The form to render.
     * @param int $courseid The course ID.
     * @return string HTML content.
     */
    public function render_upload_form($form, $courseid) {
        global $CFG, $COURSE;
        
        $data = new stdClass();
        $data->formhtml = $form->render();
        $data->courseid = $courseid;
        $data->coursename = $COURSE->fullname;
        $data->courseurl = new moodle_url('/course/view.php', array('id' => $courseid));
        
        return $this->render_from_template('enrol_paymentproof/upload_form', $data);
    }
    
    /**
     * Renders a submission details view.
     *
     * @param object $submission The submission record.
     * @param int $courseid The course ID.
     * @return string HTML content.
     */
    public function render_submission_details($submission, $courseid) {
        global $DB, $OUTPUT;
        
        $data = new stdClass();
        $data->id = $submission->id;
        $data->courseid = $courseid;
        $data->coursename = $DB->get_field('course', 'fullname', array('id' => $courseid));
        $data->username = fullname($submission);
        $data->timecreated = userdate($submission->timecreated);
        $data->status = $this->get_status_string($submission->status);
        $data->statusclass = $this->get_status_class($submission->status);
        $data->notes = format_text($submission->notes, FORMAT_MOODLE);
        
        // File attachment
        $fs = get_file_storage();
        $context = context_course::instance($courseid);
        $files = $fs->get_area_files($context->id, 'enrol_paymentproof', 'proof', $submission->id, 'timemodified', false);
        
        $data->hasfiles = !empty($files);
        $data->files = array();
        
        foreach ($files as $file) {
            $filedata = new stdClass();
            $filedata->filename = $file->get_filename();
            $filedata->fileurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );
            $data->files[] = $filedata;
        }
        
        // Action links
        $data->backurl = new moodle_url('/enrol/paymentproof/manage.php', array('courseid' => $courseid));
        
        if ($submission->status == 0) { // Pending
            $data->approveurl = new moodle_url('/enrol/paymentproof/manage.php', 
                array('action' => 'approve', 'id' => $submission->id, 'courseid' => $courseid, 
                'sesskey' => sesskey()));
            $data->rejecturl = new moodle_url('/enrol/paymentproof/manage.php', 
                array('action' => 'reject', 'id' => $submission->id, 'courseid' => $courseid, 
                'sesskey' => sesskey()));
            $data->canaction = true;
        } else {
            $data->canaction = false;
        }
        
        return $this->render_from_template('enrol_paymentproof/submission_details', $data);
    }
    
    /**
     * Get the status string based on the status code.
     *
     * @param int $status The status code (0=pending, 1=approved, 2=rejected).
     * @return string The localized status string.
     */
    private function get_status_string($status) {
        switch ($status) {
            case 0:
                return get_string('status_pending', 'enrol_paymentproof');
            case 1:
                return get_string('status_approved', 'enrol_paymentproof');
            case 2:
                return get_string('status_rejected', 'enrol_paymentproof');
            default:
                return get_string('status_unknown', 'enrol_paymentproof');
        }
    }
    
    /**
     * Get the status CSS class based on the status code.
     *
     * @param int $status The status code (0=pending, 1=approved, 2=rejected).
     * @return string The CSS class for the status.
     */
    private function get_status_class($status) {
        switch ($status) {
            case 0:
                return 'badge badge-warning';
            case 1:
                return 'badge badge-success';
            case 2:
                return 'badge badge-danger';
            default:
                return 'badge badge-secondary';
        }
    }
}