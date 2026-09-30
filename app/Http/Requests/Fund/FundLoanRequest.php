<?php

namespace App\Http\Requests\Fund;

use App\Models\FundSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FundLoanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && FundSetting::current()->isTreasurer($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,2})?$/D'],
            'monthly_rate' => ['required', 'string', 'regex:/^\d{1,6}(?:\.\d{1,6})?$/D'],
            'term_months' => ['required', 'integer', 'min:1', 'max:65535'],
        ];
    }
}
