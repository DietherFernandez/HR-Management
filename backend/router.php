<?php
/**
 * HR Management API — front controller
 *
 * Sa XAMPP: ilagay ang buong backend/ folder sa htdocs, e.g.
 *   C:/xampp/htdocs/hr-api/
 * Access:   http://localhost:8080/api/auth/login
 *
 * Sa local dev (walang Apache):
 *   php -S 0.0.0.0:8080 router.php   (run mula sa backend/ folder)
 */

require __DIR__ . '/config/config.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/app/Response.php';
require __DIR__ . '/app/Auth.php';
require __DIR__ . '/app/Geofence.php';
require __DIR__ . '/app/Notifier.php';
require __DIR__ . '/controllers/AuthController.php';
require __DIR__ . '/controllers/EmployeeController.php';
require __DIR__ . '/controllers/LeaveController.php';
require __DIR__ . '/controllers/AttendanceController.php';
require __DIR__ . '/controllers/DashboardController.php';

Response::cors();

// --- Static file: attendance photos ---
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (preg_match('#^/uploads/([A-Za-z0-9._-]+\.(?:jpg|jpeg|png|webp))$#', $uri, $m)) {
    $file = UPLOAD_DIR . DIRECTORY_SEPARATOR . $m[1];
    if (!is_file($file)) {
        http_response_code(404);
        exit('Not found');
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Cache-Control: public, max-age=86400');
    readfile($file);
    exit;
}

// --- Strip base path (/hr-api/api/... o /api/...) ---
$path = $uri;
$pos = strpos($path, '/api/');
if ($pos !== false) {
    $path = substr($path, $pos + 4); // "/api/employees" → "/employees"
}
$path = rtrim($path, '/') ?: '/';
$parts = array_values(array_filter(explode('/', $path)));
$resource = $parts[0] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- Routes ---
try {
    switch ($resource) {
        case 'auth':
            if ($method === 'POST' && ($parts[1] ?? '') === 'login') auth_login();
            if ($method === 'GET' && ($parts[1] ?? '') === 'me') auth_me();
            if ($method === 'PUT' && ($parts[1] ?? '') === 'password') auth_change_password();
            break;

        case 'employees':
            if ($method === 'GET' && !isset($parts[1])) employees_index();
            if ($method === 'POST' && !isset($parts[1])) employees_store();
            if ($method === 'PUT' && isset($parts[1]) && ctype_digit($parts[1])) employees_update((int)$parts[1]);
            if ($method === 'DELETE' && isset($parts[1]) && ctype_digit($parts[1])) employees_destroy((int)$parts[1]);
            break;

        case 'departments':
            if ($method === 'GET' && !isset($parts[1])) departments_index();
            if ($method === 'POST' && !isset($parts[1])) departments_store();
            break;

        case 'leave-requests':
            if ($method === 'GET' && !isset($parts[1])) leave_index();
            if ($method === 'POST' && !isset($parts[1])) leave_store();
            if ($method === 'PATCH' && isset($parts[1]) && ctype_digit($parts[1])) leave_update((int)$parts[1]);
            break;

        case 'notifications':
            if ($method === 'GET') notifications_index();
            break;

        case 'attendance':
            if ($method === 'GET' && !isset($parts[1])) attendance_index();
            if ($method === 'POST' && ($parts[1] ?? '') === 'clock-in') attendance_clock_in();
            if ($method === 'POST' && ($parts[1] ?? '') === 'clock-out') attendance_clock_out();
            if ($method === 'POST' && ($parts[1] ?? '') === 'manual-mark') attendance_manual_mark();
            if ($method === 'POST' && ($parts[1] ?? '') === 'biometric-import') attendance_biometric_import();
            if ($method === 'POST' && ($parts[1] ?? '') === 'verify') attendance_verify();
            break;

        case 'dashboard':
            if ($method === 'GET' && ($parts[1] ?? '') === 'stats') dashboard_stats();
            break;

        case 'sheet':
            if ($method === 'GET' && ($parts[1] ?? '') === 'live') sheet_live();
            break;

        case 'health':
            Response::json(['status' => 'ok', 'time' => date('c')]);
            break;
    }

    Response::error("Hindi kilala ang route: $method $uri", 404);
} catch (PDOException $e) {
    error_log('DB error: ' . $e->getMessage());
    Response::error('Database error. Tingnan ang XAMPP MySQL log.', 500);
} catch (Throwable $e) {
    error_log('Server error: ' . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}
