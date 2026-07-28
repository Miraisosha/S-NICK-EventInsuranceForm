<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddAdminAuthentication extends BaseMigration
{
    public function up(): void
    {
        $isSqlite = $this->getAdapter()->getAdapterType() === 'sqlite';
        $idType = $isSqlite ? 'integer' : 'biginteger';
        $idOptions = $isSqlite ? ['identity' => true] : ['identity' => true, 'signed' => false];

        if (!$this->hasTable('admin_users')) {
            $this->table('admin_users', ['id' => false, 'collation' => 'utf8mb4_unicode_ci'])
                ->addColumn('id', $idType, $idOptions)
                ->addColumn('username', 'string', ['limit' => 100])
                ->addColumn('password_hash', 'string', ['limit' => 255])
                ->addColumn('totp_secret_encrypted', 'text', ['null' => true])
                ->addColumn('totp_confirmed_at', 'datetime', ['null' => true])
                ->addColumn('last_totp_counter', 'biginteger', ['null' => true])
                ->addColumn('failed_attempts', 'integer', ['default' => 0])
                ->addColumn('locked_until', 'datetime', ['null' => true])
                ->addColumn('last_login_at', 'datetime', ['null' => true])
                ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addPrimaryKey('id')
                ->addIndex('username', ['unique' => true, 'name' => 'uq_admin_users_username'])
                ->create();
        }

        if (!$this->hasTable('admin_recovery_codes')) {
            $codes = $this->table('admin_recovery_codes', ['id' => false, 'collation' => 'utf8mb4_unicode_ci'])
                ->addColumn('id', $idType, $idOptions)
                ->addColumn('admin_user_id', $idType, $isSqlite ? [] : ['signed' => false])
                ->addColumn('code_hash', 'char', ['limit' => 64])
                ->addColumn('used_at', 'datetime', ['null' => true])
                ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addPrimaryKey('id')
                ->addIndex('admin_user_id', ['name' => 'idx_admin_recovery_codes_user'])
                ->addIndex('code_hash', ['unique' => true, 'name' => 'uq_admin_recovery_codes_hash']);

            if (!$isSqlite) {
                $codes->addForeignKey('admin_user_id', 'admin_users', 'id', [
                    'constraint' => 'fk_admin_recovery_codes_user',
                    'delete' => 'CASCADE',
                    'update' => 'RESTRICT',
                ]);
            }
            $codes->create();
        }
    }
}
