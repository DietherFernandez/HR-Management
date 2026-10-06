<?php

/** GET /api/employees — lahat (HR/admin) o sarili lang (employee) */
function employees_index(): void
{
    $user = Auth::require();
    $search = trim($_GET['q'] ?? '');
    $dept = (int)($_GET['department_id'] ?? 0);

    if ($user['role'] === 'employee') {
        Response::json(['employees' => [Auth::safe($user)], 'total' => 1]);
    }

    $sql = 'SELECT e.*, d.name AS department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR e.employee_code LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($dept > 0) {
        $sql .= ' AND e.department_id = ?';
        $params[] = $dept;
    }
    $sql .= ' ORDER BY e.last_name, e.first_name';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = array_map([Auth::class, 'safe'], $stmt->fetchAll());

    Response::json(['employees' => $rows, 'total' => count($rows)]);
}

/** POST /api/employees — magdagdag ng employee (HR/admin lang) */
function employees_store(): void
{
    $auth = Auth::require(['hr', 'admin']);
    $in = Response::input();

    $first = trim($in['first_name'] ?? '');
    $last = trim($in['last_name'] ?? '');
    $email = strtolower(trim($in['email'] ?? ''));
    $password = $in['password'] ?? '';
    $role = $in['role'] ?? 'employee';
    if (!in_array($role, ['employee', 'hr', 'admin'], true)) {
        $role = 'employee';
    }
    $dept = (int)($in['department_id'] ?? 0) ?: null;

    if (!$first || !$last) {
        Response::error('Kailangan ng first at last name.', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Hindi valid ang email.', 422);
    }
    if (strlen($password) < 6) {
        Response::error('Dapat 6+ characters ang password.', 422);
    }

    $stmt = db()->prepare('SELECT id FROM employees WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        Response::error('Ginagamit na ang email na ito.', 409);
    }

    // Auto-generate employee_code: EMP-0001
    $seq = (int)db()->query('SELECT COUNT(*) + 1 FROM employees')->fetchColumn();
    $code = 'EMP-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

    $stmt = db()->prepare(
        'INSERT INTO employees (employee_code, first_name, last_name, email, password_hash, role, department_id, position, phone, salary, date_hired, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active")'
    );
    $stmt->execute([
        $code,
        $first,
        $last,
        $email,
        password_hash($password, PASSWORD_BCRYPT),
        $role,
        $dept,
        $in['position'] ?? null,
        $in['phone'] ?? null,
        isset($in['salary']) && $in['salary'] !== '' ? (float)$in['salary'] : null,
        $in['date_hired'] ?? date('Y-m-d'),
    ]);
    $id = (int)db()->lastInsertId();

    ws_notify('employee:added', ['id' => $id, 'name' => "$first $last", 'code' => $code], null, 'hr');

    $stmt = db()->prepare('SELECT * FROM employees WHERE id = ?');
    $stmt->execute([$id]);
    Response::json(['employee' => Auth::safe($stmt->fetch()), 'message' => "Idinagdag si $first $last."], 201);
}

/** PUT /api/employees/{id} — i-update ang employee (HR/admin) */
function employees_update(int $id): void
{
    Auth::require(['hr', 'admin']);
    $in = Response::input();

    $stmt = db()->prepare('SELECT * FROM employees WHERE id = ?');
    $stmt->execute([$id]);
    $emp = $stmt->fetch();
    if (!$emp) {
        Response::error('Walang ganitong employee.', 404);
    }

    $first = trim($in['first_name'] ?? $emp['first_name']);
    $last = trim($in['last_name'] ?? $emp['last_name']);
    $email = strtolower(trim($in['email'] ?? $emp['email']));
    $role = $in['role'] ?? $emp['role'];
    if (!in_array($role, ['employee', 'hr', 'admin'], true)) {
        $role = $emp['role'];
    }
    $status = $in['status'] ?? $emp['status'];
    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = $emp['status'];
    }
    $dept = array_key_exists('department_id', $in) ? ((int)$in['department_id'] ?: null) : $emp['department_id'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Hindi valid ang email.', 422);
    }

    $stmt = db()->prepare('SELECT id FROM employees WHERE email = ? AND id != ?');
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        Response::error('Ginagamit na ang email na ito ng iba.', 409);
    }

    $sets = ['first_name=?', 'last_name=?', 'email=?', 'role=?', 'department_id=?', 'position=?', 'phone=?', 'salary=?', 'status=?'];
    $params = [
        $first, $last, $email, $role, $dept,
        $in['position'] ?? $emp['position'],
        $in['phone'] ?? $emp['phone'],
        isset($in['salary']) && $in['salary'] !== '' ? (float)$in['salary'] : $emp['salary'],
        $status,
    ];

    // Optional password reset
    if (!empty($in['password'])) {
        if (strlen($in['password']) < 6) {
            Response::error('Dapat 6+ characters ang password.', 422);
        }
        $sets[] = 'password_hash=?';
        $params[] = password_hash($in['password'], PASSWORD_BCRYPT);
    }

    $sets[] = 'updated_at=NOW()';
    $params[] = $id;
    $stmt = db()->prepare('UPDATE employees SET ' . implode(', ', $sets) . ' WHERE id=?');
    $stmt->execute($params);
    $stmt2 = db()->prepare('SELECT * FROM employees WHERE id = ?');
    $stmt2->execute([$id]);
    Response::json(['employee' => Auth::safe($stmt2->fetch()), 'message' => 'Na-update ang employee.']);
}

/** DELETE /api/employees/{id} — deactvate (soft) o tanggalin (HR/admin) */
function employees_destroy(int $id): void
{
    $auth = Auth::require(['hr', 'admin']);
    if ((int)$auth['id'] === $id) {
        Response::error('Hindi mo pwedeng tanggalin ang sarili mo.', 400);
    }

    $stmt = db()->prepare('SELECT id FROM employees WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        Response::error('Walang ganitong employee.', 404);
    }

    // Soft delete para may audit trail ng attendance/leave
    $stmt = db()->prepare('UPDATE employees SET status = "inactive" WHERE id = ?');
    $stmt->execute([$id]);
    Response::json(['message' => 'Na-deactivate ang employee.']);
}

/** GET /api/departments — list ng departments */
function departments_index(): void
{
    Auth::require();
    $rows = db()->query('SELECT * FROM departments ORDER BY name')->fetchAll();
    Response::json(['departments' => $rows]);
}

/** POST /api/departments — magdagdag ng department (HR/admin) */
function departments_store(): void
{
    Auth::require(['hr', 'admin']);
    $name = trim(Response::input()['name'] ?? '');
    if (!$name) {
        Response::error('Kailangan ng department name.', 422);
    }
    try {
        $stmt = db()->prepare('INSERT INTO departments (name) VALUES (?)');
        $stmt->execute([$name]);
    } catch (PDOException $e) {
        Response::error('Ginagamit na ang department name na ito.', 409);
    }
    Response::json(['id' => (int)db()->lastInsertId(), 'name' => $name], 201);
}
