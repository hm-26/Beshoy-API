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

            // Public contact details. Links must be http(s) so a javascript: URL can never reach the site.
            'contact' => ['nullable', 'array'],
            'contact.email' => ['nullable', 'email:rfc', 'max:190'],
            'contact.phone' => ['nullable', 'string', 'regex:/^\+?[0-9()\-\s]{5,30}$/'],
            'contact.whatsapp' => ['nullable', 'string', 'regex:/^\+?[0-9()\-\s]{5,30}$/'],
            'contact.telegram' => ['nullable', 'string', 'regex:/^@?[A-Za-z0-9_]{4,32}$/'],
            'contact.linkedin' => ['nullable', 'url:http,https', 'max:255'],
            'contact.github' => ['nullable', 'url:http,https', 'max:255'],
            'contact.x' => ['nullable', 'url:http,https', 'max:255'],
            'contact.facebook' => ['nullable', 'url:http,https', 'max:255'],
            'contact.instagram' => ['nullable', 'url:http,https', 'max:255'],
            'contact.youtube' => ['nullable', 'url:http,https', 'max:255'],
            'contact.website' => ['nullable', 'url:http,https', 'max:255'],

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
