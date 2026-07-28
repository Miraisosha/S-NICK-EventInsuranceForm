<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Service\AdminTotpService;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use OTPHP\TOTP;

class AdminAuthControllerTest extends TestCase
{
    use IntegrationTestTrait;

    private const USERNAME = 'test-admin';
    private const PASSWORD = 'Test-password-2026!';
    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    private string $csrfToken = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->csrfToken = '';
        Security::setSalt('test-only-admin-totp-encryption-salt');
        $this->fetchTable('AdminRecoveryCodes')->deleteAll([]);
        $this->fetchTable('AdminUsers')->deleteAll([]);
    }

    protected function tearDown(): void
    {
        $this->fetchTable('AdminRecoveryCodes')->deleteAll([]);
        $this->fetchTable('AdminUsers')->deleteAll([]);
        parent::tearDown();
    }

    public function testConfirmedAdminCanLoginWithUsernameAndTotpCode(): void
    {
        $this->createAdmin(true);

        $this->postJson('/api/admin/auth/code-login', [
            'username' => self::USERNAME,
            'code' => TOTP::createFromSecret(self::SECRET)->now(),
        ]);
        $this->assertResponseOk();
        $this->assertResponseContains('"authenticated":true');

        $admin = $this->fetchTable('AdminUsers')->find()->where(['username' => self::USERNAME])->firstOrFail();
        $this->session(['Admin' => ['id' => (int)$admin->id, 'username' => self::USERNAME]]);
        $this->get('/api/admin/events');
        $this->assertResponseOk();
    }

    public function testConfirmedAdminCannotUsePasswordLoginAgain(): void
    {
        $this->createAdmin(true);

        $this->postJson('/api/admin/auth/login', [
            'username' => self::USERNAME,
            'password' => self::PASSWORD,
        ]);

        $this->assertResponseCode(409);
        $this->assertResponseContains('管理者IDと認証コードでログインしてください');
    }

    public function testUnconfirmedAdminCannotUseUsernameAndCodeLogin(): void
    {
        $this->createAdmin(false);

        $this->postJson('/api/admin/auth/code-login', [
            'username' => self::USERNAME,
            'code' => '123456',
        ]);

        $this->assertResponseCode(401);
    }

    public function testWrongUsernameIsRejected(): void
    {
        $this->createAdmin(true);

        $this->postJson('/api/admin/auth/code-login', [
            'username' => 'unknown-admin',
            'code' => TOTP::createFromSecret(self::SECRET)->now(),
        ]);

        $this->assertResponseCode(401);
    }

    public function testFirstLoginReturnsSetupAndRecoveryCodes(): void
    {
        $this->createAdmin(false);

        $this->postJson('/api/admin/auth/login', [
            'username' => self::USERNAME,
            'password' => self::PASSWORD,
        ]);
        $this->assertResponseOk();
        $payload = json_decode((string)$this->_response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['requiresSetup']);
        $this->assertStringStartsWith('otpauth://totp/', $payload['provisioningUri']);

        $admin = $this->fetchTable('AdminUsers')->find()->where(['username' => self::USERNAME])->firstOrFail();
        $this->session(['AdminPending' => ['id' => (int)$admin->id]]);
        $code = TOTP::createFromSecret($payload['manualKey'])->now();
        $this->postJson('/api/admin/auth/verify', ['code' => $code]);
        $this->assertResponseOk();
        $verified = json_decode((string)$this->_response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(8, $verified['recoveryCodes']);
        $this->assertSame(8, $this->fetchTable('AdminRecoveryCodes')->find()->count());
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->createAdmin(true);

        $this->postJson('/api/admin/auth/login', [
            'username' => self::USERNAME,
            'password' => 'wrong-password',
        ]);

        $this->assertResponseCode(401);
        $admin = $this->fetchTable('AdminUsers')->find()->where(['username' => self::USERNAME])->firstOrFail();
        $this->assertSame(1, $admin->failed_attempts);
    }

    private function createAdmin(bool $totpConfirmed): void
    {
        $service = new AdminTotpService();
        $table = $this->fetchTable('AdminUsers');
        $table->saveOrFail($table->newEntity([
            'username' => self::USERNAME,
            'password_hash' => password_hash(self::PASSWORD, PASSWORD_DEFAULT),
            'totp_secret_encrypted' => $totpConfirmed ? $service->encryptSecret(self::SECRET) : null,
            'totp_confirmed_at' => $totpConfirmed ? DateTime::now() : null,
            'failed_attempts' => 0,
        ]));
    }

    private function postJson(string $url, array $data): void
    {
        if ($this->csrfToken === '') {
            $this->get('/api/admin/auth/csrf');
            $payload = json_decode((string)$this->_response->getBody(), true, flags: JSON_THROW_ON_ERROR);
            $this->csrfToken = $payload['csrfToken'];
            $csrfCookie = $this->_response->getCookieCollection()->get('csrfToken');
            $this->cookie('csrfToken', $csrfCookie->getValue());
        }
        $this->configRequest(['headers' => [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => $this->csrfToken,
        ]]);
        $this->post($url, json_encode($data, JSON_THROW_ON_ERROR));
        $this->session($this->_requestSession?->read() ?? []);
        foreach ($this->_response->getCookieCollection() as $responseCookie) {
            $this->cookie($responseCookie->getName(), $responseCookie->getValue());
        }
    }
}
