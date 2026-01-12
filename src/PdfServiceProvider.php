<?php

declare(strict_types=1);

namespace PdfLib\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;
use PdfLib\Document\PdfDocument;

class PdfServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/pdf.php', 'pdf');

        $this->app->singleton(PdfManager::class, function ($app) {
            return new PdfManager($app['config']->get('pdf', []));
        });

        $this->app->alias(PdfManager::class, 'pdf');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/pdf.php' => config_path('pdf.php'),
        ], 'pdf-config');

        $this->registerResponseMacros();
    }

    /**
     * Register response macros for PDF downloads and streaming.
     */
    protected function registerResponseMacros(): void
    {
        Response::macro('pdf', function (string $content, ?string $filename = null, bool $inline = false) {
            $headers = [
                'Content-Type' => 'application/pdf',
            ];

            if ($filename) {
                $disposition = $inline ? 'inline' : 'attachment';
                $headers['Content-Disposition'] = "{$disposition}; filename=\"{$filename}\"";
            }

            return Response::make($content, 200, $headers);
        });

        Response::macro('pdfDownload', function (string $content, string $filename = 'document.pdf') {
            return Response::pdf($content, $filename, false);
        });

        Response::macro('pdfInline', function (string $content, string $filename = 'document.pdf') {
            return Response::pdf($content, $filename, true);
        });
    }
}
