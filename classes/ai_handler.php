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
 * AI handler for HelpAI block using OpenAI API directly.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_helpai;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to handle AI interactions using OpenAI API.
 */
class ai_handler {

    /**
     * Process a question and return which PDF contains the answer.
     *
     * @param string $question User's question.
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @param bool $forceai Force AI usage even if local search works.
     * @return array Response with PDF reference.
     */
    public static function process_question($question, $courseid, $userid, $forceai = false) {
        try {
            // Check if OpenAI is configured.
            $apikey = get_config('block_helpai', 'openai_apikey');
            if (empty($apikey)) {
                return [
                    'success' => false,
                    'message' => get_string('ainotenabled', 'block_helpai'),
                    'aiused' => false,
                    'outcome' => question_log::OUTCOME_ERROR,
                ];
            }

            // Get search mode setting.
            $searchmode = get_config('block_helpai', 'searchmode');
            if (empty($searchmode)) {
                $searchmode = 'aionly'; // Default to AI only.
            }

            // If hybrid mode, try local search first.
            if ($searchmode === 'hybrid' && !$forceai) {
                // Check if PDFs are indexed, if not, index them.
                self::ensure_pdfs_indexed($courseid);

                // Try local search first (no AI cost!).
                if (local_search::should_use_local_search($question, $courseid, $userid)) {
                    $localresult = local_search::search($question, $courseid, $userid);

                    // If local search found results, return them.
                    if ($localresult['success'] && !empty($localresult['pdfs'])) {
                        $localresult['aiused'] = false;
                        $localresult['outcome'] = question_log::OUTCOME_ANSWERED;
                        return $localresult;
                    }
                }
            }

            // Use AI (either AI-only mode or hybrid fallback).
            return self::process_with_ai($question, $courseid, $userid);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('aierror', 'block_helpai') . ': ' . $e->getMessage(),
                'aiused' => false,
                'outcome' => question_log::OUTCOME_ERROR,
            ];
        }
    }

    /**
     * Ensure PDFs are indexed for the course.
     *
     * @param int $courseid Course ID.
     */
    private static function ensure_pdfs_indexed($courseid) {
        // Get all PDFs in course.
        $pdfs = pdf_processor::get_course_pdfs($courseid);

        // Check which ones need indexing.
        foreach ($pdfs as $pdf) {
            if (!pdf_indexer::is_pdf_indexed($pdf['contenthash'])) {
                // Index this PDF.
                $text = pdf_processor::extract_pdf_text($pdf['contenthash']);
                if (!empty($text) && $text !== get_string('pdftextnotavailable', 'block_helpai')) {
                    pdf_indexer::index_course_pdfs($courseid);
                    break; // Index all at once.
                }
            }
        }
    }

    /**
     * Process question using AI.
     *
     * @param string $question User's question.
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return array Response with PDF reference.
     */
    private static function process_with_ai($question, $courseid, $userid) {
        // Only PDFs this user is allowed to see in this course.
        $pdfs = pdf_processor::get_course_pdfs($courseid, $userid);

        if (empty($pdfs)) {
            return [
                'success' => false,
                'message' => get_string('nopdfsavailable', 'block_helpai'),
                'aiused' => false,
                'outcome' => question_log::OUTCOME_NO_PDFS,
            ];
        }

        // Check search mode.
        $searchmode = get_config('block_helpai', 'searchmode');

        if ($searchmode === 'aionly') {
            // Send PDFs directly to AI (no text extraction needed!).
            return self::process_with_ai_direct($question, $pdfs);
        } else {
            // Use cached text content, still limited to visible course PDFs.
            return self::process_with_ai_cached($question, $courseid, $pdfs);
        }
    }

    /**
     * Shared system prompt: stay inside this course and refuse if not in the PDFs.
     *
     * @return string
     */
    private static function get_system_prompt() {
        return "You are a study assistant for ONE Moodle course. You may only use the PDF materials from THIS course that are listed for you. Never use documents from other courses. Never invent facts that are not in those PDFs.

RULES:
1. If the provided course PDFs contain the answer, tell the student WHERE to find it (PDF name, section/chapter if you can see it). Point at that PDF. Do not dump the whole answer if you can guide them; you may briefly summarise what the materials say.
2. If the materials do NOT contain the answer, you MUST start your reply with the single word REFUSED on its own first line, then briefly say that the course PDFs do not contain this information. Do not invent. Do not guess a PDF. Do not use general knowledge to fill the gap.
3. Never mention or use documents that were not listed.
4. Reply in the same language as the student's question.
5. Be a supportive tutor.";
    }

    /**
     * Strip a leading REFUSED marker and report whether the model refused.
     *
     * @param string $response Raw model text.
     * @return array{refused:bool,message:string}
     */
    private static function apply_refusal_marker($response) {
        if (preg_match('/^\s*REFUSED\b\s*/i', $response)) {
            $message = trim(preg_replace('/^\s*REFUSED\b\s*/i', '', $response));
            if ($message === '') {
                $message = get_string('noanswerinmaterials', 'block_helpai');
            }
            return ['refused' => true, 'message' => $message];
        }
        return ['refused' => false, 'message' => $response];
    }

    /**
     * Process with AI using direct PDF files.
     *
     * @param string $question User's question.
     * @param array $pdfs Array of PDF information.
     * @return array Response.
     */
    private static function process_with_ai_direct($question, $pdfs) {
        // Build messages for OpenAI.
        $messages = [];

        // System message with instructions.
        $messages[] = [
            'role' => 'system',
            'content' => self::get_system_prompt(),
        ];

        // Build user message with PDFs information.
        $usermessage = "I have " . count($pdfs) . " PDF documents:\n\n";
        foreach ($pdfs as $idx => $pdf) {
            $usermessage .= ($idx + 1) . ". " . $pdf['name'] . " (" . $pdf['filename'] . ")\n";
        }
        $usermessage .= "\nQuestion: " . $question;

        $messages[] = [
            'role' => 'user',
            'content' => $usermessage,
        ];

        // Get model setting.
        $model = get_config('block_helpai', 'openai_model');
        if (empty($model)) {
            $model = 'gpt-4o';
        }

        // Call OpenAI API.
        $response = self::call_openai_api($messages, $model);

        if (!$response['success']) {
            $response['aiused'] = true;
            $response['outcome'] = question_log::OUTCOME_ERROR;
            return $response;
        }

        // Parse response.
        $result = self::parse_ai_response($response['response'], $pdfs);
        $refusal = self::apply_refusal_marker($result['message']);

        return [
            'success' => true,
            'message' => $refusal['message'],
            'pdfs' => $refusal['refused'] ? [] : $result['pdfs'],
            'aiused' => true,
            'outcome' => $refusal['refused'] ? question_log::OUTCOME_REFUSED : question_log::OUTCOME_ANSWERED,
        ];
    }

    /**
     * Process with AI using cached text.
     *
     * @param string $question User's question.
     * @param int $courseid Course ID.
     * @param array $pdfs Array of PDF information.
     * @return array Response.
     */
    private static function process_with_ai_cached($question, $courseid, $pdfs) {
        // Cached text only for PDFs this user can see in this course.
        $visiblecmids = [];
        foreach ($pdfs as $pdf) {
            $visiblecmids[$pdf['cmid']] = true;
        }
        $cachedpdfs = pdf_indexer::get_cached_pdfs($courseid, array_keys($visiblecmids));

        if (empty($cachedpdfs)) {
            return [
                'success' => false,
                'message' => get_string('nopdfsavailable', 'block_helpai'),
                'aiused' => false,
                'outcome' => question_log::OUTCOME_NO_PDFS,
            ];
        }

        // Build messages for OpenAI.
        $messages = [];

        // System message.
        $messages[] = [
            'role' => 'system',
            'content' => self::get_system_prompt(),
        ];

        // Build user message with PDF content previews.
        $usermessage = "Available PDFs in this course:\n\n";

        $idx = 1;
        foreach ($cachedpdfs as $pdf) {
            $usermessage .= $idx . ". PDF Name: " . $pdf->pdfname . "\n";
            $usermessage .= "   Filename: " . $pdf->filename . "\n";

            // Include content preview (first 1000 characters).
            if (!empty($pdf->content)) {
                $preview = substr($pdf->content, 0, 1000);
                $usermessage .= "   Content preview: " . $preview . "...\n";
            }
            $usermessage .= "\n";
            $idx++;
        }

        $usermessage .= "User's question: " . $question;

        $messages[] = [
            'role' => 'user',
            'content' => $usermessage,
        ];

        // Get model setting.
        $model = get_config('block_helpai', 'openai_model');
        if (empty($model)) {
            $model = 'gpt-4o';
        }

        // Call OpenAI API.
        $response = self::call_openai_api($messages, $model);

        if (!$response['success']) {
            $response['aiused'] = true;
            $response['outcome'] = question_log::OUTCOME_ERROR;
            return $response;
        }

        // Parse response.
        $result = self::parse_ai_response_from_cache($response['response'], $cachedpdfs);
        $refusal = self::apply_refusal_marker($result['message']);

        return [
            'success' => true,
            'message' => $refusal['message'],
            'pdfs' => $refusal['refused'] ? [] : $result['pdfs'],
            'aiused' => true,
            'outcome' => $refusal['refused'] ? question_log::OUTCOME_REFUSED : question_log::OUTCOME_ANSWERED,
        ];
    }

    /**
     * Call OpenAI API.
     *
     * @param array $messages Array of messages for the chat.
     * @param string $model Model to use.
     * @return array Response from OpenAI.
     */
    private static function call_openai_api($messages, $model) {
        $apikey = get_config('block_helpai', 'openai_apikey');

        if (empty($apikey)) {
            return [
                'success' => false,
                'message' => get_string('ainotenabled', 'block_helpai'),
            ];
        }

        // Build request body.
        $requestbody = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1200, // Increased for detailed study guidance responses.
        ];

        $jsonbody = json_encode($requestbody);

        // Initialize cURL.
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonbody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apikey,
        ]);

        // Execute request.
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($ch);
        curl_close($ch);

        if ($curlerror) {
            return [
                'success' => false,
                'message' => get_string('aierror', 'block_helpai') . ': cURL error - ' . $curlerror,
            ];
        }

        if ($httpcode !== 200) {
            $errordata = json_decode($response, true);
            $errormsg = isset($errordata['error']['message']) ? $errordata['error']['message'] : 'HTTP ' . $httpcode;
            return [
                'success' => false,
                'message' => get_string('aierror', 'block_helpai') . ': ' . $errormsg,
            ];
        }

        // Parse response.
        $data = json_decode($response, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            return [
                'success' => false,
                'message' => get_string('invalidairesponse', 'block_helpai'),
            ];
        }

        return [
            'success' => true,
            'response' => $data['choices'][0]['message']['content'],
        ];
    }

    /**
     * Parse AI response to extract PDF references.
     *
     * @param string $response AI response text.
     * @param array $pdfs Available PDFs.
     * @return array Parsed result.
     */
    private static function parse_ai_response($response, $pdfs) {
        $referencedpdfs = [];

        // Search for PDF names in the response.
        foreach ($pdfs as $pdf) {
            if (stripos($response, $pdf['name']) !== false || stripos($response, $pdf['filename']) !== false) {
                $referencedpdfs[] = [
                    'name' => $pdf['name'],
                    'filename' => $pdf['filename'],
                    'cmid' => $pdf['cmid'],
                ];
            }
        }

        return [
            'message' => $response,
            'pdfs' => $referencedpdfs,
        ];
    }

    /**
     * Parse AI response from cached PDFs.
     *
     * @param string $response AI response text.
     * @param array $cachedpdfs Cached PDF records.
     * @return array Parsed result.
     */
    private static function parse_ai_response_from_cache($response, $cachedpdfs) {
        $referencedpdfs = [];

        // Search for PDF names in the response.
        foreach ($cachedpdfs as $pdf) {
            if (stripos($response, $pdf->pdfname) !== false || stripos($response, $pdf->filename) !== false) {
                $referencedpdfs[] = [
                    'name' => $pdf->pdfname,
                    'filename' => $pdf->filename,
                    'cmid' => $pdf->cmid,
                ];
            }
        }

        return [
            'message' => $response,
            'pdfs' => $referencedpdfs,
        ];
    }

    /**
     * Generate a schema/outline for a PDF.
     *
     * @param int $cmid Course module ID.
     * @param int $courseid Course ID.
     * @return array Result with schema.
     */
    public static function generate_pdf_schema($cmid, $courseid) {
        global $USER;

        try {
            // Check if OpenAI is configured.
            $apikey = get_config('block_helpai', 'openai_apikey');
            if (empty($apikey)) {
                return [
                    'success' => false,
                    'message' => get_string('ainotenabled', 'block_helpai'),
                ];
            }

            // Get the PDF details the current user is allowed to see.
            $pdfs = pdf_processor::get_course_pdfs($courseid, $USER->id);
            $targetpdf = null;

            foreach ($pdfs as $pdf) {
                if ($pdf['cmid'] == $cmid) {
                    $targetpdf = $pdf;
                    break;
                }
            }

            if (!$targetpdf) {
                return [
                    'success' => false,
                    'message' => get_string('nopdfs', 'block_helpai'),
                ];
            }

            // Get cached PDF content.
            $cachedpdfs = pdf_indexer::get_cached_pdfs($courseid);
            $pdfcontent = null;

            foreach ($cachedpdfs as $cached) {
                if ($cached->cmid == $cmid) {
                    $pdfcontent = $cached->content;
                    break;
                }
            }

            if (empty($pdfcontent)) {
                return [
                    'success' => false,
                    'message' => get_string('pdftextnotavailable', 'block_helpai'),
                ];
            }

            // Build the prompt for schema generation.
            $model = get_config('block_helpai', 'openai_model');
            if (empty($model)) {
                $model = 'gpt-4o';
            }

            $messages = [];
            $messages[] = [
                'role' => 'system',
                'content' => "Eres un asistente especializado en crear esquemas/resúmenes estructurados de documentos PDF académicos.

Tu tarea es analizar el contenido del PDF y crear un esquema detallado y estructurado que incluya:

1. **Título del documento**
2. **Introducción/Resumen general** (2-3 líneas)
3. **Estructura principal**:
   - Capítulos o secciones principales
   - Subsecciones importantes
   - Temas clave tratados en cada sección
4. **Conceptos importantes** mencionados
5. **Conclusiones** (si las hay)

Formato del esquema:
- Usa encabezados claros (con ##, ###)
- Usa listas numeradas para capítulos y viñetas para subsecciones
- Sé específico sobre QUÉ se trata en cada sección
- Incluye detalles que ayuden al estudiante a navegar el documento
- Usa un lenguaje claro y conciso en español

El esquema debe ser lo suficientemente detallado para que un estudiante entienda la estructura completa del documento sin tenerlo que leer completo.",
            ];

            // Add the PDF content.
            // Limit content to first 15000 characters to avoid token limits.
            $contentpreview = substr($pdfcontent, 0, 15000);

            $messages[] = [
                'role' => 'user',
                'content' => "Por favor, genera un esquema detallado del siguiente documento PDF:\n\n" .
                             "Nombre: {$targetpdf['name']}\n" .
                             "Archivo: {$targetpdf['filename']}\n\n" .
                             "Contenido del PDF:\n{$contentpreview}" .
                             (strlen($pdfcontent) > 15000 ? "\n\n[El documento continúa...]" : ""),
            ];

            // Call OpenAI API.
            $result = self::call_openai_api($messages, $model);

            if ($result['success']) {
                return [
                    'success' => true,
                    'outline' => $result['response'],
                    'pdfname' => $targetpdf['name'],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['error'] ?? get_string('aierror', 'block_helpai'),
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('aierror', 'block_helpai') . ': ' . $e->getMessage(),
            ];
        }
    }
}
