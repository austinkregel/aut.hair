<?php

namespace App\Http\Controllers\Concerns;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;

/**
 * Extract the `jti` (== oauth_access_tokens.id) from a Passport access-token JWT.
 *
 * Mirrors MachineTokenController::extractTokenId — kept here so the GAIA token
 * wrapper can reuse the same robust parser-based extraction.
 */
trait ExtractsTokenId
{
    protected function extractTokenId(string $jwtString): ?string
    {
        try {
            $parser = new Parser(new JoseEncoder());
            $jwt = $parser->parse($jwtString);
            if (! $jwt instanceof Plain) {
                return null;
            }

            $jti = $jwt->claims()->get('jti');

            return is_string($jti) && $jti !== '' ? $jti : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
