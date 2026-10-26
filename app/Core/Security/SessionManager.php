<?php
declare(strict_types=1);

namespace App\Core\Security;

final class SessionManager
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require dirname(__DIR__, 3) . '/config/session.php';
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $sameSite = in_array($this->config['same_site'], ['Lax', 'Strict'], true) ? $this->config['same_site'] : 'Lax';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) === '443');
        $savePath = (string) ($this->config['save_path'] ?? '');
        if ($savePath !== '') {
            if (!is_dir($savePath) && !mkdir($savePath, 0700, true) && !is_dir($savePath)) {
                throw new \RuntimeException('No fue posible preparar el almacenamiento privado de sesiones.');
            }
            session_save_path($savePath);
        }
        session_name((string) $this->config['name']);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'domain' => '',
            'secure' => (bool) $this->config['secure_cookie'] || $https,
            'httponly' => true, 'samesite' => $sameSite,
        ]);
        if (!session_start()) throw new \RuntimeException('No fue posible iniciar una sesión segura.');
    }

    public function authenticate(int $userId, string $token): void
    {
        $this->start();
        if (!session_regenerate_id(true)) throw new \RuntimeException('No fue posible renovar la sesión.');
        $_SESSION = [
            'auth_user_id' => $userId,
            'auth_token' => $token,
            'csrf_token' => bin2hex(random_bytes(32)),
            'authenticated_at' => time(),
        ];
    }

    public function userId(): ?int
    {
        $this->start();
        $value = $_SESSION['auth_user_id'] ?? null;
        return is_int($value) && $value > 0 ? $value : null;
    }

    public function token(): ?string
    {
        $this->start();
        $value = $_SESSION['auth_token'] ?? null;
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1 ? $value : null;
    }

    public function csrfToken(): string
    {
        $this->start();
        $token = $_SESSION['csrf_token'] ?? null;
        if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $token;
        }
        return $token;
    }

    public function verifyCsrf(string $token): bool
    {
        return $token !== '' && hash_equals($this->csrfToken(), $token);
    }

    public function flash(string $type, string $message): void
    {
        $this->start();
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    public function pullFlash(): ?array
    {
        $this->start();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($flash) ? $flash : null;
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => $params['samesite'] ?? 'Lax']);
        }
        session_destroy();
    }

    public function idleSeconds(): int { return max(300, (int) $this->config['idle_timeout']); }
    public function absoluteSeconds(): int { return max($this->idleSeconds(), (int) $this->config['absolute_timeout']); }
}
