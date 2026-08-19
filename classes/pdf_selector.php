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
 * Choose which course PDFs to send to OpenAI for one question.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_helpai;

defined('MOODLE_INTERNAL') || die();

/**
 * Rank visible PDFs and cap how many files go in one API request.
 */
class pdf_selector {

    /** Default max files attached per question. */
    const DEFAULT_MAX_FILES = 3;

    /** Hard ceiling for the admin setting. */
    const ABSOLUTE_MAX_FILES = 10;

    /** Combined size of attached files per question (20 MB). */
    const MAX_SELECTED_BYTES = 20971520;

    /**
     * How many PDFs may be attached for one ask.
     *
     * @return int
     */
    public static function get_max_files() {
        $n = get_config('block_helpai', 'maxpdfsperask');
        if ($n === false || $n === '' || $n === null) {
            return self::DEFAULT_MAX_FILES;
        }
        $n = (int)$n;
        if ($n <= 0) {
            return self::DEFAULT_MAX_FILES;
        }
        return min(self::ABSOLUTE_MAX_FILES, $n);
    }

    /**
     * Lowercase, strip accents, keep letters and digits.
     *
     * @param string $text Raw text.
     * @return string
     */
    public static function normalize($text) {
        $text = \core_text::strtolower((string)$text);
        $from = ['á', 'à', 'ä', 'â', 'é', 'è', 'ë', 'ê', 'í', 'ì', 'ï', 'î',
            'ó', 'ò', 'ö', 'ô', 'ú', 'ù', 'ü', 'û', 'ñ', 'ç'];
        $to = ['a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i',
            'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'n', 'c'];
        $text = str_replace($from, $to, $text);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Meaningful keywords from the student question.
     *
     * @param string $question Question text.
     * @return array
     */
    public static function keywords($question) {
        $stop = [
            'que', 'quien', 'quienes', 'cual', 'cuales', 'como', 'cuando', 'donde',
            'porque', 'para', 'por', 'con', 'los', 'las', 'una', 'unos', 'unas',
            'del', 'este', 'esta', 'esto', 'ese', 'eso', 'aqui', 'alli', 'sobre',
            'entre', 'desde', 'hasta', 'hacia', 'pero', 'aunque', 'todo', 'toda',
            'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'what', 'where',
            'when', 'which', 'who', 'whom', 'whose', 'why', 'how', 'does', 'did',
            'this', 'that', 'from', 'with', 'about', 'into', 'have', 'has',
        ];
        $words = preg_split('/\s+/', self::normalize($question), -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($words as $word) {
            if (strlen($word) > 3 && !in_array($word, $stop, true)) {
                $out[] = $word;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Score one PDF against the question keywords.
     *
     * Title/filename hits weigh more than cache-text hits.
     *
     * @param array $pdf PDF info from pdf_processor::get_course_pdfs().
     * @param array $keywords Normalized keywords.
     * @param string|null $cachetext Extracted text if indexed.
     * @return int
     */
    public static function score_pdf(array $pdf, array $keywords, $cachetext = null) {
        if (empty($keywords)) {
            return 0;
        }
        $title = self::normalize(($pdf['name'] ?? '') . ' ' . ($pdf['filename'] ?? ''));
        $score = 0;
        foreach ($keywords as $kw) {
            if ($kw !== '' && str_contains($title, $kw)) {
                $score += 10;
            }
        }
        if ($cachetext) {
            $body = self::normalize($cachetext);
            foreach ($keywords as $kw) {
                if ($kw === '' || $body === '') {
                    continue;
                }
                $score += min(5, substr_count($body, $kw));
            }
        }
        return $score;
    }

    /**
     * PDFs to attach for this question (subset of $pdfs).
     *
     * Small courses (≤ max files): attach every file that fits the size cap.
     * Larger courses: attach the top scored files. If nothing scores, attach
     * none — the catalogue of titles still goes to the model.
     *
     * @param array $pdfs Visible course PDFs.
     * @param string $question Student question.
     * @param array $cachebycmid Extracted text keyed by cmid.
     * @return array Selected PDF rows, in score order.
     */
    public static function select(array $pdfs, $question, array $cachebycmid = []) {
        $maxfiles = self::get_max_files();
        $keywords = self::keywords($question);

        $ranked = [];
        foreach ($pdfs as $pdf) {
            $cmid = $pdf['cmid'] ?? 0;
            $cache = $cachebycmid[$cmid] ?? null;
            $ranked[] = [
                'pdf' => $pdf,
                'score' => self::score_pdf($pdf, $keywords, $cache),
            ];
        }

        usort($ranked, static function($a, $b) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            return ((int)$a['pdf']['filesize']) <=> ((int)$b['pdf']['filesize']);
        });

        if (count($pdfs) <= $maxfiles) {
            $candidates = $ranked;
        } else {
            $candidates = array_values(array_filter($ranked, static function($row) {
                return $row['score'] > 0;
            }));
            $candidates = array_slice($candidates, 0, $maxfiles);
        }

        $selected = [];
        $total = 0;
        foreach ($candidates as $row) {
            $size = (int)$row['pdf']['filesize'];
            if ($size <= 0 || $size > ai_handler::MAX_PDF_BYTES) {
                continue;
            }
            if (($total + $size) > self::MAX_SELECTED_BYTES) {
                continue;
            }
            $selected[] = $row['pdf'];
            $total += $size;
            if (count($selected) >= $maxfiles) {
                break;
            }
        }
        return $selected;
    }

    /**
     * Extracted-text map for scoring, keyed by cmid.
     *
     * @param int $courseid Course ID.
     * @param array $pdfs Visible PDFs.
     * @return array
     */
    public static function cache_map($courseid, array $pdfs) {
        $cmids = [];
        foreach ($pdfs as $pdf) {
            if (!empty($pdf['cmid'])) {
                $cmids[] = $pdf['cmid'];
            }
        }
        if (empty($cmids)) {
            return [];
        }
        $records = pdf_indexer::get_cached_pdfs($courseid, $cmids);
        $map = [];
        foreach ($records as $record) {
            if (!empty($record->content)) {
                $map[$record->cmid] = $record->content;
            }
        }
        return $map;
    }
}
