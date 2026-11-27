<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;

final class CreateCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['id' => 'required|uuid', 'team_id' => 'required|uuid', 'rate_id' => 'sometimes|required|uuid', 'rate_signature' => 'required_with:rate_id|exclude_without:rate_id|string|size:64', 'member_limit' => ['required_with:rate_id', 'exclude_without:rate_id', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'], 'participants' => 'required|array|min:3',
            'participants.*.client_id' => 'required|uuid|distinct', 'participants.*.amount_requested' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'],
            'preparation' => 'sometimes|required|exclude_without:rate_id|array:participants,disbursement_method,repayment_method',
            'preparation.disbursement_method' => 'required_with:preparation|in:cash_branch,bank_deposit',
            'preparation.repayment_method' => 'required_with:preparation|in:cash_branch,bank_deposit',
            'preparation.participants' => 'required_with:preparation|array|min:3',
            'preparation.participants.*' => 'required|array:client_id,amount_proposed',
            'preparation.participants.*.client_id' => 'required|uuid|distinct',
            'preparation.participants.*.amount_proposed' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'],
            'amount_approved' => 'prohibited', 'total_approved' => 'prohibited', 'participants.*.amount_approved' => 'prohibited', 'repayment_method' => 'prohibited', 'disbursement_method' => 'prohibited', 'status' => 'prohibited', 'folio' => 'prohibited'];
    }
}
