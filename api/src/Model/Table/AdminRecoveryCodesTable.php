<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class AdminRecoveryCodesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('admin_recovery_codes');
        $this->setPrimaryKey('id');
        $this->belongsTo('AdminUsers', [
            'foreignKey' => 'admin_user_id',
            'joinType' => 'INNER',
        ]);
    }
}
