<?php

declare(strict_types=1);

namespace PdfLib\Laravel;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use PdfLib\Document\PdfDocument;
use PdfLib\Export\Docx\PdfToDocxConverter;
use PdfLib\Export\Xlsx\PdfToXlsxConverter;
use PdfLib\Form\FormFiller;
use PdfLib\Form\FormFlattener;
use PdfLib\Form\FormParser;
use PdfLib\Html\HtmlConverter;
use PdfLib\Import\Docx\DocxToPdfConverter;
use PdfLib\Import\Pptx\PptxToPdfConverter;
use PdfLib\Import\Xlsx\XlsxToPdfConverter;
use PdfLib\Manipulation\Cropper;
use PdfLib\Manipulation\Merger;
use PdfLib\Manipulation\Optimizer;
use PdfLib\Manipulation\Rotator;
use PdfLib\Manipulation\Splitter;
use PdfLib\Manipulation\Stamper;
use PdfLib\Parser\PdfParser;
use PdfLib\Security\Encryption\Encryptor;
use PdfLib\Security\Signature\Signer;

/**
 * PDF Manager for Laravel.
 *
 * Provides a convenient API for working with PDFs in Laravel applications.
 */
class PdfManager
{
    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    // =========================================================================
    // Document Creation
    // =========================================================================

    /**
     * Create a new PDF document.
     *
     * @param array<string, mixed> $options
     */
    public function create(array $options = []): PdfDocument
    {
        $options = array_merge($this->config['defaults'] ?? [], $options);

        $pdf = PdfDocument::create();

        if (isset($options['title'])) {
            $pdf->setTitle($options['title']);
        }

        if (isset($options['author'])) {
            $pdf->setAuthor($options['author']);
        }

        if (isset($options['subject'])) {
            $pdf->setSubject($options['subject']);
        }

        if (isset($options['creator'])) {
            $pdf->setCreator($options['creator']);
        }

        return $pdf;
    }

    // =========================================================================
    // Loading PDFs
    // =========================================================================

    /**
     * Load and parse a PDF from a file path.
     */
    public function parse(string $path): PdfParser
    {
        return PdfParser::parseFile($path);
    }

    /**
     * Load and parse PDF content.
     */
    public function parseContent(string $content): PdfParser
    {
        return PdfParser::parseString($content);
    }

    /**
     * Load a PDF from a storage disk and parse it.
     */
    public function parseFromDisk(string $path, ?string $disk = null): PdfParser
    {
        $disk = $disk ?? $this->config['disk'] ?? config('filesystems.default');
        $content = Storage::disk($disk)->get($path);

        if ($content === null) {
            throw new \RuntimeException("Could not read PDF from disk: {$path}");
        }

        return PdfParser::parseString($content);
    }

    /**
     * Load a PDF from a file path (alias for backwards compatibility).
     */
    public function load(string $path): PdfDocument
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException("Could not read PDF file: {$path}");
        }

        return $this->loadContent($content);
    }

    /**
     * Load a PDF from a storage disk.
     */
    public function loadFromDisk(string $path, ?string $disk = null): PdfDocument
    {
        $disk = $disk ?? $this->config['disk'] ?? config('filesystems.default');
        $content = Storage::disk($disk)->get($path);

        if ($content === null) {
            throw new \RuntimeException("Could not read PDF from disk: {$path}");
        }

        return $this->loadContent($content);
    }

    /**
     * Load a PDF from string content.
     */
    public function loadContent(string $content): PdfDocument
    {
        $parser = PdfParser::parseString($content);

        $pdf = PdfDocument::create();
        $info = $parser->getInfo();
        if ($info !== null) {
            if ($info->has('Title')) {
                $title = $info->get('Title');
                if ($title !== null) {
                    $pdf->setTitle((string) $title->getValue());
                }
            }
            if ($info->has('Author')) {
                $author = $info->get('Author');
                if ($author !== null) {
                    $pdf->setAuthor((string) $author->getValue());
                }
            }
        }

        return $pdf;
    }

    // =========================================================================
    // Manipulation Tools
    // =========================================================================

    /**
     * Create a new PDF merger.
     */
    public function merger(): Merger
    {
        return new Merger();
    }

    /**
     * Create a new PDF splitter.
     */
    public function splitter(?string $path = null): Splitter
    {
        return new Splitter($path);
    }

    /**
     * Create a new PDF cropper.
     */
    public function cropper(?string $path = null): Cropper
    {
        return new Cropper($path);
    }

    /**
     * Create a new PDF rotator.
     */
    public function rotator(?string $path = null): Rotator
    {
        return new Rotator($path);
    }

    /**
     * Create a new PDF stamper (watermarks, headers, footers).
     */
    public function stamper(?string $path = null): Stamper
    {
        return new Stamper($path);
    }

    /**
     * Create a new PDF optimizer.
     */
    public function optimizer(?string $path = null): Optimizer
    {
        return new Optimizer($path);
    }

    // =========================================================================
    // Security - Encryption
    // =========================================================================

    /**
     * Create a new PDF encryptor.
     */
    public function encryptor(): Encryptor
    {
        return new Encryptor();
    }

    /**
     * Encrypt a PDF with a password.
     *
     * @param string|PdfDocument $pdf PDF content or document
     * @param string $userPassword Password for opening the PDF
     * @param string|null $ownerPassword Password for full access (defaults to user password)
     * @param string $method Encryption method: 'aes128', 'aes256', 'rc4'
     */
    public function encrypt(
        string|PdfDocument $pdf,
        string $userPassword,
        ?string $ownerPassword = null,
        string $method = 'aes128'
    ): string {
        $content = $pdf instanceof PdfDocument ? $pdf->render() : $pdf;

        $encryptor = $this->encryptor();
        $encryptor->setUserPassword($userPassword);
        $encryptor->setOwnerPassword($ownerPassword ?? $userPassword);

        return match ($method) {
            'aes256' => $encryptor->encryptAes256($content),
            'rc4' => $encryptor->encryptRc4($content),
            default => $encryptor->encryptAes128($content),
        };
    }

    /**
     * Encrypt and download a PDF.
     */
    public function encryptAndDownload(
        string|PdfDocument $pdf,
        string $userPassword,
        string $filename = 'encrypted.pdf',
        string $method = 'aes128'
    ): Response {
        $encrypted = $this->encrypt($pdf, $userPassword, null, $method);
        return $this->download($encrypted, $filename);
    }

    // =========================================================================
    // Security - Digital Signatures
    // =========================================================================

    /**
     * Create a new PDF signer.
     */
    public function signer(): Signer
    {
        return new Signer();
    }

    /**
     * Sign a PDF with a certificate.
     *
     * @param string|PdfDocument $pdf PDF content or document
     * @param string $certificatePath Path to the certificate file (.p12, .pfx, or .pem)
     * @param string $certificatePassword Password for the certificate
     * @param array<string, mixed> $options Additional signing options
     */
    public function sign(
        string|PdfDocument $pdf,
        string $certificatePath,
        string $certificatePassword,
        array $options = []
    ): string {
        $content = $pdf instanceof PdfDocument ? $pdf->render() : $pdf;

        $signer = $this->signer();
        $signer->loadContent($content);
        $signer->loadCertificate($certificatePath, $certificatePassword, $options['key'] ?? null);

        if (isset($options['reason'])) {
            $signer->setReason($options['reason']);
        }

        if (isset($options['location'])) {
            $signer->setLocation($options['location']);
        }

        if (isset($options['contact'])) {
            $signer->setContactInfo($options['contact']);
        }

        return $signer->sign();
    }

    /**
     * Apply multiple signatures to a PDF using different certificates.
     *
     * Each signer uses their own certificate to sign the document sequentially.
     * Signatures are applied using PDF incremental updates (preserving previous signatures).
     *
     * @param string|PdfDocument $pdf PDF content or document
     * @param array<array{cert: string, password: string, key?: string, reason?: string, location?: string, contact?: string}> $signers
     * @return string Signed PDF content
     *
     * @example
     * ```php
     * $signed = PDF::multiSign($pdf, [
     *     ['cert' => 'approver.p12', 'password' => 'pass1', 'reason' => 'Approved'],
     *     ['cert' => 'reviewer.p12', 'password' => 'pass2', 'reason' => 'Reviewed'],
     *     ['cert' => 'manager.p12', 'password' => 'pass3', 'reason' => 'Final approval'],
     * ]);
     * ```
     */
    public function multiSign(string|PdfDocument $pdf, array $signers): string
    {
        $content = $pdf instanceof PdfDocument ? $pdf->render() : $pdf;

        return Signer::multiSignContent($content, $signers);
    }

    /**
     * Apply multiple signatures and download.
     *
     * @param string|PdfDocument $pdf PDF content or document
     * @param array<array{cert: string, password: string, key?: string, reason?: string, location?: string}> $signers
     */
    public function multiSignAndDownload(
        string|PdfDocument $pdf,
        array $signers,
        string $filename = 'signed.pdf'
    ): Response {
        $signed = $this->multiSign($pdf, $signers);
        return $this->download($signed, $filename);
    }

    // =========================================================================
    // Form Handling
    // =========================================================================

    /**
     * Create a form filler for an existing PDF.
     */
    public function formFiller(string $path): FormFiller
    {
        return new FormFiller($path);
    }

    /**
     * Create a form filler from PDF content.
     */
    public function formFillerFromContent(string $content): FormFiller
    {
        $filler = new FormFiller();
        $filler->loadContent($content);
        return $filler;
    }

    /**
     * Create a form filler from storage.
     */
    public function formFillerFromDisk(string $path, ?string $disk = null): FormFiller
    {
        $disk = $disk ?? $this->config['disk'] ?? config('filesystems.default');
        $content = Storage::disk($disk)->get($path);

        if ($content === null) {
            throw new \RuntimeException("Could not read PDF from disk: {$path}");
        }

        return $this->formFillerFromContent($content);
    }

    /**
     * Fill a PDF form with data.
     *
     * @param string $path Path to the PDF form
     * @param array<string, mixed> $data Field name => value mapping
     * @param bool $flatten Whether to flatten the form after filling
     */
    public function fillForm(string $path, array $data, bool $flatten = false): string
    {
        $filler = $this->formFiller($path);

        foreach ($data as $field => $value) {
            $filler->setFieldValue($field, $value);
        }

        $result = $filler->fill();

        if ($flatten) {
            $flattener = new FormFlattener();
            $flattener->loadContent($result);
            return $flattener->flatten();
        }

        return $result;
    }

    /**
     * Get form fields from a PDF.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFormFields(string $path): array
    {
        $filler = $this->formFiller($path);
        return $filler->getFields();
    }

    // =========================================================================
    // Form Parsing (Coordinate Extraction)
    // =========================================================================

    /**
     * Create a form parser to extract field coordinates.
     */
    public function formParser(string $path): FormParser
    {
        return FormParser::fromFile($path);
    }

    /**
     * Create a form parser from PDF content.
     */
    public function formParserFromContent(string $content): FormParser
    {
        return FormParser::fromString($content);
    }

    /**
     * Create a form parser from storage disk.
     */
    public function formParserFromDisk(string $path, ?string $disk = null): FormParser
    {
        $disk = $disk ?? $this->config['disk'] ?? config('filesystems.default');
        $content = Storage::disk($disk)->get($path);

        if ($content === null) {
            throw new \RuntimeException("Could not read PDF from disk: {$path}");
        }

        return FormParser::fromString($content);
    }

    /**
     * Extract field coordinates from a PDF.
     *
     * @return array<string, array{x: float, y: float, width: float, height: float, page: int, type: string}>
     */
    public function extractFieldCoordinates(string $path): array
    {
        $parser = $this->formParser($path);
        $fields = $parser->getFields();
        $coordinates = [];

        foreach ($fields as $field) {
            $rect = $field->getRect();
            $coordinates[$field->getName()] = [
                'x' => $rect[0],
                'y' => $rect[1],
                'width' => $rect[2] - $rect[0],
                'height' => $rect[3] - $rect[1],
                'page' => $field->getPage(),
                'type' => $field->getFieldType(),
            ];
        }

        return $coordinates;
    }

    // =========================================================================
    // HTML to PDF Conversion
    // =========================================================================

    /**
     * Create an HTML to PDF converter.
     */
    public function htmlConverter(): HtmlConverter
    {
        return HtmlConverter::create();
    }

    /**
     * Convert HTML string to PDF.
     *
     * @param array<string, mixed> $options Converter options
     */
    public function htmlToPdf(string $html, array $options = []): PdfDocument
    {
        $converter = $this->htmlConverter();

        if (isset($options['pageSize'])) {
            $converter->setPageSize($options['pageSize']);
        }

        if (isset($options['margins'])) {
            $margins = $options['margins'];
            $converter->setMargins(
                $margins['top'] ?? 50,
                $margins['right'] ?? 50,
                $margins['bottom'] ?? 50,
                $margins['left'] ?? 50
            );
        }

        if (isset($options['font'])) {
            $converter->setDefaultFont(
                $options['font']['family'] ?? 'Helvetica',
                $options['font']['size'] ?? 12
            );
        }

        return $converter->toPdfDocument($html);
    }

    /**
     * Convert HTML string to PDF and save to file.
     *
     * @param array<string, mixed> $options
     */
    public function htmlToPdfFile(string $html, string $outputPath, array $options = []): void
    {
        $converter = $this->htmlConverter();

        if (isset($options['pageSize'])) {
            $converter->setPageSize($options['pageSize']);
        }

        if (isset($options['margins'])) {
            $margins = $options['margins'];
            $converter->setMargins(
                $margins['top'] ?? 50,
                $margins['right'] ?? 50,
                $margins['bottom'] ?? 50,
                $margins['left'] ?? 50
            );
        }

        $converter->convert($html, $outputPath);
    }

    /**
     * Convert HTML string to PDF and return as download response.
     *
     * @param array<string, mixed> $options
     */
    public function htmlToPdfDownload(string $html, string $filename = 'document.pdf', array $options = []): Response
    {
        $pdf = $this->htmlToPdf($html, $options);
        return $this->download($pdf, $filename);
    }

    /**
     * Convert HTML string to PDF and return as inline response.
     *
     * @param array<string, mixed> $options
     */
    public function htmlToPdfInline(string $html, string $filename = 'document.pdf', array $options = []): Response
    {
        $pdf = $this->htmlToPdf($html, $options);
        return $this->inline($pdf, $filename);
    }

    // =========================================================================
    // Import Converters (Office to PDF)
    // =========================================================================

    /**
     * Create a DOCX to PDF converter.
     */
    public function docxConverter(): DocxToPdfConverter
    {
        return DocxToPdfConverter::create();
    }

    /**
     * Convert DOCX file to PDF.
     *
     * @param array<string, mixed> $options Converter options
     */
    public function docxToPdf(string $docxPath, array $options = []): PdfDocument
    {
        $converter = $this->docxConverter();

        if (isset($options['pageSize'])) {
            $converter->setPageSize($options['pageSize']);
        }

        if (isset($options['margins'])) {
            $margins = $options['margins'];
            $converter->setMargins(
                $margins['top'] ?? 50,
                $margins['right'] ?? 50,
                $margins['bottom'] ?? 50,
                $margins['left'] ?? 50
            );
        }

        if (isset($options['font'])) {
            $converter->setDefaultFont(
                $options['font']['family'] ?? 'Helvetica',
                $options['font']['size'] ?? 12
            );
        }

        if (isset($options['pages'])) {
            $converter->setPages($options['pages']);
        }

        return $converter->toPdfDocument($docxPath);
    }

    /**
     * Convert DOCX file to PDF and save to file.
     *
     * @param array<string, mixed> $options
     */
    public function docxToPdfFile(string $docxPath, string $outputPath, array $options = []): void
    {
        $pdf = $this->docxToPdf($docxPath, $options);
        $pdf->save($outputPath);
    }

    /**
     * Convert DOCX file to PDF and return as download response.
     *
     * @param array<string, mixed> $options
     */
    public function docxToPdfDownload(string $docxPath, string $filename = 'document.pdf', array $options = []): Response
    {
        $pdf = $this->docxToPdf($docxPath, $options);
        return $this->download($pdf, $filename);
    }

    /**
     * Create an XLSX to PDF converter.
     */
    public function xlsxConverter(): XlsxToPdfConverter
    {
        return XlsxToPdfConverter::create();
    }

    /**
     * Convert XLSX file to PDF.
     *
     * @param array<string, mixed> $options Converter options
     */
    public function xlsxToPdf(string $xlsxPath, array $options = []): PdfDocument
    {
        $converter = $this->xlsxConverter();

        if (isset($options['pageSize'])) {
            $converter->setPageSize($options['pageSize']);
        }

        if (isset($options['landscape'])) {
            $converter->setLandscape($options['landscape']);
        }

        if (isset($options['margins'])) {
            $margins = $options['margins'];
            $converter->setMargins(
                $margins['top'] ?? 50,
                $margins['right'] ?? 50,
                $margins['bottom'] ?? 50,
                $margins['left'] ?? 50
            );
        }

        if (isset($options['font'])) {
            $converter->setDefaultFont(
                $options['font']['family'] ?? 'Helvetica',
                $options['font']['size'] ?? 10
            );
        }

        if (isset($options['sheets'])) {
            $converter->setSheets($options['sheets']);
        }

        if (isset($options['gridlines'])) {
            $converter->showGridlines($options['gridlines']);
        }

        if (isset($options['headers'])) {
            $converter->showHeaders($options['headers']);
        }

        if (isset($options['sheetPerPage'])) {
            $converter->setSheetPerPage($options['sheetPerPage']);
        }

        return $converter->toPdfDocument($xlsxPath);
    }

    /**
     * Convert XLSX file to PDF and save to file.
     *
     * @param array<string, mixed> $options
     */
    public function xlsxToPdfFile(string $xlsxPath, string $outputPath, array $options = []): void
    {
        $pdf = $this->xlsxToPdf($xlsxPath, $options);
        $pdf->save($outputPath);
    }

    /**
     * Convert XLSX file to PDF and return as download response.
     *
     * @param array<string, mixed> $options
     */
    public function xlsxToPdfDownload(string $xlsxPath, string $filename = 'spreadsheet.pdf', array $options = []): Response
    {
        $pdf = $this->xlsxToPdf($xlsxPath, $options);
        return $this->download($pdf, $filename);
    }

    /**
     * Create a PPTX to PDF converter.
     */
    public function pptxConverter(): PptxToPdfConverter
    {
        return PptxToPdfConverter::create();
    }

    /**
     * Convert PPTX file to PDF.
     *
     * @param array<string, mixed> $options Converter options
     */
    public function pptxToPdf(string $pptxPath, array $options = []): PdfDocument
    {
        $converter = $this->pptxConverter();

        if (isset($options['pageSize'])) {
            $converter->setPageSize($options['pageSize']);
        }

        if (isset($options['margins'])) {
            $margins = $options['margins'];
            $converter->setMargins(
                $margins['top'] ?? 30,
                $margins['right'] ?? 30,
                $margins['bottom'] ?? 30,
                $margins['left'] ?? 30
            );
        }

        if (isset($options['slides'])) {
            $converter->setSlides($options['slides']);
        }

        if (isset($options['slideNumbers'])) {
            $converter->showSlideNumbers($options['slideNumbers']);
        }

        if (isset($options['handout'])) {
            $converter->setHandoutMode($options['handout']);
        }

        return $converter->toPdfDocument($pptxPath);
    }

    /**
     * Convert PPTX file to PDF and save to file.
     *
     * @param array<string, mixed> $options
     */
    public function pptxToPdfFile(string $pptxPath, string $outputPath, array $options = []): void
    {
        $pdf = $this->pptxToPdf($pptxPath, $options);
        $pdf->save($outputPath);
    }

    /**
     * Convert PPTX file to PDF and return as download response.
     *
     * @param array<string, mixed> $options
     */
    public function pptxToPdfDownload(string $pptxPath, string $filename = 'presentation.pdf', array $options = []): Response
    {
        $pdf = $this->pptxToPdf($pptxPath, $options);
        return $this->download($pdf, $filename);
    }

    // =========================================================================
    // Export Converters (PDF to Office)
    // =========================================================================

    /**
     * Create a PDF to DOCX converter.
     */
    public function pdfToDocxConverter(): PdfToDocxConverter
    {
        return PdfToDocxConverter::create();
    }

    /**
     * Convert PDF to DOCX.
     *
     * @param array<string, mixed> $options Converter options
     */
    public function pdfToDocx(string $pdfPath, string $outputPath, array $options = []): void
    {
        $converter = $this->pdfToDocxConverter();

        if (isset($options['preserveFormatting'])) {
            $converter->setPreserveFormatting($options['preserveFormatting']);
        }

        if (isset($options['preserveImages'])) {
            $converter->setPreserveImages($options['preserveImages']);
        }

        if (isset($options['pages'])) {
            $converter->setPages($options['pages']);
        }

        $converter->convert($pdfPath, $outputPath);
    }

    /**
     * Convert PDF to DOCX and return as download response.
     *
     * @param array<string, mixed> $options
     */
    public function pdfToDocxDownload(string $pdfPath, string $filename = 'document.docx', array $options = []): Response
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'docx_');
        if ($tempFile === false) {
            throw new \RuntimeException('Could not create temp file');
        }
        $tempFile .= '.docx';

        $this->pdfToDocx($pdfPath, $tempFile, $options);

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        if ($content === false) {
            throw new \RuntimeException('Could not read temp file');
        }

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($content),
        ]);
    }

    /**
     * Create a PDF to XLSX converter.
     */
    public function pdfToXlsxConverter(): PdfToXlsxConverter
    {
        return PdfToXlsxConverter::create();
    }

    /**
     * Convert PDF to XLSX.
     *
     * @param array<string, mixed> $options Converter options
     */
    public function pdfToXlsx(string $pdfPath, string $outputPath, array $options = []): void
    {
        $converter = $this->pdfToXlsxConverter();

        if (isset($options['detectTables'])) {
            $converter->setDetectTables($options['detectTables']);
        }

        if (isset($options['pages'])) {
            $converter->setPages($options['pages']);
        }

        $converter->convert($pdfPath, $outputPath);
    }

    /**
     * Convert PDF to XLSX and return as download response.
     *
     * @param array<string, mixed> $options
     */
    public function pdfToXlsxDownload(string $pdfPath, string $filename = 'spreadsheet.xlsx', array $options = []): Response
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tempFile === false) {
            throw new \RuntimeException('Could not create temp file');
        }
        $tempFile .= '.xlsx';

        $this->pdfToXlsx($pdfPath, $tempFile, $options);

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        if ($content === false) {
            throw new \RuntimeException('Could not read temp file');
        }

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($content),
        ]);
    }

    // =========================================================================
    // Response Helpers
    // =========================================================================

    /**
     * Generate a download response for a PDF.
     *
     * @param string|PdfDocument $pdf PDF content or document
     */
    public function download(string|PdfDocument $pdf, string $filename = 'document.pdf'): Response
    {
        $content = $pdf instanceof PdfDocument ? $pdf->render() : $pdf;

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($content),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Generate an inline response for a PDF (display in browser).
     *
     * @param string|PdfDocument $pdf PDF content or document
     */
    public function inline(string|PdfDocument $pdf, string $filename = 'document.pdf'): Response
    {
        $content = $pdf instanceof PdfDocument ? $pdf->render() : $pdf;

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Content-Length' => strlen($content),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Alias for inline() - stream PDF to browser.
     *
     * @param string|PdfDocument $pdf PDF content or document
     */
    public function stream(string|PdfDocument $pdf, string $filename = 'document.pdf'): Response
    {
        return $this->inline($pdf, $filename);
    }

    // =========================================================================
    // Storage Helpers
    // =========================================================================

    /**
     * Store a PDF to a storage disk.
     *
     * @param string|PdfDocument $pdf PDF content or document
     */
    public function store(string|PdfDocument $pdf, string $path, ?string $disk = null): bool
    {
        $content = $pdf instanceof PdfDocument ? $pdf->render() : $pdf;
        $disk = $disk ?? $this->config['disk'] ?? config('filesystems.default');

        return Storage::disk($disk)->put($path, $content);
    }

    // =========================================================================
    // Quick Operations
    // =========================================================================

    /**
     * Merge multiple PDF files.
     *
     * @param array<string> $files Array of file paths
     */
    public function merge(array $files): Merger
    {
        $merger = $this->merger();

        foreach ($files as $file) {
            $merger->addFile($file);
        }

        return $merger;
    }

    /**
     * Quick method to merge and get content.
     *
     * @param array<string> $files Array of file paths
     */
    public function mergeFiles(array $files): string
    {
        return $this->merge($files)->merge();
    }

    /**
     * Quick method to merge and download.
     *
     * @param array<string> $files Array of file paths
     */
    public function mergeAndDownload(array $files, string $filename = 'merged.pdf'): Response
    {
        $content = $this->mergeFiles($files);
        return $this->download($content, $filename);
    }

    /**
     * Quick method to merge and store.
     *
     * @param array<string> $files Array of file paths
     */
    public function mergeAndStore(array $files, string $path, ?string $disk = null): bool
    {
        $content = $this->mergeFiles($files);
        return $this->store($content, $path, $disk);
    }

    /**
     * Optimize a PDF for smaller file size.
     *
     * @param string|PdfDocument $pdf PDF content or document
     * @param string $level Optimization level: 'minimal', 'standard', 'maximum'
     */
    public function optimize(string|PdfDocument $pdf, string $level = 'standard'): string
    {
        $content = $pdf instanceof PdfDocument ? $pdf->render() : $pdf;

        $optimizer = new Optimizer();
        $optimizer->loadContent($content);

        return match ($level) {
            'minimal' => $optimizer->optimizeMinimal(),
            'maximum' => $optimizer->optimizeMaximum(),
            default => $optimizer->optimize(),
        };
    }

    /**
     * Add watermark to a PDF.
     *
     * @param string|PdfDocument $pdf PDF content or document
     * @param string $text Watermark text
     * @param array<string, mixed> $options Watermark options
     */
    public function watermark(string|PdfDocument $pdf, string $text, array $options = []): string
    {
        $content = $pdf instanceof PdfDocument ? $pdf->render() : $pdf;

        $stamper = new Stamper();
        $stamper->loadContent($content);

        $stamper->addWatermark(
            $text,
            rotation: $options['rotation'] ?? 45,
            opacity: $options['opacity'] ?? 0.3,
            fontSize: $options['fontSize'] ?? 60,
            color: $options['color'] ?? [200, 200, 200]
        );

        return $stamper->apply();
    }

    /**
     * Add page numbers to a PDF.
     *
     * @param string|PdfDocument $pdf PDF content or document
     * @param string $format Page number format (use {n} for current, {total} for total)
     * @param string $position Position: 'bottom-center', 'bottom-right', 'bottom-left', 'top-center', 'top-right', 'top-left'
     */
    public function addPageNumbers(
        string|PdfDocument $pdf,
        string $format = 'Page {n} of {total}',
        string $position = 'bottom-center'
    ): string {
        $content = $pdf instanceof PdfDocument ? $pdf->render() : $pdf;

        $stamper = new Stamper();
        $stamper->loadContent($content);
        $stamper->addPageNumbers($format, $position);

        return $stamper->apply();
    }
}
