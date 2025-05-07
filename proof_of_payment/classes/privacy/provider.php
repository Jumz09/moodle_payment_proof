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
 * Privacy provider for enrol_paymentproof.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paymentproof\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Privacy provider for enrol_paymentproof.
 *
 * @package    enrol_paymentproof
 * @copyright  2025 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'enrol_paymentproof_submissions',
            [
                'userid' => 'privacy:metadata:enrol_paymentproof:userid',
                'courseid' => 'privacy:metadata:enrol_paymentproof:courseid',
                'status' => 'privacy:metadata:enrol_paymentproof:status',
                'filename' => 'privacy:metadata:enrol_paymentproof:filename',
                'filepath' => 'privacy:metadata:enrol_paymentproof:filepath',
                'notes' => 'privacy:metadata:enrol_paymentproof:notes',
                'feedback' => 'privacy:metadata:enrol_paymentproof:feedback',
                'timecreated' => 'privacy:metadata:enrol_paymentproof:timecreated',
                'timemodified' => 'privacy:metadata:enrol_paymentproof:timemodified',
                'reviewerid' => 'privacy:metadata:enrol_paymentproof:reviewerid',
                'timereview' => 'privacy:metadata:enrol_paymentproof:timereview',
            ],
            'privacy:metadata:enrol_paymentproof'
        );

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course} c ON ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel
                  JOIN {enrol_paymentproof_submissions} ps ON ps.courseid = c.id
                 WHERE ps.userid = :userid OR ps.reviewerid = :reviewerid";
                 
        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
            'reviewerid' => $userid,
        ];
        
        $contextlist->add_from_sql($sql, $params);
        
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        $sql = "SELECT userid
                FROM {enrol_paymentproof_submissions}
                WHERE courseid = :courseid";
        $params = ['courseid' => $context->instanceid];
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT reviewerid
                FROM {enrol_paymentproof_submissions}
                WHERE courseid = :courseid AND reviewerid IS NOT NULL";
        $params = ['courseid' => $context->instanceid];
        $userlist->add_from_sql('reviewerid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $courseid = $context->instanceid;

            // Export submissions made by the user.
            $sql = "SELECT ps.*
                      FROM {enrol_paymentproof_submissions} ps
                     WHERE ps.courseid = :courseid AND ps.userid = :userid";
            $params = ['courseid' => $courseid, 'userid' => $userid];
            $submissions = $DB->get_records_sql($sql, $params);

            if (!empty($submissions)) {
                $submissionsdata = [];
                $fs = get_file_storage();

                foreach ($submissions as $submission) {
                    $submissiondata = [
                        'courseid' => $submission->courseid,
                        'status' => $submission->status,
                        'notes' => $submission->notes,
                        'feedback' => $submission->feedback,
                        'timecreated' => transform::datetime($submission->timecreated),
                        'timemodified' => transform::datetime($submission->timemodified),
                    ];

                    if (!empty($submission->timereview)) {
                        $submissiondata['timereview'] = transform::datetime($submission->timereview);
                    }

                    // Export associated files.
                    $files = $fs->get_area_files(
                        $context->id,
                        'enrol_paymentproof',
                        'submission',
                        $submission->id,
                        '',
                        false
                    );

                    foreach ($files as $file) {
                        writer::with_context($context)->export_file([
                            'Submissions',
                            $submission->id
                        ], $file);
                    }

                    $submissionsdata[] = $submissiondata;
                }

                writer::with_context($context)->export_data(
                    ['Payment Proof Submissions'],
                    (object) ['submissions' => $submissionsdata]
                );
            }

            // Export reviews done by the user.
            $sql = "SELECT ps.*
                      FROM {enrol_paymentproof_submissions} ps
                     WHERE ps.courseid = :courseid AND ps.reviewerid = :reviewerid";
            $params = ['courseid' => $courseid, 'reviewerid' => $userid];
            $reviews = $DB->get_records_sql($sql, $params);

            if (!empty($reviews)) {
                $reviewsdata = [];

                foreach ($reviews as $review) {
                    $reviewsdata[] = [
                        'courseid' => $review->courseid,
                        'studentid' => $review->userid,
                        'status' => $review->status,
                        'feedback' => $review->feedback,
                        'timereview' => transform::datetime($review->timereview),
                    ];
                }

                writer::with_context($context)->export_data(
                    ['Payment Proof Reviews'],
                    (object) ['reviews' => $reviewsdata]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;

        // Get all submissions for this course.
        $submissions = $DB->get_records('enrol_paymentproof_submissions', ['courseid' => $courseid]);

        // Delete files for each submission.
        $fs = get_file_storage();
        foreach ($submissions as $submission) {
            $fs->delete_area_files($context->id, 'enrol_paymentproof', 'submission', $submission->id);
        }

        // Delete all submissions for this course.
        $DB->delete_records('enrol_paymentproof_submissions', ['courseid' => $courseid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $fs = get_file_storage();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $courseid = $context->instanceid;

            // Get all submissions from this user in this course.
            $submissions = $DB->get_records('enrol_paymentproof_submissions', [
                'courseid' => $courseid,
                'userid' => $userid
            ]);

            // Delete files for each submission.
            foreach ($submissions as $submission) {
                $fs->delete_area_files($context->id, 'enrol_paymentproof', 'submission', $submission->id);
            }

            // Delete all submissions from this user in this course.
            $DB->delete_records('enrol_paymentproof_submissions', [
                'courseid' => $courseid,
                'userid' => $userid
            ]);

            // Anonymize reviews done by this user.
            $DB->set_field('enrol_paymentproof_submissions', 'reviewerid', null, [
                'courseid' => $courseid,
                'reviewerid' => $userid
            ]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;
        $userids = $userlist->get_userids();
        $fs = get_file_storage();

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Get all submissions from these users in this course.
        $sql = "SELECT *
                  FROM {enrol_paymentproof_submissions}
                 WHERE courseid = :courseid AND userid $usersql";
        $params = array_merge(['courseid' => $courseid], $userparams);
        $submissions = $DB->get_records_sql($sql, $params);

        // Delete files for each submission.
        foreach ($submissions as $submission) {
            $fs->delete_area_files($context->id, 'enrol_paymentproof', 'submission', $submission->id);
        }

        // Delete all submissions from these users in this course.
        $DB->delete_records_select('enrol_paymentproof_submissions', 
            "courseid = :courseid AND userid $usersql",
            $params);

        // Anonymize reviews done by these users.
        $DB->set_field_select('enrol_paymentproof_submissions',
            'reviewerid', null,
            "courseid = :courseid AND reviewerid $usersql",
            $params);
    }
}