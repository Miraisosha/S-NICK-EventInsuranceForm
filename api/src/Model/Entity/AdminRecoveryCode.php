<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class AdminRecoveryCode extends Entity
{
    protected array $_accessible = [
        'admin_user_id' => true,
        'code_hash' => true,
        'used_at' => true,
    ];

    protected array $_hidden = ['code_hash'];
}
