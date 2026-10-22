<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // mandatory
            'name'             => ['required', 'string', 'max:120'],
            'mobile'           => ['required', 'regex:/^01[3-9][0-9]{8}$/', 'unique:users,mobile'],
            'village_holding'  => ['required', 'string', 'max:255'],
            'post_office_area' => ['required', 'string', 'max:255'],
            'district_id'      => ['required', 'integer', 'exists:districts,id'],
            'thana_id'         => [
                'required', 'integer',
                // thana must belong to the selected district
                Rule::exists('thanas', 'id')->where('district_id', $this->input('district_id')),
            ],
            'password'         => ['required', 'string', 'min:8', 'max:100'],
            'preferred_language' => ['sometimes', 'in:en,bn'],
            'role' => ['sometimes', 'in:customer,reseller'],

            // optional
            'email'            => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'facebook_id'      => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'             => 'Please enter your name.',
            'mobile.required'           => 'Please enter your mobile number.',
            'mobile.regex'              => 'Enter a valid Bangladesh mobile number (for example, 01712345678).',
            'mobile.unique'             => 'An account already uses this mobile number.',
            'village_holding.required'  => 'Please enter your village or holding.',
            'post_office_area.required' => 'Please enter your area or post office.',
            'district_id.required'      => 'Please select a district.',
            'thana_id.required'         => 'Please select a thana.',
            'thana_id.exists'           => 'The selected thana does not belong to this district.',
            'password.required'         => 'Please enter a password.',
            'password.min'              => 'The password must be at least 8 characters.',
            'password.max'              => 'The password may not exceed 100 characters.',
            'email.email'               => 'Enter a valid email address.',
            'email.unique'              => 'An account already uses this email.',
        ];
    }
}
