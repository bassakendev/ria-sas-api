<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan' => 'nullable|in:free,pro',
            'planId' => 'nullable|in:free,pro',
            'billingPeriod' => 'nullable|in:month,year',
            'effectiveDate' => 'nullable|date_format:Y-m-d',
            'reason' => 'nullable|string|max:500',
            'feedback' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Get custom error messages for validator rules.
     */
    public function messages(): array
    {
        return [
            'plan.in' => 'Le plan doit être "free" ou "pro"',
            'planId.in' => 'Le plan doit être "free" ou "pro"',
            'billingPeriod.in' => 'La période de facturation doit être "month" ou "year"',
            'effectiveDate.date_format' => 'La date doit être au format YYYY-MM-DD',
            'reason.max' => 'La raison ne peut pas dépasser 500 caractères',
            'feedback.max' => 'Le feedback ne peut pas dépasser 2000 caractères',
        ];
    }

    /**
     * Alias getPlanId() that works with both 'plan' and 'planId' parameters.
     */
    public function getPlan(): ?string
    {
        return $this->input('plan') ?? $this->input('planId');
    }
}
