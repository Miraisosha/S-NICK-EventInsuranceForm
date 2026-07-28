<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddSurveyGuestAndRefereeQuestions extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('survey_responses');

        if (!$table->hasColumn('special_guest_satisfaction')) {
            $table->addColumn('special_guest_satisfaction', 'string', [
                'limit' => 20,
                'null' => true,
                'after' => 'staff_satisfaction',
            ]);
        }
        if (!$table->hasColumn('referee_workshop_feedback')) {
            $table->addColumn('referee_workshop_feedback', 'text', [
                'null' => true,
                'after' => 'special_guest_satisfaction',
            ]);
        }

        $table->update();
    }
}
