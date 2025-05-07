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
 * Form for reviewing payment proof submissions.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paymentproof\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * Form for reviewing payment proof submissions.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_form extends \moodleform {

    /**
     * Define the form.
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;
        $submission = $this->_customdata['submission'];
        $course = $this->_customdata['course'];
        $context = $this->_customdata['context'];

        // Course information.
        $mform->addElement('header', 'courseheader', get_string('course'));
        $mform->addElement('static', 'coursename', get_string('course'), $course->fullname);

        // Student information.
        $user = $DB->get_record('user', array('id' => $submission->userid));
        $userfullname = fullname($user);
        $mform->addElement('static', 'username', get_string('user'), $userfullname);

        // Submission details.
        $mform->addElement('header', 'submissionheader', get_string('submission', 'enrol_paymentproof'));
        
        // Display submission time.
        $submissiontime = userdate($submission->timecreated);
        $mform->addElement('static', 'submissiontime', get_string('submissiontime', 'enrol_paymentproof'), $submissiontime);
        
        // Display student notes.
        if (!empty($submission->notes)) {
            $mform->addElement('static', 'notes', get_string('notes', 'enrol_paymentproof'), $submission->notes);
        }

        // Display payment proof file.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'enrol_paymentproof', 'submission', $submission->id, 'timemodified', false);
        
        if ($files) {
            $file = reset($files);
            $filename = $file->get_filename();
            $fileurl = \moodle_url::make_pluginfile_url(
                $context->id,
                'enrol_paymentproof',
                'submission',
                $submission->id,
                '/',
                $filename
            );
            
            if (in_array($file->get_mimetype(), array('image/jpeg', 'image/png', 'image/gif'))) {
                $image = \html_writer::img($fileurl, $filename, array('class' => 'payment-proof-image'));
                $mform->addElement('static', 'paymentproof', get_string('paymentproof', 'enrol_paymentproof'), $image);
            } else {
                $link = \html_writer::link($fileurl, $filename);
                $mform->addElement('static', 'paymentproof', get_string('paymentproof', 'enrol_paymentproof'), $link);
            }
        }

        // Review section.
        $mform->addElement('header', 'reviewheader', get_string('review', 'enrol_paymentproof'));
        
        // Decision radio buttons.
        $statusoptions = array(
            'approved' => get_string('approve', 'enrol_paymentproof'),
            'rejected' => get_string('reject', 'enrol_paymentproof')
        );
        $mform->addElement('select', 'status', get_string('decision', 'enrol_paymentproof'), $statusoptions);
        $mform->setDefault('status', '');
        $mform->addRule('status', get_string('required'), 'required', null, 'client');
        
        // Feedback text area.
        $mform->addElement('textarea', 'feedback', get_string('feedback', 'enrol_paymentproof'), 
            array('cols' => 50, 'rows' => 5));
        $mform->setType('feedback', PARAM_TEXT);

        // Hidden fields.
        $mform->addElement('hidden', 'id', $submission->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'review');
        $mform->setType('action', PARAM_ALPHA);

        // Submit buttons.
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validate the form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        
        // Require feedback for rejections.
        if ($data['status'] == 'rejected' && empty($data['feedback'])) {
            $errors['feedback'] = get_string('feedbackrequired', 'enrol_paymentproof');
        }
        
        return $errors;
    }
}