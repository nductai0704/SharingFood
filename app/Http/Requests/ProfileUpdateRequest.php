<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Get the validation messages in Vietnamese.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập Họ và tên.',
            'name.max' => 'Họ và tên không được vượt quá 255 ký tự.',
            'email.required' => 'Vui lòng nhập địa chỉ Email.',
            'email.email' => 'Địa chỉ Email không đúng định dạng.',
            'email.unique' => 'Email này đã tồn tại trên hệ thống.',
            'phone.required' => 'Vui lòng nhập Số điện thoại.',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'address.required' => 'Vui lòng nhập Địa chỉ chi tiết.',
            'address.max' => 'Địa chỉ chi tiết không được vượt quá 500 ký tự.',
        ];
    }
}
