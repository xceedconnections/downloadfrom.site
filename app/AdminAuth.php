<?php

declare(strict_types=1);

namespace App;

use App\Contracts\StorageInterface;
use App\Storage\StorageKeys;

class AdminAuth
{
    private StorageInterface $db;
    private RateLimiter $rateLimiter;

    public function __construct(StorageInterface $db, RateLimiter $rateLimiter)
    {
        $this->db = $db;
        $this->rateLimiter = $rateLimiter;
    }

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    public function login(string $username, string $password): array
    {
        $ipHash = Security::hashIp($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $limit = $this->rateLimiter->check($ipHash, 'admin_login');
        if (!$limit['allowed']) {
            return ['success' => false, 'message' => $limit['reason']];
        }

        $admin = $this->db->read(StorageKeys::ADMIN, []);
        $storedUser = $admin['username'] ?? 'admin';
        $storedHash = $admin['password_hash'] ?? '';

        if ($username !== $storedUser || !password_verify($password, $storedHash)) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $username;

        return ['success' => true];
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function createDefaultAdmin(StorageInterface $db): void
    {
        if ($db->exists(StorageKeys::ADMIN)) {
            return;
        }

        $db->write(StorageKeys::ADMIN, [
            'username' => 'admin',
            'password_hash' => password_hash('changeme123', PASSWORD_DEFAULT),
            'created' => date('c'),
            'note' => 'Change this password immediately after first login.',
        ]);
    }
}
