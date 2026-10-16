<?php

namespace App\Domains\Intake\Services;

use App\Domains\Intake\Rules\Curp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ApplicationFields
{
    public function schema(): array
    {
        return json_decode(file_get_contents(resource_path('intake-fields.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function validate(array $fields, bool $submit): array
    {
        $rules = [];
        $labels = [];
        foreach ($this->schema() as $field) {
            $key = $field['name'];
            $labels[$key] = $field['label'];
            $mandatory = in_array($key, ['curp', 'pNombre', 'aPaterno'], true) || ($submit && $field['required']);
            $rule = [$mandatory ? 'required' : 'nullable', 'string', 'max:'.($key === 'Observaciones' ? '10000' : '500')];
            if ($field['control'] === 'date') {
                $rule[] = 'date_format:Y-m-d';
            }
            if ($field['options']) {
                $rule[] = Rule::in(array_column($field['options'], 'value'));
            }
            if ($key === 'curp') {
                $rule[] = new Curp;
            }
            if (in_array($key, ['dudcp', 'rfcp', 'rpcp'])) {
                $rule[] = 'regex:/^[0-9]{5}$/';
            }
            if (in_array($key, ['dfchijos', 'dfcdeconomicos', 'dsenphabitan'])) {
                $rule[] = 'regex:/^[0-9]{1,3}$/';
            }
            if (in_array($key, ['dseihogar', 'dseccvivienda', 'total', 'total2'])) {
                $rule[] = 'regex:/^[0-9]{1,8}(\\.[0-9]{1,2})?$/';
            }
            $rules[$key] = $rule;
        }
        if ($submit) {
            $rules['ocupuesto'][] = 'required_if:tfdirectivos,1';
            $rules['dNombre2'][] = 'required_if:dNombre,1';
        }
        $validated = Validator::make($fields, $rules, [], $labels)->validate();
        if (! empty($fields['fNacimiento']) && isset($fields['curp'])) {
            $curp = $fields['curp'];
            $year = (ctype_digit($curp[16]) ? 1900 : 2000) + (int) substr($curp, 4, 2);
            $expected = sprintf('%04d-%s-%s', $year, substr($curp, 6, 2), substr($curp, 8, 2));
            Validator::make(['fNacimiento' => $fields['fNacimiento']], ['fNacimiento' => [Rule::in([$expected])]], ['in' => 'La fecha de nacimiento no coincide con la CURP.'])->validate();
        }

        if (! empty($fields['genero'])) {
            $expected = $fields['curp'][10] === 'H' ? 'Masculino' : 'Femenino';
            Validator::make(['genero' => $fields['genero']], ['genero' => [Rule::in([$expected])]], ['in' => 'El sexo registrado no coincide con la CURP.'])->validate();
        }

        return $validated;
    }
}
