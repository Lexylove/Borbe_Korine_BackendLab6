<?php

class Create_users_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('users')) {
            $this->upgrade_existing_users_table();
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => TRUE,
                    'auto_increment' => TRUE,
                    'null'           => FALSE,
                ],
                'username' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => FALSE,
                ],
                'email' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => FALSE,
                    'unique'     => TRUE,
                ],
                'password' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => FALSE,
                ],
                'role' => [
                    'type'       => 'ENUM',
                    'constraint' => "'admin','moderator','user'",
                    'null'       => FALSE,
                    'default'    => 'user',
                ],
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => TRUE,
                    'null'       => FALSE,
                    'default'    => 1,
                ],
                'created_at' => [
                    'type'    => 'DATETIME',
                    'null'    => FALSE,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
                'updated_at' => [
                    'type'    => 'DATETIME',
                    'null'    => TRUE,
                    'default' => NULL,
                ],
            ])
            ->add_key('id', primary: TRUE)
            ->add_key('username', unique: TRUE, name: 'username_unique')
            ->add_key('email', name: 'email_idx')
            ->add_key('role', name: 'role_idx')
            ->create_table('users');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('users');
    }

    private function upgrade_existing_users_table()
    {
        $columns = $this->_lava->db->raw('SHOW COLUMNS FROM `users`')->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('email', $columns, true)) {
            $this->_lava->db->raw('ALTER TABLE `users` ADD COLUMN `email` VARCHAR(255) NULL AFTER `username`');
        }
        if (!in_array('role', $columns, true)) {
            $this->_lava->db->raw("ALTER TABLE `users` ADD COLUMN `role` ENUM('admin','moderator','user') NOT NULL DEFAULT 'user'");
        }
        if (!in_array('is_active', $columns, true)) {
            $this->_lava->db->raw('ALTER TABLE `users` ADD COLUMN `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1');
        }
        if (!in_array('updated_at', $columns, true)) {
            $this->_lava->db->raw('ALTER TABLE `users` ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL');
        }

        // Preserve legacy accounts that predate email login with a reserved address.
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
}
