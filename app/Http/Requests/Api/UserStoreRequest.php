<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\DefaultRequest;
use App\Models\User;
use Illuminate\Validation\Rules\{File, Password};

class UserStoreRequest extends DefaultRequest
{
    /**
     * Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:' . User::class],
            'img_url' => ['nullable', File::types(['png', 'jpeg', 'jpg'])],
            'password' => ['required', 'same:confirmation', Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
            'phone' => ['nullable', 'string', 'max:64'],
            'about' => ['nullable', 'string'],
            'lang' => ['nullable', 'string', 'max:2']
        ];
    }
}