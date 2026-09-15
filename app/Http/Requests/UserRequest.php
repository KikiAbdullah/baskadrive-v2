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
        // S-09: resolve user yang sedang diedit dengan benar (jangan pakai admin login)
        $editingUserId = $this->route('user') ?? $this->route('id') ?? ($this->user ? $this->user->id : null);
        // normalize when route model binding gives User model
        if ($editingUserId instanceof \App\Models\User) {
            $editingUserId = $editingUserId->id;
        } elseif (is_object($editingUserId) && method_exists($editingUserId, 'getKey')) {
            $editingUserId = $editingUserId->getKey();
        }

        switch ($this->method()) {
            case 'POST': {
                return [
                    'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
                    'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
                    'name' => ['required', 'string', 'max:255'],
                    'password' => ['required', 'string', 'min:8', 'max:255'],
                    'role' => ['required', 'exists:roles,id'],
                    'nowa' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
                ];
            }
            case 'PATCH':
            case 'PUT': {
                return [
                    'name' => ['required', 'string', 'max:255'],
                    'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($editingUserId)],
                    'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editingUserId)],
                    'password' => ['nullable', 'string', 'min:8', 'max:255'],
                    'role' => ['required', 'exists:roles,id'],
                    'nowa' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
                    'deleted_at_baru' => ['nullable', 'in:0,1'],
                ];
            }
            default:
                break;
        }
    }
}
