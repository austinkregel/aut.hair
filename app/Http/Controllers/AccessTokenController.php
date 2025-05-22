<?php

namespace App\Http\Controllers;

use App\Repositories\KeyRepositoryContract;
use Illuminate\Http\JsonResponse;
use Laravel\Passport\Http\Controllers\AccessTokenController as PassportAccessTokenController;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ServerRequestInterface;

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
