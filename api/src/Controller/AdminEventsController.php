<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Response;

class AdminEventsController extends AppController
{
    public function index(): Response
    {
        $this->request->allowMethod(['get']);
        if (!$this->isAuthorized()) {
            return $this->json(['message' => '管理キーが正しくありません。'], 401);
        }

        $events = $this->fetchTable('Events')
            ->find()
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
        if (!$this->isAuthorized()) {
            return $this->json(['message' => '管理キーが正しくありません。'], 401);
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

    private function eventData(object $event): array
    {
        return [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'event_date' => $event->event_date?->format('Y-m-d'),
            'location' => $event->location,
        ];
    }

    private function isAuthorized(): bool
    {
        $expected = (string)Configure::read('Export.apiKey', '');
        $authorization = $this->request->getHeaderLine('Authorization');
        $provided = str_starts_with($authorization, 'Bearer ')
            ? substr($authorization, 7)
            : '';

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    private function flattenErrors(array $errors): array
    {
        $flattened = [];
        foreach ($errors as $field => $messages) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($messages));
            foreach ($iterator as $message) {
                if (is_string($message)) {
                    $flattened[$field] = $message;
                    break;
                }
            }
        }

        return $flattened;
    }

    private function json(array $payload, int $status = 200): Response
    {
        return $this->response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withStringBody((string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
