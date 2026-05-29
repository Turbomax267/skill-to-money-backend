<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiRequest;

class UpdateDescriptionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:1000'],
        ];
    }
}
