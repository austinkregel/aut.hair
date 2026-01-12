<?php

namespace App\Repositories;

use Laravel\Passport\Passport;

class KeyRepository implements KeyRepositoryContract
{
    public function getPublicKeyPem(): string
    {
        return $this->resolveKeyContents('public');
    }

    public function getPrivateKeyPem(): string
    {
        return $this->resolveKeyContents('private');
    }

    /**
     * Resolve a key to PEM contents.
     *
     * Supports:
     * - PEM contents in config/env
     * - file paths (absolute or relative to base_path)
     * - file:// URIs
     *
     * Defaults to Passport's key path when not configured.
     */
    private function resolveKeyContents(string $type): string
    {
        $configKey = 'passport.'.$type.'_key';
        $envKey = 'TEST_OIDC_'.strtoupper($type).'_KEY';

        $raw = (string) config($configKey, '');
        if ($raw === '') {
            $raw = (string) env($envKey, '');
        }

        $raw = str_replace('\\n', "\n", $raw);

        // PEM contents provided directly.
        if ($raw !== '' && str_contains($raw, '-----BEGIN')) {
            return $raw;
        }

        // Default to Passport's key path.
        if ($raw === '') {
            $raw = Passport::keyPath('oauth-'.$type.'.key');
        }

        // Normalize file:// URIs to filesystem paths.
        if (str_starts_with($raw, 'file://')) {
            $raw = substr($raw, strlen('file://'));
        }

        // Normalize relative paths (e.g. storage/oauth-public.key) to absolute.
        if ($raw !== '' && ! str_starts_with($raw, '/')) {
            $raw = base_path($raw);
        }

        return file_get_contents($raw);
    }
}
