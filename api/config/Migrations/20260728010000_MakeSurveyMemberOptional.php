<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class MakeSurveyMemberOptional extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('survey_responses');

        if ($table->hasForeignKey(['insurance_member_id'])) {
            $table->dropForeignKey(['insurance_member_id'])->update();
        }
        if (!$table->hasIndexByName('idx_survey_responses_event_id')) {
            $table->addIndex(['event_id'], ['name' => 'idx_survey_responses_event_id'])->update();
        }
        if ($table->hasIndex(['event_id', 'insurance_member_id'])) {
            $table->removeIndex(['event_id', 'insurance_member_id'])->update();
        }

        $options = ['null' => true];
        if ($this->getAdapter()->getAdapterType() !== 'sqlite') {
            $options['signed'] = false;
        }
        $table->changeColumn('insurance_member_id', 'biginteger', $options)->update();
    }
}
