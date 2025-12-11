<?php
// Quick test script to debug PDF indexing.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

use block_helpai\pdf_indexer;
use block_helpai\pdf_processor;

echo "Testing PDF indexing for course ID: 20\n\n";

// Enable debugging.
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

try {
    // Get PDFs in course.
    echo "Getting PDFs in course...\n";
    $pdfs = pdf_processor::get_course_pdfs(20);

    echo "Found " . count($pdfs) . " PDFs:\n";
    foreach ($pdfs as $pdf) {
        echo "  - {$pdf['name']} ({$pdf['filename']})\n";
        echo "    Content hash: {$pdf['contenthash']}\n";

        // Try to extract text.
        echo "    Extracting text...\n";
        $text = pdf_processor::extract_pdf_text($pdf['contenthash']);

        if (empty($text)) {
            echo "    ERROR: Empty text extracted\n";
        } else if ($text === get_string('pdftextnotavailable', 'block_helpai')) {
            echo "    ERROR: PDF text not available\n";
        } else if ($text === get_string('pdftoolnotavailable', 'block_helpai')) {
            echo "    ERROR: PDF tool not available\n";
        } else {
            $length = strlen($text);
            echo "    SUCCESS: Extracted {$length} characters\n";
            echo "    Preview: " . substr($text, 0, 100) . "...\n";
        }
        echo "\n";
    }

    echo "\nNow trying to index course...\n";
    $stats = pdf_indexer::index_course_pdfs(20);

    echo "Indexing stats:\n";
    echo "  - Indexed: {$stats['indexed']}\n";
    echo "  - Skipped: {$stats['skipped']}\n";
    echo "  - Errors: {$stats['errors']}\n";

} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
