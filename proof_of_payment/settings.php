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
 * Payment proof enrollment plugin settings.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_paymentproof_settings', '', get_string('pluginname_desc', 'enrol_paymentproof')));

    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_paymentproof_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_paymentproof/status',
        get_string('status', 'enrol_paymentproof'), get_string('status_desc', 'enrol_paymentproof'), ENROL_INSTANCE_DISABLED, $options));

    $options = get_default_enrol_roles(context_system::instance());
    $student = get_archetype_roles('student');
    $student = reset($student);
    $settings->add(new admin_setting_configselect('enrol_paymentproof/roleid',
        get_string('defaultrole', 'enrol_paymentproof'), get_string('defaultrole_desc', 'enrol_paymentproof'), $student->id, $options));

    $settings->add(new admin_setting_configduration('enrol_paymentproof/enrolperiod',
        get_string('enrolperiod', 'enrol_paymentproof'), get_string('enrolperiod_desc', 'enrol_paymentproof'), 0));

    $options = array(
        0 => get_string('no'),
        1 => get_string('expirynotifyenroller', 'core_enrol'),
        2 => get_string('expirynotifyall', 'core_enrol')
    );
    $settings->add(new admin_setting_configselect('enrol_paymentproof/expirynotify',
        get_string('expirynotify', 'core_enrol'), get_string('expirynotify_help', 'core_enrol'), 0, $options));

    $settings->add(new admin_setting_configduration('enrol_paymentproof/expirythreshold',
        get_string('expirythreshold', 'core_enrol'), get_string('expirythreshold_help', 'core_enrol'), 86400, 86400));

    $options = array(
        0 => get_string('no'),
        1 => get_string('yes')
    );
    $settings->add(new admin_setting_configselect('enrol_paymentproof/notifyenrollers',
        get_string('notifyenrollers', 'enrol_paymentproof'), get_string('notifyenrollers_desc', 'enrol_paymentproof'), 1, $options));

    $settings->add(new admin_setting_configtextarea('enrol_paymentproof/paymentinstructions',
        get_string('paymentinstructions', 'enrol_paymentproof'), get_string('paymentinstructions_desc', 'enrol_paymentproof'), ''));

    // Add file types setting
    $settings->add(new admin_setting_configtext('enrol_paymentproof/filetypes',
        get_string('filetypes', 'enrol_paymentproof'), get_string('filetypes_desc', 'enrol_paymentproof'), 'jpg,jpeg,png,pdf'));

    // Max file size
    $maxfilesize = get_config('', 'maxbytes');
    $options = get_max_upload_sizes($maxfilesize);
    $settings->add(new admin_setting_configselect('enrol_paymentproof/maxfilesize',
        get_string('maxfilesize', 'enrol_paymentproof'), get_string('maxfilesize_desc', 'enrol_paymentproof'), 1048576, $options));

    // Default payment types to enable
    $settings->add(new admin_setting_configmulticheckbox('enrol_paymentproof/paymenttypes',
        get_string('paymenttypes', 'enrol_paymentproof'), get_string('paymenttypes_desc', 'enrol_paymentproof'),
        array('bank' => 1, 'cash' => 1, 'check' => 1, 'credit' => 1, 'other' => 1),
        array(
            'bank' => get_string('paymenttype_bank', 'enrol_paymentproof'),
            'cash' => get_string('paymenttype_cash', 'enrol_paymentproof'),
            'check' => get_string('paymenttype_check', 'enrol_paymentproof'),
            'credit' => get_string('paymenttype_credit', 'enrol_paymentproof'),
            'other' => get_string('paymenttype_other', 'enrol_paymentproof')
        )
    ));
}