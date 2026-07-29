<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\I18n\DateTime;

class SurveysController extends AppController
{
    public function options(): Response
    {
        $this->request->allowMethod(['get']);
        $events = $this->fetchTable('Events')->find()
            ->where(['deleted_at IS' => null])
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->all();

        return $this->json(['events' => array_map(static fn(object $event): array => [
            'id' => $event->id,
            'name' => $event->event_name,
            'date' => $event->event_date?->format('Y-m-d'),
            'location' => $event->location,
        ], $events->toList())]);
    }

    public function submit(): Response
    {
        $this->request->allowMethod(['post']);
        $eventId = filter_var($this->request->getData('event_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $attendeeName = trim((string)$this->request->getData('attendee_name'));

        if ($eventId === false || $eventId === null) {
            return $this->json(['message' => '参加したイベントを選択してください。'], 422);
        }

        if (!$this->fetchTable('Events')->exists(['id' => $eventId, 'deleted_at IS' => null])) {
            return $this->json(['message' => '選択したイベントを確認してください。'], 422);
        }

        $fields = [
            'attendance_days', 'event_enjoyment', 'overall_satisfaction', 'lesson_satisfaction',
            'staff_satisfaction', 'special_guest_satisfaction', 'referee_workshop_feedback',
            'difficulty', 'training_amount', 'participation_intent',
            'participation_reason', 'best_training', 'improvements', 'future_training', 'other_comments',
        ];
        $payload = array_intersect_key((array)$this->request->getData(), array_flip($fields));
        foreach ($fields as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]);
            }
        }
        $payload += [
            'event_id' => $eventId,
            'attendee_name' => $attendeeName,
            'submitted_at' => DateTime::now(),
        ];

        $table = $this->fetchTable('SurveyResponses');
        $response = $table->newEntity($payload);
        if ($response->getErrors() !== []) {
            return $this->json([
                'message' => '必須項目の回答内容を確認してください。',
                'errors' => $response->getErrors(),
            ], 422);
        }

        if (!$table->save($response)) {
            return $this->json(['message' => 'アンケートを保存できませんでした。'], 422);
        }

        return $this->json(['submitted' => true], 201);
    }
}
