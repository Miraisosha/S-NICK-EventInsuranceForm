<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\AdminUser;
use App\Service\AdminTotpService;
use Cake\Http\Response;
use Cake\I18n\DateTime;

class AdminAuthController extends AppController
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    private AdminTotpService $totp;

    public function initialize(): void
    {
        parent::initialize();
        $this->totp = new AdminTotpService();
    }

    public function csrf(): Response
    {
        $this->request->allowMethod(['get']);

        return $this->json(['csrfToken' => (string)$this->request->getAttribute('csrfToken')]);
    }

    public function sessionStatus(): Response
    {
        $this->request->allowMethod(['get']);
        $session = $this->request->getSession();
        $id = $session->read('Admin.id');
        if (!$id) {
            return $this->json(['authenticated' => false], 401);
        }

        return $this->json([
            'authenticated' => true,
            'user' => ['id' => (int)$id, 'username' => (string)$session->read('Admin.username')],
        ]);
    }

    public function login(): Response
    {
        $this->request->allowMethod(['post']);
        $username = trim((string)$this->request->getData('username'));
        $password = (string)$this->request->getData('password');
        $admins = $this->fetchTable('AdminUsers');
        $admin = $username === '' ? null : $admins->find()->where(['username' => $username])->first();

        if (!$admin instanceof AdminUser) {
            password_verify($password, password_hash('invalid-admin-password', PASSWORD_DEFAULT));
            usleep(250000);
            return $this->authenticationFailed();
        }
        if ($this->isLocked($admin) || !password_verify($password, (string)$admin->password_hash)) {
            if (!$this->isLocked($admin)) {
                $this->recordFailure($admin);
            }
            return $this->authenticationFailed();
        }

        $session = $this->request->getSession();
        $session->renew();
        $session->delete('Admin');
        $session->delete('AdminPending');
        $session->write('AdminPending.id', (int)$admin->id);

        if (!$admin->totp_secret_encrypted) {
            $secret = $this->totp->generateSecret();
            $admin->totp_secret_encrypted = $this->totp->encryptSecret($secret);
            if (!$admins->save($admin)) {
                return $this->json(['message' => '認証アプリの設定を開始できませんでした。'], 500);
            }
        } else {
            $secret = $this->totp->decryptSecret((string)$admin->totp_secret_encrypted);
        }

        if ($admin->totp_confirmed_at === null) {
            return $this->json([
                'requiresSetup' => true,
                'provisioningUri' => $this->totp->provisioningUri($secret, (string)$admin->username),
                'manualKey' => $secret,
            ]);
        }

        return $this->json(['requiresTotp' => true]);
    }

    public function verify(): Response
    {
        $this->request->allowMethod(['post']);
        $pendingId = $this->request->getSession()->read('AdminPending.id');
        if (!$pendingId) {
            return $this->json(['message' => 'ログインを最初からやり直してください。'], 401);
        }

        $admins = $this->fetchTable('AdminUsers');
        $admin = $admins->get((int)$pendingId);
        if ($this->isLocked($admin)) {
            return $this->authenticationFailed();
        }

        $code = trim((string)$this->request->getData('code'));
        $secret = $this->totp->decryptSecret((string)$admin->totp_secret_encrypted);
        $counter = $this->totp->matchingCounter($secret, (string)preg_replace('/\s+/', '', $code));
        $usedRecoveryCode = false;

        if ($counter === null && $admin->totp_confirmed_at !== null) {
            $usedRecoveryCode = $this->consumeRecoveryCode($admin, $code);
        }
        if (($counter === null && !$usedRecoveryCode)
            || ($counter !== null && $admin->last_totp_counter !== null && $counter <= (int)$admin->last_totp_counter)
        ) {
            $this->recordFailure($admin);
            return $this->authenticationFailed('認証コードが正しくありません。');
        }

        $isSetup = $admin->totp_confirmed_at === null;
        $recoveryCodes = [];
        $admin->last_totp_counter = $counter ?? $admin->last_totp_counter;
        if ($admin->totp_confirmed_at === null) {
            $admin->totp_confirmed_at = DateTime::now();
        }
        $admin->failed_attempts = 0;
        $admin->locked_until = null;
        $admin->last_login_at = DateTime::now();

        $admins->getConnection()->transactional(function () use ($admins, $admin, $isSetup, &$recoveryCodes): void {
            $admins->saveOrFail($admin);
            if (!$isSetup) {
                return;
            }

            $recoveryCodes = $this->totp->generateRecoveryCodes();
            $codesTable = $this->fetchTable('AdminRecoveryCodes');
            $entities = array_map(fn (string $code) => $codesTable->newEntity([
                'admin_user_id' => $admin->id,
                'code_hash' => $this->totp->recoveryCodeHash($code),
            ]), $recoveryCodes);
            $codesTable->saveManyOrFail($entities);
        });

        $session = $this->request->getSession();
        $session->renew();
        $session->delete('AdminPending');
        $session->write('Admin', ['id' => (int)$admin->id, 'username' => (string)$admin->username]);

        return $this->json([
            'authenticated' => true,
            'user' => ['id' => (int)$admin->id, 'username' => (string)$admin->username],
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    public function logout(): Response
    {
        $this->request->allowMethod(['post']);
        $this->request->getSession()->destroy();

        return $this->json(['message' => 'ログアウトしました。']);
    }

    private function consumeRecoveryCode(AdminUser $admin, string $code): bool
    {
        $hash = $this->totp->recoveryCodeHash($code);
        $codes = $this->fetchTable('AdminRecoveryCodes');
        $entity = $codes->find()->where([
            'admin_user_id' => $admin->id,
            'code_hash' => $hash,
            'used_at IS' => null,
        ])->first();
        if ($entity === null) {
            return false;
        }

        $entity->used_at = DateTime::now();
        return (bool)$codes->save($entity);
    }

    private function isLocked(AdminUser $admin): bool
    {
        return $admin->locked_until !== null && $admin->locked_until->isFuture();
    }

    private function recordFailure(AdminUser $admin): void
    {
        $admin->failed_attempts = (int)$admin->failed_attempts + 1;
        if ($admin->failed_attempts >= self::MAX_FAILED_ATTEMPTS) {
            $admin->locked_until = DateTime::now()->addMinutes(self::LOCK_MINUTES);
        }
        $this->fetchTable('AdminUsers')->save($admin);
    }

    private function authenticationFailed(string $message = '管理者IDまたはパスワードが正しくありません。'): Response
    {
        return $this->json(['message' => $message], 401);
    }
}
