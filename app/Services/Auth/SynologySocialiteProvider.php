<?php

namespace App\Services\Auth;

use GuzzleHttp\RequestOptions;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class SynologySocialiteProvider extends AbstractProvider
{
    public const IDENTIFIER = 'SYNOLOGY';

    protected $scopes = ['user_id'];

    protected function getCodeFields($state = null)
    {
        $fields = [
            'scope' => 'user_id',
            'redirect_uri' => $this->redirectUrl,
            'app_id' => $this->clientId,
            'state' => $state,
        ];

        if ($this->usesState()) {
            $fields['state'] = $state;
        }

        if ($this->usesPKCE()) {
            $fields['code_challenge'] = $this->getCodeChallenge();
            $fields['code_challenge_method'] = $this->getCodeChallengeMethod();
        }

        return array_merge($fields, $this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            env('SYNOLOGY_HOST').'/webman/sso/SSOOauth.cgi',
            $state
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return env('SYNOLOGY_HOST').'/webman/sso/SSOAccessToken.cgi';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {

        dd($token, request()->all());
        $response = $this->getHttpClient()->get(
            $this->getTokenUrl().'?action=exchange&access_token='.$token.'&app_id='.env('SYNOLOGY_CLIENT_ID'),
            [
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                ],
            ]
        );

        dd(
            (string) $response->getBody(),
            request()->all()
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        dd($user);
        return (new User())->setRaw($user)->map([
            'id'    => $user['id'], 'nickname' => null, 'name' => $user['name'],
            'email' => $user['login'], 'avatar' => $user['avatar_url'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }
}