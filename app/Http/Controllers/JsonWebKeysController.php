<?php

namespace App\Http\Controllers;

use App\Repositories\KeyRepositoryContract;
use Illuminate\Http\JsonResponse;
use phpseclib3\Crypt\Common\AsymmetricKey;
use phpseclib3\Crypt\Common\PublicKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Math\BigInteger;

class JsonWebKeysController extends Controller
{
    private KeyRepositoryContract $keyRepository;

    public function __construct(KeyRepositoryContract $keyRepository)
    {
        $this->keyRepository = $keyRepository;
    }

    public function __invoke(): JsonResponse
    {
        $publicKeyPem = $this->keyRepository->getPublicKeyPem();
        $publicKey = PublicKeyLoader::load($publicKeyPem);

        // ---
        // OIDC JWKS requires:
        //   - modulus (n): the base64url-encoded modulus of the RSA public key
        //   - exponent (e): the base64url-encoded public exponent of the RSA public key
        //   - key ID (kid): a unique identifier for this key (used by clients to select the correct key)
        // ---

        $modulusBase64Url = null; // 'n' in JWKS
        $exponentBase64Url = null; // 'e' in JWKS
        $keyId = 'laravel-passport'; // 'kid' in JWKS

        // Helper to extract modulus and exponent from PKCS1 DER
        [$modulusBase64Url, $exponentBase64Url] = $this->extractModulusExponentFromPublicKey($publicKey);

        $jwks = [
            'keys' => [
                [
                    'kty' => 'RSA', // Key Type
                    'alg' => 'RS256', // Algorithm
                    'use' => 'sig', // Public Key Use: signature
                    'n' => $modulusBase64Url, // Modulus
                    'e' => $exponentBase64Url, // Exponent
                    'kid' => $keyId, // Key ID
                ],
            ],
        ];
        return response()->json($jwks);
    }

    /**
     * Extracts the modulus and exponent from a phpseclib3 RSA public key object.
     * Returns [modulusBase64Url, exponentBase64Url].
     */
    private function extractModulusExponentFromPublicKey(AsymmetricKey $publicKey): array
    {
        // Ensure we have a PublicKey instance
        if (!$publicKey instanceof \phpseclib3\Crypt\Common\PublicKey) {
            throw new \RuntimeException('Key is not a PublicKey instance');
        }
        // Use reflection to access private/protected properties
        $reflection = new \ReflectionClass($publicKey);
        $modulusProp = $reflection->getProperty('modulus');
        $modulusProp->setAccessible(true);
        $modulus = $modulusProp->getValue($publicKey);
        $exponentProp = $reflection->getProperty('publicExponent');
        $exponentProp->setAccessible(true);
        $exponent = $exponentProp->getValue($publicKey);
        $modulusBase64Url = rtrim(strtr(base64_encode($modulus->toBytes()), '+/', '-_'), '=');
        $exponentBase64Url = rtrim(strtr(base64_encode($exponent->toBytes()), '+/', '-_'), '=');
        return [$modulusBase64Url, $exponentBase64Url];
    }
}
