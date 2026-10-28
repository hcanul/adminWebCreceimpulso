<?php

namespace App\Domains\Documents\Services;

use Illuminate\Support\Facades\DB;

final class DocumentProfiles
{
    public const FIELDS = ['legal_address', 'representative_name', 'representative_position', 'payment_place', 'signing_place'];

    public function read(string $scope): array
    {
        $row = DB::table('document_profiles')->where('scope', $scope)->first();
        $fields = $row ? json_decode($row->fields, true, 512, JSON_THROW_ON_ERROR) : [];

        return ['scope' => $scope, 'version' => $row?->version ?? 0, 'use_general' => (bool) ($row?->use_general ?? true), 'fields' => array_merge(array_fill_keys(self::FIELDS, ''), $fields)];
    }

    public function resolved(string $branch): array
    {
        $local = $this->read($branch);
        $profile = $local['use_general'] ? $this->read('general') : $local;

        return ['source' => $profile['scope'], 'version' => $profile['version'], 'fields' => $profile['fields'],
            'missing' => array_keys(array_filter($profile['fields'], fn ($value) => trim($value) === ''))];
    }
}
