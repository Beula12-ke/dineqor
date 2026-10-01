<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Restaurant;
use App\Models\User;

final class Auth
{
    private const KEY_USER_ID     = 'user_id';
    private const KEY_USER_TYPE   = 'user_type';
    private const KEY_RESTAURANT  = 'restaurant_id';

    private static ?array $cachedUser = null;

    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);

        // Always run a hash comparison to keep timing uniform for unknown emails.
        $hash = $user['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin';

        if (!password_verify($password, (string) $hash) || $user === null) {
            return false;
        }

        if (($user['status'] ?? 'active') !== 'active') {
            return false;
        }

        self::login($user);

        return true;
    }

    public static function login(array $user): void
    {
        Session::regenerate();

        Session::set(self::KEY_USER_ID, (int) $user['id']);
        Session::set(self::KEY_USER_TYPE, (string) $user['user_type']);
        Session::forget(self::KEY_RESTAURANT);

        $userType = (string) $user['user_type'];
        $userId = (int) $user['id'];

        $restaurantId = self::resolveRestaurantId($userType, $userId, $user);

        if ($restaurantId !== null) {
            Session::set(self::KEY_RESTAURANT, $restaurantId);
        }

        self::$cachedUser = $user;

        self::touchLogin($userId);
    }

    public static function logout(): void
    {
        self::$cachedUser = null;
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::get(self::KEY_USER_ID) !== null;
    }

    public static function id(): ?int
    {
        $id = Session::get(self::KEY_USER_ID);

        return $id === null ? null : (int) $id;
    }

    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $id = self::id();

        if ($id === null) {
            return null;
        }

        $user = User::find($id);

        if ($user === null) {
            self::logout();

            return null;
        }

        self::$cachedUser = $user;

        return $user;
    }

    public static function userType(): ?string
    {
        $type = Session::get(self::KEY_USER_TYPE);

        return $type === null ? null : (string) $type;
    }

    public static function restaurantId(): ?int
    {
        $id = Session::get(self::KEY_RESTAURANT);

        return $id === null ? null : (int) $id;
    }

    public static function require(): void
    {
        if (self::check()) {
            return;
        }

        Session::flash('error', 'Please sign in to continue.');
        Response::redirect('/login');
    }

    public static function isPlatformAdmin(): bool
    {
        return self::userType() === 'platform_admin';
    }

    private static function resolveRestaurantId(string $userType, int $userId, array $user): ?int
    {
        if ($userType === 'platform_admin' || $userType === 'customer') {
            return null;
        }

        if ($userType === 'restaurant_owner') {
            return Restaurant::findByOwner($userId);
        }

        if ($userType === 'restaurant_staff') {
            // Bootstrap lookup: at login there is no tenant context yet, so this
            // query is intentionally not routed through TenantQuery.
            $stmt = Database::pdo()->prepare(
                "SELECT restaurant_id FROM staff WHERE user_id = :uid AND status = 'active' ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute(['uid' => $userId]);
            $restaurantId = $stmt->fetchColumn();

            return $restaurantId === false ? null : (int) $restaurantId;
        }

        return null;
    }

    /**
     * last_login_ip is VARBINARY(16), so store the packed form.
     */
    private static function touchLogin(int $userId): void
    {
        $packed = @inet_pton(Request::ip());

        $sql = 'UPDATE users SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id';
        Database::pdo()->prepare($sql)->execute([
            'ip'  => $packed === false ? null : $packed,
            'id'  => $userId,
        ]);
    }
}
