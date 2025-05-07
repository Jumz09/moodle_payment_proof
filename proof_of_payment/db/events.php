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
 * Event observers for the payment proof enrollment plugin.
 *
 * @package    enrol_paymentproof
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\enrol_paymentproof\observer::course_deleted',
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => '\enrol_paymentproof\observer::user_deleted',
    ],
    [
        'eventname' => '\core\event\role_assigned',
        'callback' => '\enrol_paymentproof\observer::role_assigned',
    ],
    [
        'eventname' => '\core\event\role_unassigned',
        'callback' => '\enrol_paymentproof\observer::role_unassigned',
    ],
    [
        'eventname' => '\enrol_paymentproof\event\submission_created',
        'callback' => '\enrol_paymentproof\observer::submission_created',
    ],
    [
        'eventname' => '\enrol_paymentproof\event\submission_approved',
        'callback' => '\enrol_paymentproof\observer::submission_approved',
    ],
    [
        'eventname' => '\enrol_paymentproof\event\submission_rejected',
        'callback' => '\enrol_paymentproof\observer::submission_rejected',
    ],
];