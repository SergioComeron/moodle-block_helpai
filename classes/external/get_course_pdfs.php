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
 * External service for getting course PDFs.
 *
 * @package    block_helpai
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_helpai\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use block_helpai\pdf_processor;

/**
 * External service to get list of PDFs in a course.
 */
class get_course_pdfs extends external_api {

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
     * Get list of PDFs in a course with their schema status.
     *
     * @param int $courseid Course ID.
     * @return array PDFs data.
     */
    public static function execute($courseid) {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        // Check capabilities.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/helpai:askquestion', $context);

        // Get PDFs from course.
        $pdfs = pdf_processor::get_course_pdfs($params['courseid']);

        // Get existing schemas.
        $schemas = $DB->get_records('block_helpai_schemas', [
            'courseid' => $params['courseid'],
        ], '', 'cmid, id, timecreated');

        $result = [];
        foreach ($pdfs as $pdf) {
            $hasschema = isset($schemas[$pdf['cmid']]);
            $result[] = [
                'cmid' => $pdf['cmid'],
                'name' => $pdf['name'],
                'filename' => $pdf['filename'],
                'hasschema' => $hasschema,
                'schematimecreated' => $hasschema ? $schemas[$pdf['cmid']]->timecreated : 0,
            ];
        }

        return ['pdfs' => $result];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'pdfs' => new external_multiple_structure(
                new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                    'name' => new external_value(PARAM_TEXT, 'PDF name'),
                    'filename' => new external_value(PARAM_TEXT, 'PDF filename'),
                    'hasschema' => new external_value(PARAM_BOOL, 'Has generated schema'),
                    'schematimecreated' => new external_value(PARAM_INT, 'Schema creation time'),
                ])
            ),
        ]);
    }
}
