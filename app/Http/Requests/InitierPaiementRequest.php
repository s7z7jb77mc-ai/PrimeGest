<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitierPaiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan'  => ['required', 'in:premium,pro'],
            'duree' => ['required', 'integer', 'min:1', 'max:12'],
            'devise' => ['required', 'in:USD,CDF'],
        ];
    }
}
