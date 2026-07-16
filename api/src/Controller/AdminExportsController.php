<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Database\Expression\QueryExpression;
use Cake\Http\Response;
use ZipArchive;

class AdminExportsController extends AppController
{
    public function registrations(?string $eventId = null): Response
    {
        $this->request->allowMethod(['get']);

        if ($unauthorized = $this->requireAdmin()) {
            return $unauthorized;
        }

        if ($eventId !== null && !$this->fetchTable('Events')->exists(['id' => (int)$eventId])) {
            return $this->json(['message' => 'イベントが見つかりません。'], 404);
        }

        $query = $this->fetchTable('InsuranceMembers')
        ->find()
        ->contain(['Events'])
        ->where(fn(QueryExpression $exp): QueryExpression => $exp->isNotNull('InsuranceMembers.submitted_at'))
        ->orderByAsc('InsuranceMembers.id');
        if ($eventId !== null) {
            $query->where(['InsuranceMembers.event_id' => (int)$eventId]);
        }
        $members = $query->all();
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return $this->json(['message' => 'CSVを作成できませんでした。'], 500);
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'ID', 'イベント名', '開催日', '場所', '事前登録名', '氏名', '氏名（フリガナ）', 'メールアドレス', '電話番号',
        '郵便番号', '都道府県', '市区町村', '町名・番地', '建物名・部屋番号',
            '生年月日', '同意規約版', '同意日時', '登録日時',
        ], ',', '"', '');

        foreach ($members as $member) {
            $row = [
                $member->id,
            $member->event?->event_name,
            $member->event?->event_date?->format('Y-m-d'),
            $member->event?->location,
            $member->invited_name,
                $member->full_name,
                $member->full_name_kana,
                $member->email,
                $member->phone,
                $member->postal_code,
                $member->prefecture,
                $member->city,
                $member->street_address,
                $member->building,
                $member->birth_date?->format('Y-m-d'),
                $member->privacy_policy_version,
                $member->consented_at?->format('Y-m-d H:i:s'),
                $member->submitted_at?->format('Y-m-d H:i:s'),
            ];
            fputcsv($stream, array_map($this->safeCsvCell(...), $row), ',', '"', '');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if ($csv === false) {
            return $this->json(['message' => 'CSVを作成できませんでした。'], 500);
        }

        $zipPassword = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
        $baseFilename = sprintf('snick-insurance-%s-%s', $eventId === null ? 'all' : 'event-' . $eventId, date('Ymd-His'));
        $zip = $this->createEncryptedZip($csv, $baseFilename . '.csv', $zipPassword);
        if ($zip === null) {
            return $this->json(['message' => '暗号化ZIPを作成できませんでした。'], 500);
        }

        return $this->response
            ->withHeader('Content-Type', 'application/zip')
            ->withHeader('Content-Disposition', sprintf('attachment; filename="%s.zip"', $baseFilename))
            ->withHeader('Cache-Control', 'no-store')
        ->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('X-Zip-Password', $zipPassword)
        ->withStringBody($zip);
    }

    private function safeCsvCell(mixed $value): string
    {
        $cell = (string)($value ?? '');

        if ($cell !== '' && preg_match('/^[=+\-@\t\r]/u', $cell)) {
            return "'" . $cell;
        }

        return $cell;
    }

    private function createEncryptedZip(string $csv, string $csvFilename, string $password): ?string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'snick-export-');
        if ($temporaryFile === false) {
            return null;
        }

        try {
            $archive = new ZipArchive();
            if ($archive->open($temporaryFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return null;
            }

            $created = $archive->addFromString($csvFilename, $csv)
                && $archive->setEncryptionName($csvFilename, ZipArchive::EM_AES_256, $password)
                && $archive->close();
            if (!$created) {
                return null;
            }

            $contents = file_get_contents($temporaryFile);

            return $contents === false ? null : $contents;
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }
}
