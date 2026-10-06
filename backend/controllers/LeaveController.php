<?php

/** GET /api/leave-requests — sarili (employee) o lahat (HR/admin), may filter sa status */
function leave_index(): void
{
    $user = Auth::require();
    $status = $_GET['status'] ?? '';
    $allowed = ['pending', 'approved', 'rejected', 'all', ''];

    $sql = 'SELECT lr.*, e.first_name, e.last_name, e.employee_code, d.name AS department_name,
                   r.first_name AS reviewer_first, r.last_name AS reviewer_last
            FROM leave_requests lr
            JOIN employees e ON e.id = lr.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN employees r ON r.id = lr.reviewed_by
            WHERE 1=1';
    $params = [];

    if ($user['role'] === 'employee') {
        $sql .= ' AND lr.employee_id = ?';
        $params[] = $user['id'];
    } elseif (!empty($status) && in_array($status, $allowed, true) && $status !== 'all') {
        $sql .= ' AND lr.status = ?';
        $params[] = $status;
    }

    $sql .= ' ORDER BY lr.created_at DESC LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    Response::json(['leave_requests' => $stmt->fetchAll()]);
}

/** POST /api/leave-requests — mag-request ng leave (kahit sino na nakalogin) */
function leave_store(): void
{
    $user = Auth::require();
    $in = Response::input();

    $type = in_array($in['leave_type'] ?? '', ['vacation', 'sick', 'personal', 'emergency', 'maternity', 'paternity', 'bereavement', 'other'], true)
        ? $in['leave_type'] : 'vacation';
    $start = $in['start_date'] ?? '';
    $end = $in['end_date'] ?? '';
    $reason = trim($in['reason'] ?? '');

    if (!$start || !$end || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        Response::error('Kailangan ng valid na start at end date (YYYY-MM-DD).', 422);
    }
    if ($end < $start) {
        Response::error('Hindi pwedeng mas maaga ang end date kaysa start date.', 422);
    }
    if ($start < date('Y-m-d')) {
        Response::error('Hindi pwedeng mag-request ng leave sa nakaraan.', 422);
    }

    $days = (int)((strtotime($end) - strtotime($start)) / 86400) + 1;

    // Check overlapping pending/approved requests
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM leave_requests
         WHERE employee_id = ? AND status IN ("pending","approved")
           AND NOT (end_date < ? OR start_date > ?)'
    );
    $stmt->execute([$user['id'], $start, $end]);
    if ((int)$stmt->fetchColumn() > 0) {
        Response::error('May existing leave request ka na sa petsang ito.', 409);
    }

    $stmt = db()->prepare(
        'INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, days, reason, status)
         VALUES (?, ?, ?, ?, ?, ?, "pending")'
    );
    $stmt->execute([$user['id'], $type, $start, $end, $days, $reason ?: null]);
    $id = (int)db()->lastInsertId();

    ws_notify('leave:new', [
        'id' => $id, 'type' => $type, 'days' => $days,
        'name' => trim($user['first_name'] . ' ' . $user['last_name']),
    ], null, 'hr');

    Response::json(['id' => $id, 'status' => 'pending', 'days' => $days,
        'message' => "Na-submit ang leave request ($days araw)."], 201);
}

/** PATCH /api/leave-requests/{id} — approve/reject (HR/admin) */
function leave_update(int $id): void
{
    $auth = Auth::require(['hr', 'admin']);
    $in = Response::input();
    $status = $in['status'] ?? '';

    if (!in_array($status, ['approved', 'rejected'], true)) {
        Response::error('Status dapat ay approved o rejected.', 422);
    }

    $stmt = db()->prepare('SELECT * FROM leave_requests WHERE id = ?');
    $stmt->execute([$id]);
    $lr = $stmt->fetch();
    if (!$lr) {
        Response::error('Walang ganitong leave request.', 404);
    }
    if ($lr['status'] !== 'pending') {
        Response::error("Na-process na ang request na ito ({$lr['status']}).", 409);
    }

    $stmt = db()->prepare(
        'UPDATE leave_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ? WHERE id = ?'
    );
    $stmt->execute([$status, $auth['id'], $in['review_notes'] ?? null, $id]);

    // In-app notification + websocket push sa employee
    $stmt = db()->prepare(
        'INSERT INTO notifications (employee_id, title, body, event) VALUES (?, ?, ?, ?)'
    );
    $title = $status === 'approved' ? 'Leave Approved ✅' : 'Leave Rejected ❌';
    $body = 'Ang iyong ' . $lr['leave_type'] . ' leave (' . $lr['start_date'] . ' → ' . $lr['end_date'] . ') ay '
        . ($status === 'approved' ? 'aprubado.' : 'tinanggihan.');
    $stmt->execute([$lr['employee_id'], $title, $body, 'leave_status']);

    ws_notify('leave:status', [
        'id' => $id, 'status' => $status, 'type' => $lr['leave_type'],
        'start' => $lr['start_date'], 'end' => $lr['end_date'],
    ], (int)$lr['employee_id']);

    Response::json(['message' => "Na-" . ($status === 'approved' ? 'aprubado' : 'tinanggihan') . " ang request.", 'status' => $status]);
}

/** GET /api/notifications — in-app notifications ng user */
function notifications_index(): void
{
    $user = Auth::require();
    $stmt = db()->prepare(
        'SELECT * FROM notifications
         WHERE employee_id = ? OR employee_id IS NULL
         ORDER BY created_at DESC LIMIT 30'
    );
    $stmt->execute([$user['id']]);
    Response::json(['notifications' => $stmt->fetchAll()]);
}
