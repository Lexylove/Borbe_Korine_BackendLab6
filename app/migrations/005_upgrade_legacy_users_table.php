<?php

/**
 * Upgrade older users tables while preserving their existing accounts.
 * The same repair is used by migration 001 for databases with no migration history.
 */
class Upgrade_legacy_users_table
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if (!$this->_lava->dbforge->table_exists('users')) {
            throw new RuntimeException('The users table must exist before upgrading it.');
        }

        $columns = $this->_lava->db->raw('SHOW COLUMNS FROM `users`')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('username', $columns, true) || !in_array('password', $columns, true)) {
            throw new RuntimeException('The existing users table is missing username or password; it was left unchanged.');
        }

        if (!in_array('email', $columns, true)) {
            $this->_lava->db->raw('ALTER TABLE `users` ADD COLUMN `email` VARCHAR(255) NULL AFTER `username`');
        }
        if (!in_array('role', $columns, true)) {
            $this->_lava->db->raw("ALTER TABLE `users` ADD COLUMN `role` ENUM('admin','moderator','user') NOT NULL DEFAULT 'user'");
        }
        if (!in_array('is_active', $columns, true)) {
            $this->_lava->db->raw('ALTER TABLE `users` ADD COLUMN `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1');
        }
        if (!in_array('created_at', $columns, true)) {
            $this->_lava->db->raw('ALTER TABLE `users` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        }
        if (!in_array('updated_at', $columns, true)) {
            $this->_lava->db->raw('ALTER TABLE `users` ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL');
        }

        // Keep pre-existing username/password accounts usable after the upgrade.
        $this->_lava->db->raw("UPDATE `users` SET `email` = CONCAT('legacy-', `id`, '@local.invalid') WHERE `email` IS NULL OR `email` = ''");
        $this->_lava->db->raw('ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(255) NOT NULL');

        $indexes = $this->_lava->db->raw('SHOW INDEX FROM `users`')->fetchAll(PDO::FETCH_ASSOC);
        $email_is_unique = false;
        foreach ($indexes as $index) {
            if ($index['Column_name'] === 'email' && (int) $index['Non_unique'] === 0) {
                $email_is_unique = true;
                break;
            }
        }
        if (!$email_is_unique) {
            $this->_lava->db->raw('ALTER TABLE `users` ADD UNIQUE KEY `email_unique` (`email`)');
        }
    }

    public function down()
    {
        // Keep added account metadata on rollback to avoid deleting user data.
    }
}
