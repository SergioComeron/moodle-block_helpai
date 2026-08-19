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
 * Settings for HelpAI block.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Site-owned OpenAI API key (BYOK). Stored as a password field; never logged.
    $settings->add(new admin_setting_configpasswordunmask(
        'block_helpai/openai_apikey',
        get_string('openai_apikey', 'block_helpai'),
        get_string('openai_apikey_desc', 'block_helpai'),
        ''
    ));

    // OpenAI Model.
    $settings->add(new admin_setting_configselect(
        'block_helpai/openai_model',
        get_string('openai_model', 'block_helpai'),
        get_string('openai_model_desc', 'block_helpai'),
        'gpt-4o',
        [
            'gpt-4o' => 'GPT-4o (Recommended - supports PDFs)',
            'gpt-4-turbo' => 'GPT-4 Turbo (supports PDFs)',
            'gpt-4' => 'GPT-4 (text only)',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (text only)',
        ]
    ));

    // Search mode setting.
    $settings->add(new admin_setting_configselect(
        'block_helpai/searchmode',
        get_string('searchmode', 'block_helpai'),
        get_string('searchmode_desc', 'block_helpai'),
        'aionly',
        [
            'hybrid' => get_string('searchmode_hybrid', 'block_helpai'),
            'aionly' => get_string('searchmode_aionly', 'block_helpai'),
        ]
    ));

    // Daily question limit per student per course.
    $settings->add(new admin_setting_configtext(
        'block_helpai/dailylimit',
        get_string('dailylimit', 'block_helpai'),
        get_string('dailylimit_desc', 'block_helpai'),
        20,
        PARAM_INT
    ));
}
