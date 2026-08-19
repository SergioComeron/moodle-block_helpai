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
 * Scheduled task to index PDFs.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_helpai\task;

use block_helpai\pdf_indexer;
use block_helpai\pdf_processor;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to index course PDFs.
 */
class index_pdfs extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskindexpdfs', 'block_helpai');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace('Starting PDF indexing task...');

        // Get all courses that have PDF resources.
        $sql = "SELECT DISTINCT c.id, c.fullname
                FROM {course} c
                INNER JOIN {course_modules} cm ON cm.course = c.id
                INNER JOIN {modules} m ON m.id = cm.module
                INNER JOIN {resource} r ON r.id = cm.instance
                WHERE m.name = 'resource'
                AND c.id > 1";

        $courses = $DB->get_records_sql($sql);

        $totalindexed = 0;
        $totalerrors = 0;

        foreach ($courses as $course) {
            mtrace("Processing course: {$course->fullname} (ID: {$course->id})");

            try {
                $stats = pdf_indexer::index_course_pdfs($course->id);

                mtrace("  - Indexed: {$stats['indexed']}");
                mtrace("  - Skipped: {$stats['skipped']}");
                mtrace("  - Errors: {$stats['errors']}");

                $totalindexed += $stats['indexed'];
                $totalerrors += $stats['errors'];

            } catch (\Exception $e) {
                mtrace("  - Error: " . $e->getMessage());
                $totalerrors++;
            }
        }

        mtrace("PDF indexing task completed.");
        mtrace("Total indexed: {$totalindexed}");
        mtrace("Total errors: {$totalerrors}");
    }
}
