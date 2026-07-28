<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateSurveyResponses extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('survey_responses')) {
            return;
        }

        $isSqlite = $this->getAdapter()->getAdapterType() === 'sqlite';
        $idType = $isSqlite ? 'integer' : 'biginteger';
        $idOptions = $isSqlite ? ['identity' => true] : ['identity' => true, 'signed' => false];
        $foreignIdOptions = $isSqlite ? [] : ['signed' => false];

        $table = $this->table('survey_responses', ['id' => false, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id', $idType, $idOptions)
            ->addColumn('event_id', $idType, $foreignIdOptions)
            ->addColumn('insurance_member_id', $idType, $foreignIdOptions)
            ->addColumn('attendee_name', 'string', ['limit' => 100])
            ->addColumn('attendance_days', 'string', ['limit' => 20])
            ->addColumn('overall_satisfaction', 'string', ['limit' => 20])
            ->addColumn('lesson_satisfaction', 'string', ['limit' => 20])
            ->addColumn('staff_satisfaction', 'string', ['limit' => 20])
            ->addColumn('difficulty', 'string', ['limit' => 20])
            ->addColumn('training_amount', 'string', ['limit' => 20])
            ->addColumn('participation_intent', 'string', ['limit' => 30])
            ->addColumn('participation_reason', 'text', ['null' => true])
            ->addColumn('best_training', 'text', ['null' => true])
            ->addColumn('improvements', 'text', ['null' => true])
            ->addColumn('future_training', 'text', ['null' => true])
            ->addColumn('other_comments', 'text', ['null' => true])
            ->addColumn('submitted_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addPrimaryKey('id')
            ->addIndex(['event_id', 'insurance_member_id'], [
                'unique' => true,
                'name' => 'uq_survey_responses_member_event',
            ])
            ->addIndex('submitted_at', ['name' => 'idx_survey_responses_submitted_at']);

        if (!$isSqlite) {
            $table
                ->addForeignKey('event_id', 'events', 'id', [
                    'constraint' => 'fk_survey_responses_event',
                    'delete' => 'RESTRICT',
                    'update' => 'RESTRICT',
                ])
                ->addForeignKey('insurance_member_id', 'insurance_members', 'id', [
                    'constraint' => 'fk_survey_responses_member',
                    'delete' => 'RESTRICT',
                    'update' => 'RESTRICT',
                ]);
        }

        $table->create();
    }
}
