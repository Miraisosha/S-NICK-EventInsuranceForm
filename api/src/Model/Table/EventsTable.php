<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class EventsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('events');
        $this->setDisplayField('event_name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->hasMany('InsuranceMembers', [
            'foreignKey' => 'event_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('event_name')
            ->maxLength('event_name', 150)
            ->requirePresence('event_name', 'create', 'イベント名を入力してください。')
            ->notEmptyString('event_name', 'イベント名を入力してください。')
            ->date('event_date', ['ymd'], '正しい開催日を入力してください。')
            ->requirePresence('event_date', 'create', '開催日を入力してください。')
            ->notEmptyDate('event_date', '開催日を入力してください。')
            ->scalar('location')
            ->maxLength('location', 255)
            ->requirePresence('location', 'create', '場所を入力してください。')
            ->notEmptyString('location', '場所を入力してください。');

        return $validator;
    }
}
