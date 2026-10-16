<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;

final class ProposeCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => 'required|integer|min:0', 'notes' => 'required|string|max:4000',
            'disbursement_method' => 'required|in:cash_branch,bank_deposit',
            'repayment_method' => 'required|in:cash_branch,bank_deposit',
            'participants' => 'required|array|min:3',
            'participants.*' => 'required|array:client_id,amount_proposed',
            'participants.*.client_id' => 'required|uuid|distinct',
            'participants.*.amount_proposed' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'],
            'total_approved' => 'missing', 'approved_snapshot' => 'missing', 'rate_id' => 'missing', 'status' => 'missing',
        ];
    }
}
