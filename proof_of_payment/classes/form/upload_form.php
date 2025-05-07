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
 * Form for uploading payment proof.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paymentproof\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/repository/lib.php');

/**
 * Form for uploading payment proof.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_form extends \moodleform {

    /**
     * Define the form.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $course = $this->_customdata['course'];
        $instance = $this->_customdata['instance'];

        // Course information.
        $mform->addElement('header', 'courseheader', get_string('course'));
        $mform->addElement('static', 'coursename', get_string('course'), $course->fullname);
        
        if (!empty($instance->customtext1)) {
            $mform->addElement('static', 'paymentinstructions', get_string('paymentinstructions', 'enrol_paymentproof'), 
                format_text($instance->customtext1, FORMAT_MOODLE));
        }

        // Payment proof upload.
        $mform->addElement('header', 'uploadheader', get_string('uploadpaymentproof', 'enrol_paymentproof'));
        
        $fileoptions = array(
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => array('image', 'document', 'application/pdf'),
            'maxfiles' => 1,
            'required' => true
        );
        
        $mform->addElement('filepicker', 'paymentproof', get_string('file'), null, $fileoptions);
        $mform->addRule('paymentproof', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('paymentproof', 'paymentproof', 'enrol_paymentproof');

        // Additional notes.
        $mform->addElement('textarea', 'notes', get_string('notes', 'enrol_paymentproof'), 
            array('cols' => 50, 'rows' => 5));
        $mform->setType('notes', PARAM_TEXT);
        $mform->addHelpButton('notes', 'notes', 'enrol_paymentproof');

        // Hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'instanceid', $instance->id);
        $mform->setType('instanceid', PARAM_INT);

        // Add recaptcha if enabled.
        if (!empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey)) {
            $mform->addElement('recaptcha', 'recaptcha', get_string('security_question', 'auth'));
            $mform->addHelpButton('recaptcha', 'recaptcha', 'auth');
        }

        // Submit buttons.
        $this->add_action_buttons(true, get_string('submit', 'enrol_paymentproof'));
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
        
        // Check if file was uploaded.
        $usercontext = \context_user::instance($data['userid']);
        $fs = get_file_storage();
        $draftitemid = file_get_submitted_draft_itemid('paymentproof');
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
        
        if (count($files) == 0) {
            $errors['paymentproof'] = get_string('required');
        }
        
        return $errors;
    }

    /**
     * Get the uploaded file info.
     *
     * @param int $draftitemid The draft item id for the file area
     * @return \stored_file|bool The stored file or false if no file was uploaded
     */
    public function get_uploaded_file($draftitemid) {
        $fs = get_file_storage();
        $usercontext = \context_user::instance($this->_customdata['userid']);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
        
        if (count($files) == 1) {
            return reset($files);
        }
        
        return false;
    }
}