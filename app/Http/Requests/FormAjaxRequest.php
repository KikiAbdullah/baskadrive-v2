<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

abstract class FormAjaxRequest extends LaravelFormRequest
{
    /**
     * Extend this class to get JSON return on validation failure.
     */
    abstract public function rules();

    abstract public function authorize();

    protected function failedValidation(Validator $validator)
    {
        $errors = (new ValidationException($validator))->errors();

        $transformed = [];

        foreach ($errors as $field => $message) {
            $transformed = [
                'field' => $field,
                'message' => $message[0],
            ];
        }

        $response = [
            'code' => 422,
            'status' => false,
            'data' => null,
            'message' => $transformed,
        ];

        throw new HttpResponseException(
            response()->json($response, 422)
        );
    }
}
