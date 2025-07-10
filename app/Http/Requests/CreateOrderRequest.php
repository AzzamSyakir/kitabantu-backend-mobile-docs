<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\ApiResponseHelper;

class CreateOrderRequest extends FormRequest
{
    protected array $unexpectedFields = [];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'freelancer_id' => 'required|uuid',
            'work_start_time' => 'required|date_format:Y-m-d H:i',
            'work_end_time' => 'required|date_format:Y-m-d H:i|after:work_start_time',
            'estimated_travel_time' => 'required|integer|min:0',
            'hourly_rate' => 'required|numeric|min:0',
            'village_id' => 'required|string',
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
