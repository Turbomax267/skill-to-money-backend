<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiRequest;

class UpdateSocialLinksRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'social_links' => ['required', 'array'],
            'social_links.linkedin' => ['nullable', 'url', 'max:255'],
            'social_links.instagram' => ['nullable', 'url', 'max:255'],
            'social_links.facebook' => ['nullable', 'url', 'max:255'],
            'social_links.x' => ['nullable', 'url', 'max:255'],
            'social_links.website' => ['nullable', 'url', 'max:255'],
        ];
    }
}
