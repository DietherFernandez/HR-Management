<?php

/** POST /api/auth/login — email + password → JWT */
function auth_login(): void
{
    $in = Response::input();
    $email = strtolower(trim($in['email'] ?? ''));
    $password = $in['password'] ?? '';

    if (!$email || !$password) {
        Response::error('Kailangan ng email at password.', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Hindi valid ang email address.', 422);
    }

    $stmt = db()->prepare('SELECT * FROM employees WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        Response::error('Maling email o password.', 401);
    }
    if ($user['status'] !== 'active') {
        Response::error('Naka-suspend ang account. Kontakin ang HR.', 403);
    }

    $token = Auth::issue($user);
    $safe = Auth::safe($user);

    ws_notify('auth:login', ['employee_id' => (int)$user['id'], 'name' => trim($user['first_name'] . ' ' . $user['last_name'])]);

    Response::json([
        'token'     => $token,
        'user'      => $safe,
        'expires_in'=> JWT_TTL_HOURS * 3600,
    ]);
}

/** GET /api/auth/me — profile ng nakalogin */
function auth_me(): void
{
    $user = Auth::require();
    Response::json(['user' => Auth::safe($user)]);
}

/** PUT /api/auth/password — palitan ang sariling password */
function auth_change_password(): void
{
    $user = Auth::require();
    $in = Response::input();
    $current = $in['current_password'] ?? '';
    $new = $in['new_password'] ?? '';

    if (!password_verify($current, $user['password_hash'])) {
        Response::error('Mali ang kasalukuyang password.', 400);
    }
    if (strlen($new) < 6) {
        Response::error('Dapat 6+ characters ang bagong password.', 422);
    }

    $stmt = db()->prepare('UPDATE employees SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);
    Response::json(['message' => 'Na-update na ang password.']);
}
