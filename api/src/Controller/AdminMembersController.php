<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\I18n\DateTime;

class AdminMembersController extends AppController
{
    public function pending(string $eventId): Response
    {
        $this->request->allowMethod(['get']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }
        if (!$this->eventExists($eventId)) {
            return $this->json(['message' => 'イベントが見つかりません。'], 404);
        }

        $members = $this->fetchTable('InsuranceMembers')->find()
            ->where(['event_id' => (int)$eventId, 'submitted_at IS' => null])
            ->orderByDesc('id')
            ->all();

        return $this->json(['members' => array_map($this->pendingData(...), $members->toList())]);
    }

    public function issue(string $eventId): Response
    {
        $this->request->allowMethod(['post']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }
        if (!$this->eventExists($eventId)) {
            return $this->json(['message' => 'イベントが見つかりません。'], 404);
        }

        $name = trim((string)$this->request->getData('name', ''));
        if (mb_strlen($name) > 100) {
            return $this->json(['message' => '入力内容を確認してください。', 'errors' => [
                'name' => '氏名は100文字以内で入力してください。',
            ]], 422);
        }
        $days = min(365, max(1, (int)$this->request->getData('days', 30)));
        [$token, $tokenHash] = $this->newToken();

        $table = $this->fetchTable('InsuranceMembers');
        $member = $table->newEmptyEntity();
        $member->set('event_id', (int)$eventId);
        $member->set('invited_name', $name);
        $member->set('token_hash', $tokenHash);
        $member->set('token_expires_at', DateTime::now()->addDays($days));

        if (!$table->save($member)) {
            return $this->json(['message' => '登録URLを発行できませんでした。'], 500);
        }

        return $this->json([
            'member' => $this->pendingData($member),
            'url' => $this->registrationUrl($token),
        ], 201);
    }

    public function bulkIssue(string $eventId): Response
    {
        $this->request->allowMethod(['post']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }
        if (!$this->eventExists($eventId)) {
            return $this->json(['message' => 'イベントが見つかりません。'], 404);
        }

        $lines = preg_split('/\R/u', (string)$this->request->getData('text', '')) ?: [];
        $names = [];
        foreach ($lines as $lineNumber => $line) {
            $name = trim($line);
            if ($name === '') {
                continue;
            }
            if (mb_strlen($name) > 100) {
                return $this->json([
                    'message' => sprintf('%d行目の氏名は100文字以内で入力してください。', $lineNumber + 1),
                ], 422);
            }
            $names[] = $name;
        }

        if ($names === []) {
            return $this->json(['message' => '登録する氏名を1名以上入力してください。'], 422);
        }
        if (count($names) > 500) {
            return $this->json(['message' => '一度に登録できる人数は500名までです。'], 422);
        }

        $days = min(365, max(1, (int)$this->request->getData('days', 30)));
        $table = $this->fetchTable('InsuranceMembers');

        try {
            $members = $table->getConnection()->transactional(function () use ($table, $eventId, $names, $days): array {
                $results = [];
                foreach ($names as $name) {
                    [$token, $tokenHash] = $this->newToken();
                    $member = $table->newEmptyEntity();
                    $member->set('event_id', (int)$eventId);
                    $member->set('invited_name', $name);
                    $member->set('token_hash', $tokenHash);
                    $member->set('token_expires_at', DateTime::now()->addDays($days));
                    $table->saveOrFail($member);

                    $results[] = $this->pendingData($member) + [
                        'url' => $this->registrationUrl($token),
                    ];
                }

                return $results;
            });
        } catch (\Throwable) {
            return $this->json(['message' => '加入前ユーザーを一括登録できませんでした。'], 500);
        }

        return $this->json([
            'count' => count($members),
            'members' => $members,
        ], 201);
    }

    public function reissue(string $eventId, string $memberId): Response
    {
        $this->request->allowMethod(['post']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }

        $table = $this->fetchTable('InsuranceMembers');
        $member = $table->find()->where([
            'id' => (int)$memberId,
            'event_id' => (int)$eventId,
            'submitted_at IS' => null,
        ])->first();
        if ($member === null || !$this->eventExists($eventId)) {
            return $this->json(['message' => '加入前ユーザーが見つかりません。'], 404);
        }

        $days = min(365, max(1, (int)$this->request->getData('days', 30)));
        [$token, $tokenHash] = $this->newToken();
        $member->token_hash = $tokenHash;
        $member->token_expires_at = DateTime::now()->addDays($days);
        if (!$table->save($member)) {
            return $this->json(['message' => '登録URLを再発行できませんでした。'], 500);
        }

        return $this->json([
            'member' => $this->pendingData($member),
            'url' => $this->registrationUrl($token),
        ]);
    }

    public function completed(string $eventId): Response
    {
        $this->request->allowMethod(['get']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }
        if (!$this->eventExists($eventId, true)) {
            return $this->json(['message' => 'イベントが見つかりません。'], 404);
        }

        $members = $this->fetchTable('InsuranceMembers')->find()
            ->where(['event_id' => (int)$eventId, 'submitted_at IS NOT' => null])
            ->orderByDesc('submitted_at')
            ->all();

        return $this->json(['members' => array_map($this->completedData(...), $members->toList())]);
    }

    public function viewCompleted(string $eventId, string $memberId): Response
    {
        $this->request->allowMethod(['get']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }

        $member = $this->fetchTable('InsuranceMembers')->find()->where([
            'id' => (int)$memberId,
            'event_id' => (int)$eventId,
            'submitted_at IS NOT' => null,
        ])->first();
        if ($member === null) {
            return $this->json(['message' => '加入済みユーザーが見つかりません。'], 404);
        }

        return $this->json(['member' => $this->completedData($member, true)]);
    }

    private function eventExists(string $eventId, bool $includeDeleted = false): bool
    {
        $conditions = ['id' => (int)$eventId];
        if (!$includeDeleted) {
            $conditions['deleted_at IS'] = null;
        }

        return (int)$eventId > 0 && $this->fetchTable('Events')->exists($conditions);
    }

    private function newToken(): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return [$token, hash('sha256', $token)];
    }

    private function registrationUrl(string $token): string
    {
        $baseUrl = Configure::read('App.frontendPublicUrl');
        if (!$baseUrl) {
            $baseUrl = Configure::read('debug')
                ? Configure::read('App.frontendOrigin')
                : Configure::read('App.fullBaseUrl');
        }
        $baseUrl = (string)($baseUrl
            ?: Configure::read('App.frontendOrigin')
            ?: Configure::read('App.fullBaseUrl')
            ?: 'http://localhost:5173');
        $baseUrl = rtrim($baseUrl, '/');

        return sprintf('%s/register/%s', $baseUrl, $token);
    }

    private function pendingData(object $member): array
    {
        $expired = $member->token_expires_at !== null
            && $member->token_expires_at->getTimestamp() < time();

        return [
            'id' => $member->id,
            'invited_name' => $member->invited_name,
            'token_status' => $expired ? 'expired' : 'active',
            'token_expires_at' => $member->token_expires_at?->format('Y-m-d H:i:s'),
            'created' => $member->created?->format('Y-m-d H:i:s'),
        ];
    }

    private function completedData(object $member, bool $includeAddress = false): array
    {
        $data = [
            'id' => $member->id,
            'full_name' => $member->full_name,
            'full_name_kana' => $member->full_name_kana,
            'birth_date' => $member->birth_date?->format('Y-m-d'),
            'email' => $member->email,
            'phone' => $member->phone,
            'submitted_at' => $member->submitted_at?->format('Y-m-d H:i:s'),
        ];
        if ($includeAddress) {
            $data += [
                'postal_code' => $member->postal_code,
                'prefecture' => $member->prefecture,
                'city' => $member->city,
                'street_address' => $member->street_address,
                'building' => $member->building,
            ];
        }

        return $data;
    }
}
