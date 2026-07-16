<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

class CreateAdminCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('管理者アカウントを作成し、初回パスワードを一度だけ表示します。')
            ->addArgument('username', ['help' => '管理者ID', 'required' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $username = trim((string)$args->getArgument('username'));
        $password = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $table = $this->fetchTable('AdminUsers');
        $admin = $table->newEntity([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'failed_attempts' => 0,
        ]);

        if (!$table->save($admin)) {
            $io->err('管理者を作成できませんでした。管理者IDの形式または重複を確認してください。');
            return static::CODE_ERROR;
        }

        $io->out('<success>管理者を作成しました。</success>');
        $io->out(sprintf('管理者ID: %s', $username));
        $io->out(sprintf('初回パスワード: %s', $password));
        $io->warning('このパスワードは再表示できません。安全な場所へ保存してください。');
        $io->out('初回ログイン時に認証アプリを登録してください。');

        return static::CODE_SUCCESS;
    }
}
