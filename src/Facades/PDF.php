<?php

declare(strict_types=1);

namespace PdfLib\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use PdfLib\Document\PdfDocument;
use PdfLib\Export\Docx\PdfToDocxConverter;
use PdfLib\Export\Xlsx\PdfToXlsxConverter;
use PdfLib\Form\FormFiller;
use PdfLib\Form\FormParser;
use PdfLib\Html\HtmlConverter;
use PdfLib\Import\Docx\DocxToPdfConverter;
use PdfLib\Import\Pptx\PptxToPdfConverter;
use PdfLib\Import\Xlsx\XlsxToPdfConverter;
use PdfLib\Laravel\PdfManager;
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
 * PDF Facade for Laravel.
 *
 * Document Creation:
 * @method static PdfDocument create(array $options = [])
 *
 * Loading/Parsing PDFs:
 * @method static PdfParser parse(string $path)
 * @method static PdfParser parseContent(string $content)
 * @method static PdfParser parseFromDisk(string $path, ?string $disk = null)
 * @method static PdfDocument load(string $path)
 * @method static PdfDocument loadContent(string $content)
 * @method static PdfDocument loadFromDisk(string $path, ?string $disk = null)
 *
 * Manipulation Tools:
 * @method static Merger merger()
 * @method static Splitter splitter(?string $path = null)
 * @method static Cropper cropper(?string $path = null)
 * @method static Rotator rotator(?string $path = null)
 * @method static Stamper stamper(?string $path = null)
 * @method static Optimizer optimizer(?string $path = null)
 *
 * Security - Encryption:
 * @method static Encryptor encryptor()
 * @method static string encrypt(string|PdfDocument $pdf, string $userPassword, ?string $ownerPassword = null, string $method = 'aes128')
 * @method static \Illuminate\Http\Response encryptAndDownload(string|PdfDocument $pdf, string $userPassword, string $filename = 'encrypted.pdf', string $method = 'aes128')
 *
 * Security - Digital Signatures:
 * @method static Signer signer()
 * @method static string sign(string|PdfDocument $pdf, string $certificatePath, string $certificatePassword, array $options = [])
 * @method static string multiSign(string|PdfDocument $pdf, array $signers)
 * @method static \Illuminate\Http\Response multiSignAndDownload(string|PdfDocument $pdf, array $signers, string $filename = 'signed.pdf')
 *
 * Form Handling:
 * @method static FormFiller formFiller(string $path)
 * @method static FormFiller formFillerFromContent(string $content)
 * @method static FormFiller formFillerFromDisk(string $path, ?string $disk = null)
 * @method static string fillForm(string $path, array $data, bool $flatten = false)
 * @method static array getFormFields(string $path)
 *
 * Form Parsing (Coordinate Extraction):
 * @method static FormParser formParser(string $path)
 * @method static FormParser formParserFromContent(string $content)
 * @method static FormParser formParserFromDisk(string $path, ?string $disk = null)
 * @method static array extractFieldCoordinates(string $path)
 *
 * HTML to PDF Conversion:
 * @method static HtmlConverter htmlConverter()
 * @method static PdfDocument htmlToPdf(string $html, array $options = [])
 * @method static void htmlToPdfFile(string $html, string $outputPath, array $options = [])
 * @method static \Illuminate\Http\Response htmlToPdfDownload(string $html, string $filename = 'document.pdf', array $options = [])
 * @method static \Illuminate\Http\Response htmlToPdfInline(string $html, string $filename = 'document.pdf', array $options = [])
 *
 * Import Converters (Office to PDF):
 * @method static DocxToPdfConverter docxConverter()
 * @method static PdfDocument docxToPdf(string $docxPath, array $options = [])
 * @method static void docxToPdfFile(string $docxPath, string $outputPath, array $options = [])
 * @method static \Illuminate\Http\Response docxToPdfDownload(string $docxPath, string $filename = 'document.pdf', array $options = [])
 * @method static XlsxToPdfConverter xlsxConverter()
 * @method static PdfDocument xlsxToPdf(string $xlsxPath, array $options = [])
 * @method static void xlsxToPdfFile(string $xlsxPath, string $outputPath, array $options = [])
 * @method static \Illuminate\Http\Response xlsxToPdfDownload(string $xlsxPath, string $filename = 'spreadsheet.pdf', array $options = [])
 * @method static PptxToPdfConverter pptxConverter()
 * @method static PdfDocument pptxToPdf(string $pptxPath, array $options = [])
 * @method static void pptxToPdfFile(string $pptxPath, string $outputPath, array $options = [])
 * @method static \Illuminate\Http\Response pptxToPdfDownload(string $pptxPath, string $filename = 'presentation.pdf', array $options = [])
 *
 * Export Converters (PDF to Office):
 * @method static PdfToDocxConverter pdfToDocxConverter()
 * @method static void pdfToDocx(string $pdfPath, string $outputPath, array $options = [])
 * @method static \Illuminate\Http\Response pdfToDocxDownload(string $pdfPath, string $filename = 'document.docx', array $options = [])
 * @method static PdfToXlsxConverter pdfToXlsxConverter()
 * @method static void pdfToXlsx(string $pdfPath, string $outputPath, array $options = [])
 * @method static \Illuminate\Http\Response pdfToXlsxDownload(string $pdfPath, string $filename = 'spreadsheet.xlsx', array $options = [])
 *
 * Response Helpers:
 * @method static \Illuminate\Http\Response download(string|PdfDocument $pdf, string $filename = 'document.pdf')
 * @method static \Illuminate\Http\Response inline(string|PdfDocument $pdf, string $filename = 'document.pdf')
 * @method static \Illuminate\Http\Response stream(string|PdfDocument $pdf, string $filename = 'document.pdf')
 *
 * Storage Helpers:
 * @method static bool store(string|PdfDocument $pdf, string $path, ?string $disk = null)
 *
 * Quick Operations:
 * @method static Merger merge(array $files)
 * @method static string mergeFiles(array $files)
 * @method static \Illuminate\Http\Response mergeAndDownload(array $files, string $filename = 'merged.pdf')
 * @method static bool mergeAndStore(array $files, string $path, ?string $disk = null)
 * @method static string optimize(string|PdfDocument $pdf, string $level = 'standard')
 * @method static string watermark(string|PdfDocument $pdf, string $text, array $options = [])
 * @method static string addPageNumbers(string|PdfDocument $pdf, string $format = 'Page {n} of {total}', string $position = 'bottom-center')
 *
 * @see \PdfLib\Laravel\PdfManager
 */
class PDF extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return PdfManager::class;
    }
}
