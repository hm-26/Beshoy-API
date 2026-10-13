<?php

/*
 | Report attachment limits (images / videos / documents added to a report).
 | NOTE: PHP has its own caps that must be at least as large as these, otherwise uploads fail before Laravel sees them:
 |   php.ini   upload_max_filesize = 128M   post_max_size = 130M
 | (nginx: client_max_body_size 130m;)
 */
return [
    // Per-file limit in megabytes.
    'max_mb' => (int) env('REPORT_UPLOAD_MAX_MB', 100),
    // How many files one request may carry (the React admin sends them one by one anyway).
    'max_files_per_request' => 20,
    // File links in the API JSON are signed and expire after this many hours.
    'url_ttl_hours' => (int) env('REPORT_FILE_URL_TTL_HOURS', 12),
    // Encrypt every uploaded file at rest (AES-256-GCM, key derived from APP_KEY; see App\Support\FileVault).
    // Files stored before this existed keep working and are converted with:  php artisan attachments:encrypt
    'encrypt' => filter_var(env('REPORT_ENCRYPT_FILES', true), FILTER_VALIDATE_BOOLEAN),
];
