<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

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
        $event = $this->createEvent();

        $this->get('/api/surveys/options');
        $this->assertResponseOk();
        $this->assertSame('Survey Event', $this->payload()['events'][0]['name']);

        $this->postJson('/api/surveys/responses', $this->responseData($event->id, '  Survey User  '));
        $this->assertResponseCode(201);
        $this->assertSame(1, $this->fetchTable('SurveyResponses')->find()->count());
        $this->assertSame(
            'Survey User',
            $this->fetchTable('SurveyResponses')->find()->firstOrFail()->attendee_name,
        );
    }

    public function testSubmissionRequiresAllMandatoryAnswers(): void
    {
        $event = $this->createEvent();
        $data = $this->responseData($event->id);
        unset($data['event_enjoyment']);

        $this->postJson('/api/surveys/responses', $data);

        $this->assertResponseCode(422);
        $this->assertSame(0, $this->fetchTable('SurveyResponses')->find()->count());
    }

    public function testSubmissionAllowsEmptyAttendeeName(): void
    {
        $event = $this->createEvent();
        $data = $this->responseData($event->id, '   ');

        $this->postJson('/api/surveys/responses', $data);

        $this->assertResponseCode(201);
        $this->assertSame('', $this->fetchTable('SurveyResponses')->find()->firstOrFail()->attendee_name);
    }

    public function testAdminCanListAndExportResponses(): void
    {
        $event = $this->createEvent();
        $this->postJson('/api/surveys/responses', $this->responseData($event->id));
        $this->assertResponseCode(201);

        $this->session(['Admin' => ['id' => 1, 'username' => 'test-admin']]);
        $this->get('/api/admin/surveys');
        $this->assertResponseOk();
        $this->assertSame('Survey User', $this->payload()['responses'][0]['attendee_name']);
        $this->assertSame('とても楽しかった', $this->payload()['responses'][0]['event_enjoyment']);
        $this->assertSame('とても満足', $this->payload()['responses'][0]['overall_satisfaction']);
        $this->assertSame('満足', $this->payload()['responses'][0]['special_guest_satisfaction']);
        $this->assertSame('勉強になりました。', $this->payload()['responses'][0]['referee_workshop_feedback']);

        $this->get('/api/admin/surveys.csv');
        $this->assertResponseOk();
        $this->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Survey User', (string)$this->_response->getBody());
    }

    private function createEvent(): object
    {
        $event = $this->fetchTable('Events')->newEntity([
            'event_name' => 'Survey Event',
            'event_date' => '2026-08-10',
            'location' => 'Survey Court',
        ]);
        $this->fetchTable('Events')->saveOrFail($event);

        return $event;
    }

    private function responseData(int $eventId, string $attendeeName = 'Survey User'): array
    {
        return [
            'event_id' => $eventId,
            'attendee_name' => $attendeeName,
            'attendance_days' => '両日参加',
            'event_enjoyment' => 'とても楽しかった',
            'overall_satisfaction' => 'とても満足',
            'lesson_satisfaction' => '満足',
            'staff_satisfaction' => 'とても満足',
            'special_guest_satisfaction' => '満足',
            'referee_workshop_feedback' => '勉強になりました。',
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
