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
 * Language strings for the Payment Proof enrollment plugin.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Payment Proof Enrollment';
$string['pluginname_desc'] = 'This plugin allows students to upload payment proof for course enrollment. Teachers can review and approve these submissions.';

// General strings
$string['paymentproof:config'] = 'Configure Payment Proof enrollment instances';
$string['paymentproof:manage'] = 'Manage user enrollment via Payment Proof';
$string['paymentproof:unenrol'] = 'Unenroll users from course';
$string['paymentproof:unenrolself'] = 'Unenroll self from course';
$string['paymentproof:review'] = 'Review payment proof submissions';
$string['paymentproof:upload'] = 'Upload payment proof';

// Instance configuration
$string['assignrole'] = 'Assign role';
$string['cost'] = 'Course cost';
$string['currency'] = 'Currency';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Role to assign users to when their payment proof is approved';
$string['enrolperiod'] = 'Enrollment duration';
$string['enrolperiod_desc'] = 'Default length of time that enrollment is valid';
$string['enrolperiod_help'] = 'Length of time that enrollment is valid, starting from the moment the user is enrolled. If disabled, enrollment duration will be unlimited.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can only be enrolled from this date onwards.';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can only be enrolled until this date.';
$string['paymentinstructions'] = 'Payment instructions';
$string['paymentinstructions_help'] = 'Instructions that will be shown to students before they upload payment proof.';
$string['notificationemail'] = 'Notification email';
$string['notificationemail_desc'] = 'Email address to send notifications about new submissions (leave empty to disable)';
$string['allowresubmit'] = 'Allow resubmission';
$string['allowresubmit_help'] = 'Allow students to resubmit payment proof if rejected';
$string['maxfilesize'] = 'Maximum file size (KB)';
$string['maxfilesize_help'] = 'Maximum size of uploaded payment proof files in KB';

// Upload form
$string['uploadproof'] = 'Upload payment proof';
$string['uploadproofinstructions'] = 'Please upload your payment proof to enroll in this course.';
$string['existingsubmission'] = 'Your existing submission';
$string['resubmitproof'] = 'Resubmit payment proof';
$string['resubmitinstructions'] = 'You can submit a new payment proof as your previous submission was rejected.';
$string['paymentreference'] = 'Payment reference/ID';
$string['paymentreference_help'] = 'Enter any reference number or ID associated with your payment.';
$string['paymentdate'] = 'Payment date';
$string['paymentdate_help'] = 'Date when the payment was made.';
$string['paymentamount'] = 'Payment amount';
$string['paymentamount_help'] = 'The amount you paid.';
$string['paymentmethod'] = 'Payment method';
$string['paymentmethod_help'] = 'The method used for the payment (e.g., bank transfer, direct deposit).';
$string['uploadfile'] = 'Receipt or proof of payment';
$string['uploadfile_help'] = 'Upload a scanned receipt, screenshot, or other proof of payment.';
$string['comments'] = 'Additional comments';
$string['comments_help'] = 'Any additional information about your payment.';
$string['submit'] = 'Submit';
$string['submitsuccess'] = 'Your payment proof has been submitted successfully. You will be enrolled once your submission is approved.';

// Management page
$string['manage'] = 'Manage enrollments';
$string['managesubmissions'] = 'Manage payment proof submissions';
$string['nosubmissions'] = 'No payment proof submissions to review';
$string['submissionstatus'] = 'Submission status';
$string['status:pending'] = 'Pending';
$string['status:approved'] = 'Approved';
$string['status:rejected'] = 'Rejected';
$string['status:all'] = 'All';
$string['approve'] = 'Approve';
$string['reject'] = 'Reject';
$string['approveconfirm'] = 'Are you sure you want to approve this payment proof?';
$string['rejectconfirm'] = 'Are you sure you want to reject this payment proof?';
$string['reviewcomments'] = 'Review comments';
$string['reviewcomments_help'] = 'These comments will be visible to the student.';
$string['datesubmitted'] = 'Date submitted';
$string['actiontaken'] = 'Action taken';
$string['datereveiwed'] = 'Date reviewed';
$string['reviewedby'] = 'Reviewed by';
$string['submittedby'] = 'Submitted by';
$string['viewsubmission'] = 'View submission';
$string['downloadproof'] = 'Download payment proof';

// Email notifications
$string['emailnotifysubject'] = 'New payment proof submission for {$a->course}';
$string['emailnotifybody'] = 'A new payment proof has been submitted for the course "{$a->course}" by {$a->user}.
You can review this submission at: {$a->link}';
$string['emailapprovesubject'] = 'Your payment proof for {$a->course} has been approved';
$string['emailapprovebody'] = 'Your payment proof submission for the course "{$a->course}" has been approved.
You are now enrolled in the course and can access it at: {$a->link}

{$a->comments}';
$string['emailrejectsubject'] = 'Your payment proof for {$a->course} has been rejected';
$string['emailrejectbody'] = 'Your payment proof submission for the course "{$a->course}" has been rejected.

Reviewer comments: {$a->comments}

You can submit a new payment proof at: {$a->link}';

// Errors
$string['error:nopermission'] = 'You do not have permission to perform this action';
$string['error:submissions_disabled'] = 'Payment proof submissions are not accepted at this time';
$string['error:submission_failed'] = 'Your payment proof could not be submitted due to an error';
$string['error:upload_failed'] = 'File upload failed';
$string['error:invalid_filetype'] = 'The uploaded file type is not allowed';
$string['error:file_too_large'] = 'The uploaded file exceeds the maximum allowed size';

// Privacy API
$string['privacy:metadata:enrol_paymentproof'] = 'Information about payment proof submissions';
$string['privacy:metadata:enrol_paymentproof:userid'] = 'The ID of the user who submitted the payment proof';
$string['privacy:metadata:enrol_paymentproof:courseid'] = 'The ID of the course the payment proof was submitted for';
$string['privacy:metadata:enrol_paymentproof:paymentreference'] = 'The payment reference or ID provided by the user';
$string['privacy:metadata:enrol_paymentproof:paymentdate'] = 'The date when the payment was made';
$string['privacy:metadata:enrol_paymentproof:paymentamount'] = 'The amount that was paid';
$string['privacy:metadata:enrol_paymentproof:paymentmethod'] = 'The method used for payment';
$string['privacy:metadata:enrol_paymentproof:comments'] = 'Any additional comments provided with the submission';
$string['privacy:metadata:enrol_paymentproof:status'] = 'The status of the payment proof submission';
$string['privacy:metadata:enrol_paymentproof:timecreated'] = 'The time when the payment proof was submitted';
$string['privacy:metadata:enrol_paymentproof:timemodified'] = 'The time when the payment proof submission was last modified';
$string['privacy:metadata:enrol_paymentproof:filesubmission'] = 'The file uploaded as payment proof';
$string['privacy:metadata:enrol_paymentproof:reviewcomments'] = 'Comments provided by the reviewer';
$string['privacy:metadata:enrol_paymentproof:reviewerid'] = 'The ID of the user who reviewed the submission';
$string['privacy:metadata:enrol_paymentproof:timereveiwed'] = 'The time when the submission was reviewed';