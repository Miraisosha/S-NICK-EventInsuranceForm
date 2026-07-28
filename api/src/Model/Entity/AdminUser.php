<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class AdminUser extends Entity
{
    protected array $_accessible = [
        'username' => true,
        'password_hash' => true,
        'totp_secret_encrypted' => true,
        'totp_confirmed_at' => true,
        'last_totp_counter' => true,
        'failed_attempts' => true,
        'locked_until' => true,
        'last_login_at' => true,
    ];

    protected array $_hidden = [
        'password_hash',
        'totp_secret_encrypted',
    ];
}
