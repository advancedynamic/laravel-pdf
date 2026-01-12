<?php

declare(strict_types=1);

namespace PdfLib\Laravel\Tests;

use Orchestra\Testbench\TestCase;
use PdfLib\Document\PdfDocument;
use PdfLib\Export\Docx\PdfToDocxConverter;
use PdfLib\Export\Xlsx\PdfToXlsxConverter;
use PdfLib\Html\HtmlConverter;
use PdfLib\Import\Docx\DocxToPdfConverter;
use PdfLib\Import\Pptx\PptxToPdfConverter;
use PdfLib\Import\Xlsx\XlsxToPdfConverter;
use PdfLib\Laravel\Facades\PDF;
use PdfLib\Laravel\PdfManager;
use PdfLib\Laravel\PdfServiceProvider;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;

/**
 * Tests for converter features in Laravel package.
 */
class ConverterTest extends TestCase
{
    protected string $targetDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->targetDir = sys_get_temp_dir() . '/laravel-pdf-converter-tests';
        if (!is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0755, true);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [PdfServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'PDF' => PDF::class,
        ];
    }

    // =========================================================================
    // HTML to PDF Conversion Tests
    // =========================================================================

    public function testHtmlConverterReturnsInstance(): void
    {
        $converter = PDF::htmlConverter();
        $this->assertInstanceOf(HtmlConverter::class, $converter);
    }

    public function testHtmlToPdfBasicConversion(): void
    {
        $html = '<html><body><h1>Test Document</h1><p>This is a test.</p></body></html>';
        $pdf = PDF::htmlToPdf($html);

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testHtmlToPdfWithOptions(): void
    {
        $html = '<html><body><h1>Styled Document</h1></body></html>';
        $pdf = PDF::htmlToPdf($html, [
            'pageSize' => 'letter',
            'margins' => ['top' => 30, 'right' => 30, 'bottom' => 30, 'left' => 30],
            'font' => ['family' => 'Helvetica', 'size' => 14],
        ]);

        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testHtmlToPdfFile(): void
    {
        $html = '<html><body><h1>File Output Test</h1></body></html>';
        $outputPath = $this->targetDir . '/html-output.pdf';

        PDF::htmlToPdfFile($html, $outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    public function testHtmlToPdfDownloadResponse(): void
    {
        $html = '<html><body><h1>Download Test</h1></body></html>';
        $response = PDF::htmlToPdfDownload($html, 'test.pdf');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function testHtmlToPdfInlineResponse(): void
    {
        $html = '<html><body><h1>Inline Test</h1></body></html>';
        $response = PDF::htmlToPdfInline($html, 'inline.pdf');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    // =========================================================================
    // DOCX to PDF Conversion Tests
    // =========================================================================

    public function testDocxConverterReturnsInstance(): void
    {
        $converter = PDF::docxConverter();
        $this->assertInstanceOf(DocxToPdfConverter::class, $converter);
    }

    public function testDocxToPdfFileNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PDF::docxToPdf('/nonexistent/file.docx');
    }

    public function testDocxToPdfWithSimpleDocx(): void
    {
        // Create a minimal DOCX file for testing
        $docxPath = $this->createTestDocx('Test content for DOCX');

        $pdf = PDF::docxToPdf($docxPath);
        $this->assertInstanceOf(PdfDocument::class, $pdf);

        unlink($docxPath);
    }

    public function testDocxToPdfWithOptions(): void
    {
        $docxPath = $this->createTestDocx('Styled content');

        $pdf = PDF::docxToPdf($docxPath, [
            'pageSize' => 'a4',
            'margins' => ['top' => 40, 'right' => 40, 'bottom' => 40, 'left' => 40],
            'font' => ['family' => 'Times', 'size' => 11],
        ]);

        $this->assertInstanceOf(PdfDocument::class, $pdf);
        unlink($docxPath);
    }

    // =========================================================================
    // XLSX to PDF Conversion Tests
    // =========================================================================

    public function testXlsxConverterReturnsInstance(): void
    {
        $converter = PDF::xlsxConverter();
        $this->assertInstanceOf(XlsxToPdfConverter::class, $converter);
    }

    public function testXlsxToPdfFileNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PDF::xlsxToPdf('/nonexistent/file.xlsx');
    }

    public function testXlsxToPdfWithSimpleXlsx(): void
    {
        $xlsxPath = $this->createTestXlsx([
            ['Name', 'Value'],
            ['Item 1', '100'],
            ['Item 2', '200'],
        ]);

        $pdf = PDF::xlsxToPdf($xlsxPath);
        $this->assertInstanceOf(PdfDocument::class, $pdf);

        unlink($xlsxPath);
    }

    public function testXlsxToPdfWithOptions(): void
    {
        $xlsxPath = $this->createTestXlsx([
            ['A', 'B', 'C'],
            ['1', '2', '3'],
        ]);

        $pdf = PDF::xlsxToPdf($xlsxPath, [
            'pageSize' => 'a3',
            'landscape' => true,
            'gridlines' => true,
            'headers' => true,
        ]);

        $this->assertInstanceOf(PdfDocument::class, $pdf);
        unlink($xlsxPath);
    }

    // =========================================================================
    // PPTX to PDF Conversion Tests
    // =========================================================================

    public function testPptxConverterReturnsInstance(): void
    {
        $converter = PDF::pptxConverter();
        $this->assertInstanceOf(PptxToPdfConverter::class, $converter);
    }

    public function testPptxToPdfFileNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PDF::pptxToPdf('/nonexistent/file.pptx');
    }

    public function testPptxToPdfWithSimplePptx(): void
    {
        $pptxPath = $this->createTestPptx('Slide Title', 'Slide Content');

        $pdf = PDF::pptxToPdf($pptxPath);
        $this->assertInstanceOf(PdfDocument::class, $pdf);

        unlink($pptxPath);
    }

    public function testPptxToPdfWithOptions(): void
    {
        $pptxPath = $this->createTestPptx('Test', 'Content');

        $pdf = PDF::pptxToPdf($pptxPath, [
            'pageSize' => 'letter',
            'slideNumbers' => true,
        ]);

        $this->assertInstanceOf(PdfDocument::class, $pdf);
        unlink($pptxPath);
    }

    // =========================================================================
    // PDF to DOCX Export Tests
    // =========================================================================

    public function testPdfToDocxConverterReturnsInstance(): void
    {
        $converter = PDF::pdfToDocxConverter();
        $this->assertInstanceOf(PdfToDocxConverter::class, $converter);
    }

    public function testPdfToDocxConversion(): void
    {
        // Create a simple PDF
        $pdf = PDF::create();
        $page = new Page(PageSize::a4());
        $page->addText('Test content for DOCX export', 100, 750);
        $pdf->addPageObject($page);

        $pdfPath = $this->targetDir . '/source-for-docx.pdf';
        $pdf->save($pdfPath);

        $docxPath = $this->targetDir . '/exported.docx';
        PDF::pdfToDocx($pdfPath, $docxPath);

        $this->assertFileExists($docxPath);
        $this->assertGreaterThan(0, filesize($docxPath));

        unlink($pdfPath);
        unlink($docxPath);
    }

    public function testPdfToDocxDownloadResponse(): void
    {
        $pdf = PDF::create();
        $page = new Page(PageSize::a4());
        $page->addText('Download test', 100, 750);
        $pdf->addPageObject($page);

        $pdfPath = $this->targetDir . '/download-source.pdf';
        $pdf->save($pdfPath);

        $response = PDF::pdfToDocxDownload($pdfPath, 'export.docx');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $response->headers->get('Content-Type')
        );

        unlink($pdfPath);
    }

    // =========================================================================
    // PDF to XLSX Export Tests
    // =========================================================================

    public function testPdfToXlsxConverterReturnsInstance(): void
    {
        $converter = PDF::pdfToXlsxConverter();
        $this->assertInstanceOf(PdfToXlsxConverter::class, $converter);
    }

    public function testPdfToXlsxConversion(): void
    {
        // Create a PDF with tabular data
        $pdf = PDF::create();
        $page = new Page(PageSize::a4());
        $page->addText('Column A    Column B    Column C', 100, 750);
        $page->addText('Value 1     Value 2     Value 3', 100, 730);
        $pdf->addPageObject($page);

        $pdfPath = $this->targetDir . '/source-for-xlsx.pdf';
        $pdf->save($pdfPath);

        $xlsxPath = $this->targetDir . '/exported.xlsx';
        PDF::pdfToXlsx($pdfPath, $xlsxPath);

        $this->assertFileExists($xlsxPath);
        $this->assertGreaterThan(0, filesize($xlsxPath));

        unlink($pdfPath);
        unlink($xlsxPath);
    }

    public function testPdfToXlsxDownloadResponse(): void
    {
        $pdf = PDF::create();
        $page = new Page(PageSize::a4());
        $page->addText('Spreadsheet export test', 100, 750);
        $pdf->addPageObject($page);

        $pdfPath = $this->targetDir . '/xlsx-download-source.pdf';
        $pdf->save($pdfPath);

        $response = PDF::pdfToXlsxDownload($pdfPath, 'export.xlsx');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );

        unlink($pdfPath);
    }

    // =========================================================================
    // PdfManager Direct Usage Tests
    // =========================================================================

    public function testPdfManagerHtmlConversion(): void
    {
        $manager = app(PdfManager::class);
        $html = '<html><body><p>Manager test</p></body></html>';

        $pdf = $manager->htmlToPdf($html);
        $this->assertInstanceOf(PdfDocument::class, $pdf);
    }

    public function testPdfManagerConverterChaining(): void
    {
        $manager = app(PdfManager::class);

        // Test converter returns instance that can be chained
        $converter = $manager->docxConverter()
            ->setPageSize('letter')
            ->setMargins(30, 30, 30, 30);

        $this->assertInstanceOf(DocxToPdfConverter::class, $converter);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Create a minimal test DOCX file.
     */
    private function createTestDocx(string $content): string
    {
        $path = $this->targetDir . '/test-' . uniqid() . '.docx';

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>');

        // _rels/.rels
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');

        // word/document.xml
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:body>
<w:p><w:r><w:t>' . htmlspecialchars($content) . '</w:t></w:r></w:p>
</w:body>
</w:document>');

        $zip->close();

        return $path;
    }

    /**
     * Create a minimal test XLSX file.
     *
     * @param array<array<string>> $data
     */
    private function createTestXlsx(array $data): string
    {
        $path = $this->targetDir . '/test-' . uniqid() . '.xlsx';

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>');

        // _rels/.rels
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        // xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>');

        // xl/workbook.xml
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

        // Build sheet data
        $sheetData = '';
        foreach ($data as $rowIndex => $row) {
            $rowNum = $rowIndex + 1;
            $sheetData .= "<row r=\"{$rowNum}\">";
            foreach ($row as $colIndex => $value) {
                $col = chr(65 + $colIndex); // A, B, C...
                $cellRef = $col . $rowNum;
                $sheetData .= "<c r=\"{$cellRef}\" t=\"inlineStr\"><is><t>" . htmlspecialchars($value) . "</t></is></c>";
            }
            $sheetData .= "</row>";
        }

        // xl/worksheets/sheet1.xml
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>' . $sheetData . '</sheetData>
</worksheet>');

        $zip->close();

        return $path;
    }

    /**
     * Create a minimal test PPTX file.
     */
    private function createTestPptx(string $title, string $content): string
    {
        $path = $this->targetDir . '/test-' . uniqid() . '.pptx';

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>
<Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>
</Types>');

        // _rels/.rels
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>');

        // ppt/_rels/presentation.xml.rels
        $zip->addFromString('ppt/_rels/presentation.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>');

        // ppt/presentation.xml
        $zip->addFromString('ppt/presentation.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<p:sldIdLst><p:sldId id="256" r:id="rId1"/></p:sldIdLst>
</p:presentation>');

        // ppt/slides/slide1.xml
        $zip->addFromString('ppt/slides/slide1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
<p:cSld>
<p:spTree>
<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
<p:grpSpPr/>
<p:sp>
<p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
<p:spPr><a:xfrm><a:off x="457200" y="274638"/><a:ext cx="8229600" cy="1143000"/></a:xfrm></p:spPr>
<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>' . htmlspecialchars($title) . '</a:t></a:r></a:p></p:txBody>
</p:sp>
<p:sp>
<p:nvSpPr><p:cNvPr id="3" name="Content"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
<p:spPr><a:xfrm><a:off x="457200" y="1600200"/><a:ext cx="8229600" cy="4525963"/></a:xfrm></p:spPr>
<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>' . htmlspecialchars($content) . '</a:t></a:r></a:p></p:txBody>
</p:sp>
</p:spTree>
</p:cSld>
</p:sld>');

        $zip->close();

        return $path;
    }
}
