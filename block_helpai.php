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
 * HelpAI block main class.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_helpai extends block_base {

    /**
     * Initialize the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_helpai');
    }

    /**
     * Returns the block content.
     *
     * @return stdClass The block content.
     */
    public function get_content() {
        global $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Get the course context.
        $context = $this->page->context;

        // Only show in course context.
        if ($context->contextlevel != CONTEXT_COURSE) {
            $this->content->text = get_string('notincourse', 'block_helpai');
            return $this->content;
        }

        // Get courseid from context.
        $courseid = $context->instanceid;

        // Initialize JavaScript modules.
        $PAGE->requires->js_call_amd('block_helpai/chat', 'init', [[
            'courseid' => (int)$courseid,
            'contextid' => (int)$this->context->id,
        ]]);

        $PAGE->requires->js_call_amd('block_helpai/schemas', 'init', [[
            'courseid' => (int)$courseid,
            'contextid' => (int)$this->context->id,
        ]]);

        // Build the tabbed interface.
        $this->content->text = $this->get_tabbed_interface();

        return $this->content;
    }

    /**
     * Get the tabbed interface HTML.
     *
     * @return string HTML content.
     */
    private function get_tabbed_interface() {
        $html = html_writer::start_div('block-helpai-container');

        // Tab navigation.
        $html .= html_writer::start_div('block-helpai-tabs');
        $html .= html_writer::tag('button', get_string('chat', 'block_helpai'), [
            'class' => 'block-helpai-tab active',
            'data-tab' => 'chat',
        ]);
        $html .= html_writer::tag('button', get_string('schemas', 'block_helpai'), [
            'class' => 'block-helpai-tab',
            'data-tab' => 'schemas',
        ]);
        $html .= html_writer::end_div();

        // Tab content containers.
        $html .= html_writer::start_div('block-helpai-tab-content');

        // Chat section.
        $html .= html_writer::start_div('block-helpai-section active', ['data-section' => 'chat']);
        $html .= $this->get_chat_section($this->page->context->instanceid);
        $html .= html_writer::end_div();

        // Schemas section.
        $html .= html_writer::start_div('block-helpai-section', ['data-section' => 'schemas']);
        $html .= $this->get_schemas_section();
        $html .= html_writer::end_div();

        $html .= html_writer::end_div();

        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Get the chat section HTML.
     *
     * @param int $courseid Course ID.
     * @return string HTML content.
     */
    private function get_chat_section($courseid) {
        $html = '';

        $coursecontext = \context_course::instance($courseid);

        // Header: teacher report link (if allowed) and clear-history button.
        $html .= html_writer::start_div('block-helpai-header');
        if (has_capability('block/helpai:viewhistory', $coursecontext)) {
            $reporturl = new \moodle_url('/blocks/helpai/report.php', ['id' => $courseid]);
            $html .= html_writer::link($reporturl, get_string('viewquestionlog', 'block_helpai'), [
                'class' => 'btn btn-sm btn-secondary block-helpai-report-link',
            ]);
        }
        $html .= html_writer::start_div('block-helpai-header-actions');
        $html .= html_writer::tag('button', get_string('clearhistory', 'block_helpai'), [
            'id' => 'block-helpai-clear',
            'class' => 'btn btn-sm btn-secondary block-helpai-clear',
            'title' => get_string('clearhistory', 'block_helpai'),
        ]);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        // Quick actions area.
        $html .= html_writer::start_div('block-helpai-quick-actions');
        $html .= html_writer::div(get_string('quickactions', 'block_helpai'), 'block-helpai-quick-actions-title');

        // Quick action buttons.
        $quickactions = [
            get_string('quickaction1', 'block_helpai'),
            get_string('quickaction2', 'block_helpai'),
            get_string('quickaction3', 'block_helpai'),
        ];

        foreach ($quickactions as $action) {
            $html .= html_writer::tag('button', $action, [
                'class' => 'btn btn-sm btn-outline-primary block-helpai-quick-action',
                'data-question' => $action,
            ]);
        }
        $html .= html_writer::end_div();

        // Messages area.
        $html .= html_writer::start_div('block-helpai-messages', ['id' => 'block-helpai-messages']);
        $html .= html_writer::div(
            get_string('welcomemessage', 'block_helpai'),
            'block-helpai-welcome'
        );
        $html .= html_writer::end_div();

        // Input area.
        $html .= html_writer::start_div('block-helpai-input-container');
        $html .= html_writer::tag('textarea', '', [
            'id' => 'block-helpai-input',
            'class' => 'block-helpai-input',
            'placeholder' => get_string('askquestion', 'block_helpai'),
            'rows' => 2,
        ]);
        $html .= html_writer::tag('button', get_string('send', 'block_helpai'), [
            'id' => 'block-helpai-send',
            'class' => 'btn btn-primary block-helpai-send',
        ]);
        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Get the schemas section HTML.
     *
     * @return string HTML content.
     */
    private function get_schemas_section() {
        $html = '';

        // Description.
        $html .= html_writer::div(
            get_string('schemas_description', 'block_helpai'),
            'block-helpai-schemas-description'
        );

        // PDFs list container.
        $html .= html_writer::start_div('block-helpai-schemas-list', ['id' => 'block-helpai-schemas-list']);
        $html .= html_writer::div(get_string('loading_pdfs', 'block_helpai'), 'block-helpai-loading');
        $html .= html_writer::end_div();

        // Schema viewer (hidden by default).
        $html .= html_writer::start_div('block-helpai-schema-viewer', [
            'id' => 'block-helpai-schema-viewer',
            'style' => 'display: none;',
        ]);

        // Schema header.
        $html .= html_writer::start_div('block-helpai-schema-header');
        $html .= html_writer::tag('h4', '', ['id' => 'block-helpai-schema-title']);
        $html .= html_writer::tag('div', '', [
            'id' => 'block-helpai-schema-date',
            'class' => 'block-helpai-schema-date',
        ]);
        $html .= html_writer::tag('button', get_string('close_schema', 'block_helpai'), [
            'id' => 'block-helpai-close-schema',
            'class' => 'btn btn-sm btn-secondary',
        ]);
        $html .= html_writer::end_div();

        // Schema content.
        $html .= html_writer::div('', 'block-helpai-schema-content', ['id' => 'block-helpai-schema-content']);

        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Locations where the block can be displayed.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'course-view' => true,
            'mod' => false,
            'my' => false,
        ];
    }

    /**
     * Allow multiple instances of this block.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Does this block have global settings?
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }
}
