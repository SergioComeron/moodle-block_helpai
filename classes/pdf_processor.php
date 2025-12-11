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
 * PDF processor for HelpAI block.
 *
 * @package    block_helpai
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_helpai;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/resource/lib.php');

/**
 * Class to handle PDF resources in a course.
 */
class pdf_processor {

    /**
     * Get all PDF resources from a course.
     *
     * @param int $courseid Course ID.
     * @return array Array of PDF resources with their content.
     */
    public static function get_course_pdfs($courseid) {
        global $DB;

        $pdfs = [];

        // Get all resource modules in the course.
        $sql = "SELECT r.id, r.name, cm.id as cmid, f.id as fileid,
                       f.filename, f.contenthash, f.filesize
                FROM {resource} r
                JOIN {course_modules} cm ON cm.instance = r.id
                JOIN {modules} m ON m.id = cm.module
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                JOIN {files} f ON f.contextid = ctx.id
                WHERE cm.course = :courseid
                AND m.name = 'resource'
                AND f.component = 'mod_resource'
                AND f.filearea = 'content'
                AND f.filename != '.'
                AND f.mimetype = 'application/pdf'
                ORDER BY r.name";

        $params = [
            'courseid' => $courseid,
            'contextlevel' => CONTEXT_MODULE,
        ];

        $resources = $DB->get_records_sql($sql, $params);

        foreach ($resources as $resource) {
            $pdfs[] = [
                'id' => $resource->id,
                'cmid' => $resource->cmid,
                'name' => $resource->name,
                'filename' => $resource->filename,
                'contenthash' => $resource->contenthash,
                'filesize' => $resource->filesize,
            ];
        }

        return $pdfs;
    }

    /**
     * Extract text content from a PDF file.
     *
     * @param string $contenthash File content hash.
     * @return string Extracted text content.
     */
    public static function extract_pdf_text($contenthash) {
        global $DB;

        $fs = get_file_storage();

        // Get file by content hash.
        $files = $DB->get_records('files', ['contenthash' => $contenthash, 'filename' => ['!=' => '.']]);

        if (empty($files)) {
            return '';
        }

        $file = reset($files);
        $storedfile = $fs->get_file_by_id($file->id);

        if (!$storedfile) {
            return '';
        }

        // Try to extract text using pdftotext if available.
        $text = self::pdftotext($storedfile);

        if (empty($text)) {
            // Fallback: return basic info if extraction fails.
            return get_string('pdftextnotavailable', 'block_helpai');
        }

        return $text;
    }

    /**
     * Extract text from PDF using external tools or libraries.
     *
     * @param stored_file $file The PDF file.
     * @return string Extracted text.
     */
    private static function pdftotext($file) {
        global $CFG;

        // Try pdftotext command first if available.
        $text = self::extract_with_pdftotext($file);
        if (!empty($text) && $text !== get_string('pdftoolnotavailable', 'block_helpai')) {
            return $text;
        }

        // Fallback to PHP-based extraction.
        $text = self::extract_with_php($file);
        if (!empty($text)) {
            return $text;
        }

        return get_string('pdftoolnotavailable', 'block_helpai');
    }

    /**
     * Try to extract text using pdftotext command.
     *
     * @param stored_file $file The PDF file.
     * @return string Extracted text or empty string.
     */
    private static function extract_with_pdftotext($file) {
        global $CFG;

        $tempfile = tempnam($CFG->tempdir, 'pdfhelper_');
        $pdfpath = $tempfile . '.pdf';
        $txtpath = $tempfile . '.txt';

        try {
            $file->copy_content_to($pdfpath);

            $cmd = "pdftotext " . escapeshellarg($pdfpath) . " " . escapeshellarg($txtpath) . " 2>&1";
            exec($cmd, $output, $returnvar);

            if ($returnvar === 0 && file_exists($txtpath)) {
                $text = file_get_contents($txtpath);
                @unlink($pdfpath);
                @unlink($txtpath);
                @unlink($tempfile);
                return $text;
            }

            @unlink($pdfpath);
            @unlink($txtpath);
            @unlink($tempfile);

            return '';

        } catch (\Exception $e) {
            @unlink($pdfpath);
            @unlink($txtpath);
            @unlink($tempfile);
            return '';
        }
    }

    /**
     * Extract text from PDF using pure PHP (basic extraction).
     *
     * @param stored_file $file The PDF file.
     * @return string Extracted text.
     */
    private static function extract_with_php($file) {
        try {
            $content = $file->get_content();

            if (empty($content)) {
                return '';
            }

            $text = '';

            // Method 1: Extract text from compressed streams (FlateDecode).
            if (preg_match_all('/<<.*?\/Filter\s*\/FlateDecode.*?>>.*?stream\s*\n(.*?)\n\s*endstream/s', $content, $matches)) {
                foreach ($matches[1] as $stream) {
                    $decoded = @gzuncompress($stream);
                    if ($decoded !== false) {
                        $text .= self::decode_pdf_stream($decoded) . ' ';
                    }
                }
            }

            // Method 2: Extract text from uncompressed streams.
            if (strlen($text) < 100) {
                if (preg_match_all('/stream\s*\n(.*?)\n\s*endstream/s', $content, $matches)) {
                    foreach ($matches[1] as $stream) {
                        if (strpos($stream, 'FlateDecode') === false) {
                            $decoded = self::decode_pdf_stream($stream);
                            $text .= $decoded . ' ';
                        }
                    }
                }
            }

            // Method 3: Extract text between parentheses (simple text strings in PDF).
            if (strlen($text) < 100) {
                if (preg_match_all('/\((.*?)\)/s', $content, $matches)) {
                    foreach ($matches[1] as $match) {
                        // Clean up PDF escapes.
                        $cleaned = str_replace(['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'],
                                               ['(', ')', '\\', ' ', ' ', ' '], $match);
                        $text .= $cleaned . ' ';
                    }
                }
            }

            // Method 4: Extract using BT/ET markers (text objects).
            if (strlen($text) < 100) {
                if (preg_match_all('/BT\s+(.*?)\s+ET/s', $content, $matches)) {
                    foreach ($matches[1] as $textobj) {
                        // Extract strings from text objects.
                        if (preg_match_all('/\((.*?)\)/s', $textobj, $strings)) {
                            foreach ($strings[1] as $str) {
                                $text .= $str . ' ';
                            }
                        }
                    }
                }
            }

            // Clean up the extracted text.
            $text = self::clean_pdf_text($text);

            // If we got less than 50 characters, consider it a failure.
            if (strlen($text) < 50) {
                return '';
            }

            return $text;

        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Decode a PDF stream (basic decoding for uncompressed streams).
     *
     * @param string $stream The stream content.
     * @return string Decoded text.
     */
    private static function decode_pdf_stream($stream) {
        // Remove PDF operators and extract text-like content.
        $text = preg_replace('/[^\x20-\x7E\s]/', '', $stream);
        return $text;
    }

    /**
     * Clean extracted PDF text.
     *
     * @param string $text Raw text.
     * @return string Cleaned text.
     */
    private static function clean_pdf_text($text) {
        // Remove extra whitespace.
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove common PDF artifacts.
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);

        // Trim.
        $text = trim($text);

        return $text;
    }

    /**
     * Get PDF content summary for AI processing.
     *
     * @param int $courseid Course ID.
     * @return string Formatted PDF content summary.
     */
    public static function get_pdfs_summary($courseid) {
        $pdfs = self::get_course_pdfs($courseid);

        if (empty($pdfs)) {
            return get_string('nopdfs', 'block_helpai');
        }

        $summary = "Available PDFs in this course:\n\n";

        foreach ($pdfs as $pdf) {
            $summary .= "PDF: {$pdf['name']} (File: {$pdf['filename']})\n";
            $summary .= "---\n";

            // For now, we'll include basic info.
            // In production, you might want to extract and index PDF content.
            $text = self::extract_pdf_text($pdf['contenthash']);
            $summary .= substr($text, 0, 1000) . "...\n\n";
        }

        return $summary;
    }
}
