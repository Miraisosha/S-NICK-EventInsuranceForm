<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddDeletedAtToEvents extends BaseMigration
{
    public function up(): void
    {
        $events = $this->table('events');
        if (!$events->hasColumn('deleted_at')) {
            $events
                ->addColumn('deleted_at', 'datetime', ['null' => true, 'after' => 'modified'])
                ->addIndex('deleted_at', ['name' => 'idx_events_deleted_at'])
                ->update();
        }
    }
}
