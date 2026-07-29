<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class SurveyResponsesTable extends Table
{
    public const SATISFACTION_CHOICES = ['とても満足', '満足', '普通', 'やや不満', '不満'];
    public const ATTENDANCE_CHOICES = ['1日目のみ', '2日目のみ', '両日参加'];
    public const ENJOYMENT_CHOICES = ['とても楽しかった', '楽しかった', '普通', '楽しくなかった', 'すごく楽しくなかった'];
    public const DIFFICULTY_CHOICES = ['とても易しかった', 'やや易しかった', 'ちょうど良かった', 'やや難しかった', 'とても難しかった'];
    public const AMOUNT_CHOICES = ['多かった', 'やや多かった', 'ちょうど良かった', 'やや少なかった', '少なかった'];
    public const INTENT_CHOICES = ['ぜひ参加したい', '機会があれば参加したい', 'どちらとも言えない', 'あまり参加したくない', '参加したくない'];

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('survey_responses');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Events', ['foreignKey' => 'event_id']);
        $this->belongsTo('InsuranceMembers', ['foreignKey' => 'insurance_member_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('event_id')->greaterThan('event_id', 0)->requirePresence('event_id')->notEmptyString('event_id')
            ->scalar('attendee_name')->maxLength('attendee_name', 100)->allowEmptyString('attendee_name');

        $this->requiredChoice($validator, 'attendance_days', self::ATTENDANCE_CHOICES);
        $this->requiredChoice($validator, 'event_enjoyment', self::ENJOYMENT_CHOICES);
        foreach ([
            'overall_satisfaction',
            'lesson_satisfaction',
            'staff_satisfaction',
            'special_guest_satisfaction',
        ] as $field) {
            $this->requiredChoice($validator, $field, self::SATISFACTION_CHOICES);
        }
        $this->requiredChoice($validator, 'difficulty', self::DIFFICULTY_CHOICES);
        $this->requiredChoice($validator, 'training_amount', self::AMOUNT_CHOICES);
        $this->requiredChoice($validator, 'participation_intent', self::INTENT_CHOICES);

        foreach ([
            'referee_workshop_feedback',
            'participation_reason',
            'best_training',
            'improvements',
            'future_training',
            'other_comments',
        ] as $field) {
            $validator->scalar($field)->maxLength($field, 2000)->allowEmptyString($field);
        }

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['event_id'], 'Events'), ['errorField' => 'event_id']);

        return $rules;
    }

    private function requiredChoice(Validator $validator, string $field, array $choices): void
    {
        $validator
            ->scalar($field)
            ->requirePresence($field, true, '回答を選択してください。')
            ->notEmptyString($field, '回答を選択してください。')
            ->inList($field, $choices, '選択肢から回答してください。');
    }
}
