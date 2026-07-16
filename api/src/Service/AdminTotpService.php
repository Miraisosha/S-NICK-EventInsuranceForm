<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Utility\Security;
use OTPHP\TOTP;
use RuntimeException;

class AdminTotpService
{
    private const PERIOD = 30;

    public function generateSecret(): string
    {
        return TOTP::generate(secretSize: 32)->getSecret();
    }

    public function provisioningUri(string $secret, string $username): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setIssuer((string)Configure::read('AdminAuth.issuer', 'S-NICK Event Insurance'));
        $totp->setLabel($username);

        return $totp->getProvisioningUri();
    }

    public function matchingCounter(string $secret, string $code, ?int $timestamp = null): ?int
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $timestamp ??= time();
        $totp = TOTP::createFromSecret($secret);
        foreach ([-1, 0, 1] as $offset) {
            $candidateTime = $timestamp + ($offset * self::PERIOD);
            if (hash_equals($totp->at($candidateTime), $code)) {
                return intdiv($candidateTime, self::PERIOD);
            }
        }

        return null;
    }

    public function encryptSecret(string $secret): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($secret, $nonce, $this->encryptionKey());

        return base64_encode($nonce . $ciphertext);
    }

    public function decryptSecret(string $encrypted): string
    {
        $payload = base64_decode($encrypted, true);
        if ($payload === false || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('TOTP secret is invalid.');
        }

        $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $secret = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->encryptionKey());
        if ($secret === false) {
            throw new RuntimeException('TOTP secret could not be decrypted.');
        }

        return $secret;
    }

    /** @return list<string> */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(8)));
            $codes[] = implode('-', str_split($raw, 4));
        }

        return $codes;
    }

    public function recoveryCodeHash(string $code): string
    {
        $normalized = strtoupper((string)preg_replace('/[^A-F0-9]/i', '', $code));

        return hash('sha256', $normalized);
    }

    private function encryptionKey(): string
    {
        $salt = Security::getSalt();
        if ($salt === '') {
            throw new RuntimeException('Security salt is not configured.');
        }

        return sodium_crypto_generichash($salt, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
