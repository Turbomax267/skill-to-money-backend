<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiRequest;

class UpdateProfilePhotoRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'photo' => ['nullable', 'file', 'max:4096', 'required_without:photo_url'],
            'photo_url' => ['nullable', 'url', 'max:500', 'required_without:photo'],
        ];
    }
}
