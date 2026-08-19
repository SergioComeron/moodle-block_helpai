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

namespace block_helpai;

use block_helpai\privacy\provider;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\metadata\types\external_location;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy metadata tests for HelpAI.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {

    /**
     * Local tables and the OpenAI external location are declared.
     */
    public function test_get_metadata_declares_openai(): void {
        $collection = provider::get_metadata(new collection('block_helpai'));
        $items = $collection->get_collection();

        $tables = [];
        $openai = null;
        foreach ($items as $item) {
            if ($item instanceof database_table) {
                $tables[] = $item->get_name();
            }
            if ($item instanceof external_location && $item->get_name() === 'openai') {
                $openai = $item;
            }
        }

        $this->assertContains('block_helpai_history', $tables);
        $this->assertContains('block_helpai_questions', $tables);
        $this->assertNotNull($openai, 'OpenAI must be declared as an external location');

        $fields = $openai->get_privacy_fields();
        $this->assertArrayHasKey('prompttext', $fields);
        $this->assertArrayHasKey('pdfs', $fields);
        $this->assertArrayHasKey('model', $fields);
        $this->assertSame('privacy:metadata:openai', $openai->get_summary());

        // Strings used as identifiers must exist (Moodle debugging flags invalid ones).
        $this->assertNotEmpty(get_string('privacy:metadata:openai', 'block_helpai'));
        $this->assertNotEmpty(get_string('privacy:metadata:openai:prompttext', 'block_helpai'));
        $this->assertNotEmpty(get_string('privacy:metadata:openai:pdfs', 'block_helpai'));
        $this->assertNotEmpty(get_string('privacy:metadata:openai:model', 'block_helpai'));
    }
}
