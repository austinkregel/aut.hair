<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray(Request $request): array
    {
        $user = [];

        if ($request->user()->tokenCan('openid')) {
            $user['id'] = auth()->id();
            $user['updated_at'] = auth()->user()->updated_at;
            $user['created_at'] = auth()->user()->created_at;
        }
        if ($request->user()->tokenCan('profile')) {
            $user['photo_url'] = auth()->user()->profile_photo_url;
            $user['name'] = auth()->user()->name;
        }

        if ($request->user()->tokenCan('email')) {
            $user['email'] = auth()->user()->email;
            $user['email_verified_at'] = auth()->user()->eamil_verified_at;
        }

        return $user;
    }
}
