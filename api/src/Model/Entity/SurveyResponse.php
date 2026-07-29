<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class SurveyResponse extends Entity
{
    protected array $_accessible = [
        'event_id' => true,
        'insurance_member_id' => true,
        'attendee_name' => true,
        'attendance_days' => true,
        'event_enjoyment' => true,
        'overall_satisfaction' => true,
        'lesson_satisfaction' => true,
        'staff_satisfaction' => true,
        'special_guest_satisfaction' => true,
        'referee_workshop_feedback' => true,
        'difficulty' => true,
        'training_amount' => true,
        'participation_intent' => true,
        'participation_reason' => true,
        'best_training' => true,
        'improvements' => true,
        'future_training' => true,
        'other_comments' => true,
        'submitted_at' => true,
    ];
}
