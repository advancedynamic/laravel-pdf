# Laravel PDF Package

## Overview
Laravel wrapper package for the php-pdf/pdf-lib library, providing seamless PDF operations in Laravel applications.

## Structure
- `src/` - Package source code
  - `Facades/PDF.php` - Laravel facade for PDF operations
  - `PdfManager.php` - Main manager class
  - `PdfServiceProvider.php` - Service provider for Laravel
- `config/pdf.php` - Configuration file
- `examples/` - Usage examples
- `tests/` - Test suite

## Key Components
- **PdfServiceProvider**: Registers the package with Laravel
- **PDF Facade**: Provides static access to PDF operations
- **PdfManager**: Handles document creation, manipulation, signing, and conversion

## Usage
```php
use PdfLib\Laravel\Facades\PDF;

// Create a new PDF
$pdf = PDF::create();

// Merge PDFs
PDF::merge(['file1.pdf', 'file2.pdf'], 'output.pdf');

// Sign a PDF
PDF::sign('document.pdf', $certificate, $privateKey);
```

## Dependencies
- php-pdf/pdf-lib (core PDF library)
- Laravel 9.x, 10.x, or 11.x

## Testing
```bash
composer test
```
