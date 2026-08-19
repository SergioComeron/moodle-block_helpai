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
 * External service for generating PDF schema/outline.
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
use block_helpai\ai_handler;

/**
 * External service to generate schema for a PDF.
 */
class generate_schema extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Generate schema/outline for a PDF.
     *
     * @param int $courseid Course ID.
     * @param int $cmid Course module ID.
     * @return array Schema data.
     */
    public static function execute($courseid, $cmid) {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
        ]);

        // Check capabilities.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/helpai:askquestion', $context);

        // Check if schema already exists.
        $existing = $DB->get_record('block_helpai_schemas', [
            'courseid' => $params['courseid'],
            'cmid' => $params['cmid'],
        ]);

        if ($existing) {
            return [
                'success' => true,
                'outline' => $existing->outline,
                'pdfname' => $existing->pdfname,
                'timecreated' => $existing->timecreated,
            ];
        }

        // Generate new schema using AI.
        $result = ai_handler::generate_pdf_schema($params['cmid'], $params['courseid']);

        if ($result['success']) {
            // Save to database.
            $record = new \stdClass();
            $record->courseid = $params['courseid'];
            $record->cmid = $params['cmid'];
            $record->pdfname = $result['pdfname'];
            $record->outline = $result['outline'];
            $record->timecreated = time();
            $record->timemodified = time();

            $DB->insert_record('block_helpai_schemas', $record);

            return [
                'success' => true,
                'outline' => $result['outline'],
                'pdfname' => $result['pdfname'],
                'timecreated' => $record->timecreated,
            ];
        } else {
            return [
                'success' => false,
                'outline' => '',
                'pdfname' => '',
                'timecreated' => 0,
                'error' => $result['message'] ?? get_string('aierror', 'block_helpai'),
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'outline' => new external_value(PARAM_RAW, 'Generated outline/schema'),
            'pdfname' => new external_value(PARAM_TEXT, 'PDF name'),
            'timecreated' => new external_value(PARAM_INT, 'Creation timestamp'),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
        ]);
    }
}
