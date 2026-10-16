<?php

namespace App\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;

final class CloseCashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['id' => 'required|uuid', 'branch_id' => 'required|uuid', 'business_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Cancun')->toDateString()], 'version' => 'required|string|size:64', 'cash_counted' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'], 'notes' => 'nullable|string|max:2000', 'confirmed' => 'required|accepted', 'actor_id' => 'missing', 'cash_total' => 'missing', 'bank_total' => 'missing', 'payment_ids' => 'missing'];
    }
}
