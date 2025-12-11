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
 * PDF indexer for HelpAI block.
 *
 * @package    block_helpai
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_helpai;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to handle PDF indexing and caching.
 */
class pdf_indexer {

    /**
     * Index all PDFs in a course.
     *
     * @param int $courseid Course ID.
     * @return array Statistics about indexing.
     */
    public static function index_course_pdfs($courseid) {
        $stats = [
            'indexed' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $pdfs = pdf_processor::get_course_pdfs($courseid);

        foreach ($pdfs as $pdf) {
            try {
                if (self::is_pdf_indexed($pdf['contenthash'])) {
                    $stats['skipped']++;
                    continue;
                }

                $text = pdf_processor::extract_pdf_text($pdf['contenthash']);

                if (empty($text) || $text === get_string('pdftextnotavailable', 'block_helpai')) {
                    $stats['errors']++;
                    continue;
                }

                self::cache_pdf_content($courseid, $pdf, $text);
                self::index_pdf_content($pdf['contenthash'], $text);

                $stats['indexed']++;

            } catch (\Exception $e) {
                $stats['errors']++;
                debugging('Error indexing PDF: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return $stats;
    }

    /**
     * Check if a PDF is already indexed.
     *
     * @param string $contenthash File content hash.
     * @return bool True if indexed.
     */
    public static function is_pdf_indexed($contenthash) {
        global $DB;

        return $DB->record_exists('block_helpai_pdf_cache', ['contenthash' => $contenthash]);
    }

    /**
     * Cache PDF content in database.
     *
     * @param int $courseid Course ID.
     * @param array $pdf PDF information.
     * @param string $text Extracted text.
     * @return int Cache record ID.
     */
    private static function cache_pdf_content($courseid, $pdf, $text) {
        global $DB;

        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->cmid = $pdf['cmid'];
        $record->contenthash = $pdf['contenthash'];
        $record->filename = $pdf['filename'];
        $record->pdfname = $pdf['name'];
        $record->content = $text;
        $record->filesize = $pdf['filesize'];
        $record->timecreated = time();
        $record->timemodified = time();

        return $DB->insert_record('block_helpai_pdf_cache', $record);
    }

    /**
     * Index PDF content for searching.
     *
     * @param string $contenthash File content hash.
     * @param string $text Text content to index.
     */
    private static function index_pdf_content($contenthash, $text) {
        global $DB;

        // Get cache ID.
        $cache = $DB->get_record('block_helpai_pdf_cache', ['contenthash' => $contenthash], 'id');
        if (!$cache) {
            return;
        }

        // Tokenize and count words.
        $words = self::tokenize($text);
        $wordfreq = array_count_values($words);

        // Only index meaningful words (more than 3 characters, appears at least twice).
        $wordfreq = array_filter($wordfreq, function($freq, $word) {
            return strlen($word) > 3 && $freq >= 2;
        }, ARRAY_FILTER_USE_BOTH);

        // Sort by frequency (descending) and limit to top 500 words.
        arsort($wordfreq);
        $wordfreq = array_slice($wordfreq, 0, 500, true);

        // Insert index records.
        $records = [];
        foreach ($wordfreq as $word => $freq) {
            $record = new \stdClass();
            $record->cacheid = $cache->id;
            $record->word = $word;
            $record->frequency = $freq;
            $records[] = $record;
        }

        if (!empty($records)) {
            $DB->insert_records('block_helpai_pdf_index', $records);
        }
    }

    /**
     * Tokenize text into words.
     *
     * @param string $text Text to tokenize.
     * @return array Array of words.
     */
    private static function tokenize($text) {
        // Convert to lowercase.
        $text = \core_text::strtolower($text);

        // Remove special characters, keep only letters, numbers, and spaces.
        $text = preg_replace('/[^a-z0-9\s\u00C0-\u00FF]/u', ' ', $text);

        // Split by whitespace.
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stop words (common words).
        $words = self::remove_stopwords($words);

        return $words;
    }

    /**
     * Remove common stop words.
     *
     * @param array $words Array of words.
     * @return array Filtered array.
     */
    private static function remove_stopwords($words) {
        // Common stop words in English and Spanish.
        $stopwords = [
            // English.
            'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'her', 'was', 'one',
            'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'man', 'new', 'now', 'old',
            'see', 'two', 'way', 'who', 'boy', 'did', 'its', 'let', 'put', 'say', 'she', 'too', 'use',
            'with', 'that', 'this', 'have', 'from', 'they', 'will', 'what', 'been', 'more', 'when',
            'your', 'about', 'than', 'into', 'could', 'would', 'there', 'their', 'which', 'other',
            // Spanish.
            'que', 'los', 'del', 'las', 'una', 'por', 'con', 'para', 'como', 'este', 'esta', 'esto',
            'ese', 'eso', 'aquel', 'aquella', 'aquello', 'estos', 'estas', 'esos', 'esas', 'aquellos',
            'aquellas', 'todo', 'toda', 'todos', 'todas', 'otro', 'otra', 'otros', 'otras', 'algo',
            'alguien', 'alguno', 'alguna', 'algunos', 'algunas', 'nada', 'nadie', 'ninguno', 'ninguna',
            'ningunos', 'ningunas', 'mismo', 'misma', 'mismos', 'mismas', 'cual', 'cuales', 'quien',
            'quienes', 'donde', 'cuando', 'porque', 'sino', 'pero', 'aunque', 'sobre', 'entre', 'desde',
            'hasta', 'hacia', 'tras', 'durante', 'mediante', 'excepto', 'salvo', 'menos',
        ];

        return array_filter($words, function($word) use ($stopwords) {
            return !in_array($word, $stopwords);
        });
    }

    /**
     * Get cached PDF content for a course.
     *
     * @param int $courseid Course ID.
     * @return array Array of cached PDFs.
     */
    public static function get_cached_pdfs($courseid) {
        global $DB;

        return $DB->get_records('block_helpai_pdf_cache', ['courseid' => $courseid]);
    }

    /**
     * Clear cache for a specific PDF.
     *
     * @param string $contenthash File content hash.
     */
    public static function clear_pdf_cache($contenthash) {
        global $DB;

        $cache = $DB->get_record('block_helpai_pdf_cache', ['contenthash' => $contenthash]);
        if ($cache) {
            // Delete index entries.
            $DB->delete_records('block_helpai_pdf_index', ['cacheid' => $cache->id]);
            // Delete cache entry.
            $DB->delete_records('block_helpai_pdf_cache', ['id' => $cache->id]);
        }
    }

    /**
     * Clear all cache for a course.
     *
     * @param int $courseid Course ID.
     */
    public static function clear_course_cache($courseid) {
        global $DB;

        $caches = $DB->get_records('block_helpai_pdf_cache', ['courseid' => $courseid]);
        foreach ($caches as $cache) {
            $DB->delete_records('block_helpai_pdf_index', ['cacheid' => $cache->id]);
        }
        $DB->delete_records('block_helpai_pdf_cache', ['courseid' => $courseid]);
    }
}
