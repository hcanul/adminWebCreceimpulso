<?php

namespace App\Http\Requests\Credit;

use App\Domains\Credit\Services\CreditReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'action' => ['required', Rule::in(['save', 'return', 'complete', 'resubmit'])],
            'notes' => ['required_if:action,return,resubmit,complete', 'nullable', 'string', 'max:4000'],
            'checks' => ['required_unless:action,resubmit', 'array', 'max:6000'],
            'checks.*' => ['required', 'array:client_id,key,status,notes'],
            'checks.*.client_id' => ['required', 'uuid'],
            'checks.*.key' => ['required', Rule::in(CreditReview::CHECKS)],
            'checks.*.status' => ['required', Rule::in(['pending', 'verified', 'observed'])],
            'checks.*.notes' => ['required_unless:checks.*.status,pending', 'nullable', 'string', 'max:2000'],
        ];
    }
}
