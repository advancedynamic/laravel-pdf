<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The default filesystem disk to use when storing PDFs. If null, the
    | default filesystem disk from config/filesystems.php will be used.
    |
    */

    'disk' => env('PDF_DISK', null),

    /*
    |--------------------------------------------------------------------------
    | Default PDF Options
    |--------------------------------------------------------------------------
    |
    | Default options applied to all new PDF documents created via the facade.
    | These can be overridden when calling PDF::create($options).
    |
    */

    'defaults' => [
        'author' => env('PDF_AUTHOR', env('APP_NAME', 'Laravel')),
        'creator' => env('PDF_CREATOR', 'PdfLib'),
        // 'title' => null,
        // 'subject' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Temporary Directory
    |--------------------------------------------------------------------------
    |
    | Directory for temporary PDF files during processing. If null, the
    | system temp directory will be used.
    |
    */

    'temp_dir' => env('PDF_TEMP_DIR', null),

];
