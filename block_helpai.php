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
 * @copyright  2025 Your Name
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

        // Initialize JavaScript - pass as indexed array, then convert to object in JS.
        $PAGE->requires->js_call_amd('block_helpai/chat', 'init', [[
            'courseid' => (int)$courseid,
            'contextid' => (int)$this->context->id,
        ]]);

        // Build the chat interface.
        $this->content->text = $this->get_chat_interface();

        return $this->content;
    }

    /**
     * Get the chat interface HTML.
     *
     * @return string HTML content.
     */
    private function get_chat_interface() {
        $html = html_writer::start_div('block-helpai-container');

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
