<?php
/**
 * HR Management — Global configuration
 * Ang mga value dito ay pwede ding i-override sa pamamagitan ng environment variables.
 */

// Opsyonal: backend/.env file (hindi ito naka-commit sa git)
$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        if ($k !== '' && getenv($k) === false) {
            putenv($k . '=' . trim($v, " \t\n\r\0\x0B\"'"));
        }
    }
}

// ---- Database (XAMPP default: root, walang password) ----
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'hr_management');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ---- Auth (JWTHS256 — libre, walang external service) ----
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'palitan-ito-ng-random-string-bago-i-deploy-7f3a9c2e1b');
define('JWT_TTL_HOURS', (int)(getenv('JWT_TTL_HOURS') ?: 12));

// ---- Geofence (attendance proof) ----
// I-set ang eksaktong GPS ng office dito. Ang layo radius ay nasa meters.
define('COMPANY_LAT', (float)(getenv('COMPANY_LAT') ?: 14.5995));
define('COMPANY_LNG', (float)(getenv('COMPANY_LNG') ?: 120.9842));
define('GEOFENCE_RADIUS_M', (float)(getenv('GEOFENCE_RADIUS_M') ?: 100));
// Kung true, ang clock-in outside geofence ay REJECT (maliban sa manual/biometric)
define('REQUIRE_GEOFENCE', (bool)(getenv('REQUIRE_GEOFENCE') ?: false));

// ---- Late policy ----
define('WORK_START_H', (int)(getenv('WORK_START_H') ?: 8));
define('WORK_START_M', (int)(getenv('WORK_START_M') ?: 0));

// ---- Uploads (selfie proof) ----
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('UPLOAD_URL', rtrim(getenv('UPLOAD_URL') ?: 'http://localhost:8080/uploads', '/'));

// ---- WebSocket (Node.js server) ----
define('WS_NOTIFY_URL', rtrim(getenv('WS_NOTIFY_URL') ?: 'http://127.0.0.1:8081', '/'));

// ---- Google Sheets live data ----
// Published-to-web CSV URL (File > Share > Publish to web) — libre, walang API key
define('SHEET_CSV_URL', getenv('SHEET_CSV_URL') ?: '');

// ---- CORS: pwede ding i-extend bago i-deploy sa Netlify ----
define('CORS_ORIGINS', array_values(array_filter(array_map('trim', explode(',', getenv('CORS_ORIGINS')
    ?: 'http://localhost:5173,http://127.0.0.1:5173,http://localhost:3000,http://localhost:8080')))));

// ---- App timezone (Philippines) ----
date_default_timezone_set(getenv('APP_TZ') ?: 'Asia/Manila');
