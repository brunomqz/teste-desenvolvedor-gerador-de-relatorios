<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReportBillingRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'date_base' => ['required', 'string', 'in:issue,due,payment'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'status' => ['nullable', 'string', 'in:pending,paid'],
            'sort_by' => ['nullable', 'string', 'in:issue_date,due_date,original_amount'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * Configuração para validar se pago.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('date_base') === 'payment' && $this->input('status') !== 'paid') {
                $validator->errors()->add(
                    'date_base',
                    'Para filtrar por data de pagamento, o filtro de status deve ser "paid".'
                );
            }
        });
    }
}
