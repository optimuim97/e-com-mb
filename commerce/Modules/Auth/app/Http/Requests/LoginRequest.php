<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Volontairement `string` et non `email` : le champ accepte aussi
            // un identifiant court (« souattara »), pour les comptes sans
            // adresse électronique. La clé garde son nom pour ne pas casser
            // les clients existants.
            'email'    => 'required|string|max:255',
            'password' => 'required|string',
            'remember' => 'boolean',
        ];
    }
}
