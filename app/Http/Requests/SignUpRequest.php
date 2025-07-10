<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\ApiResponseHelper;

class SignUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'full_name' => 'required|string',
            'phone_number' => 'required|string|max:20',
            'nick_name' => 'nullable|string',
            'gender' => 'required|string',
            'villageId' => 'required|string',
            'preferred_service' => 'nullable|in:translator,akuntan,supir,data_analyst,arsitek,teknisi,buruh_harian,catering,tukang,translator,kurir',
            'profile_photo' => 'nullable|file|image|mimes:jpg,jpeg,png',
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
