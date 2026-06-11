<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class CreateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'freelancer_profile_id' => 'required_without:mype_profile_id|exists:freelancer_profiles,id',
            'mype_profile_id' => 'required_without:freelancer_profile_id|exists:mype_profiles,id',
            'service_id' => 'nullable|exists:services,id',
            'message' => 'required|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'freelancer_profile_id.required_without' => 'Debes especificar el perfil del freelancer o de la MYPE.',
            'mype_profile_id.required_without' => 'Debes especificar el perfil del freelancer o de la MYPE.',
            'message.required' => 'El mensaje inicial es obligatorio.',
        ];
    }
}
