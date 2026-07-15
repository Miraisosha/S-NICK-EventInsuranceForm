<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class InsuranceMember extends Entity
{
    protected array $_accessible = [
        'event_id' => true,
        'event' => true,
        'full_name' => true,
        'full_name_kana' => true,
        'email' => true,
        'phone' => true,
        'postal_code' => true,
        'prefecture' => true,
        'city' => true,
        'street_address' => true,
        'building' => true,
        'birth_date' => true,
        'privacy_consent' => true,
    ];

    protected array $_hidden = [
        'token_hash',
    ];
}
