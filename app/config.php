<?php

define('DB_PATH',        '/var/www/data/printserver.db');
define('UPLOAD_DIR',     __DIR__ . '/public/uploads/');
define('MAX_UPLOAD_MB',  512);
define('MAX_UPLOAD_BYTES', MAX_UPLOAD_MB * 1024 * 1024);

// Default printer — override with the name shown by `lpstat -p`
define('DEFAULT_PRINTER', getenv('PRINTER_NAME') ?: '');

define('APP_NAME', 'PHP Print Server');
define('APP_VERSION', '1.0');

define('JOBS_PER_PAGE', 20);
