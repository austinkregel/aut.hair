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
        $claims = [
            'sub' => (string) $this->id,
        ];

        if ($request->user()->tokenCan('profile')) {
            $claims['name'] = $this->name;
            $claims['picture'] = $this->profile_photo_url;
        }

        if ($request->user()->tokenCan('email')) {
            $claims['email'] = $this->email;
            $claims['email_verified'] = (bool) $this->email_verified_at;
        }

        return $claims;
    }
}
