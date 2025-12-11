<?php
// Test AJAX endpoint.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

use block_helpai\external\process_question;

echo "Testing AJAX endpoint...\n\n";

// Enable debugging.
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

// Set up user.
$admin = get_admin();
$USER = $admin;

try {
    echo "Calling process_question::execute(20, 'test question')...\n";

    $result = process_question::execute(20, 'What is the Spanish Civil War about?');

    echo "\nResult:\n";
    print_r($result);

} catch (Exception $e) {
    echo "\nEXCEPTION: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
