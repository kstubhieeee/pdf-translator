<?php
header('Content-Type: application/json');

require_once 'pdf_to_html_converter.php';

try {
  
    if (!isset($_FILES['pdf_file'])) {
        throw new Exception('No file was uploaded');
    }

    $uploadedFile = $_FILES['pdf_file'];

  
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed with error code: ' . $uploadedFile['error']);
    }

    
    $fileType = mime_content_type($uploadedFile['tmp_name']);
    if ($fileType !== 'application/pdf') {
        throw new Exception('Uploaded file must be a PDF');
    }

    
    if (!is_dir('uploads')) {
        mkdir('uploads', 0755, true);
    }

    
    if (!is_dir('downloads')) {
        mkdir('downloads', 0755, true);
    }

   
    $timestamp = time();
    $uploadPath = 'uploads/' . $timestamp . '_' . basename($uploadedFile['name']);

   
    if (!move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to save uploaded file');
    }

 
    $apiKey = 'your-pdfco-api-key';
    $googleTranslateApiKey = 'your-api-key';
    $converter = new PDFtoHTMLConverter($apiKey, $googleTranslateApiKey);

    
    $result = $converter->convertPDFToHTMLAndTranslate($uploadPath);

    if (!$result['success']) {
        throw new Exception($result['error'] ?? 'Failed to process PDF');
    }

  
    if (!isset($result['translated_pdf']) || !file_exists($result['translated_pdf'])) {
        throw new Exception('Translated PDF was not generated');
    }

  
    $downloadUrl = 'download.php?file=' . urlencode(basename($result['translated_pdf']));

   
    echo json_encode([
        'success' => true,
        'download_url' => $downloadUrl,
        'message' => 'PDF processed successfully',
        'logs' => $result['logs'] ?? []
    ]);

} catch (Exception $e) {
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'logs' => isset($result['logs']) ? $result['logs'] : []
    ]);
}


$files = glob('uploads/*');
$now = time();
foreach ($files as $file) {
    if (is_file($file) && ($now - filemtime($file) > 3600)) { // Remove files older than 1 hour
        unlink($file);
    }
} 