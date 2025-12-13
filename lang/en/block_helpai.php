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
 * English language strings for HelpAI block.
 *
 * @package    block_helpai
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'HelpAI';
$string['helpai:addinstance'] = 'Add a new HelpAI block';
$string['helpai:myaddinstance'] = 'Add a new HelpAI block to Dashboard';
$string['helpai:askquestion'] = 'Ask questions to HelpAI';

// Block content.
$string['welcomemessage'] = 'Hello! Ask me about the PDFs in this course and I\'ll tell you where to find the information.';
$string['askquestion'] = 'Ask your question...';
$string['send'] = 'Send';
$string['notincourse'] = 'This block is only available in course pages.';

// AI responses.
$string['nopdfsavailable'] = 'There are no PDF documents available in this course.';
$string['ainotenabled'] = 'AI is not enabled in this Moodle site. Please contact your administrator.';
$string['noprovideravailable'] = 'No AI provider is available. Please contact your administrator.';
$string['invalidairesponse'] = 'Invalid response from AI provider.';
$string['aierror'] = 'Error processing AI request';

// PDF processing.
$string['nopdfs'] = 'No PDF documents found in this course.';
$string['pdftextnotavailable'] = 'PDF text content is not available.';
$string['pdftoolnotavailable'] = 'PDF text extraction tool is not available on this server.';

// Search.
$string['nokeywords'] = 'Could not extract keywords from your question. Please try rephrasing.';
$string['noresults'] = 'No PDFs found matching your question.';
$string['foundinfo'] = 'I found relevant information in the following PDFs:';

// Tasks.
$string['taskindexpdfs'] = 'Index PDF documents for search';

// Settings.
$string['openai_apikey'] = 'OpenAI API Key';
$string['openai_apikey_desc'] = 'Enter your OpenAI API key. You can get one from https://platform.openai.com/api-keys';
$string['openai_model'] = 'OpenAI Model';
$string['openai_model_desc'] = 'Select which OpenAI model to use. GPT-4o and GPT-4 Turbo support PDF analysis.';
$string['searchmode'] = 'Search mode';
$string['searchmode_desc'] = 'Choose how to search PDFs: Hybrid (local search first, then AI) or AI only (always use AI, no text extraction needed)';
$string['searchmode_hybrid'] = 'Hybrid (local + AI fallback) - Requires pdftotext';
$string['searchmode_aionly'] = 'AI only (always use AI) - No pdftotext required';

// History.
$string['clearhistory'] = 'Clear history';
$string['confirmclearhistory'] = 'Are you sure you want to clear your entire conversation history?';
$string['historycleared'] = 'History cleared successfully.';

// Quick actions.
$string['quickactions'] = 'Suggested questions:';
$string['quickaction1'] = 'What topics are covered in the PDFs of this course?';
$string['quickaction2'] = 'Where can I find information about the main course topic?';
$string['quickaction3'] = 'Which PDF should I read first?';

// Schemas.
$string['schemas'] = 'Schemas';
$string['chat'] = 'Chat';
$string['schemas_tab'] = 'PDF Schemas';
$string['schemas_description'] = 'Generate structured outlines of course PDFs to get an overview of the content.';
$string['generate_schema'] = 'Generate schema';
$string['view_schema'] = 'View schema';
$string['regenerate_schema'] = 'Regenerate schema';
$string['generating_schema'] = 'Generating schema...';
$string['schema_generated'] = 'Schema generated successfully';
$string['no_schemas_yet'] = 'No schemas generated yet. Click "Generate schema" to create one.';
$string['schema_generation_failed'] = 'Failed to generate schema. Please try again.';
$string['loading_pdfs'] = 'Loading PDFs...';
$string['no_pdfs_in_course'] = 'No PDFs available in this course.';
$string['schema_for'] = 'Schema for: {$a}';
$string['generated_on'] = 'Generated on: {$a}';
$string['close_schema'] = 'Close schema';

// Privacy.
$string['privacy:metadata'] = 'The HelpAI block stores user conversation history.';
$string['privacy:metadata:block_helpai_history'] = 'HelpAI chat conversation history';
$string['privacy:metadata:block_helpai_history:userid'] = 'User ID who asked the question';
$string['privacy:metadata:block_helpai_history:courseid'] = 'Course ID where the question was asked';
$string['privacy:metadata:block_helpai_history:role'] = 'Message role (user or assistant)';
$string['privacy:metadata:block_helpai_history:message'] = 'Message content';
$string['privacy:metadata:block_helpai_history:timecreated'] = 'Timestamp when the message was created';
