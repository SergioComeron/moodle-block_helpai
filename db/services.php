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
 * Web service definitions for HelpAI block.
 *
 * @package    block_helpai
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_helpai_process_question' => [
        'classname'   => 'block_helpai\external\process_question',
        'methodname'  => 'execute',
        'description' => 'Process a user question and return AI response',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'block_helpai_get_history' => [
        'classname'   => 'block_helpai\external\get_history',
        'methodname'  => 'execute',
        'description' => 'Get chat history for the current user in a course',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'block_helpai_clear_history' => [
        'classname'   => 'block_helpai\external\clear_history',
        'methodname'  => 'execute',
        'description' => 'Clear chat history for the current user in a course',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'block_helpai_get_course_pdfs' => [
        'classname'   => 'block_helpai\external\get_course_pdfs',
        'methodname'  => 'execute',
        'description' => 'Get list of PDFs in a course with schema status',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'block_helpai_generate_schema' => [
        'classname'   => 'block_helpai\external\generate_schema',
        'methodname'  => 'execute',
        'description' => 'Generate schema/outline for a PDF',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'block_helpai_get_schema' => [
        'classname'   => 'block_helpai\external\get_schema',
        'methodname'  => 'execute',
        'description' => 'Get a saved schema for a PDF',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
