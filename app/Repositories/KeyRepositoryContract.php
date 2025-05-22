<?php

namespace App\Repositories;

interface KeyRepositoryContract
{
    /**
     * Get the public key PEM string for encryption/signature verification.
     */
    public function getPublicKeyPem(): string;

    /**
     * Get the private key PEM string for decryption/signature.
     */
    public function getPrivateKeyPem(): string;
}
