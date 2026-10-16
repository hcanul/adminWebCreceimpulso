<?php

namespace App\Http\Requests\Intake;

use App\Domains\Intake\Rules\Curp;
use Illuminate\Foundation\Http\FormRequest;

final class SaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('curp'))) {
            $this->merge(['curp' => Curp::normalize($this->input('curp'))]);
        }
    }

    public function rules(): array
    {
        return [
            'id' => 'required|uuid', 'version' => 'required|integer|min:0', 'team_id' => 'required|uuid',
            'curp' => ['required', 'string', new Curp], 'client_id' => 'nullable|uuid', 'client_version' => 'nullable|integer|min:1',
            'confirm_existing' => 'required|boolean', 'submit' => 'required|boolean', 'fields' => 'required|array|max:72',
            'amount_requested' => 'prohibited',
        ];
    }
}
