<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;
use function Cake\Core\env;

class CreateInvitationCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('保険加入者の個別登録URLを発行します。')
            ->addArgument('name', ['help' => '招待する方の氏名', 'required' => true])
            ->addOption('event-id', ['help' => '招待するイベントID', 'required' => true])
            ->addOption('days', ['help' => 'URLの有効日数', 'default' => 30]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $name = trim((string)$args->getArgument('name'));
        $eventId = filter_var($args->getOption('event-id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $days = max(1, (int)$args->getOption('days'));

        if (
            $eventId === false || !$this->fetchTable('Events')->exists([
            'id' => $eventId,
            'deleted_at IS' => null,
            ])
        ) {
            $io->err('指定したイベントが見つかりません。');

            return static::CODE_ERROR;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $table = $this->fetchTable('InsuranceMembers');
        $member = $table->newEmptyEntity();
        $member->set('invited_name', $name);
        $member->set('event_id', $eventId);
        $member->set('token_hash', hash('sha256', $token));
        $member->set('token_expires_at', DateTime::now()->addDays($days));

        if (!$table->save($member)) {
            $io->err('URLを発行できませんでした。');

            return static::CODE_ERROR;
        }

        $baseUrl = rtrim((string)env('FRONTEND_PUBLIC_URL', 'http://localhost:5173'), '/');
        $io->out('<success>登録URLを発行しました。</success>');
        $io->out(sprintf('%s/register/%s', $baseUrl, $token));

        return static::CODE_SUCCESS;
    }
}
