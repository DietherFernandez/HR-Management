<?php

/** GET /api/dashboard/stats — summary cards depende sa role */
function dashboard_stats(): void
{
    $user = Auth::require();

    if ($user['role'] === 'employee') {
        $today = date('Y-m-d');
        $stmt = db()->prepare('SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?');
        $stmt->execute([$user['id'], $today]);
        $todayRec = $stmt->fetch() ?: null;

        $stmt = db()->prepare('SELECT COUNT(*) FROM leave_requests WHERE employee_id = ?');
        $stmt->execute([$user['id']]);
        $totalLeaves = (int)$stmt->fetchColumn();

        $stmt = db()->prepare('SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = "pending"');
        $stmt->execute([$user['id']]);
        $pendingLeaves = (int)$stmt->fetchColumn();

        $stmt = db()->prepare('SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND YEARWEEK(attendance_date, 1) = YEARWEEK(CURDATE(), 1)');
        $stmt->execute([$user['id']]);
        $daysThisWeek = (int)$stmt->fetchColumn();

        Response::json([
            'role' => 'employee',
            'today' => $todayRec,
            'total_leaves' => $totalLeaves,
            'pending_leaves' => $pendingLeaves,
            'days_this_week' => $daysThisWeek,
            'is_clocked_in' => (bool)($todayRec && $todayRec['time_in']),
            'is_clocked_out' => (bool)($todayRec && $todayRec['time_out']),
        ]);
    }

    // HR / admin view
    $today = date('Y-m-d');
    $totalEmployees = (int)db()->query('SELECT COUNT(*) FROM employees WHERE status = "active"')->fetchColumn();
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status IN ("present","late")'
    );
    $stmt->execute([$today]);
    $presentToday = (int)$stmt->fetchColumn();

    $stmt = db()->prepare('SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = "late"');
    $stmt->execute([$today]);
    $lateToday = (int)$stmt->fetchColumn();

    $pendingLeaves = (int)db()->query('SELECT COUNT(*) FROM leave_requests WHERE status = "pending"')->fetchColumn();

    $stmt = db()->prepare('SELECT method, COUNT(*) c FROM attendance WHERE attendance_date = ? GROUP BY method');
    $stmt->execute([$today]);
    $byMethod = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    Response::json([
        'role' => $user['role'],
        'total_employees' => $totalEmployees,
        'present_today' => $presentToday,
        'late_today' => $lateToday,
        'pending_leaves' => $pendingLeaves,
        'attendance_by_method' => [
            'app' => (int)($byMethod['app'] ?? 0),
            'biometric' => (int)($byMethod['biometric'] ?? 0),
            'manual' => (int)($byMethod['manual'] ?? 0),
        ],
    ]);
}

/** GET /api/sheet/live — live data mula sa Google Spreadsheet (published CSV) */
function sheet_live(): void
{
    Auth::require();

    if (!SHEET_CSV_URL) {
        Response::json([
            'enabled' => false,
            'message' => 'Walang SHEET_CSV_URL na naka-configure. I-paste ang published-to-web CSV URL sa backend/.env (tingnan ang .env.example).',
        ]);
    }

    $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
    $csv = @file_get_contents(SHEET_CSV_URL, false, $ctx);
    if ($csv === false || trim($csv) === '') {
        Response::error('Hindi ma-fetch ang Google Sheet. I-check ang share settings (Published to web).', 502);
    }

    $rows = [];
    $lines = preg_split('/\r\n|\r|\n/', $csv);
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $rows[] = str_getcsv($line);
    }

    Response::json([
        'enabled' => true,
        'fetched_at' => date('c'),
        'headers' => $rows ? array_values(array_filter($rows[0], static fn($h) => $h !== '')) : [],
        'rows' => array_slice($rows, 1, 200),
        'source' => SHEET_CSV_URL,
    ]);
}
