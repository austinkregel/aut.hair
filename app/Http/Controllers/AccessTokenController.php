<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\AccessTokenController as PassportAccessTokenController;
use Laravel\Passport\TokenRepository;
use App\Repositories\KeyRepositoryContract;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ServerRequestInterface;
use Nyholm\Psr7\Response as Psr7Response;
use Illuminate\Http\JsonResponse;
use Lcobucci\JWT\Signer\Rsa\Sha256;

class AccessTokenController extends PassportAccessTokenController
{
    protected KeyRepositoryContract $keyRepository;

    public function __construct(
        AuthorizationServer $server,
        TokenRepository $tokens,
        KeyRepositoryContract $keyRepository
    ) {
        parent::__construct($server, $tokens);
        $this->keyRepository = $keyRepository;
    }

    
    public function issueToken(ServerRequestInterface $request)
    {
        return $this->withErrorHandling(function () use ($request) {
            $psrResponse = $this->server->respondToAccessTokenRequest($request, new Psr7Response);
            $laravelResponse = $this->convertResponse($psrResponse);
            $data = json_decode($laravelResponse->getContent(), true);

            return new JsonResponse($data, $laravelResponse->getStatusCode(), $laravelResponse->headers->all());
        });
    }
}
