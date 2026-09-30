<?php

namespace App\Http\Requests\Fund;

use App\Models\FundSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FundTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && (! $this->routeIs('fund.transactions.correct') || FundSetting::current()->isTreasurer($this->user()));
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
            'period_ids' => ['sometimes', 'array'],
            'period_ids.*' => ['integer', 'distinct'],
            'installment_ids' => ['sometimes', 'array'],
            'installment_ids.*' => ['integer', 'distinct'],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:10240'],
            'reason' => [$this->routeIs('fund.transactions.correct') ? 'required' : 'nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array{bank_id: int, reference: string, transaction_date: string, amount: string, period_ids: list<int>, installment_ids: list<int>, reason: string} */
    public function transactionData(): array
    {
        return [
            'bank_id' => (int) $this->validated('bank_id'), 'reference' => (string) $this->validated('reference'),
            'transaction_date' => (string) $this->validated('transaction_date'), 'amount' => (string) $this->validated('amount'),
            'period_ids' => array_values(array_map(intval(...), $this->validated('period_ids', []))),
            'installment_ids' => array_values(array_map(intval(...), $this->validated('installment_ids', []))),
            'reason' => (string) $this->validated('reason', ''),
        ];
    }
}
