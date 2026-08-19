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
 * External service for getting chat history.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_helpai\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

/**
 * External service to get chat history.
 */
class get_history extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Get chat history for the current user in a course.
     *
     * @param int $courseid Course ID.
     * @return array History data.
     */
    public static function execute($courseid) {
        global $USER, $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        // Check capabilities.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/helpai:askquestion', $context);

        // Get history for this user and course.
        $history = $DB->get_records('block_helpai_history', [
            'userid' => $USER->id,
            'courseid' => $params['courseid'],
        ], 'timecreated ASC');

        $result = [];
        foreach ($history as $record) {
            $result[] = [
                'role' => $record->role,
                'message' => $record->message,
                'timecreated' => $record->timecreated,
            ];
        }

        return ['history' => $result];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'history' => new external_multiple_structure(
                new external_single_structure([
                    'role' => new external_value(PARAM_TEXT, 'Message role (user or assistant)'),
                    'message' => new external_value(PARAM_TEXT, 'Message content'),
                    'timecreated' => new external_value(PARAM_INT, 'Timestamp'),
                ])
            ),
        ]);
    }
}
