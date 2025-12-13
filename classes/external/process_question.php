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
 * External service for processing questions.
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
use block_helpai\ai_handler;

/**
 * External service to process user questions.
 */
class process_question extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'question' => new external_value(PARAM_TEXT, 'User question'),
        ]);
    }

    /**
     * Process a question and return AI response.
     *
     * @param int $courseid Course ID.
     * @param string $question User question.
     * @return array Response data.
     */
    public static function execute($courseid, $question) {
        global $USER, $CFG, $DB;

        // Debug logging.
        if ($CFG->debugdeveloper) {
            debugging('HelpAI: courseid=' . $courseid . ', question=' . $question, DEBUG_DEVELOPER);
        }

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'question' => $question,
        ]);

        // Check capabilities.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/helpai:askquestion', $context);

        // Save user question to history.
        $userhistory = new \stdClass();
        $userhistory->userid = $USER->id;
        $userhistory->courseid = $params['courseid'];
        $userhistory->role = 'user';
        $userhistory->message = $params['question'];
        $userhistory->timecreated = time();
        $DB->insert_record('block_helpai_history', $userhistory);

        // Process the question.
        $result = ai_handler::process_question($params['question'], $params['courseid'], $USER->id);

        // Save assistant response to history.
        if (isset($result['message']) && !empty($result['message'])) {
            $assistanthistory = new \stdClass();
            $assistanthistory->userid = $USER->id;
            $assistanthistory->courseid = $params['courseid'];
            $assistanthistory->role = 'assistant';
            $assistanthistory->message = $result['message'];
            $assistanthistory->timecreated = time();
            $DB->insert_record('block_helpai_history', $assistanthistory);
        }

        // Ensure required fields are present and properly typed.
        if (!isset($result['success'])) {
            $result['success'] = false;
        }
        if (!isset($result['message'])) {
            $result['message'] = '';
        }
        if (!isset($result['pdfs'])) {
            $result['pdfs'] = [];
        }

        // Ensure success is boolean.
        $result['success'] = (bool)$result['success'];

        return $result;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
            'pdfs' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'PDF name'),
                    'filename' => new external_value(PARAM_TEXT, 'PDF filename'),
                    'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                ]),
                'Referenced PDFs',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
