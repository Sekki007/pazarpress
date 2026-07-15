<?php

declare(strict_types=1);

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email)), 'admin']);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['passwordHash'])) {
            return false;
        }
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'] ?? 'Admin',
            'role' => 'admin',
        ];
        return true;
    }

    public static function attemptOwner(string $email, string $password): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email)), 'restaurant_owner']);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['passwordHash'])) {
            return false;
        }
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'] ?? 'Vlasnik',
            'role' => 'restaurant_owner',
        ];

        return true;
    }

    public static function registerOwner(string $email, string $password, string $name): ?string
    {
        $email = strtolower(trim($email));
        if ($email === '' || strlen($password) < 8) {
            return null;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return null;
        }
        $id = new_id();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (id, email, passwordHash, name, role) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, $email, $hash, trim($name), 'restaurant_owner']);

        return $id;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function requireAdmin(): void
    {
        if (!self::check() || (self::user()['role'] ?? 'admin') !== 'admin') {
            $return = urlencode($_SERVER['REQUEST_URI'] ?? '/admin');
            redirect('/admin/login?return=' . $return);
        }
    }

    public static function requireOwner(): void
    {
        if (!self::check() || (self::user()['role'] ?? '') !== 'restaurant_owner') {
            $return = urlencode($_SERVER['REQUEST_URI'] ?? '/moj-meni');
            redirect('/moj-meni/prijava?return=' . $return);
        }
    }

    public static function isOwner(): bool
    {
        return self::check() && (self::user()['role'] ?? '') === 'restaurant_owner';
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
    }
}
