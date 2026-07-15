<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

class InsuranceMembersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('insurance_members');
        $this->setDisplayField('invited_name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Events', [
            'foreignKey' => 'event_id',
            'joinType' => 'LEFT',
        ]);
    }

    public function validationRegistration(Validator $validator): Validator
    {
        $validator
            ->integer('event_id', 'イベントを選択してください。')
            ->greaterThan('event_id', 0, 'イベントを選択してください。')
            ->requirePresence('event_id', true, 'イベントを選択してください。')
            ->notEmptyString('event_id', 'イベントを選択してください。')
            ->scalar('full_name')->maxLength('full_name', 100)
            ->requirePresence('full_name', true, '氏名を入力してください。')->notEmptyString('full_name', '氏名を入力してください。')
            ->scalar('full_name_kana')->maxLength('full_name_kana', 100)
            ->requirePresence('full_name_kana', true, '氏名（フリガナ）を入力してください。')->notEmptyString('full_name_kana', '氏名（フリガナ）を入力してください。')
            ->add('full_name_kana', 'kana', [
                'rule' => fn ($value): bool => (bool)preg_match('/^[ァ-ヶー\s　]+$/u', (string)$value),
                'message' => 'フリガナは全角カタカナで入力してください。',
            ])
            ->email('email', false, '正しいメールアドレスを入力してください。')
            ->requirePresence('email', true, 'メールアドレスを入力してください。')->notEmptyString('email', 'メールアドレスを入力してください。')
            ->scalar('phone')->maxLength('phone', 30)
            ->requirePresence('phone', true, '電話番号を入力してください。')->notEmptyString('phone', '電話番号を入力してください。')
            ->add('phone', 'format', [
                'rule' => fn ($value): bool => (bool)preg_match('/^[0-9+()\-\s]{10,20}$/', (string)$value),
                'message' => '正しい電話番号を入力してください。',
            ])
            ->scalar('postal_code')->maxLength('postal_code', 8)
            ->requirePresence('postal_code', true, '郵便番号を入力してください。')->notEmptyString('postal_code', '郵便番号を入力してください。')
            ->add('postal_code', 'format', [
                'rule' => fn ($value): bool => (bool)preg_match('/^\d{3}-?\d{4}$/', (string)$value),
                'message' => '郵便番号は123-4567の形式で入力してください。',
            ])
            ->scalar('prefecture')->maxLength('prefecture', 10)
            ->requirePresence('prefecture', true, '都道府県を選択してください。')->notEmptyString('prefecture', '都道府県を選択してください。')
            ->scalar('city')->maxLength('city', 100)
            ->requirePresence('city', true, '市区町村を入力してください。')->notEmptyString('city', '市区町村を入力してください。')
            ->scalar('street_address')->maxLength('street_address', 255)
            ->requirePresence('street_address', true, '番地を入力してください。')->notEmptyString('street_address', '番地を入力してください。')
            ->allowEmptyString('building')
            ->date('birth_date', ['ymd'], '正しい生年月日を入力してください。')
            ->requirePresence('birth_date', true, '生年月日を入力してください。')->notEmptyDate('birth_date', '生年月日を入力してください。')
            ->boolean('privacy_consent', '同意欄の値が正しくありません。')
            ->requirePresence('privacy_consent', true, '個人情報の取扱いに同意してください。')
            ->equals('privacy_consent', true, '個人情報の取扱いに同意してください。');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['event_id'], 'Events'), [
            'errorField' => 'event_id',
            'message' => '選択したイベントが見つかりません。',
        ]);

        return $rules;
    }
}
