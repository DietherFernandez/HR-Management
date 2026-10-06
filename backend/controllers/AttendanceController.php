<?php

/**
 * Attendance — tatlong method para ma-verify ang presence:
 *
 * 1. APP    (default) — clock-in sa website/app na may SELFIE PHOTO + GPS geofence check.
 *                        Kailangan nasa loob ng radius ng office ang GPS, at may picture proof.
 * 2. BIOMETRIC        — in-import galing sa fingerprint/face device (CSV/JSON export).
 * 3. MANUAL           — HR ang nag-mark mismo (para sa walang device/app access).
 *
 * Lahat ng method ay may audit trail: sino, kailan, paano (method), at kung verified.
 */

/** POST /api/attendance/clock-in — app method na may selfie + GPS */
function attendance_clock_in(): void
{
    $user = Auth::require();
    $in = Response::input();

    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');

    // Huwag doble clock-in
    $stmt = db()->prepare('SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?');
    $stmt->execute([$user['id'], $today]);
    $existing = $stmt->fetch();
    if ($existing && $existing['time_in']) {
        Response::error('Naka-clock-in ka na ngayong araw.', 409);
    }

    // --- GPS / geofence ---
    $lat = isset($in['latitude']) && $in['latitude'] !== '' ? (float)$in['latitude'] : null;
    $lng = isset($in['longitude']) && $in['longitude'] !== '' ? (float)$in['longitude'] : null;
    $geo = Geofence::check($lat, $lng);

    if (REQUIRE_GEOFENCE && !$geo['verified']) {
        Response::error('Hindi ka nasa loob ng office geofence. ' .
            ($geo['distance_m'] ? "Layo mo: {$geo['distance_m']}m." : 'Walang GPS data.'), 403);
    }

    // --- Selfie photo proof ---
    $photoUrl = null;
    if (!empty($in['photo'])) {
        $photoUrl = save_photo($in['photo'], $user['id']);
        if ($photoUrl === false) {
            Response::error('Hindi ma-save ang selfie photo. Subukan ulit.', 422);
        }
    } else {
        Response::error('Kailangan ng selfie photo bilang proof of presence.', 422);
    }

    // --- Status: present o late ---
    $lateAfter = sprintf('%02d:%02d:00', WORK_START_H, WORK_START_M);
    $status = date('H:i:s') > $lateAfter ? 'late' : 'present';

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
    $device = substr(trim($in['device_info'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);

    $stmt = db()->prepare(
        'INSERT INTO attendance
           (employee_id, attendance_date, time_in, method, photo_url, latitude, longitude,
            location_verified, distance_m, ip_address, device_info, status)
         VALUES (?, ?, ?, "app", ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'], $today, $now, $photoUrl,
        $lat, $lng, $geo['verified'] ? 1 : 0, $geo['distance_m'],
        $ip, $device, $status,
    ]);
    $id = (int)db()->lastInsertId();

    ws_notify('attendance:in', [
        'id' => $id, 'name' => trim($user['first_name'] . ' ' . $user['last_name']),
        'time' => $now, 'status' => $status, 'method' => 'app',
    ], null, 'hr');

    Response::json([
        'id' => $id, 'time_in' => $now, 'status' => $status,
        'method' => 'app',
        'location_verified' => $geo['verified'],
        'distance_m' => $geo['distance_m'],
        'message' => 'Naka-clock-in ka na! 🎉',
    ], 201);
}

/** POST /api/attendance/clock-out */
function attendance_clock_out(): void
{
    $user = Auth::require();
    $today = date('Y-m-d');

    $stmt = db()->prepare('SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?');
    $stmt->execute([$user['id'], $today]);
    $row = $stmt->fetch();

    if (!$row) {
        Response::error('Wala pang clock-in ngayong araw.', 404);
    }
    if ($row['time_out']) {
        Response::error('Naka-clock-out ka na ngayong araw.', 409);
    }

    $stmt = db()->prepare('UPDATE attendance SET time_out = NOW() WHERE id = ?');
    $stmt->execute([$row['id']]);

    ws_notify('attendance:out', [
        'id' => (int)$row['id'],
        'name' => trim($user['first_name'] . ' ' . $user['last_name']),
    ], null, 'hr');

    Response::json(['time_out' => date('Y-m-d H:i:s'), 'message' => 'Naka-clock-out ka na. Ingat sa pag-uwi! 👋']);
}

/** GET /api/attendance — history (employee: sarili; HR/admin: lahat, may filter) */
function attendance_index(): void
{
    $user = Auth::require();
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');
    $empId = (int)($_GET['employee_id'] ?? 0);

    $sql = 'SELECT a.*, e.first_name, e.last_name, e.employee_code, d.name AS department_name,
                   v.first_name AS verifier_first, v.last_name AS verifier_last
            FROM attendance a
            JOIN employees e ON e.id = a.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN employees v ON v.id = a.verified_by
            WHERE a.attendance_date BETWEEN ? AND ?';
    $params = [$from, $to];

    if ($user['role'] === 'employee') {
        $sql .= ' AND a.employee_id = ?';
        $params[] = $user['id'];
    } elseif ($empId > 0) {
        $sql .= ' AND a.employee_id = ?';
        $params[] = $empId;
    }

    $sql .= ' ORDER BY a.attendance_date DESC, e.last_name LIMIT 500';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    Response::json(['attendance' => $stmt->fetchAll()]);
}

/** POST /api/attendance/manual-mark — HR ang nag-mark (walang device/app) */
function attendance_manual_mark(): void
{
    $auth = Auth::require(['hr', 'admin']);
    $in = Response::input();

    $empId = (int)($in['employee_id'] ?? 0);
    $date = $in['date'] ?? date('Y-m-d');
    $status = $in['status'] ?? 'present';
    if (!in_array($status, ['present', 'late', 'half_day', 'absent', 'on_leave'], true)) {
        $status = 'present';
    }

    if (!$empId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        Response::error('Kailangan ng employee_id at valid na date.', 422);
    }

    $stmt = db()->prepare('SELECT id, first_name, last_name FROM employees WHERE id = ?');
    $stmt->execute([$empId]);
    $emp = $stmt->fetch();
    if (!$emp) {
        Response::error('Walang ganitong employee.', 404);
    }

    $stmt = db()->prepare(
        'INSERT INTO attendance (employee_id, attendance_date, time_in, time_out, method, notes, verified_by, verified_at, status)
         VALUES (?, ?, ?, ?, "manual", ?, ?, NOW(), ?)
         ON DUPLICATE KEY UPDATE
           time_in = COALESCE(VALUES(time_in), time_in),
           time_out = COALESCE(VALUES(time_out), time_out),
           method = "manual", notes = VALUES(notes), verified_by = VALUES(verified_by),
           verified_at = NOW(), status = VALUES(status)'
    );
    $stmt->execute([
        $empId,
        $date,
        $in['time_in'] ?? ($date . ' 08:00:00'),
        $in['time_out'] ?? null,
        'Manual mark ni ' . trim($auth['first_name'] . ' ' . $auth['last_name']) . (isset($in['notes']) && $in['notes'] !== '' ? ': ' . $in['notes'] : ''),
        $auth['id'],
        $status,
    ]);

    ws_notify('attendance:manual', ['name' => trim($emp['first_name'] . ' ' . $emp['last_name']), 'date' => $date], null, 'hr');
    Response::json(['message' => "Na-mark si {$emp['first_name']} {$emp['last_name']} bilang $status sa $date."], 201);
}

/** POST /api/attendance/biometric-import — bulk import mula sa device export (CSV o JSON) */
function attendance_biometric_import(): void
{
    Auth::require(['hr', 'admin']);
    $in = Response::input();
    $rows = $in['rows'] ?? null;

    // Tinatanggap:
    //   { "rows": [{ "code": "EMP-002", "date": "2026-10-06", "time_in": "08:03", "time_out": "17:05" }, ...] }
    // o CSV text: code,date,time_in,time_out
    if (!$rows && !empty($in['csv'])) {
        $rows = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($in['csv']));
        foreach ($lines as $i => $line) {
            if ($i === 0 && stripos($line, 'code') !== false) continue; // header
            $cols = str_getcsv($line);
            if (count($cols) < 2) continue;
            $rows[] = [
                'code' => trim($cols[0]),
                'date' => trim($cols[1]),
                'time_in' => trim($cols[2] ?? ''),
                'time_out' => trim($cols[3] ?? ''),
            ];
        }
    }

    if (!$rows || !is_array($rows)) {
        Response::error('Walang valid na rows. Format: [{code, date, time_in, time_out}] o CSV text.', 422);
    }

    // Code → employee_id map
    $map = [];
    $stmt = db()->query('SELECT id, employee_code FROM employees');
    foreach ($stmt->fetchAll() as $r) {
        $map[$r['employee_code']] = (int)$r['id'];
    }

    $imported = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $r) {
        $code = trim($r['code'] ?? '');
        $date = trim($r['date'] ?? '');
        if (!$code || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !isset($map[$code])) {
            $skipped++;
            $errors[] = "Hindi alam o invalid: $code / $date";
            continue;
        }

        $timeIn = trim($r['time_in'] ?? '');
        $timeOut = trim($r['time_out'] ?? '');
        $dtIn = $timeIn ? $date . ' ' . (strlen($timeIn) === 5 ? $timeIn . ':00' : $timeIn) : null;
        $dtOut = $timeOut ? $date . ' ' . (strlen($timeOut) === 5 ? $timeOut . ':00' : $timeOut) : null;

        $lateAfter = sprintf('%02d:%02d:00', WORK_START_H, WORK_START_M);
        $status = 'present';
        if ($timeIn && substr($timeIn, 0, 5) > substr($lateAfter, 0, 5)) {
            $status = 'late';
        }

        $stmt = db()->prepare(
            'INSERT INTO attendance (employee_id, attendance_date, time_in, time_out, method, status, notes)
             VALUES (?, ?, ?, ?, "biometric", ?, ?)
             ON DUPLICATE KEY UPDATE time_in = COALESCE(VALUES(time_in), time_in),
               time_out = COALESCE(VALUES(time_out), time_out), method = "biometric",
               status = VALUES(status), notes = VALUES(notes)'
        );
        $stmt->execute([$map[$code], $date, $dtIn, $dtOut, $status, 'Imported mula sa biometric device']);
        $imported++;
    }

    ws_notify('attendance:biometric-import', ['imported' => $imported, 'skipped' => $skipped], null, 'hr');
    Response::json([
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => array_slice($errors, 0, 20),
        'message' => "Na-import ang $imported record(s), $skipped skipped.",
    ], 201);
}

/** POST /api/attendance/verify — HR ang mag-verify ng app/biometric record */
function attendance_verify(): void
{
    $auth = Auth::require(['hr', 'admin']);
    $in = Response::input();
    $id = (int)($in['attendance_id'] ?? 0);

    $stmt = db()->prepare('SELECT * FROM attendance WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        Response::error('Walang ganitong attendance record.', 404);
    }

    $stmt = db()->prepare('UPDATE attendance SET verified_by = ?, verified_at = NOW(), notes = ? WHERE id = ?');
    $stmt->execute([$auth['id'], 'Na-verify ni ' . trim($auth['first_name'] . ' ' . $auth['last_name']) . ' — ' . ($in['notes'] ?? 'OK'), $id]);

    Response::json(['message' => 'Na-verify ang attendance record.']);
}

/**
 * I-save ang base64 na photo sa uploads/ folder.
 * @return string|false relative URL o false kapag invalid
 */
function save_photo(string $base64, int $empId)
{
    if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $base64, $m)) {
        return false;
    }
    $bin = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64), true);
    if ($bin === false || strlen($bin) > 6 * 1024 * 1024) {
        return false; // max 6MB
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $ext = strtolower($m[1]) === 'png' ? 'png' : 'jpg';
    $name = sprintf('att_%d_%s_%s.%s', $empId, date('Ymd_His'), bin2hex(random_bytes(3)), $ext);
    if (file_put_contents(UPLOAD_DIR . DIRECTORY_SEPARATOR . $name, $bin) === false) {
        return false;
    }
    return UPLOAD_URL . '/' . $name;
}
