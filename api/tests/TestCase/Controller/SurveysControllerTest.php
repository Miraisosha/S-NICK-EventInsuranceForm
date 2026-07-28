<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class SurveysControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fetchTable('SurveyResponses')->deleteAll([]);
        $this->fetchTable('InsuranceMembers')->deleteAll([]);
        $this->fetchTable('Events')->deleteAll([]);
    }

    protected function tearDown(): void
    {
        $this->fetchTable('SurveyResponses')->deleteAll([]);
        $this->fetchTable('InsuranceMembers')->deleteAll([]);
        $this->fetchTable('Events')->deleteAll([]);
        parent::tearDown();
    }

    public function testOptionsAndSubmissionFlow(): void
    {
        [$event, $member] = $this->createParticipant();

        $this->get('/api/surveys/options');
        $this->assertResponseOk();
        $this->assertSame('Survey Event', $this->payload()['events'][0]['name']);

        $this->get('/api/surveys/options?event_id=' . $event->id);
        $this->assertResponseOk();
        $this->assertSame('Survey User', $this->payload()['members'][0]['name']);
        $this->assertFalse($this->payload()['members'][0]['answered']);

        $this->postJson('/api/surveys/responses', $this->responseData($event->id, $member->id));
        $this->assertResponseCode(201);
        $this->assertSame(1, $this->fetchTable('SurveyResponses')->find()->count());

        $this->get('/api/surveys/options?event_id=' . $event->id);
        $this->assertResponseOk();
        $this->assertTrue($this->payload()['members'][0]['answered']);

        $this->postJson('/api/surveys/responses', $this->responseData($event->id, $member->id));
        $this->assertResponseCode(422);
        $this->assertSame(1, $this->fetchTable('SurveyResponses')->find()->count());
    }

    public function testSubmissionRequiresAllMandatoryAnswers(): void
    {
        [$event, $member] = $this->createParticipant();
        $data = $this->responseData($event->id, $member->id);
        unset($data['overall_satisfaction']);

        $this->postJson('/api/surveys/responses', $data);

        $this->assertResponseCode(422);
        $this->assertSame(0, $this->fetchTable('SurveyResponses')->find()->count());
    }

    public function testAdminCanListAndExportResponses(): void
    {
        [$event, $member] = $this->createParticipant();
        $this->postJson('/api/surveys/responses', $this->responseData($event->id, $member->id));
        $this->assertResponseCode(201);

        $this->session(['Admin' => ['id' => 1, 'username' => 'test-admin']]);
        $this->get('/api/admin/surveys');
        $this->assertResponseOk();
        $this->assertSame('Survey User', $this->payload()['responses'][0]['attendee_name']);
        $this->assertSame('とても満足', $this->payload()['responses'][0]['overall_satisfaction']);

        $this->get('/api/admin/surveys.csv');
        $this->assertResponseOk();
        $this->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Survey User', (string)$this->_response->getBody());
    }

    private function createParticipant(): array
    {
        $event = $this->fetchTable('Events')->newEntity([
            'event_name' => 'Survey Event',
            'event_date' => '2026-08-10',
            'location' => 'Survey Court',
        ]);
        $this->fetchTable('Events')->saveOrFail($event);

        $member = $this->fetchTable('InsuranceMembers')->newEmptyEntity();
        foreach ([
            'event_id' => $event->id,
            'invited_name' => 'Survey User',
            'full_name' => 'Survey User',
            'token_hash' => hash('sha256', 'survey-test-token'),
            'submitted_at' => DateTime::now(),
        ] as $field => $value) {
            $member->set($field, $value);
        }
        $this->fetchTable('InsuranceMembers')->saveOrFail($member, ['validate' => false]);

        return [$event, $member];
    }

    private function responseData(int $eventId, int $memberId): array
    {
        return [
            'event_id' => $eventId,
            'insurance_member_id' => $memberId,
            'attendance_days' => '両日参加',
            'overall_satisfaction' => 'とても満足',
            'lesson_satisfaction' => '満足',
            'staff_satisfaction' => 'とても満足',
            'difficulty' => 'ちょうど良かった',
            'training_amount' => 'ちょうど良かった',
            'participation_intent' => 'ぜひ参加したい',
            'participation_reason' => '楽しかったため',
            'best_training' => '基礎練習',
            'improvements' => '',
            'future_training' => 'ゲーム形式',
            'other_comments' => 'ありがとうございました。',
        ];
    }

    private function postJson(string $url, array $data): void
    {
        $this->configRequest(['headers' => ['Content-Type' => 'application/json']]);
        $this->post($url, json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function payload(): array
    {
        return json_decode((string)$this->_response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
