<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\I18n\DateTime;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class AdminEventsController extends AppController
{
    public function index(): Response
    {
        $this->request->allowMethod(['get']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }

        $events = $this->fetchTable('Events')
            ->find()
            ->where(['deleted_at IS' => null])
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->all();

        return $this->json([
            'events' => array_map($this->eventData(...), $events->toList()),
        ]);
    }

    public function add(): Response
    {
        $this->request->allowMethod(['post']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }

        $table = $this->fetchTable('Events');
        $event = $table->newEntity((array)$this->request->getData(), [
            'fields' => ['event_name', 'event_date', 'location'],
        ]);
        $errors = $this->flattenErrors($event->getErrors());
        if ($errors !== []) {
            return $this->json([
                'message' => '入力内容を確認してください。',
                'errors' => $errors,
            ], 422);
        }

        if (!$table->save($event)) {
            return $this->json(['message' => 'イベントを登録できませんでした。'], 500);
        }

        return $this->json(['event' => $this->eventData($event)], 201);
    }

    public function view(string $id): Response
    {
        $this->request->allowMethod(['get']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }

        $event = $this->activeEvent($id);
        if ($event === null) {
            return $this->json(['message' => 'イベントが見つかりません。'], 404);
        }

        return $this->json(['event' => $this->eventData($event)]);
    }

    public function edit(string $id): Response
    {
        $this->request->allowMethod(['put', 'patch']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }

        $table = $this->fetchTable('Events');
        $event = $this->activeEvent($id);
        if ($event === null) {
            return $this->json(['message' => 'イベントが見つかりません。'], 404);
        }

        $event = $table->patchEntity($event, (array)$this->request->getData(), [
            'fields' => ['event_name', 'event_date', 'location'],
        ]);
        $errors = $this->flattenErrors($event->getErrors());
        if ($errors !== []) {
            return $this->json(['message' => '入力内容を確認してください。', 'errors' => $errors], 422);
        }
        if (!$table->save($event)) {
            return $this->json(['message' => 'イベントを更新できませんでした。'], 500);
        }

        return $this->json(['event' => $this->eventData($event)]);
    }

    public function delete(string $id): Response
    {
        $this->request->allowMethod(['delete']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }

        $table = $this->fetchTable('Events');
        $event = $this->activeEvent($id);
        if ($event === null) {
            return $this->json(['message' => 'イベントが見つかりません。'], 404);
        }

        $event->deleted_at = DateTime::now();
        if (!$table->save($event, ['checkRules' => false])) {
            return $this->json(['message' => 'イベントを削除できませんでした。'], 500);
        }

        return $this->json(['deleted' => true]);
    }

    private function eventData(object $event): array
    {
        $members = $this->fetchTable('InsuranceMembers');
        $pendingCount = $members->find()
            ->where(['event_id' => $event->id, 'submitted_at IS' => null])
            ->count();
        $completedCount = $members->find()
            ->where(['event_id' => $event->id, 'submitted_at IS NOT' => null])
            ->count();

        return [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'event_date' => $event->event_date?->format('Y-m-d'),
            'location' => $event->location,
            'pending_count' => $pendingCount,
            'completed_count' => $completedCount,
        ];
    }

    private function activeEvent(string $id): ?object
    {
        $eventId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($eventId === false) {
            return null;
        }

        return $this->fetchTable('Events')->find()
            ->where(['id' => $eventId, 'deleted_at IS' => null])
            ->first();
    }

    private function flattenErrors(array $errors): array
    {
        $flattened = [];
        foreach ($errors as $field => $messages) {
            $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($messages));
            foreach ($iterator as $message) {
                if (is_string($message)) {
                    $flattened[$field] = $message;
                    break;
                }
            }
        }

        return $flattened;
    }
}
