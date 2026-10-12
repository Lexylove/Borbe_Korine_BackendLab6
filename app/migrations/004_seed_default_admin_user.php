<?php

/**
 * Creates the first login account.
 * Credentials come from INITIAL_ADMIN_USERNAME, INITIAL_ADMIN_EMAIL, and
 * INITIAL_ADMIN_PASSWORD. No default credentials are used.
 */
class Seed_default_admin_user {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
    }

    public function up()
    {
        $username = trim((string) getenv('INITIAL_ADMIN_USERNAME'));
        $email    = trim((string) getenv('INITIAL_ADMIN_EMAIL'));
        $password = (string) getenv('INITIAL_ADMIN_PASSWORD');

        if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            // Existing databases may already have accounts created before this
            // seed migration was added. Do not block schema migrations in that case.
            $existing_user = $this->_lava->db->raw("SELECT id FROM users LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($existing_user) {
                return;
            }
            throw new RuntimeException('Set INITIAL_ADMIN_USERNAME, a valid INITIAL_ADMIN_EMAIL, and an INITIAL_ADMIN_PASSWORD of at least 12 characters before running this migration.');
        }

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
        $email = trim((string) getenv('INITIAL_ADMIN_EMAIL'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Set a valid INITIAL_ADMIN_EMAIL before rolling back this migration.');
        }

        $this->_lava->db->raw("DELETE FROM users WHERE email = ?", [$email]);
    }
}
