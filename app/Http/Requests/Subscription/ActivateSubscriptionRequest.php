<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class ActivateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', 'in:free,premium,pro'],
            'duration' => ['required', 'integer', 'min:1', 'max:12'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan.required' => 'Le plan est obligatoire.',
            'plan.in' => 'Le plan doit être free, premium ou pro.',
            'duration.required' => 'La durée est obligatoire.',
            'duration.min' => 'La durée minimale est 1 mois.',
            'duration.max' => 'La durée maximale est 12 mois.',
            'trial_days.max' => 'La période d\'essai ne peut pas dépasser 30 jours.',
        ];
    }
}
