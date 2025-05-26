<?php
header('Content-Type: application/json');


class PDFtoHTMLConverter {
    
    private $apiKey;
    private $baseUrl = 'https://api.pdf.co/v1';
    private $googleTranslateApiKey; // Add your Google Translate API key here
    private $logs = [];
    
    public function __construct($apiKey, $googleTranslateApiKey) {
        $this->apiKey = $apiKey;
        $this->googleTranslateApiKey = $googleTranslateApiKey;
    }
    
   
    private function log($message) {
        $this->logs[] = $message;
    }
    
 
    public function getLogs() {
        return $this->logs;
    }
    
  
    private function translateContent($htmlContent) {
        $this->log("Starting translation process...");
        
     
        $dom = new DOMDocument();
        @$dom->loadHTML($htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
    
        $xpath = new DOMXPath($dom);
        
       
        $textNodes = $xpath->query('//text()');
        
        $this->log("Found " . $textNodes->length . " text nodes to process");
        
        foreach ($textNodes as $node) {
            $text = trim($node->nodeValue);
            
           
            if (empty($text)) {
                continue;
            }
            
            if (strpos($text, '&') !== false && preg_match('/&[a-zA-Z0-9#]+;/', $text)) {
                continue;
            }
            
            $this->log("Processing text: '$text'");
            
        
            $translatedText = $this->translateTextSegment($text);
            
            if ($translatedText !== $text) {
                $this->log("Translation successful: '$text' -> '$translatedText'");
                
                $node->nodeValue = $translatedText;
            }
        }
        
        // Get the modified HTML
        $translatedContent = $dom->saveHTML();
        
        $this->log("Translation process completed.");
        
        return $translatedContent;
    }
    
  
    private function translateTextSegment($text) {
        if (empty(trim($text))) {
            return $text;
        }
        
        $this->log("Sending translation request for: '$text'");
        
        try {
            $url = 'https://translation.googleapis.com/language/translate/v2';
            
            $postData = [
                'q' => $text,
                'source' => 'es',
                'target' => 'en',
                'key' => $this->googleTranslateApiKey
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if (isset($result['data']['translations'][0]['translatedText'])) {
                return html_entity_decode($result['data']['translations'][0]['translatedText'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } else {
                $this->log("Translation failed. API Error: " . json_encode($result['error'] ?? []));
                return $text;
            }
            
        } catch (Exception $e) {
            $this->log("Exception during translation: " . $e->getMessage());
            return $text;
        }
    }
    
   
    public function convertPDFToHTML($inputFile, $outputFile = null, $options = []) {
        // Default options
        $defaultOptions = [
            'simple' => false,
            'columns' => false,
            'pages' => '',
            'password' => '',
            'async' => false
        ];
        
        $options = array_merge($defaultOptions, $options);
        
       
        $params = [
            'url' => $this->isUrl($inputFile) ? $inputFile : '',
            'name' => $outputFile ?: 'converted_' . time() . '.html',
            'simple' => $options['simple'] ? 'true' : 'false',
            'columns' => $options['columns'] ? 'true' : 'false',
            'pages' => $options['pages'],
            'password' => $options['password'],
            'async' => $options['async'] ? 'true' : 'false'
        ];
        
        
        if (!$this->isUrl($inputFile)) {
            $this->log("Uploading local file: $inputFile");
            $uploadResult = $this->uploadFile($inputFile);
            if (!$uploadResult['success']) {
                $this->log("Upload failed: " . ($uploadResult['error'] ?? 'Unknown error'));
                return $uploadResult;
            }
            $params['url'] = $uploadResult['url'];
            $this->log("File uploaded successfully");
        }
        
     
        $endpoint = $this->baseUrl . '/pdf/convert/to/html';
        
        
        $queryString = http_build_query(array_filter($params));
        $fullUrl = $endpoint . '?' . $queryString;
        
        $this->log("Sending conversion request to PDF.co API");
        
       
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
       
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
       
        if ($curlError) {
            $this->log("cURL Error: $curlError");
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $curlError,
                'logs' => $this->getLogs()
            ];
        }
        
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result['url'])) {
            $this->log("PDF successfully converted to HTML");
            
        
            $downloadResult = $this->downloadFile($result['url'], $params['name']);
            
            if ($downloadResult['success']) {
                $this->log("HTML file downloaded successfully");
                return [
                    'success' => true,
                    'message' => 'PDF successfully converted to HTML',
                    'output_file' => $downloadResult['filename'],
                    'download_url' => $result['url'],
                    'pages_processed' => $result['pageCount'] ?? 'Unknown',
                    'logs' => $this->getLogs()
                ];
            } else {
                $this->log("Failed to download HTML file: " . ($downloadResult['error'] ?? 'Unknown error'));
                return $downloadResult;
            }
        } else {
            $this->log("API Error: " . ($result['message'] ?? 'Unknown error'));
            return [
                'success' => false,
                'error' => $result['message'] ?? 'Unknown error occurred',
                'http_code' => $httpCode,
                'response' => $result,
                'logs' => $this->getLogs()
            ];
        }
    }
    
   
    private function uploadFile($filePath) {
        if (!file_exists($filePath)) {
            $this->log("File not found: $filePath");
            return [
                'success' => false,
                'error' => 'File not found: ' . $filePath,
                'logs' => $this->getLogs()
            ];
        }
        
        $endpoint = $this->baseUrl . '/file/upload';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new CURLFile($filePath)
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result['url'])) {
            return [
                'success' => true,
                'url' => $result['url']
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['message'] ?? 'Failed to upload file',
                'logs' => $this->getLogs()
            ];
        }
    }
    
   
    private function downloadFile($url, $filename) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $fileContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $fileContent) {
            $savedFile = 'downloads/' . $filename;
            
           
            if (!is_dir('downloads')) {
                mkdir('downloads', 0755, true);
            }
            
            if (file_put_contents($savedFile, $fileContent)) {
                return [
                    'success' => true,
                    'filename' => $savedFile
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to save file locally',
                    'logs' => $this->getLogs()
                ];
            }
        } else {
            return [
                'success' => false,
                'error' => 'Failed to download converted file',
                'logs' => $this->getLogs()
            ];
        }
    }
    
  
    private function isUrl($string) {
        return filter_var($string, FILTER_VALIDATE_URL) !== false;
    }
  
    private function convertHTMLToPDF($htmlFile, $pdfFile) {
        $this->log("Converting HTML to PDF using PDF.co API...");
        
        // Read the HTML content
        $htmlContent = file_get_contents($htmlFile);
        if ($htmlContent === false) {
            $this->log("Error: Could not read HTML file");
            return false;
        }
        
        $url = $this->baseUrl . '/pdf/convert/from/html';
        
        $postData = [
            'html' => $htmlContent,
            'name' => basename($pdfFile),
            'async' => false
        ];
        
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        
       
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
       
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result['url'])) {
           
            $pdfContent = file_get_contents($result['url']);
            if ($pdfContent === false) {
                $this->log("Error: Could not download the converted PDF");
                return false;
            }
            
            if (file_put_contents($pdfFile, $pdfContent) === false) {
                $this->log("Error: Could not save the PDF file");
                return false;
            }
            
            $this->log("Successfully converted HTML to PDF: $pdfFile");
            return true;
        } else {
            $this->log("Error: PDF conversion failed. " . ($result['message'] ?? ''));
            return false;
        }
    }

    
    public function convertPDFToHTMLAndTranslate($inputFile, $outputFile = null, $options = []) {
        $this->log("Starting PDF to HTML conversion and translation process...");
        
    
        $result = $this->convertPDFToHTML($inputFile, $outputFile, $options);
        
        if ($result['success']) {
            $this->log("PDF conversion successful.");
            $this->log("Reading HTML from: " . $result['output_file']);
            
           
            $htmlContent = file_get_contents($result['output_file']);
            if ($htmlContent === false) {
                return [
                    'success' => false,
                    'error' => 'Could not read the HTML file',
                    'logs' => $this->getLogs()
                ];
            }
            
        
            $translatedContent = $this->translateContent($htmlContent);
            
         
            $translatedFile = str_replace('.html', '_translated.html', $result['output_file']);
            
            if (file_put_contents($translatedFile, $translatedContent) === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to save translated file',
                    'logs' => $this->getLogs()
                ];
            }
            
            $result['translated_file'] = $translatedFile;
            
          
            $outputPdfFile = str_replace('.html', '_translated.pdf', $result['output_file']);
            if ($this->convertHTMLToPDF($translatedFile, $outputPdfFile)) {
                $result['translated_pdf'] = $outputPdfFile;
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to create final PDF',
                    'logs' => $this->getLogs()
                ];
            }
        }
        
        $result['logs'] = $this->getLogs();
        return $result;
    }
}

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($argv[1])) {
        // Configuration
        $apiKey = 'your-pdfco-api-key';
        $googleTranslateApiKey = 'your-google-translate-api-key';
        
     
        $converter = new PDFtoHTMLConverter($apiKey, $googleTranslateApiKey);
        
        try {
            
            $inputFile = $_POST['pdf_file'] ?? $argv[1] ?? null;
            $outputFile = $_POST['output_file'] ?? ($argv[2] ?? null);
            
            if (!$inputFile) {
                throw new Exception('No input file specified');
            }
            
            
            $options = [
                'simple' => isset($_POST['simple']) ? (bool)$_POST['simple'] : false,
                'columns' => isset($_POST['columns']) ? (bool)$_POST['columns'] : false,
                'pages' => $_POST['pages'] ?? '',
                'password' => $_POST['password'] ?? '',
                'async' => isset($_POST['async']) ? (bool)$_POST['async'] : false
            ];
            
            
            $result = $converter->convertPDFToHTMLAndTranslate($inputFile, $outputFile, $options);
            
            
            echo json_encode($result);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}

?> 