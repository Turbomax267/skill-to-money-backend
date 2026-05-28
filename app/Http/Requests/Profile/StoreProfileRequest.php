<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiRequest;

class StoreProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'headline' => ['nullable', 'string', 'max:140'],
            'category' => ['nullable', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:120'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:60'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'url', 'max:255'],
            'photo_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
