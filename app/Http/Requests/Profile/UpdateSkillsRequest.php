<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiRequest;

class UpdateSkillsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'skills' => ['required', 'array', 'min:1'],
            'skills.*' => ['required', 'string', 'max:60'],
        ];
    }
}
