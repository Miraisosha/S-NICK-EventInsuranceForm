<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateEvents extends BaseMigration
{
    public function up(): void
    {
        if (!$this->hasTable('events')) {
            $idType = $this->getAdapter()->getAdapterType() === 'sqlite' ? 'integer' : 'biginteger';
            $this->table('events', [
                'id' => false,
                'collation' => 'utf8mb4_unicode_ci',
            ])
                ->addColumn('id', $idType, ['identity' => true, 'signed' => false])
                ->addColumn('event_name', 'string', ['limit' => 150])
                ->addColumn('event_date', 'date')
                ->addColumn('location', 'string', ['limit' => 255])
                ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addPrimaryKey('id')
                ->addIndex('event_date', ['name' => 'idx_events_event_date'])
                ->create();
        }

        $members = $this->table('insurance_members');
        if (!$members->hasColumn('event_id')) {
            $members
                ->addColumn('event_id', 'biginteger', ['signed' => false, 'null' => true, 'after' => 'invited_name'])
                ->addIndex('event_id', ['name' => 'idx_insurance_members_event_id'])
                ->update();

            if ($this->getAdapter()->getAdapterType() !== 'sqlite') {
                $members->addForeignKey('event_id', 'events', 'id', [
                    'constraint' => 'fk_insurance_members_event',
                    'delete' => 'RESTRICT',
                    'update' => 'RESTRICT',
                ])->update();
            }
        }
    }
}
