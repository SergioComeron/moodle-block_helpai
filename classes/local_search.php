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
 * Local search without AI for HelpAI block.
 *
 * @package    block_helpai
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_helpai;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to handle local keyword-based search.
 */
class local_search {

    /**
     * Search for PDFs matching the question keywords.
     *
     * @param string $question User's question.
     * @param int $courseid Course ID.
     * @return array Search results with PDFs and scores.
     */
    public static function search($question, $courseid) {
        global $DB;

        // Get question keywords.
        $keywords = self::extract_keywords($question);

        if (empty($keywords)) {
            return [
                'success' => false,
                'message' => get_string('nokeywords', 'block_helpai'),
                'pdfs' => [],
            ];
        }

        // Search in cached PDFs.
        $results = self::search_in_cache($keywords, $courseid);

        if (empty($results)) {
            return [
                'success' => false,
                'message' => get_string('noresults', 'block_helpai'),
                'pdfs' => [],
                'needsai' => true, // Indicates AI might be needed.
            ];
        }

        // Format response.
        $topresults = array_slice($results, 0, 3); // Top 3 results.
        $pdflist = [];

        foreach ($topresults as $result) {
            $pdflist[] = [
                'name' => $result->pdfname,
                'filename' => $result->filename,
                'cmid' => $result->cmid,
                'score' => $result->score,
            ];
        }

        // Build message.
        $message = get_string('foundinfo', 'block_helpai') . "\n\n";
        foreach ($pdflist as $idx => $pdf) {
            $message .= ($idx + 1) . ". " . $pdf['name'] . "\n";
        }

        return [
            'success' => true,
            'message' => $message,
            'pdfs' => $pdflist,
            'needsai' => false,
        ];
    }

    /**
     * Extract keywords from question.
     *
     * @param string $question Question text.
     * @return array Array of keywords.
     */
    private static function extract_keywords($question) {
        // Convert to lowercase.
        $text = \core_text::strtolower($question);

        // Remove special characters.
        $text = preg_replace('/[^a-z0-9\s\u00C0-\u00FF]/u', ' ', $text);

        // Split by whitespace.
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stop words and short words.
        $keywords = array_filter($words, function($word) {
            return strlen($word) > 3 && !self::is_stopword($word);
        });

        return array_values($keywords);
    }

    /**
     * Check if word is a stop word.
     *
     * @param string $word Word to check.
     * @return bool True if stop word.
     */
    private static function is_stopword($word) {
        $stopwords = [
            // English.
            'what', 'where', 'when', 'which', 'who', 'whom', 'whose', 'why', 'how',
            'does', 'did', 'doing', 'would', 'could', 'should', 'will', 'shall',
            'may', 'might', 'must', 'can', 'the', 'and', 'for', 'are', 'but', 'not',
            'you', 'all', 'her', 'was', 'one', 'our', 'out', 'day', 'get', 'has',
            'him', 'his', 'man', 'new', 'now', 'old', 'see', 'two', 'way', 'boy',
            'did', 'its', 'let', 'put', 'say', 'she', 'too', 'use', 'with', 'that',
            'this', 'have', 'from', 'they', 'been', 'more', 'your', 'about', 'than',
            'into', 'there', 'their', 'other',
            // Spanish.
            'qué', 'que', 'dónde', 'donde', 'cuándo', 'cuando', 'cuál', 'cual',
            'quién', 'quien', 'cómo', 'como', 'por', 'para', 'con', 'los', 'las',
            'del', 'una', 'este', 'esta', 'esto', 'ese', 'eso', 'aquel', 'aquella',
            'todo', 'toda', 'todos', 'todas', 'otro', 'otra', 'otros', 'otras',
            'algo', 'alguien', 'alguno', 'alguna', 'nada', 'nadie', 'ninguno',
            'sobre', 'entre', 'desde', 'hasta', 'hacia', 'pero', 'aunque',
        ];

        return in_array($word, $stopwords);
    }

    /**
     * Search keywords in cached PDFs.
     *
     * @param array $keywords Keywords to search.
     * @param int $courseid Course ID.
     * @return array Array of matching PDFs with scores.
     */
    private static function search_in_cache($keywords, $courseid) {
        global $DB;

        if (empty($keywords)) {
            return [];
        }

        // Build SQL for keyword search.
        list($insql, $params) = $DB->get_in_or_equal($keywords, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;

        $sql = "SELECT c.id, c.cmid, c.pdfname, c.filename, c.content,
                       SUM(i.frequency) as score
                FROM {block_helpai_pdf_cache} c
                INNER JOIN {block_helpai_pdf_index} i ON i.cacheid = c.id
                WHERE c.courseid = :courseid
                AND i.word $insql
                GROUP BY c.id, c.cmid, c.pdfname, c.filename, c.content
                ORDER BY score DESC";

        $results = $DB->get_records_sql($sql, $params);

        // If no results from index, try full-text search in content.
        if (empty($results)) {
            $results = self::fulltext_search($keywords, $courseid);
        }

        return $results;
    }

    /**
     * Full-text search in PDF content.
     *
     * @param array $keywords Keywords to search.
     * @param int $courseid Course ID.
     * @return array Array of matching PDFs.
     */
    private static function fulltext_search($keywords, $courseid) {
        global $DB;

        $pdfs = $DB->get_records('block_helpai_pdf_cache', ['courseid' => $courseid]);
        $results = [];

        foreach ($pdfs as $pdf) {
            $score = 0;
            $content = \core_text::strtolower($pdf->content);

            foreach ($keywords as $keyword) {
                // Count occurrences of keyword in content.
                $count = substr_count($content, $keyword);
                $score += $count;
            }

            if ($score > 0) {
                $pdf->score = $score;
                $results[$pdf->id] = $pdf;
            }
        }

        // Sort by score.
        uasort($results, function($a, $b) {
            return $b->score - $a->score;
        });

        return $results;
    }

    /**
     * Check if local search is likely to work.
     *
     * @param string $question Question text.
     * @param int $courseid Course ID.
     * @return bool True if local search should be attempted.
     */
    public static function should_use_local_search($question, $courseid) {
        // Check if there are cached PDFs.
        $cachedcount = pdf_indexer::get_cached_pdfs($courseid);

        if (empty($cachedcount)) {
            return false;
        }

        // Check if question has meaningful keywords.
        $keywords = self::extract_keywords($question);

        return count($keywords) > 0;
    }
}
