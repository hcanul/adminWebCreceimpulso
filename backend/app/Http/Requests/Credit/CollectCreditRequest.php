<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;

final class CollectCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|uuid', 'balance_version' => 'required|string|size:64',
            'amount' => ['required', 'string', 'regex:/^\d{1,9}(?:\.\d{1,2})?$/D'],
            'method' => 'required|in:cash_branch,bank_deposit',
            'bank_name' => 'nullable|string|max:100', 'bank_reference' => 'nullable|string|max:150',
            'deposited_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Cancun')->toDateString()],
            'notes' => 'nullable|string|max:2000', 'confirmed' => 'required|accepted',
            'contributions' => 'required|array|min:1|max:100',
            'contributions.*' => 'required|array:client_id,payer_client_id,amount',
            'contributions.*.client_id' => 'required|uuid', 'contributions.*.payer_client_id' => 'required|uuid',
            'contributions.*.amount' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'],
            'received_on' => 'missing', 'branch_id' => 'missing', 'actor_id' => 'missing',
        ];
    }
}
