<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\I18n\DateTime;
use Throwable;

class SurveysController extends AppController
{
    public function options(): Response
    {
        $this->request->allowMethod(['get']);
        $eventId = filter_var($this->request->getQuery('event_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($eventId === false || $eventId === null) {
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

        if (!$this->fetchTable('Events')->exists(['id' => $eventId, 'deleted_at IS' => null])) {
            return $this->json(['message' => 'イベントが見つかりません。'], 404);
        }

        $answeredIds = $this->fetchTable('SurveyResponses')->find()
            ->select(['insurance_member_id'])
            ->where(['event_id' => $eventId])
            ->all()
            ->extract('insurance_member_id')
            ->toArray();
        $answered = array_fill_keys(array_map('intval', $answeredIds), true);

        $members = $this->fetchTable('InsuranceMembers')->find()
            ->where(['event_id' => $eventId, 'submitted_at IS NOT' => null])
            ->orderByAsc('full_name')
            ->orderByAsc('id')
            ->all();

        return $this->json(['members' => array_map(static fn(object $member): array => [
            'id' => $member->id,
            'name' => $member->full_name ?: $member->invited_name,
            'answered' => isset($answered[(int)$member->id]),
        ], $members->toList())]);
    }

    public function submit(): Response
    {
        $this->request->allowMethod(['post']);
        $eventId = filter_var($this->request->getData('event_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $memberId = filter_var($this->request->getData('insurance_member_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($eventId === false || $memberId === false) {
            return $this->json(['message' => '参加したイベントとお名前を選択してください。'], 422);
        }

        $member = $this->fetchTable('InsuranceMembers')->find()->where([
            'id' => $memberId,
            'event_id' => $eventId,
            'submitted_at IS NOT' => null,
        ])->first();
        if ($member === null) {
            return $this->json(['message' => '選択したイベントとお名前を確認してください。'], 422);
        }

        $fields = [
            'attendance_days', 'overall_satisfaction', 'lesson_satisfaction',
            'staff_satisfaction', 'difficulty', 'training_amount', 'participation_intent',
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
            'insurance_member_id' => $memberId,
            'attendee_name' => $member->full_name ?: $member->invited_name,
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

        try {
            if (!$table->save($response)) {
                return $this->json([
                    'message' => $response->getError('insurance_member_id')
                        ? 'このイベントのアンケートには回答済みです。'
                        : 'アンケートを保存できませんでした。',
                ], 422);
            }
        } catch (Throwable) {
            return $this->json(['message' => 'このイベントのアンケートには回答済みです。'], 409);
        }

        return $this->json(['submitted' => true], 201);
    }
}
