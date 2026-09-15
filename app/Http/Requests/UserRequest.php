<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
     * @return array
     */
    public function rules()
    {
        switch ($this->method()) {
            case 'POST': {
                return [
                    'username' => 'required|unique:users',
                    'email' => 'nullable|email|unique:users,email',
                    'name' => 'required',
                    'password' => 'required|min:8',
                    // S-03: role wajib & harus ID role yang ada
                    'role' => 'required|exists:roles,id',
                ];
            }
            case 'PATCH':
            case 'PUT': {
                return [
                    'name' => 'required',
                    'email' => [Rule::unique('users')->ignore($this->user), 'nullable', 'email'],
                    'role' => 'required|exists:roles,id',
                ];
            }
            default:
                break;
        }
    }
}
