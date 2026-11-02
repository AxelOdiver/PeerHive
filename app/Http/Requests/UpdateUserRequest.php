<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        if ($this->routeIs('profile.update') && $this->boolean('course_unlisted')) {
            $other = $this->input('course_other');
            $this->merge(['course' => is_string($other) ? trim($other) : $other]);
        }
        if (is_string($this->input('username'))) {
            $this->merge(['username' => strtolower(trim($this->input('username')))]);
        }
        if ($this->routeIs('profile.update') && is_string($this->input('learning_interests'))) {
            $this->merge(['learning_interests' => array_values(array_unique(array_filter(array_map('trim', explode(',', $this->input('learning_interests'))))))]);
        }
        if ($this->routeIs('profile.update') && is_string($this->input('expertise'))) {
            $this->merge(['expertise' => array_values(array_unique(array_filter(array_map('trim', explode(',', $this->input('expertise'))), fn ($label) => $label !== '')))]);
        }
    }

    public function rules(): array
    {
        $user = $this->route('user') ?? $this->user();
        $topicRule = function ($attribute, $value, $fail) use ($user) {
            $field = str_starts_with($attribute, 'learning_interests') ? 'learning_interests' : 'expertise';
            if (!in_array($value, $user?->{$field} ?? [], true) && !\App\Models\CatalogEntry::approved()->where('kind', 'topic')->where('name', $value)->exists()) $fail('Choose an approved catalog topic or suggest a missing topic.');
        };

        return [
            'username' => ['nullable', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_]+$/', Rule::unique('users', 'username')->ignore($user?->id)],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'description' => ['nullable', 'string', 'max:200'],
            'learning_interests' => ['nullable', 'array', 'max:10'],
            'learning_interests.*' => ['bail', 'required', 'string', 'max:100', 'distinct', $topicRule],
            'course' => ['bail', 'nullable', 'string', 'max:120', function ($attribute, $value, $fail) use ($user) {
                if ($this->routeIs('profile.update') && !$this->boolean('course_unlisted') && $value !== $user?->course && !\App\Models\CatalogEntry::approved()->where('kind', 'program')->where('name', $value)->exists()) $fail('Choose a degree program or select Other / Not listed.');
            }],
            'course_unlisted' => ['sometimes', 'boolean'],
            'course_other' => ['required_if:course_unlisted,1', 'nullable', 'string', 'max:120'],
            'expertise' => ['nullable', 'array', 'max:10'],
            'expertise.*' => ['bail', 'required', 'string', 'max:100', 'distinct', $topicRule],
            'school' => ['nullable', 'string', 'max:160'],
            'school_location' => ['nullable', 'string', 'max:160'],
            'show_course' => ['sometimes', 'boolean'],
            'show_expertise' => ['sometimes', 'boolean'],
            'show_school' => ['sometimes', 'boolean'],
            'show_school_location' => ['sometimes', 'boolean'],
            'profile_picture' => ['nullable', 'image', 'max:5120'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Z])(?=.*[0-9])(?=.*[^a-zA-Z0-9]).+$/'],
        ];
    }
}
