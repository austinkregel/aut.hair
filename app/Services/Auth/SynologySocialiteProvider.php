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
        $token = request()->get('access_token');
        $response = $this->getHttpClient()->get(
            $this->getTokenUrl().'?action=exchange&access_token='.$token.(env('SYNOLOGY_DOMAIN') ? '&domain_name='.env('SYNOLOGY_DOMAIN') : ''),
            [
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                ],
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user)
    {
        // Soooo, synology is pretty weird, they don't actually follow OAuth
        // based standards and want you to use their SSO JSDK.
        $username = $user['data']['user_name'];
        $email = explode('\\', $username);
        $data = [
            'id' => $user['data']['user_id'],
            'username' => $username,
            'email' => end($email).'@'.env('SYNOLOGY_DOMAIN'),
        ];
        return (new User())->setRaw($data)->map([
            'id'    => $data['id'], 'nickname' => $username, 'name' => $username,
            'email' => $data['email'], 'avatar' => $data['avatar_url'] ?? null,
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
