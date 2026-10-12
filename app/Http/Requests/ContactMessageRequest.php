<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CleansInput;
use Illuminate\Foundation\Http\FormRequest;

/** A message typed into the public contact form ("send through the website"). */
class ContactMessageRequest extends FormRequest
{
    use CleansInput;

    public function authorize(): bool
    {
        return true; // public endpoint, protected by throttling + honeypot
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9()\-\s]{5,30}$/'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:5', 'max:3000'],
            // Honeypot: real visitors never see or fill this field; bots usually do. Not validated on purpose:
            // the controller answers a bot with a normal "ok" instead of an error it could learn from.
            'hp' => ['nullable', 'string', 'max:200'],
        ];
    }
}
