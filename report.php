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
 * Course question log for teachers.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// El plugin puede estar symlinkeado: __DIR__ y '..' resolverían el symlink y
// caerían fuera del árbol de Moodle, así que se recorta SCRIPT_FILENAME.
require_once(dirname($_SERVER['SCRIPT_FILENAME'], 3) . '/config.php');

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 25;

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
require_capability('block/helpai:viewhistory', $context);

$baseurl = new moodle_url('/blocks/helpai/report.php', ['id' => $course->id]);
$pageurl = new moodle_url($baseurl, ['userid' => $userid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('questionlog', 'block_helpai'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('pluginname', 'block_helpai'));
$PAGE->navbar->add(get_string('questionlog', 'block_helpai'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('questionlog', 'block_helpai'));
echo html_writer::div(get_string('questionlog_desc', 'block_helpai'), 'mb-3');

// Users who have asked at least one question in this course.
$namefields = 'u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
               u.middlename, u.alternatename';
$users = $DB->get_records_sql(
    "SELECT DISTINCT u.id, {$namefields}
       FROM {user} u
       JOIN {block_helpai_questions} q ON q.userid = u.id
      WHERE q.courseid = :courseid
   ORDER BY u.lastname, u.firstname",
    ['courseid' => $course->id]
);

$options = [0 => get_string('allusers', 'block_helpai')];
foreach ($users as $user) {
    $options[$user->id] = fullname($user, has_capability('moodle/site:viewfullnames', $context));
}

echo $OUTPUT->single_select($baseurl, 'userid', $options, $userid, [], 'helpaiuserfilter');

$params = ['courseid' => $course->id];
$wheresql = 'q.courseid = :courseid';
if ($userid) {
    $wheresql .= ' AND q.userid = :userid';
    $params['userid'] = $userid;
}

$total = $DB->count_records_select('block_helpai_questions', str_replace('q.', '', $wheresql), $params);

if ($total === 0) {
    echo $OUTPUT->notification(get_string('noquestions', 'block_helpai'), 'info');
} else {
    echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);

    $sql = "SELECT q.id, q.userid, q.question, q.answer, q.aiused, q.outcome, q.timecreated,
                   {$namefields}
              FROM {block_helpai_questions} q
              JOIN {user} u ON u.id = q.userid
             WHERE {$wheresql}
          ORDER BY q.timecreated DESC";

    $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

    $table = new html_table();
    $table->attributes['class'] = 'generaltable block-helpai-report';
    $table->head = [
        get_string('col_time', 'block_helpai'),
        get_string('col_user', 'block_helpai'),
        get_string('col_question', 'block_helpai'),
        get_string('col_answer', 'block_helpai'),
        get_string('col_aiused', 'block_helpai'),
        get_string('col_outcome', 'block_helpai'),
    ];

    $viewfullnames = has_capability('moodle/site:viewfullnames', $context);

    foreach ($records as $record) {
        $profileurl = new moodle_url('/user/view.php', ['id' => $record->userid, 'course' => $course->id]);
        $username = html_writer::link($profileurl, fullname($record, $viewfullnames));

        $outcomekey = 'outcome_' . $record->outcome;
        if (get_string_manager()->string_exists($outcomekey, 'block_helpai')) {
            $outcometext = get_string($outcomekey, 'block_helpai');
        } else {
            $outcometext = $record->outcome;
        }

        $table->data[] = [
            userdate($record->timecreated),
            $username,
            s(shorten_text($record->question, 200)),
            s(shorten_text((string)$record->answer, 200)),
            $record->aiused ? get_string('yes') : get_string('no'),
            $outcometext,
        ];
    }

    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
}

echo $OUTPUT->footer();
