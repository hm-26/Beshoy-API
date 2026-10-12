<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public const SEVERITIES = ['Low', 'Medium', 'High', 'Critical', 'Review'];

    public function authorize(): bool
    {
        return true; // protected by auth:sanctum + admin middleware
    }

    public function rules(): array
    {
        // POST = create (title/summary mandatory), PUT = partial update is allowed
        $req = $this->isMethod('POST') ? 'required' : 'sometimes|required';
        $text = ['nullable', 'string', 'max:10000'];
        $short = ['nullable', 'string', 'max:255'];

        return [
            'title' => [$req, 'string', 'max:255'],
            'summary' => [$req, 'string', 'max:10000'],
            'severity' => ['nullable', Rule::in(self::SEVERITIES)],
            'platform' => ['nullable', 'string', 'max:60'],
            'programType' => ['nullable', 'string', 'max:60'],
            'bounty' => ['nullable', 'string', 'max:60'],
            'asset' => $short,
            'weakness' => $short,
            'endpoint' => $short,
            'stepsToReproduce' => $text,
            'impact' => $text,
            'recommendation' => $text,
            'references' => $text,

            'request' => ['nullable', 'array'],
            'request.language' => ['nullable', 'string', 'max:30'],
            'request.code' => ['nullable', 'string', 'max:100000'],
            'response' => ['nullable', 'array'],
            'response.language' => ['nullable', 'string', 'max:30'],
            'response.code' => ['nullable', 'string', 'max:100000'],

            'ar' => ['nullable', 'array'],
            'ar.title' => $short,
            'ar.summary' => $text,
            'ar.weakness' => $short,
            'ar.stepsToReproduce' => $text,
            'ar.impact' => $text,
            'ar.recommendation' => $text,
            'ar.references' => $text,

            'published' => ['sometimes', 'boolean'],
        ];
    }

    /** Validated input (camelCase, as the React app sends it) -> database columns (snake_case). */
    public function toModelAttributes(): array
    {
        $map = [
            'platform' => 'platform',
            'programType' => 'program_type',
            'bounty' => 'bounty',
            'asset' => 'asset',
            'weakness' => 'weakness',
            'title' => 'title',
            'summary' => 'summary',
            'severity' => 'severity',
            'stepsToReproduce' => 'steps_to_reproduce',
            'request' => 'request',
            'response' => 'response',
            'references' => 'supporting_references',
            'endpoint' => 'endpoint',
            'impact' => 'impact',
            'recommendation' => 'recommendation',
            'ar' => 'ar',
            'published' => 'published',
        ];

        $data = $this->validated();
        $out = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $out[$column] = $data[$input];
            }
        }
        if (array_key_exists('severity', $out) && $out['severity'] === null) {
            $out['severity'] = 'Review';
        }

        return $out;
    }
}
