<?php

namespace App\Http\Requests\Fund;

use App\Models\FundSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FundContributionRangeRequest extends FormRequest
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
            'first_month' => ['required', 'date_format:Y-m'],
            'last_month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,2})?$/D'],
        ];
    }
}
