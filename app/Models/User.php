<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class User
{
    public static function find(int $id): ?array
    {
        $sql = 'SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function findByEmail(string $email): ?array
    {
        $sql = 'SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO users (email, password_hash, full_name, phone, user_type, status)
                VALUES (:email, :password_hash, :full_name, :phone, :user_type, :status)';

        Database::pdo()->prepare($sql)->execute([
            'email'         => strtolower(trim((string) $data['email'])),
            'password_hash' => (string) $data['password_hash'],
            'full_name'     => $data['full_name'] ?? null,
            'phone'         => $data['phone'] ?? null,
            'user_type'     => (string) ($data['user_type'] ?? 'customer'),
            'status'        => (string) ($data['status'] ?? 'active'),
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $allowed = ['full_name', 'phone', 'avatar_path', 'status', 'password_hash'];
        $sets = [];
        $params = ['id' => $id];

        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $sets[] = "{$column} = :{$column}";
                $params[$column] = $data[$column];
            }
        }

        if ($sets === []) {
            return false;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id AND deleted_at IS NULL';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() >= 0;
    }

    public static function emailExists(string $email): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['email' => strtolower(trim($email))]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
