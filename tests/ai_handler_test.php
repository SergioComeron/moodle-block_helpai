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

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for AI-only PDF attachment helpers.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(ai_handler::class)]
final class ai_handler_test extends \advanced_testcase {

    /**
     * GPT-4o family accepts native PDF parts; 3.5 does not.
     */
    public function test_model_supports_pdf_files(): void {
        $this->assertTrue(ai_handler::model_supports_pdf_files('gpt-4o'));
        $this->assertTrue(ai_handler::model_supports_pdf_files('gpt-4-turbo'));
        $this->assertFalse(ai_handler::model_supports_pdf_files('gpt-3.5-turbo'));
        $this->assertFalse(ai_handler::model_supports_pdf_files('gpt-4'));
    }

    /**
     * Size caps keep a single request under the OpenAI file budget.
     */
    public function test_can_attach_pdf(): void {
        $this->assertFalse(ai_handler::can_attach_pdf(0, 0));
        $this->assertTrue(ai_handler::can_attach_pdf(10890, 0));
        $this->assertFalse(ai_handler::can_attach_pdf(ai_handler::MAX_PDF_BYTES + 1, 0));
        $this->assertFalse(ai_handler::can_attach_pdf(100, ai_handler::MAX_PDF_TOTAL_BYTES));
    }

    /**
     * File parts use the Chat Completions data-URL shape.
     */
    public function test_make_pdf_file_part(): void {
        $binary = '%PDF-1.4 test';
        $part = ai_handler::make_pdf_file_part('demo.pdf', $binary);
        $this->assertSame('file', $part['type']);
        $this->assertSame('demo.pdf', $part['file']['filename']);
        $this->assertStringStartsWith('data:application/pdf;base64,', $part['file']['file_data']);
        $this->assertSame(
            base64_encode($binary),
            substr($part['file']['file_data'], strlen('data:application/pdf;base64,'))
        );
    }

    /**
     * Only file-input API errors trigger the text-only retry.
     */
    public function test_file_input_rejected(): void {
        $this->assertTrue(ai_handler::file_input_rejected("invalid 'file_data'"));
        $this->assertTrue(ai_handler::file_input_rejected('unsupported file type'));
        $this->assertFalse(ai_handler::file_input_rejected('Incorrect API key provided'));
    }

    /**
     * AI-only messages attach stored PDFs, not just their titles.
     */
    public function test_build_direct_ai_messages_attaches_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $fs = get_file_storage();
        $resource = $generator->create_module('resource', [
            'course' => $course->id,
            'name' => 'La conquista de América',
        ]);
        $cm = get_coursemodule_from_instance('resource', $resource->id);
        $context = \context_module::instance($cm->id);
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_resource',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'conquista_america.pdf',
            'mimetype' => 'application/pdf',
        ], '%PDF-1.4 fake-conquista');

        $pdfs = pdf_processor::get_course_pdfs($course->id, $user->id);
        $this->assertCount(1, $pdfs);
        $this->assertArrayHasKey('fileid', $pdfs[0]);
        $this->assertGreaterThan(0, $pdfs[0]['fileid']);

        $messages = ai_handler::build_direct_ai_messages('quién descubrió America?', $pdfs, true);
        $parts = $messages[1]['content'];
        $files = array_values(array_filter($parts, static function($part) {
            return ($part['type'] ?? '') === 'file';
        }));
        $this->assertCount(1, $files);
        $this->assertSame('conquista_america.pdf', $files[0]['file']['filename']);
        $this->assertStringContainsString('quién descubrió America?', $parts[count($parts) - 1]['text']);
    }
}
