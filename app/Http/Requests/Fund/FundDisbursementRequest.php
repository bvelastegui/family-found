<?php

namespace App\Http\Requests\Fund;

use App\Models\FundSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FundDisbursementRequest extends FormRequest
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
            'bank_id' => ['required', 'integer', 'exists:banks,id'],
            'reference' => ['required', 'string', 'max:191'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,2})?$/D'],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:10240'],
            'reason' => [$this->routeIs('fund.loans.correct') ? 'required' : 'nullable', 'string', 'max:5000'],
            'monthly_rate' => [$this->routeIs('fund.loans.correct') ? 'required' : 'nullable', 'string', 'regex:/^\d{1,6}(?:\.\d{1,6})?$/D'],
            'term_months' => [$this->routeIs('fund.loans.correct') ? 'required' : 'nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }
}
