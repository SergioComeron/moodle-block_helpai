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
 * Question log and daily limit helpers for HelpAI.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_helpai;

defined('MOODLE_INTERNAL') || die();

/**
 * Persist asks and enforce the per-user daily cap.
 */
class question_log {

    /** Outcome: a normal answer was returned (local search or AI). */
    const OUTCOME_ANSWERED = 'answered';

    /** Outcome: the course PDFs do not contain the answer. */
    const OUTCOME_REFUSED = 'refused';

    /** Outcome: the student hit the daily question cap. */
    const OUTCOME_LIMIT_HIT = 'limit_hit';

    /** Outcome: processing failed (missing key, API error, …). */
    const OUTCOME_ERROR = 'error';

    /** Outcome: the user has no visible PDFs in this course. */
    const OUTCOME_NO_PDFS = 'no_pdfs';

    /**
     * Configured daily question limit (0 = unlimited).
     *
     * @return int
     */
    public static function get_daily_limit() {
        $limit = get_config('block_helpai', 'dailylimit');
        if ($limit === false || $limit === '' || $limit === null) {
            return 20;
        }
        return (int)$limit;
    }

    /**
     * Teachers/managers with viewhistory are not subject to the student cap.
     *
     * @param \context $context Course context.
     * @param int|null $userid User ID, or null for the current user.
     * @return bool
     */
    public static function is_exempt_from_limit(\context $context, $userid = null) {
        return has_capability('block/helpai:viewhistory', $context, $userid);
    }

    /**
     * Count questions asked today by this user in this course.
     *
     * Limit-hit rows are excluded so a blocked retry does not inflate the count.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return int
     */
    public static function count_today($userid, $courseid) {
        global $DB;

        $startofday = usergetmidnight(time());

        return $DB->count_records_select(
            'block_helpai_questions',
            'userid = :userid AND courseid = :courseid AND timecreated >= :startofday
             AND outcome <> :limithit',
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'startofday' => $startofday,
                'limithit' => self::OUTCOME_LIMIT_HIT,
            ]
        );
    }

    /**
     * Whether this user has used their student daily allowance in this course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param \context $context Course context.
     * @return bool
     */
    public static function has_reached_daily_limit($userid, $courseid, \context $context) {
        $limit = self::get_daily_limit();
        if ($limit <= 0) {
            return false;
        }
        if (self::is_exempt_from_limit($context, $userid)) {
            return false;
        }
        return self::count_today($userid, $courseid) >= $limit;
    }

    /**
     * Store one ask for the teacher report and privacy API.
     *
     * Stores the student-facing question and answer only. Never store API
     * keys, request bodies, or other raw provider payloads.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param string $question Question text.
     * @param string $answer Answer or short summary shown to the student.
     * @param bool $aiused Whether OpenAI was called.
     * @param string $outcome One of the OUTCOME_* constants.
     * @return int New record id.
     */
    public static function log($userid, $courseid, $question, $answer, $aiused, $outcome) {
        global $DB;

        $record = new \stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->question = $question;
        $record->answer = $answer;
        $record->aiused = $aiused ? 1 : 0;
        $record->outcome = $outcome;
        $record->timecreated = time();

        return $DB->insert_record('block_helpai_questions', $record);
    }

    /**
     * Map an ai_handler result to an outcome constant.
     *
     * @param array $result Result from ai_handler::process_question().
     * @return string
     */
    public static function outcome_from_result(array $result) {
        if (!empty($result['outcome'])) {
            return $result['outcome'];
        }
        if (empty($result['success'])) {
            return self::OUTCOME_ERROR;
        }
        return self::OUTCOME_ANSWERED;
    }
}
