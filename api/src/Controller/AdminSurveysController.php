<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

class AdminSurveysController extends AppController
{
    public function index(): Response
    {
        $this->request->allowMethod(['get']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }

        return $this->json(['responses' => array_map(
            $this->responseData(...),
            $this->responseQuery()->all()->toList(),
        )]);
    }

    public function export(): Response
    {
        $this->request->allowMethod(['get']);
        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return $this->json(['message' => 'CSVを作成できませんでした。'], 500);
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            '回答日時', 'イベント名', '開催日', '氏名', '参加日程',
            'イベント全体の満足度', '練習・チーム戦の内容', 'スタッフの対応',
            'スペシャルゲストの満足度', '練習・ゲームの難易度',
            '運動量', 'また参加したいと思いますか',
            'その理由', '特に良かった練習', '改善してほしいこと',
            '今後やってほしい練習', '審判勉強会の満足度（1日目参加の方）',
            'その他ご意見・ご感想',
        ], ',', '"', '');

        foreach ($this->responseQuery()->all() as $item) {
            $data = $this->responseData($item);
            fputcsv($stream, array_map($this->safeCsvCell(...), [
                $data['submitted_at'], $data['event_name'], $data['event_date'],
                $data['attendee_name'], $data['attendance_days'],
                $data['overall_satisfaction'], $data['lesson_satisfaction'],
                $data['staff_satisfaction'], $data['special_guest_satisfaction'],
                $data['difficulty'], $data['training_amount'],
                $data['participation_intent'], $data['participation_reason'],
                $data['best_training'], $data['improvements'], $data['future_training'],
                $data['referee_workshop_feedback'], $data['other_comments'],
            ]), ',', '"', '');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        if ($csv === false) {
            return $this->json(['message' => 'CSVを作成できませんでした。'], 500);
        }

        return $this->response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', sprintf(
                'attachment; filename="snick-survey-%s.csv"',
                date('Ymd-His'),
            ))
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withStringBody($csv);
    }

    private function responseQuery(): object
    {
        $query = $this->fetchTable('SurveyResponses')->find()
            ->contain(['Events'])
            ->orderByDesc('SurveyResponses.submitted_at')
            ->orderByDesc('SurveyResponses.id');
        $eventId = filter_var($this->request->getQuery('event_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($eventId !== false && $eventId !== null) {
            $query->where(['SurveyResponses.event_id' => $eventId]);
        }

        return $query;
    }

    private function responseData(object $item): array
    {
        return [
            'id' => $item->id,
            'submitted_at' => $item->submitted_at?->format('Y-m-d H:i:s'),
            'event_id' => $item->event_id,
            'event_name' => $item->event?->event_name,
            'event_date' => $item->event?->event_date?->format('Y-m-d'),
            'attendee_name' => $item->attendee_name,
            'attendance_days' => $item->attendance_days,
            'overall_satisfaction' => $item->overall_satisfaction,
            'lesson_satisfaction' => $item->lesson_satisfaction,
            'staff_satisfaction' => $item->staff_satisfaction,
            'special_guest_satisfaction' => $item->special_guest_satisfaction,
            'referee_workshop_feedback' => $item->referee_workshop_feedback,
            'difficulty' => $item->difficulty,
            'training_amount' => $item->training_amount,
            'participation_intent' => $item->participation_intent,
            'participation_reason' => $item->participation_reason,
            'best_training' => $item->best_training,
            'improvements' => $item->improvements,
            'future_training' => $item->future_training,
            'other_comments' => $item->other_comments,
        ];
    }

    private function safeCsvCell(mixed $value): string
    {
        $cell = (string)($value ?? '');
        return $cell !== '' && preg_match('/^[=+\-@\t\r]/u', $cell) ? "'" . $cell : $cell;
    }
}
