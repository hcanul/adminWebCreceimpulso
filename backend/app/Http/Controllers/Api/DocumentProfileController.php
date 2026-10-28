<?php

namespace App\Http\Controllers\Api;

use App\Domains\Documents\Actions\SaveDocumentProfileAction;
use App\Domains\Documents\Services\DocumentProfiles;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

final class DocumentProfileController extends Controller
{
    public function show(string $scope, DocumentProfiles $profiles)
    {
        if ($scope !== 'general') {
            Branch::findOrFail($scope);
        }

        return response()->json(['profile' => $profiles->read($scope), 'effective' => $profiles->resolved($scope)])->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, string $scope, SaveDocumentProfileAction $action)
    {
        $rules = ['version' => 'required|integer|min:0', 'use_general' => 'required|boolean', 'fields' => 'required|array:'.implode(',', DocumentProfiles::FIELDS)];
        foreach (DocumentProfiles::FIELDS as $field) {
            $rules['fields.'.$field] = 'present|nullable|string|max:600';
        }
        $data = $request->validate($rules);
        $data['version'] = (int) $data['version'];
        $action->execute($request->user(), $scope, $data);

        return $this->show($scope, app(DocumentProfiles::class));
    }
}
