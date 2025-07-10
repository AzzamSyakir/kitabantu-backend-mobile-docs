<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\ApiResponseHelper;

class CreatePaymentRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            $this->generalRules(),
            $this->methodSpecificRules()
        );
    }

    private function generalRules(): array
    {
        return [
            'freelancer_id' => 'required|uuid',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
        ];
    }

    private function methodSpecificRules(): array
    {
        $method = $this->input('payment_method');

        return match ($method) {
            'card' => [
                'card.card_number' => 'required|string',
                'card.card_cvv' => 'required|string',
                'card.card_exp' => 'required|string',
                'card.card_holder_name' => 'required|string',
                'card.card_address' => 'required|string',
                'card.save_card' => 'nullable|boolean',
            ],
            default => [],
        };
    }


    public function validateResolved()
    {
        parent::validateResolved();

        $inputFields = array_keys($this->input());
        $fileFields = array_keys($this->allFiles());

        $allRequestFields = array_merge($inputFields, $fileFields);
        $ruleFields = collect(array_keys($this->rules()))
            ->map(fn($key) => explode('.', $key)[0])
            ->unique()
            ->toArray();

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