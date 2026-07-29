<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddEventEnjoymentToSurveyResponses extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('survey_responses');
        if (!$table->hasColumn('event_enjoyment')) {
            $table->addColumn('event_enjoyment', 'string', [
                'limit' => 30,
                'null' => true,
                'after' => 'attendance_days',
            ])->update();
        }
    }
}
