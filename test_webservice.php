<?php
// Test web service with detailed debugging.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Enable debugging.
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

echo "Testing web service...\n\n";

// Set up user.
$admin = get_admin();
$USER = $admin;

// Simulate AJAX call.
$_POST['args'] = json_encode([
    'courseid' => 20,
    'question' => 'What is the Spanish Civil War about?'
]);

try {
    echo "Calling web service directly...\n";

    $courseid = 20;
    $question = 'What is the Spanish Civil War about?';

    // Check if course exists.
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    echo "Course found: " . $course->fullname . "\n\n";

    // Check context.
    $context = context_course::instance($courseid);
    echo "Context ID: " . $context->id . "\n\n";

    // Check capability.
    $hascap = has_capability('block/helpai:askquestion', $context);
    echo "Has capability: " . ($hascap ? 'YES' : 'NO') . "\n\n";

    // Call the web service function directly.
    echo "Calling process_question::execute()...\n";
    $result = \block_helpai\external\process_question::execute($courseid, $question);

    echo "\nResult:\n";
    print_r($result);

} catch (Exception $e) {
    echo "\nEXCEPTION: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
