<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateInsuranceMembers extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('insurance_members')) {
            return;
        }

        $idType = $this->getAdapter()->getAdapterType() === 'sqlite' ? 'integer' : 'biginteger';

        $this->table('insurance_members', [
            'id' => false,
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', $idType, ['identity' => true, 'signed' => false])
            ->addColumn('invited_name', 'string', ['limit' => 100])
            ->addColumn('full_name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('full_name_kana', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('phone', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('postal_code', 'string', ['limit' => 8, 'null' => true])
            ->addColumn('prefecture', 'string', ['limit' => 10, 'null' => true])
            ->addColumn('city', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('street_address', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('building', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('birth_date', 'date', ['null' => true])
            ->addColumn('token_hash', 'char', ['limit' => 64])
            ->addColumn('token_expires_at', 'datetime', ['null' => true])
            ->addColumn('privacy_consent', 'boolean', ['default' => false])
            ->addColumn('privacy_policy_version', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('consented_at', 'datetime', ['null' => true])
            ->addColumn('submitted_at', 'datetime', ['null' => true])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addPrimaryKey('id')
            ->addIndex('token_hash', ['unique' => true, 'name' => 'uq_insurance_members_token_hash'])
            ->addIndex('submitted_at', ['name' => 'idx_insurance_members_submitted_at'])
            ->create();
    }
}
