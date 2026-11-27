<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;

final class AuthorizeCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => 'required|integer|min:0', 'decision' => 'required|in:approve,return', 'notes' => 'required_if:decision,return|nullable|string|max:4000',
            'first_due_on' => ['exclude_unless:decision,approve', 'required', 'date_format:Y-m-d', 'after:'.now('America/Cancun')->toDateString(), 'before:9999-09-17'],
            'rate_id' => 'exclude_unless:decision,approve|required_with:participants,disbursement_method,repayment_method|uuid',
            'rate_signature' => 'required_with:rate_id|string|size:64',
            'participants' => 'required_with:rate_id|array|min:3',
            'participants.*' => 'required|array:client_id,amount_approved',
            'participants.*.client_id' => 'required|uuid|distinct',
            'participants.*.amount_approved' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'],
            'disbursement_method' => 'required_with:rate_id|in:cash_branch,bank_deposit,bank_transfer',
            'repayment_method' => 'required_with:rate_id|in:cash_branch,bank_deposit,bank_transfer',
            'total_approved' => 'missing', 'approved_snapshot' => 'missing', 'status' => 'missing',
        ];
    }
}
