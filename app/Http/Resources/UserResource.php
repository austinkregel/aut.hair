<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray(Request $request): array
    {
        $now = time();
        $user = [];

        // OIDC required claims
        $user['iss'] = config('app.url'); // Issuer Identifier
        $user['sub'] = (string) $this->id; // Subject Identifier
        $user['aud'] = $request->input('client_id') ?? 'unknown'; // Audience (client_id)
        $user['exp'] = $now + 3600; // Expiration time (1 hour from now)
        $user['iat'] = $now; // Issued at
        $user['auth_time'] = $now; // Authentication time (for password grant, same as iat)

        // OIDC 'name', 'picture', etc.
        if ($request->user()->tokenCan('profile')) {
            $user['name'] = $this->name;
            $user['picture'] = $this->profile_photo_url;
        }
        // OIDC 'email' and 'email_verified'
        if ($request->user()->tokenCan('email')) {
            $user['email'] = $this->email;
            $user['email_verified'] = (bool) $this->email_verified_at;
        }

        // Optionally add more standard OIDC claims as needed
        return $user;
    }
}
