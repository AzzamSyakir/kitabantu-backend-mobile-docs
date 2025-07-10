<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\ApiResponseHelper;

class CreateNegotiationRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'order_id' => 'required|uuid|exists:orders,id',
      'work_start_time' => 'required|date',
      'work_end_time' => 'required|date|after:work_start_time',
      'working_hours_duration' => 'required|numeric',
      'hourly_rate' => 'required|numeric',
      'note' => 'nullable|string',
      'village_id' => 'required|string'
    ];
  }

  protected function prepareForValidation(): void
  {
    $this->unexpectedFields = array_diff(
      array_keys($this->all()),
      array_keys($this->rules())
    );
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

    foreach ($this->unexpectedFields as $field) {
      $errors[] = [
        'field' => $field,
        'message' => 'field is invalid'
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

