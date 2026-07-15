<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\InsuranceMember;
use App\Model\Table\InsuranceMembersTable;
use Cake\Http\Response;
use Cake\I18n\DateTime;

class InvitationsController extends AppController
{
    private const POLICY_VERSION = '2026-07-15';

    private const REGISTRATION_FIELDS = [
        'event_id',
        'full_name',
        'full_name_kana',
        'email',
        'phone',
        'postal_code',
        'prefecture',
        'city',
        'street_address',
        'building',
        'birth_date',
        'privacy_consent',
    ];

    public function health(): Response
    {
        $this->request->allowMethod(['get']);

        return $this->json(['status' => 'ok']);
    }

    public function view(string $token): Response
    {
        $this->request->allowMethod(['get']);
        $member = $this->findByToken($token);

        if ($member === null) {
            return $this->json(['message' => 'このURLは無効です。'], 404);
        }
        if ($this->isExpired($member)) {
            return $this->json(['message' => 'このURLの有効期限が切れています。'], 410);
        }

        return $this->json([
            'invitedName' => $member->invited_name,
            'submitted' => $member->submitted_at !== null,
            'policyVersion' => self::POLICY_VERSION,
            'events' => $this->availableEvents(),
        ]);
    }

    public function validateRegistration(string $token): Response
    {
        $this->request->allowMethod(['post']);
        $member = $this->findActiveMember($token);
        if ($member instanceof Response) {
            return $member;
        }

        $data = $this->registrationData();
        if (!$this->eventExists($data['event_id'] ?? null)) {
            return $this->json([
                'message' => '入力内容を確認してください。',
                'errors' => ['event_id' => '選択したイベントが見つかりません。'],
            ], 422);
        }

        $table = $this->membersTable();
        $candidate = $table->newEntity(
            $data,
            ['validate' => 'registration', 'fields' => self::REGISTRATION_FIELDS],
        );
        $errors = $this->flattenErrors($candidate->getErrors());

        if ($errors !== []) {
            return $this->json([
                'message' => '入力内容を確認してください。',
                'errors' => $errors,
            ], 422);
        }

        return $this->json([
            'valid' => true,
            'data' => $data,
        ]);
    }

    public function submit(string $token): Response
    {
        $this->request->allowMethod(['post']);
        $table = $this->membersTable();
        $data = $this->registrationData();

        $result = $table->getConnection()->transactional(function () use ($table, $token, $data) {
            $member = $table->find()
                ->where(['token_hash' => hash('sha256', $token)])
                ->epilog('FOR UPDATE')
                ->first();

            if ($member === null) {
                return $this->json(['message' => 'このURLは無効です。'], 404);
            }
            if ($this->isExpired($member)) {
                return $this->json(['message' => 'このURLの有効期限が切れています。'], 410);
            }
            if ($member->submitted_at !== null) {
                return $this->json(['message' => 'このURLからの登録は完了しています。'], 409);
            }
            if (!$this->eventExists($data['event_id'] ?? null)) {
                return $this->json([
                    'message' => '入力内容を確認してください。',
                    'errors' => ['event_id' => '選択したイベントが見つかりません。'],
                ], 422);
            }

            $member = $table->patchEntity(
                $member,
                $data,
                ['validate' => 'registration', 'fields' => self::REGISTRATION_FIELDS],
            );
            $errors = $this->flattenErrors($member->getErrors());
            if ($errors !== []) {
                return $this->json([
                    'message' => '入力内容を確認してください。',
                    'errors' => $errors,
                ], 422);
            }

            $now = DateTime::now();
            $member->privacy_policy_version = self::POLICY_VERSION;
            $member->consented_at = $now;
            $member->submitted_at = $now;

            if (!$table->save($member)) {
                return $this->json(['message' => '登録に失敗しました。時間をおいて再度お試しください。'], 500);
            }

            return $this->json(['registered' => true]);
        });

        return $result;
    }

    private function membersTable(): InsuranceMembersTable
    {
        /** @var \App\Model\Table\InsuranceMembersTable $table */
        $table = $this->fetchTable('InsuranceMembers');

        return $table;
    }

    private function findByToken(string $token): ?InsuranceMember
    {
        if (!preg_match('/^[A-Za-z0-9_-]{20,200}$/', $token)) {
            return null;
        }

        return $this->membersTable()->find()
            ->where(['token_hash' => hash('sha256', $token)])
            ->first();
    }

    private function findActiveMember(string $token): InsuranceMember|Response
    {
        $member = $this->findByToken($token);
        if ($member === null) {
            return $this->json(['message' => 'このURLは無効です。'], 404);
        }
        if ($this->isExpired($member)) {
            return $this->json(['message' => 'このURLの有効期限が切れています。'], 410);
        }
        if ($member->submitted_at !== null) {
            return $this->json(['message' => 'このURLからの登録は完了しています。'], 409);
        }

        return $member;
    }

    private function isExpired(InsuranceMember $member): bool
    {
        return $member->token_expires_at !== null
            && $member->token_expires_at->getTimestamp() < time();
    }

    private function registrationData(): array
    {
        return array_intersect_key(
            (array)$this->request->getData(),
            array_flip(self::REGISTRATION_FIELDS),
        );
    }

    private function eventExists(mixed $eventId): bool
    {
        $id = filter_var($eventId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $id !== false && $this->fetchTable('Events')->exists(['id' => $id]);
    }

    private function availableEvents(): array
    {
        $events = $this->fetchTable('Events')
            ->find()
            ->orderByAsc('event_date')
            ->orderByAsc('id')
            ->all();

        return array_map(static fn ($event): array => [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'event_date' => $event->event_date?->format('Y-m-d'),
            'location' => $event->location,
        ], $events->toList());
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
