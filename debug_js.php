<?php
// Debug script to check what JavaScript is sending.

require(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

echo "<!DOCTYPE html>\n";
echo "<html>\n<head>\n<title>HelpAI Debug</title>\n";
echo "<script src=\"{$CFG->wwwroot}/lib/requirejs.php\"></script>\n";
echo "</head>\n<body>\n";
echo "<h1>HelpAI JavaScript Debug</h1>\n";
echo "<p>Course ID from PHP: {$courseid}</p>\n";

echo "<div id='block-helpai-messages' style='border:1px solid #ccc; padding:10px; margin:10px 0; height:200px; overflow-y:auto;'></div>\n";
echo "<textarea id='block-helpai-input' rows='2' style='width:100%;'></textarea>\n";
echo "<button id='block-helpai-send' class='btn btn-primary'>Send</button>\n";

echo "<script>\n";
echo "require(['block_helpai/chat'], function(chat) {\n";
echo "    console.log('Initializing chat with courseid: {$courseid}');\n";
echo "    chat.init({courseid: {$courseid}, contextid: 1});\n";
echo "});\n";
echo "</script>\n";

echo "</body>\n</html>\n";
