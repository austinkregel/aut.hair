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
        $user = [];

        // OIDC 'sub' claim: unique identifier for the user (as a string)
        if ($request->user()->tokenCan('openid')) {
            $user['sub'] = (string) auth()->id();
        }
        // OIDC 'name', 'picture', etc.
        if ($request->user()->tokenCan('profile')) {
            $user['name'] = auth()->user()->name;
            $user['picture'] = auth()->user()->profile_photo_url;
        }
        // OIDC 'email' and 'email_verified'
        if ($request->user()->tokenCan('email')) {
            $user['email'] = auth()->user()->email;
            $user['email_verified'] = (bool) auth()->user()->email_verified_at;
        }
        // Optionally add more standard OIDC claims as needed
        return $user;
    }
}
