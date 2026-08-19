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
 * @copyright  2025–2026 Sergio Comerón
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
     * Get PDF resources from a course.
     *
     * Only resource files that belong to this course are considered. When
     * $userid is provided, hidden or otherwise unavailable activities are
     * dropped so a student never receives (or sends to the model) a PDF
     * they cannot open.
     *
     * Pass null for $userid from the scheduled indexer so the cache can
     * hold every course PDF; asking paths must pass the asking user.
     *
     * @param int $courseid Course ID.
     * @param int|null $userid User to check visibility for, or null to skip.
     * @return array Array of PDF resources with their content.
     */
    public static function get_course_pdfs($courseid, $userid = null) {
        global $DB;

        $pdfs = [];

        // Get resource PDFs that belong to this course only.
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

        $modinfo = null;
        if ($userid) {
            $modinfo = get_fast_modinfo($courseid, $userid);
        }

        foreach ($resources as $resource) {
            if ($modinfo) {
                if (!isset($modinfo->cms[$resource->cmid])) {
                    continue;
                }
                $cm = $modinfo->cms[$resource->cmid];
                if (!$cm->uservisible) {
                    continue;
                }
                $modcontext = \context_module::instance($cm->id);
                if (!has_capability('mod/resource:view', $modcontext, $userid)) {
                    continue;
                }
            }

            $pdfs[] = [
                'id' => $resource->id,
                'cmid' => $resource->cmid,
                'fileid' => (int)$resource->fileid,
                'name' => $resource->name,
                'filename' => $resource->filename,
                'contenthash' => $resource->contenthash,
                'filesize' => $resource->filesize,
            ];
        }

        return $pdfs;
    }

    /**
     * Course-module IDs of PDFs the user is allowed to see in this course.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return array List of cmids keyed by cmid.
     */
    public static function get_visible_pdf_cmids($courseid, $userid) {
        $cmids = [];
        foreach (self::get_course_pdfs($courseid, $userid) as $pdf) {
            $cmids[$pdf['cmid']] = $pdf['cmid'];
        }
        return $cmids;
    }

    /**
     * Stored file for a course PDF row from get_course_pdfs().
     *
     * @param array $pdf PDF info (fileid and/or contenthash).
     * @return \stored_file|null
     */
    public static function get_stored_file(array $pdf) {
        global $DB;

        $fs = get_file_storage();
        if (!empty($pdf['fileid'])) {
            $stored = $fs->get_file_by_id((int)$pdf['fileid']);
            if ($stored) {
                return $stored;
            }
        }
        if (empty($pdf['contenthash'])) {
            return null;
        }
        $record = $DB->get_record_select(
            'files',
            'contenthash = :hash AND filename <> :dot AND filesize > 0',
            ['hash' => $pdf['contenthash'], 'dot' => '.'],
            '*',
            IGNORE_MULTIPLE
        );
        return $record ? $fs->get_file_by_id($record->id) : null;
    }

    /**
     * Extract text content from a PDF file.
     *
     * @param string $contenthash File content hash.
     * @return string Extracted text content.
     */
    public static function extract_pdf_text($contenthash) {
        $storedfile = self::get_stored_file(['contenthash' => $contenthash]);

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

            // Method 1a: ASCII85 + FlateDecode (ReportLab and similar).
            if (preg_match_all(
                '/\/Filter\s*\[\s*\/ASCII85Decode\s*\/FlateDecode\s*\].*?stream\s*\r?\n(.*?)\r?\nendstream/s',
                $content,
                $matches
            )) {
                foreach ($matches[1] as $stream) {
                    $decoded85 = self::ascii85_decode($stream);
                    $decoded = $decoded85 !== '' ? @gzuncompress($decoded85) : false;
                    if ($decoded !== false) {
                        $text .= self::decode_pdf_stream($decoded) . ' ';
                    }
                }
            }

            // Method 1b: Extract text from compressed streams (FlateDecode).
            if (strlen($text) < 50) {
                if (preg_match_all('/<<.*?\/Filter\s*\/FlateDecode.*?>>.*?stream\s*\n(.*?)\n\s*endstream/s', $content, $matches)) {
                    foreach ($matches[1] as $stream) {
                        $decoded = @gzuncompress($stream);
                        if ($decoded !== false) {
                            $text .= self::decode_pdf_stream($decoded) . ' ';
                        }
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
     * Decode Adobe ASCII85 (used by ReportLab before FlateDecode).
     *
     * @param string $data ASCII85 payload, optionally ending in ~>.
     * @return string Binary data, or '' on failure.
     */
    public static function ascii85_decode($data) {
        $data = preg_replace('/\s+/', '', $data);
        $data = str_replace('~>', '', $data);
        if ($data === '' || $data === null) {
            return '';
        }

        $out = '';
        $len = strlen($data);
        $i = 0;
        while ($i < $len) {
            if ($data[$i] === 'z') {
                $out .= "\0\0\0\0";
                $i++;
                continue;
            }
            $chunk = substr($data, $i, 5);
            $n = strlen($chunk);
            if ($n < 2) {
                break;
            }
            $padded = $chunk . str_repeat('u', 5 - $n);
            $val = 0;
            for ($j = 0; $j < 5; $j++) {
                $c = ord($padded[$j]);
                if ($c < 33 || $c > 117) {
                    return '';
                }
                $val = $val * 85 + ($c - 33);
            }
            $out .= substr(pack('N', $val), 0, $n - 1);
            $i += $n;
        }
        return $out;
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
