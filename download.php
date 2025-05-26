<?php
// Validate file parameter
if (!isset($_GET['file'])) {
    die('No file specified');
}

$filename = basename($_GET['file']);
$filepath = 'downloads/' . $filename;

// Validate file exists and is within downloads directory
if (!file_exists($filepath) || !is_file($filepath)) {
    die('File not found');
}

// Set headers for forced download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="translated_' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Output file content
readfile($filepath);

// Clean up - delete file after download
unlink($filepath); 