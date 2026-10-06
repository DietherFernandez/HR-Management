<?php

final class Auth
{
    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function ub64(string $s): string
    {
        return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }

    public static function issue(array $employee): string
    {
        $now = time();
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::b64(json_encode([
            'sub'  => (int)$employee['id'],
            'role' => $employee['role'],
            'name' => trim($employee['first_name'] . ' ' . $employee['last_name']),
            'iat'  => $now,
            'exp'  => $now + JWT_TTL_HOURS * 3600,
        ]));
        $sig = self::b64(hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true));
        return $header . '.' . $payload . '.' . $sig;
    }

    /** @return array|null payload o null kapag invalid/expired */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        $expected = self::b64(hash_hmac('sha256', $h . '.' . $p, JWT_SECRET, true));
        if (!hash_equals($expected, $s)) {
            return null;
        }
        $payload = json_decode(self::ub64($p), true);
        if (!is_array($payload) || (int)($payload['exp'] ?? 0) < time()) {
            return null;
        }
        return $payload;
    }

    /** Kunin ang current user mula sa Authorization header */
    public static function user(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (!$header && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) {
                    $header = $v;
                    break;
                }
            }
        }
        if (!$header || !preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            return null;
        }
        $payload = self::verify($m[1]);
        if (!$payload) {
            return null;
        }
        $stmt = db()->prepare('SELECT * FROM employees WHERE id = ? AND status = "active"');
        $stmt->execute([(int)$payload['sub']]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Required guard. $roles: null = kahit sino, array = piliin ang role.
     * @return array employee row
     */
    public static function require(?array $roles = null): array
    {
        $user = self::user();
        if (!$user) {
            Response::error('Walang access. Mag-login muna.', 401);
        }
        if ($roles !== null && !in_array($user['role'], $roles, true)) {
            Response::error('Hindi sapat ang iyong role para sa aksyong ito.', 403);
        }
        return $user;
    }

    /** Huwag ipakita ang password hash sa anumang response */
    public static function safe(array $employee): array
    {
        unset($employee['password_hash']);
        return $employee;
    }
}
