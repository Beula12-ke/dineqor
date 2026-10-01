<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\ActivityLogger;
use RuntimeException;
use Throwable;

final class AuthController
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 900;

    public function showLogin(): void
    {
        Response::view('auth/login', ['title' => 'Sign In']);
    }

    public function login(): void
    {
        Csrf::check();

        $email = (string) Request::input('email', '');
        $password = (string) Request::input('password', '');

        if ($this->isLockedOut($email)) {
            Session::flash('error', 'Too many login attempts. Please try again in a few minutes.');
            Session::flash('__old', ['email' => $email]);

            Response::redirect('/login');
        }

        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if ($password === '') {
            $errors[] = 'Enter your password.';
        }

        if ($errors !== []) {
            $this->fail($errors, $email);
        }

        try {
            $authenticated = Auth::attempt($email, $password);
        } catch (Throwable $e) {
            error_log('[AuthController] ' . $e->getMessage());
            $authenticated = false;
        }

        if (!$authenticated) {
            $this->recordAttempt($email);
            $this->fail(['Invalid email or password.'], $email);
        }

        Session::forget('__login_attempts');

        ActivityLogger::log('auth.login', 'user', Auth::id(), 'User signed in');

        Response::redirect($this->homeFor((string) (Auth::userType() ?? 'customer')));
    }

    public function showRegister(): void
    {
        Response::view('auth/register', ['title' => 'Create Account']);
    }

    public function register(): void
    {
        Csrf::check();

        $fullName = trim((string) Request::input('full_name', ''));
        $email = strtolower(trim((string) Request::input('email', '')));
        $password = (string) Request::input('password', '');
        $confirm = (string) Request::input('password_confirmation', '');

        $errors = [];

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if (mb_strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($password !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }

        if ($errors === [] && User::emailExists($email)) {
            $errors[] = 'An account with that email already exists.';
        }

        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            Session::flash('__old', ['full_name' => $fullName, 'email' => $email]);

            Response::redirect('/register');
        }

        $userId = User::create([
            'email'         => $email,
            'password_hash' => User::hashPassword($password),
            'full_name'     => $fullName,
            'user_type'     => 'customer',
        ]);

        $user = User::find($userId);

        if ($user === null) {
            throw new RuntimeException('User creation failed.');
        }

        Auth::login($user);

        ActivityLogger::log('auth.register', 'user', $userId, 'Customer account created');

        Response::redirect($this->homeFor('customer'));
    }

    public function logout(): void
    {
        Csrf::check();

        $userId = Auth::id();

        if ($userId !== null) {
            ActivityLogger::log('auth.logout', 'user', $userId, 'User signed out');
        }

        Auth::logout();

        Response::redirect('/');
    }

    private function homeFor(string $userType): string
    {
        return match ($userType) {
            'platform_admin'   => '/admin/dashboard',
            'restaurant_owner' => '/restaurant/dashboard',
            'restaurant_staff' => '/staff/dashboard',
            default            => '/customer/account',
        };
    }

    /**
     * @param array<int,string> $errors
     */
    private function fail(array $errors, string $email): never
    {
        Session::flash('error', implode(' ', $errors));
        Session::flash('__old', ['email' => $email]);

        Response::redirect('/login');
    }

    private function isLockedOut(string $email): bool
    {
        $attempts = Session::get('__login_attempts', []);
        $key = $this->attemptKey($email);
        $entry = $attempts[$key] ?? null;

        if (!is_array($entry) || (int) ($entry['count'] ?? 0) < self::MAX_ATTEMPTS) {
            return false;
        }

        return (time() - (int) ($entry['started_at'] ?? 0)) < self::WINDOW_SECONDS;
    }

    private function recordAttempt(string $email): void
    {
        $attempts = Session::get('__login_attempts', []);
        $key = $this->attemptKey($email);
        $entry = $attempts[$key] ?? ['count' => 0, 'started_at' => time()];

        if ((time() - (int) ($entry['started_at'] ?? 0)) >= self::WINDOW_SECONDS) {
            $entry = ['count' => 0, 'started_at' => time()];
        }

        $entry['count'] = (int) $entry['count'] + 1;
        $attempts[$key] = $entry;

        Session::set('__login_attempts', $attempts);
    }

    private function attemptKey(string $email): string
    {
        return hash('sha256', Request::ip() . '|' . strtolower(trim($email)));
    }
}
