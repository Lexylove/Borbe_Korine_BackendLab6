<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * AuthController - JWT authentication using the LavaLust Api library.
 *
 *  POST /api/auth/login    { email, password }        -> access + refresh token
 *  POST /api/auth/register { username, email, password } -> create a user account
 *  POST /api/auth/refresh  { refresh_token }          -> new tokens
 *  POST /api/auth/logout   { refresh_token }          -> revokes the refresh token
 *  GET  /api/auth/me       (Bearer access token)      -> current user
 */
class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // Load api first so CORS/security headers are sent even if something later fails
        $this->call->library('api');
        $this->call->database();
    }

    public function register()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 60);

        $body = $this->api->body();
        $username = trim((string) ($body['username'] ?? ''));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) {
            $errors['username'] = 'Username must be 3–100 characters and use only letters, numbers, dots, underscores, or hyphens.';
        }
        if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address (up to 255 characters).';
        }
        if (strlen($password) < 12 || strlen($password) > 72) {
            $errors['password'] = 'Password must be between 12 and 72 characters.';
        }
        if ($errors) {
            $this->api->respond(['error' => 'Validation failed.', 'errors' => $errors], 422);
        }

        $existing = $this->db->raw(
            'SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1',
            [$email, $username]
        )->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $this->api->respond_error('That email or username is already registered.', 409);
        }

        $this->db->raw(
            "INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, 'user', 1)",
            [$username, $email, password_hash($password, PASSWORD_DEFAULT)]
        );

        $this->api->respond(['message' => 'Account created. You can now log in.'], 201);
    }

    public function login()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 60);

        $body     = $this->api->body();
        $login    = trim((string) ($body['email'] ?? $body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($login === '' || $password === '') {
            $this->api->respond_error('Email and password are required.', 422);
        }

        $stmt = $this->db->raw(
            "SELECT id, username, email, password, role, is_active FROM users
             WHERE email = ? OR username = ? LIMIT 1",
            [$login, $login]
        );
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Same message for unknown user / wrong password (no user enumeration)
        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid email or password.', 401);
        }
        if ((int) $user['is_active'] !== 1) {
            $this->api->respond_error('Account is disabled.', 403);
        }

        $tokens = $this->api->issue_tokens([
            'id'     => (int) $user['id'],
            'role'   => $user['role'],
            'scopes' => $this->scopes($user['role']),
        ]);

        $this->api->respond([
            'message' => 'Login successful',
            'user'    => $this->public_user($user),
            'tokens'  => $tokens,
        ]);
    }

    public function refresh()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('refresh_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 30, 60);

        $body  = $this->api->body();
        $token = (string) ($body['refresh_token'] ?? '');
        if ($token === '') {
            $this->api->respond_error('refresh_token is required.', 422);
        }
        $this->api->refresh_access_token($token); // responds + exits
    }

    public function logout()
    {
        $this->api->require_method('POST');

        $body  = $this->api->body();
        $token = (string) ($body['refresh_token'] ?? '');
        if ($token !== '') {
            $this->api->revoke_refresh_token($token);
        }
        $this->api->respond(['message' => 'Logged out']);
    }

    public function me()
    {
        $this->api->require_method('GET');
        $auth = $this->api->require_jwt();

        $stmt = $this->db->raw(
            "SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1",
            [$auth['sub']]
        );
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->api->respond(['user' => $user]);
    }

    private function scopes($role)
    {
        // Registered accounts must be able to complete product CRUD for the lab.
        $map = ['admin' => ['read', 'write', 'delete'], 'moderator' => ['read', 'write'], 'user' => ['read', 'write', 'delete']];
        return $map[$role] ?? ['read'];
    }

    private function public_user(array $u)
    {
        return ['id' => (int) $u['id'], 'username' => $u['username'], 'email' => $u['email'], 'role' => $u['role']];
    }
}
