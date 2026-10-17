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
            'learning_interests.*' => ['required', 'string', 'max:100', 'distinct'],
            'course' => ['nullable', 'string', 'max:120'],
            'expertise' => ['nullable', 'array', 'max:10'],
            'expertise.*' => ['required', 'string', 'max:40', 'distinct'],
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
