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
 * Upgrade script for block_helpai.
 *
 * @package    block_helpai
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the block_helpai plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool
 */
function xmldb_block_helpai_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025121003) {

        // Define table block_helpai_history to be created.
        $table = new xmldb_table('block_helpai_history');

        // Adding fields to table block_helpai_history.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('role', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_helpai_history.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        // Adding indexes to table block_helpai_history.
        $table->add_index('userid_courseid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        // Conditionally launch create table for block_helpai_history.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Helpai savepoint reached.
        upgrade_block_savepoint(true, 2025121003, 'helpai');
    }

    if ($oldversion < 2025121004) {

        // Define table block_helpai_schemas to be created.
        $table = new xmldb_table('block_helpai_schemas');

        // Adding fields to table block_helpai_schemas.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('pdfname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('outline', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_helpai_schemas.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('cmid', XMLDB_KEY_FOREIGN, ['cmid'], 'course_modules', ['id']);

        // Adding indexes to table block_helpai_schemas.
        $table->add_index('courseid_cmid', XMLDB_INDEX_UNIQUE, ['courseid', 'cmid']);

        // Conditionally launch create table for block_helpai_schemas.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Helpai savepoint reached.
        upgrade_block_savepoint(true, 2025121004, 'helpai');
    }

    return true;
}
