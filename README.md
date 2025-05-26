# PDF Spanish to English Translator

A web-based application that converts Spanish PDF documents to English using PDF.co and Google Translate APIs. The application preserves the original PDF formatting while translating the content.

## Features

- PDF to HTML conversion with formatting preservation
- Spanish to English translation
- Automatic PDF regeneration after translation
- Web-based interface for easy file upload
- Progress tracking during conversion
- Automatic file cleanup

## Prerequisites

- PHP 7.4 or higher
- cURL extension for PHP
- Write permissions for the application directory
- PDF.co API key
- Google Cloud Translation API key

## Installation

1. Clone the repository or download the source code:
```bash
git clone [repository-url]
cd pdf-translator
```

2. Create required directories:
```bash
mkdir uploads
mkdir downloads
chmod 755 uploads downloads
```

3. Configure API keys:
   - Open `pdf_to_html_converter.php`
   - Replace `your-pdfco-api-key` with your PDF.co API key
   - Replace `your-google-translate-api-key` with your Google Cloud Translation API key

## Usage

1. Start the PHP development server:
```bash
php -S localhost:8000
```

2. Access the application:
   - Open a web browser
   - Navigate to `http://localhost:8000`

3. Upload and translate a PDF:
   - Click the upload area or drag and drop a Spanish PDF file
   - Click "Translate PDF"
   - Wait for the translation to complete
   - The translated PDF will automatically download


## API Integration

### PDF.co API
- Used for PDF to HTML conversion
- Handles document structure preservation
- Manages file uploads and downloads

### Google Cloud Translation API
- Performs Spanish to English translation
- Maintains text formatting
- Processes content in segments

## Error Handling

The application includes comprehensive error handling for:
- File upload issues
- API communication failures
- File permission problems
- Invalid file types
- Translation errors

## Security Considerations

- Input validation for uploaded files
- Secure file handling
- Automatic cleanup of temporary files
- Protected API keys
- Sanitized file paths

## Limitations

- Maximum file size depends on PHP configuration
- Translation quality depends on Google Translate accuracy
- Complex PDF layouts may affect translation formatting
- API rate limits apply based on service tier

## Troubleshooting

1. File Upload Issues:
   - Check PHP file upload configuration
   - Verify directory permissions
   - Ensure valid PDF file format

2. Translation Errors:
   - Verify API keys are correct
   - Check API service status
   - Confirm input PDF contains extractable text

3. Download Problems:
   - Check browser download settings
   - Verify file permissions
   - Ensure sufficient disk space

## License

[Specify your license information here]

## Support

For technical support or questions:
- Submit an issue through the repository
- Contact the development team at [contact information] 