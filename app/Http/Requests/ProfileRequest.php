<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CleansInput;
use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    use CleansInput;

    public function authorize(): bool
    {
        return true; // protected by auth:sanctum + admin middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'location' => ['nullable', 'string', 'max:120'],
            'skills' => ['nullable', 'array', 'max:40'],
            'skills.*' => ['string', 'max:80'],

            'ar' => ['nullable', 'array'],
            'ar.name' => ['nullable', 'string', 'max:120'],
            'ar.role' => ['nullable', 'string', 'max:120'],
            'ar.bio' => ['nullable', 'string', 'max:3000'],
            'ar.location' => ['nullable', 'string', 'max:120'],
            'ar.skills' => ['nullable', 'array', 'max:40'],
            'ar.skills.*' => ['string', 'max:80'],
        ];
    }
}
