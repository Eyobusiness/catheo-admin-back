<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'canal' => ['required', 'string', 'in:sms,email,in_app'],
            'destinataire' => ['required', 'string', 'max:255'],
            'sujet' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'statut_envoi' => ['nullable', 'string', 'in:en_attente,envoye,echec'],
            'erreur_message' => ['nullable', 'string'],
            'date_envoi' => ['nullable', 'date'],
        ];
    }
}
