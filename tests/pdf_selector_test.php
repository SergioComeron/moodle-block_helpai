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
 * Tests for ranking which PDFs to send to OpenAI.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(pdf_selector::class)]
final class pdf_selector_test extends \advanced_testcase {

    /**
     * Fold accents so "América" matches "America".
     */
    public function test_normalize_folds_accents(): void {
        $this->assertSame('america', pdf_selector::normalize('América'));
        $this->assertSame('descubrio', pdf_selector::normalize('descubrió'));
    }

    /**
     * Question words that are not stopwords become keywords.
     */
    public function test_keywords_keep_topic_words(): void {
        $kw = pdf_selector::keywords('quién descubrió America?');
        $this->assertContains('america', $kw);
        $this->assertContains('descubrio', $kw);
        $this->assertNotContains('quien', $kw);
    }

    /**
     * Title match beats an unrelated PDF.
     */
    public function test_score_prefers_title_hit(): void {
        $kw = pdf_selector::keywords('quién descubrió America?');
        $conquista = [
            'name' => 'La conquista de América',
            'filename' => 'conquista_america.pdf',
        ];
        $guerra = [
            'name' => 'La guerra civil española',
            'filename' => 'guerra_civil.pdf',
        ];
        $this->assertGreaterThan(
            pdf_selector::score_pdf($guerra, $kw),
            pdf_selector::score_pdf($conquista, $kw)
        );
    }

    /**
     * Courses with few PDFs attach all that fit (the test-course case).
     */
    public function test_small_course_attaches_all(): void {
        $pdfs = [
            self::pdf(1, 'La conquista de América', 'conquista_america.pdf', 10000),
            self::pdf(2, 'La guerra civil española', 'guerra_civil.pdf', 16000),
        ];
        $selected = pdf_selector::select($pdfs, 'quién descubrió America?');
        $this->assertCount(2, $selected);
    }

    /**
     * Larger courses attach only the top matches, not every file.
     */
    public function test_large_course_attaches_matches_only(): void {
        $pdfs = [];
        for ($i = 1; $i <= 8; $i++) {
            $pdfs[] = self::pdf($i, "Tema {$i}", "tema{$i}.pdf", 1000);
        }
        $pdfs[] = self::pdf(99, 'La conquista de América', 'conquista.pdf', 2000);

        $selected = pdf_selector::select($pdfs, 'quién descubrió America?');
        $this->assertCount(1, $selected);
        $this->assertSame(99, $selected[0]['cmid']);
    }

    /**
     * No title/cache hit on a large course: attach nothing (titles still go in the catalogue).
     */
    public function test_large_course_no_match_attaches_none(): void {
        $pdfs = [];
        for ($i = 1; $i <= 8; $i++) {
            $pdfs[] = self::pdf($i, "Tema {$i}", "tema{$i}.pdf", 1000);
        }
        $selected = pdf_selector::select($pdfs, 'quién descubrió America?');
        $this->assertSame([], $selected);
    }

    /**
     * Files over the per-file byte cap are skipped.
     */
    public function test_skips_file_over_size_cap(): void {
        $pdfs = [
            self::pdf(1, 'América gorda', 'big.pdf', ai_handler::MAX_PDF_BYTES + 1),
            self::pdf(2, 'América corta', 'small.pdf', 1000),
        ];
        $selected = pdf_selector::select($pdfs, 'america');
        $this->assertCount(1, $selected);
        $this->assertSame(2, $selected[0]['cmid']);
    }

    /**
     * Cache text can rank a PDF whose title does not match.
     */
    public function test_cache_text_can_score(): void {
        $pdfs = [
            self::pdf(1, 'Tema 3', 't3.pdf', 1000),
            self::pdf(2, 'Apéndice', 'ap.pdf', 1000),
        ];
        $cache = [1 => 'Cristóbal Colón descubrió América en 1492'];
        $selected = pdf_selector::select($pdfs, 'quién descubrió America?', $cache);
        $this->assertCount(2, $selected);
        $this->assertSame(1, $selected[0]['cmid']);
    }

    /**
     * Helper to build a PDF row.
     *
     * @param int $cmid Course-module id.
     * @param string $name Display name.
     * @param string $filename File name.
     * @param int $filesize Size in bytes.
     * @return array
     */
    private static function pdf($cmid, $name, $filename, $filesize) {
        return [
            'cmid' => $cmid,
            'name' => $name,
            'filename' => $filename,
            'filesize' => $filesize,
            'contenthash' => 'h' . $cmid,
        ];
    }
}
