<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;
use Lcobucci\Clock\SystemClock;

class OidcTokenRevocationController extends Controller
{
    /**
     * RFC 7009-compliant token revocation endpoint (POST /oauth/revoke)
     */
    public function __invoke(Request $request)
    {
        $token = $request->input('token');
        if (!$token) {
            return response()->json(['error' => 'Missing token'], 400);
        }
        try {
            $parser = new Parser(new JoseEncoder());
            $jwt = $parser->parse($token);
            $validator = new Validator();
            $validator->assert(
                $jwt,
                new LooseValidAt(new SystemClock(new \DateTimeZone('UTC'))),
                new SignedWith(
                    new Sha256(),
                    InMemory::file(config('passport.private_key'))
                )
            );
            $jti = $jwt->claims()->get('jti');
            Cache::put('oidc_token_blacklist:' . $jti, true, now()->addDay());
            return response()->json(['revoked' => true], 200);
        } catch (RequiredConstraintsViolated $e) {
            return response()->json(['error' => 'Invalid token: ' . $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid token'], 400);
        }
    }
}

