<?php

namespace App\Repositories;

class KeyRepository implements KeyRepositoryContract
{
    public function getPublicKeyPem(): string
    {
        if (app()->environment('testing')) {
            $path = base_path('tests/Feature/test-public.key');
        } else {
            $path = base_path('storage/oauth-public.key');
        }

        return file_get_contents($path);
    }

    public function getPrivateKeyPem(): string
    {
        if (app()->environment('testing')) {
            $path = base_path('tests/Feature/test-private.key');
        } else {
            $path = base_path('storage/oauth-private.key');
        }

        return file_get_contents($path);
    }
}
