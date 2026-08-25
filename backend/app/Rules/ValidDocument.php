<?php

namespace App\Rules;

use App\Services\DocumentValidationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

class ValidDocument implements ValidationRule
{
    public function __construct(
        private readonly DocumentValidationService $documentValidationService = new DocumentValidationService
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->documentValidationService->isValid($value)) {
            $fail('Valid documents: CPF (11 digits) or CNPJ (14 digits) are required.');
        }
    }
}