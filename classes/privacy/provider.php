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
 * Privacy provider for block_helpai.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_helpai\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Privacy provider for block_helpai.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'block_helpai_history',
            [
                'userid' => 'privacy:metadata:block_helpai_history:userid',
                'courseid' => 'privacy:metadata:block_helpai_history:courseid',
                'role' => 'privacy:metadata:block_helpai_history:role',
                'message' => 'privacy:metadata:block_helpai_history:message',
                'timecreated' => 'privacy:metadata:block_helpai_history:timecreated',
            ],
            'privacy:metadata:block_helpai_history'
        );

        $collection->add_database_table(
            'block_helpai_questions',
            [
                'userid' => 'privacy:metadata:block_helpai_questions:userid',
                'courseid' => 'privacy:metadata:block_helpai_questions:courseid',
                'question' => 'privacy:metadata:block_helpai_questions:question',
                'answer' => 'privacy:metadata:block_helpai_questions:answer',
                'aiused' => 'privacy:metadata:block_helpai_questions:aiused',
                'outcome' => 'privacy:metadata:block_helpai_questions:outcome',
                'timecreated' => 'privacy:metadata:block_helpai_questions:timecreated',
            ],
            'privacy:metadata:block_helpai_questions'
        );

        // Questions and course PDFs are sent to OpenAI. User id, name and email are not.
        $collection->add_external_location_link('openai', [
            'prompttext' => 'privacy:metadata:openai:prompttext',
            'pdfs' => 'privacy:metadata:openai:pdfs',
            'model' => 'privacy:metadata:openai:model',
        ], 'privacy:metadata:openai');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course} c ON c.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {block_helpai_history} h ON h.courseid = c.id
                 WHERE h.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course} c ON c.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {block_helpai_questions} q ON q.courseid = c.id
                 WHERE q.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $courseid = $context->instanceid;
            $exportdata = new \stdClass();

            $history = $DB->get_records('block_helpai_history', [
                'userid' => $user->id,
                'courseid' => $courseid,
            ], 'timecreated ASC');

            if (!empty($history)) {
                $data = [];
                foreach ($history as $record) {
                    $data[] = (object)[
                        'role' => $record->role,
                        'message' => $record->message,
                        'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    ];
                }
                $exportdata->history = $data;
            }

            $questions = $DB->get_records('block_helpai_questions', [
                'userid' => $user->id,
                'courseid' => $courseid,
            ], 'timecreated ASC');

            if (!empty($questions)) {
                $qdata = [];
                foreach ($questions as $record) {
                    $qdata[] = (object)[
                        'question' => $record->question,
                        'answer' => $record->answer,
                        'aiused' => \core_privacy\local\request\transform::yesno($record->aiused),
                        'outcome' => $record->outcome,
                        'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    ];
                }
                $exportdata->questions = $qdata;
            }

            if (!empty($exportdata->history) || !empty($exportdata->questions)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_helpai')],
                    $exportdata
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $DB->delete_records('block_helpai_history', ['courseid' => $context->instanceid]);
        $DB->delete_records('block_helpai_questions', ['courseid' => $context->instanceid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $params = [
                'userid' => $userid,
                'courseid' => $context->instanceid,
            ];
            $DB->delete_records('block_helpai_history', $params);
            $DB->delete_records('block_helpai_questions', $params);
        }
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $params = ['courseid' => $context->instanceid];

        $userlist->add_from_sql('userid',
            "SELECT userid FROM {block_helpai_history} WHERE courseid = :courseid",
            $params
        );
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {block_helpai_questions} WHERE courseid = :courseid",
            $params
        );
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $select = "courseid = :courseid AND userid $usersql";
        $params = array_merge(['courseid' => $context->instanceid], $userparams);

        $DB->delete_records_select('block_helpai_history', $select, $params);
        $DB->delete_records_select('block_helpai_questions', $select, $params);
    }
}
