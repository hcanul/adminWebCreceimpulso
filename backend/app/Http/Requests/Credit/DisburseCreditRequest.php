<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;

final class DisburseCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|uuid', 'authorization_version' => 'required|integer|min:1',
            'disbursed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Cancun')->toDateString()],
            'first_due_on' => 'required|date_format:Y-m-d|after:disbursed_on|before:9999-09-17',
            'bank_name' => 'nullable|string|max:100', 'bank_reference' => 'nullable|string|max:150',
            'notes' => 'required|string|max:4000', 'confirmed' => 'required|accepted',
            'capital' => 'missing', 'net_delivered' => 'missing', 'guarantee_retained' => 'missing',
            'method' => 'missing', 'schedule' => 'missing',
        ];
    }
}
