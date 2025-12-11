<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

global $DB;

echo "Checking block_helpai installation...\n\n";

// Check version in database.
$version = $DB->get_record('config_plugins', ['plugin' => 'block_helpai', 'name' => 'version']);
echo "Version in DB: " . ($version ? $version->value : 'NOT INSTALLED') . "\n";
echo "Version in file: 2025121001\n\n";

// Check if tables exist.
$dbman = $DB->get_manager();
$table1 = new xmldb_table('block_helpai_pdf_cache');
$table2 = new xmldb_table('block_helpai_pdf_index');

echo "Table block_helpai_pdf_cache exists: " . ($dbman->table_exists($table1) ? 'YES' : 'NO') . "\n";
echo "Table block_helpai_pdf_index exists: " . ($dbman->table_exists($table2) ? 'YES' : 'NO') . "\n";
