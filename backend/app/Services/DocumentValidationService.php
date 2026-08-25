<?php

namespace App\Services;

class DocumentValidationService
{
    public function isValid(string $document): bool
    {
        $document = $this->onlyDigits($document);
        $length = strlen($document);

        if (! in_array($length, [11, 14], true)) {
            return false;
        }

        if (preg_match('/^(\d)\1*$/', $document)) {
            return false;
        }

        return true;
    }

    public function onlyDigits(string $document): string
    {
        return preg_replace('/\D/', '', $document);
    }
}