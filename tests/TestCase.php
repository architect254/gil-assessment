<?php

namespace Tests;

use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Session\Middleware\AuthenticateSession;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('openssl_cipher_iv_length')) {
            $this->withoutMiddleware(EncryptCookies::class);
            $this->withoutMiddleware(AuthenticateSession::class);

            $this->app->bind(EncrypterContract::class, function () {
                return new class implements EncrypterContract
                {
                    public function encrypt($value, $serialize = true)
                    {
                        return base64_encode($serialize ? serialize($value) : (string) $value);
                    }

                    public function decrypt($payload, $unserialize = true)
                    {
                        $data = base64_decode($payload);

                        return $unserialize ? unserialize($data) : $data;
                    }

                    public function encryptString($value)
                    {
                        return base64_encode($value);
                    }

                    public function decryptString($payload)
                    {
                        return base64_decode($payload);
                    }

                    public function getKey()
                    {
                        return 'base64:testing-key-123456789012345678901234567890=';
                    }

                    public function getAllKeys()
                    {
                        return [$this->getKey()];
                    }

                    public function getPreviousKeys()
                    {
                        return [];
                    }
                };
            });
        }
    }
}
