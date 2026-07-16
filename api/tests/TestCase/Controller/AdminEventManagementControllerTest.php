<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class AdminEventManagementControllerTest extends TestCase
{
    use IntegrationTestTrait;

    private string $csrfToken = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fetchTable('InsuranceMembers')->deleteAll([]);
        $this->fetchTable('Events')->deleteAll([]);
        $this->session(['Admin' => ['id' => 1, 'username' => 'test-admin']]);
    }

    protected function tearDown(): void
    {
        $this->fetchTable('InsuranceMembers')->deleteAll([]);
        $this->fetchTable('Events')->deleteAll([]);
        parent::tearDown();
    }

    public function testEventCrudUsesSoftDelete(): void
    {
        $event = $this->createEvent(100, [
            'event_name' => 'Test Event',
            'event_date' => '2026-08-01',
            'location' => 'Test Hall',
        ]);

        $this->requestJson('put', '/api/admin/events/' . $event->id, [
            'event_name' => 'Updated Event',
            'event_date' => '2026-08-02',
            'location' => 'Updated Hall',
        ]);
        $this->assertResponseOk();
        $this->assertSame('Updated Event', $this->payload()['event']['event_name']);

        $this->requestJson('delete', '/api/admin/events/' . $event->id);
        $this->assertResponseOk();
        $stored = $this->fetchTable('Events')->get($event->id);
        $this->assertNotNull($stored->deleted_at);

        $this->get('/api/admin/events');
        $this->assertResponseOk();
        $this->assertSame([], $this->payload()['events']);
    }

    public function testPendingMemberIssueAndReissueReturnsUrlOnce(): void
    {
        $event = $this->createEvent(101, [
            'event_name' => 'Invitation Event',
            'event_date' => '2026-08-03',
            'location' => 'Court',
        ]);

        $this->requestJson('post', '/api/admin/events/' . $event->id . '/pending', [
            'name' => '山田 太郎',
            'days' => 30,
        ]);
        $this->assertResponseCode(201);
        $issued = $this->payload();
        $this->assertStringStartsWith('http://localhost:5173/register/', $issued['url']);
        $memberId = $issued['member']['id'];
        $oldHash = $this->fetchTable('InsuranceMembers')->get($memberId)->token_hash;

        $this->requestJson('post', sprintf('/api/admin/events/%d/pending/%d/reissue', $event->id, $memberId), [
            'days' => 7,
        ]);
        $this->assertResponseOk();
        $this->assertNotSame($oldHash, $this->fetchTable('InsuranceMembers')->get($memberId)->token_hash);

        $this->get('/api/admin/events/' . $event->id . '/pending');
        $this->assertResponseOk();
        $this->assertCount(1, $this->payload()['members']);
        $this->assertArrayNotHasKey('url', $this->payload()['members'][0]);
    }

    public function testEventExportReturnsOneTimePasswordHeader(): void
    {
        $event = $this->createEvent(102, [
            'event_name' => 'Export Event',
            'event_date' => '2026-08-04',
            'location' => 'Export Hall',
        ]);
        $table = $this->fetchTable('InsuranceMembers');
        $member = $table->newEmptyEntity();
        foreach (
            [
            'id' => 200,
            'event_id' => $event->id,
            'invited_name' => 'Export User',
            'full_name' => 'Export User',
            'token_hash' => hash('sha256', 'export-test-token'),
            'submitted_at' => DateTime::now(),
            ] as $field => $value
        ) {
            $member->set($field, $value);
        }
        $table->saveOrFail($member, ['validate' => false]);

        $this->get('/api/admin/events/' . $event->id . '/registrations.zip');
        $this->assertResponseOk();
        $this->assertHeader('Content-Type', 'application/zip');
        $this->assertNotSame('', $this->_response->getHeaderLine('X-Zip-Password'));
        $this->assertStringStartsWith('PK', (string)$this->_response->getBody());
    }

    private function requestJson(string $method, string $url, array $data = []): void
    {
        if ($this->csrfToken === '') {
            $this->get('/api/admin/auth/csrf');
            $this->csrfToken = $this->payload()['csrfToken'];
            $csrfCookie = $this->_response->getCookieCollection()->get('csrfToken');
            $this->cookie('csrfToken', $csrfCookie->getValue());
        }
        $this->configRequest(['headers' => [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => $this->csrfToken,
        ]]);
        $this->{$method}($url, json_encode($data, JSON_THROW_ON_ERROR));
        $this->session(['Admin' => ['id' => 1, 'username' => 'test-admin']]);
    }

    private function createEvent(int $id, array $data): object
    {
        $table = $this->fetchTable('Events');
        $event = $table->newEntity($data);
        $event->set('id', $id);

        return $table->saveOrFail($event);
    }

    private function payload(): array
    {
        return json_decode((string)$this->_response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
