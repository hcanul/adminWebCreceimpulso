<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;

final class ReversePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['id' => 'required|uuid', 'balance_version' => 'required|string|size:64', 'reason' => 'required|string|min:10|max:2000', 'confirmed' => 'required|accepted', 'amount' => 'missing', 'actor_id' => 'missing', 'branch_id' => 'missing'];
    }
}
