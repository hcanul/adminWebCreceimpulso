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
        return ['id' => 'required|uuid', 'team_id' => 'required|uuid', 'rate_id' => 'required|uuid', 'rate_signature' => 'required|string|size:64', 'member_limit' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'], 'participants' => 'required|array|min:3',
            'participants.*.client_id' => 'required|uuid|distinct', 'participants.*.amount_requested' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'],
            'amount_approved' => 'prohibited', 'total_approved' => 'prohibited', 'participants.*.amount_approved' => 'prohibited', 'repayment_method' => 'prohibited', 'disbursement_method' => 'prohibited', 'status' => 'prohibited', 'folio' => 'prohibited'];
    }
}
