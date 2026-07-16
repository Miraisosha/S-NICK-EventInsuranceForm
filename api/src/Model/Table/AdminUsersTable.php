<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class AdminUsersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('admin_users');
        $this->setDisplayField('username');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->hasMany('AdminRecoveryCodes', [
            'foreignKey' => 'admin_user_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->scalar('username')
            ->maxLength('username', 100)
            ->requirePresence('username', 'create')
            ->notEmptyString('username')
            ->add('username', 'format', [
                'rule' => ['custom', '/^[A-Za-z0-9._-]{3,100}$/'],
                'message' => '管理者IDは半角英数字と . _ - のみ使用できます。',
            ])
            ->scalar('password_hash')
            ->maxLength('password_hash', 255)
            ->requirePresence('password_hash', 'create')
            ->notEmptyString('password_hash');
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['username']), [
            'errorField' => 'username',
            'message' => 'この管理者IDは既に登録されています。',
        ]);

        return $rules;
    }
}
