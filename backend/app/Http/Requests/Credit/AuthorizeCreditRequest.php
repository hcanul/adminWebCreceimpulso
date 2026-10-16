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
            'version' => 'required|integer|min:0', 'decision' => 'required|in:approve,return', 'notes' => 'required|string|max:4000',
            'first_due_on' => ['exclude_unless:decision,approve', 'required', 'date_format:Y-m-d', 'after:'.now('America/Cancun')->toDateString(), 'before:9999-09-17'],
            'participants' => 'missing', 'total_approved' => 'missing', 'approved_snapshot' => 'missing',
            'disbursement_method' => 'missing', 'repayment_method' => 'missing', 'status' => 'missing',
        ];
    }
}
