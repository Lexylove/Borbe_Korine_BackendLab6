<?php

/**
 * Creates the first login account.
 * Credentials come from .env (ADMIN_USERNAME / ADMIN_EMAIL / ADMIN_PASSWORD)
 * and fall back to admin / admin@example.com / Admin@12345 - change it after the first login!
 */
class Seed_default_admin_user {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
    }

    public function up()
    {
        $username = getenv('ADMIN_USERNAME') ?: 'admin';
        $email    = getenv('ADMIN_EMAIL')    ?: 'admin@example.com';
        $password = getenv('ADMIN_PASSWORD') ?: 'Admin@12345';

        $exists = $this->_lava->db->raw(
            "SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1",
            [$email, $username]
        )->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            return;
        }

        $this->_lava->db->raw(
            "INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, 'admin', 1)",
            [$username, $email, password_hash($password, PASSWORD_BCRYPT)]
        );
    }

    public function down()
    {
        $email = getenv('ADMIN_EMAIL') ?: 'admin@example.com';
        $this->_lava->db->raw("DELETE FROM users WHERE email = ?", [$email]);
    }
}
