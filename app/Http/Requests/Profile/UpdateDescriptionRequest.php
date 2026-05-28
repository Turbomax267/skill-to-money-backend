<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiRequest;

class UpdateDescriptionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
