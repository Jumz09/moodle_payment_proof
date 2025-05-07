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
 * Payment proof enrollment plugin upgrade code.
 *
 * @package    enrol_paymentproof
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for the payment proof enrollment plugin.
 * 
 * @param int $oldversion The old version of the plugin
 * @return bool
 */
function xmldb_enrol_paymentproof_upgrade($oldversion) {
    global $DB, $CFG;
    
    $dbman = $DB->get_manager();
    
    if ($oldversion < 2023052500) {
        // Define table enrol_paymentproof_submissions.
        $table = new xmldb_table('enrol_paymentproof_submissions');
        
        // Add 'feedback' field if it doesn't exist.
        if (!$dbman->field_exists($table, new xmldb_field('feedback'))) {
            $field = new xmldb_field('feedback', XMLDB_TYPE_TEXT, null, null, null, null, null, 'status');
            $dbman->add_field($table, $field);
        }
        
        // Add 'reviewerid' field if it doesn't exist.
        if (!$dbman->field_exists($table, new xmldb_field('reviewerid'))) {
            $field = new xmldb_field('reviewerid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'feedback');
            $dbman->add_field($table, $field);
            
            // Add key for the reviewer ID.
            $key = new xmldb_key('reviewerid', XMLDB_KEY_FOREIGN, ['reviewerid'], 'user', ['id']);
            $dbman->add_key($table, $key);
        }
        
        // Add 'reviewdate' field if it doesn't exist.
        if (!$dbman->field_exists($table, new xmldb_field('reviewdate'))) {
            $field = new xmldb_field('reviewdate', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'reviewerid');
            $dbman->add_field($table, $field);
        }
        
        // Create index on status and courseid if it doesn't exist.
        $index = new xmldb_index('status-courseid', XMLDB_INDEX_NOTUNIQUE, ['status', 'courseid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Create index on userid and courseid if it doesn't exist.
        $index = new xmldb_index('userid-courseid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2023052500, 'enrol', 'paymentproof');
    }
    
    // Future upgrade steps would be added here.
    
    return true;
}