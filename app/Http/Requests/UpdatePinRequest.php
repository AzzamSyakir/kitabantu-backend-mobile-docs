<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\ApiResponseHelper;

class UpdatePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'old_pin' => 'required|string|min:4|max:6',
            'new_pin' => 'required|string|min:4|max:6',
            'new_pin_validate' => 'required|string|same:new_pin',
        ];
    }

    public function validateResolved()
    {
        parent::validateResolved();

        $inputFields = array_keys($this->input());
        $fileFields = array_keys($this->allFiles());

        $allRequestFields = array_merge($inputFields, $fileFields);
        $ruleFields = array_keys($this->rules());

        $unexpectedFields = array_diff($allRequestFields, $ruleFields);

        if (!empty($unexpectedFields)) {
            $errors = [];
            foreach ($unexpectedFields as $field) {
                $errors[] = [
                    'field' => $field,
                    'message' => 'field is not allowed',
                ];
            }

            $this->failValidation($errors, 'Request validation failed.');
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = [];

        foreach ($validator->errors()->toArray() as $field => $messages) {
            $errors[] = [
                'field' => $field,
                'message' => $messages[0] ?? 'field is invalid'
            ];
        }

        $this->failValidation($errors, 'Request validation failed.');
    }

    private function failValidation(array $errors, string $message): void
    {
        throw new HttpResponseException(
            ApiResponseHelper::respond(
                ['errors' => $errors],
                $message,
                422
            )
        );
    }
}
