<?php

namespace App\Repositories;

class KeyRepository implements KeyRepositoryContract
{
    public function getPublicKeyPem(): string
    {
        if (app()->environment('testing')) {
            return env('TEST_OIDC_PUBLIC_KEY', $this->testPublicKey());
        } else {
            $path = base_path('storage/oauth-public.key');
        }

        return file_get_contents($path);
    }

    public function getPrivateKeyPem(): string
    {
        if (app()->environment('testing')) {
            return env('TEST_OIDC_PRIVATE_KEY', $this->testPrivateKey());
        } else {
            $path = base_path('storage/oauth-private.key');
        }

        return file_get_contents($path);
    }

    private function testPublicKey(): string
    {
        return <<<'KEY'
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDEiAfDdc0AGJi/7luWGINuD/7+
+UZ5EONosFVJeFt3PcTJS3BMqzirSolFFIZF5jfXEbnY1r83DNCDOr3LxaVTm160
exa1Jt/EhVsaMNi2fLZKwlVxR7x8D66zCFAaj6MqFmoVoeAQMtk0iA0WIMRqjYOE
oTP0TO+cdLXx+3CXywIDAQAB
-----END PUBLIC KEY-----
KEY;
    }

    private function testPrivateKey(): string
    {
        return <<<'KEY'
-----BEGIN RSA PRIVATE KEY-----
MIICXQIBAAKBgQDEiAfDdc0AGJi/7luWGINuD/7++UZ5EONosFVJeFt3PcTJS3BM
qzirSolFFIZF5jfXEbnY1r83DNCDOr3LxaVTm160exa1Jt/EhVsaMNi2fLZKwlVx
R7x8D66zCFAaj6MqFmoVoeAQMtk0iA0WIMRqjYOEoTP0TO+cdLXx+3CXywIDAQAB
AoGBAKpLXw++ImQxGc1dQc5sKXc5teLoI0lp4rWuHwoMvVJE9idh+NROm4tW7x1Y
SOI9wO00SsRKIYYNP6calKZYpFYuZUCDq5ZPkNcaUVbn8NKiWkjOfE7wsBhb1kKE
n2AFD+a83H8XTur2qxGn8pY/+bexdFv+DE5jBqFaUG2Rgl2BAkEA6WXT14BMWrM8
pvN8MPmMXxMvmFo0xSg6u40qCMgfHdCqkfNNpJBWlAbIYW/W2PASi6DPd7OJbRRs
IOSk5pz50QJBAN5aXoaZySUdkGFUTkOcJCIZy9FHn5Vf3L7hIwrKyYVJZZzKzbwQ
vurIWBLL8GMDIS9ZhDJW60Trw7O3cu6UytUCQQCZ9h5pz50jdK5Zk90un0nLBKBP
n1HULICwhf66A1VpzwuNFuIBqmoeZaZX6mE6xPD58Ll35H0TADaBrZEcD3jpAkB7
1LdystD5nq2WEYLRh1SeDsICoZ6irMUiP+6JGZveHFkNjEcNWef39/C4R2tQeM+c
K91tIbp1KUKf5pFwch5hAkBlZG9wirtmHg56k97X3CJidb8sRP/6IDdnh3oX1N1C
ccahJgN9zeps2sonMSKcwk3Y8ZndKyGKoU9pYNCXS6tl
-----END RSA PRIVATE KEY-----
KEY;
    }
}
