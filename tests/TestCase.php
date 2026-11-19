<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var array{private: string, public: string}|null
     */
    private static ?array $passportKeys = null;

    protected function setUp(): void
    {
        parent::setUp();

        // O Passport assina os JWT com um par de chaves RSA. Em vez de depender de
        // storage/oauth-*.key (não versionado, inexistente no CI), a suíte gera um par
        // em memória, uma vez por processo, e o entrega pela config.
        self::$passportKeys ??= $this->generatePassportKeys();

        config()->set('passport.private_key', self::$passportKeys['private']);
        config()->set('passport.public_key', self::$passportKeys['public']);
    }

    /**
     * @return array{private: string, public: string}
     */
    private function generatePassportKeys(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($key === false) {
            throw new \RuntimeException('Could not generate the RSA key pair for the test suite.');
        }

        openssl_pkey_export($key, $private);
        $details = openssl_pkey_get_details($key);

        if (! is_string($private) || $details === false) {
            throw new \RuntimeException('Could not export the RSA key pair for the test suite.');
        }

        return ['private' => $private, 'public' => $details['key']];
    }
}
