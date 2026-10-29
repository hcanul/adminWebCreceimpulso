<?php

namespace App\Http\Controllers\Api;

use App\Domains\Documents\Actions\IssueDocumentAction;
use App\Domains\Documents\Services\DocumentAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class DocumentController extends Controller
{
    public function index(Request $request, DocumentAccess $access, string $type, string $source)
    {
        $record = $access->source($request->user(), $type, $source);
        $documents = DB::table('document_versions')->where('source_type', $type)->where('source_id', $source)->orderByDesc('created_at')->get(['id', 'kind', 'source_version', 'template_version', 'actor_name', 'sha256', 'created_at']);
        $files = DB::table('document_signed_files')->whereIn('document_version_id', $documents->pluck('id'))->orderBy('created_at')->get(['id', 'document_version_id', 'actor_name', 'sha256', 'bytes', 'created_at'])->groupBy('document_version_id');
        foreach ($documents as $document) {
            $document->signed_files = $files[$document->id] ?? [];
        }

        return response()->json(['source_version' => $type === 'file' ? $record->version : $record->authorization_version, 'can_issue' => $type === 'file' || (bool) $record->approved_snapshot, 'documents' => $documents])->header('Cache-Control', 'private, no-store');
    }

    public function issue(Request $request, IssueDocumentAction $action, string $type, string $source)
    {
        $v = $request->validate(['kind' => 'required|in:dossier,installation,authorization,schedule,contract_review,promissory_review']);

        return response()->json(['id' => $action->execute($request->user(), $type, $source, $v['kind'])]);
    }

    public function show(Request $request, DocumentAccess $access, string $id)
    {
        $row = $access->document($request->user(), $id);
        abort_unless(hash_equals($row->sha256, hash('sha256', $row->html)), 409, 'El documento no supera la comprobación de integridad.');

        return response()->json(['html' => $row->html, 'sha256' => $row->sha256])->header('Cache-Control', 'private, no-store');
    }

    public function print(Request $request, \App\Domains\ClientAccess\Actions\PrintContractAccessAction $action, string $id)
    {
        return response()->json(['html' => $action->execute($request->user(), $id)])->header('Cache-Control', 'private, no-store');
    }

    public function attach(Request $request, DocumentAccess $access, string $id)
    {
        $document = $access->document($request->user(), $id);
        abort_if(in_array($document->kind, ['contract_review', 'promissory_review'], true), 422, 'Este documento está en revisión; no admite archivos firmados.');
        $request->validate(['file' => 'required|file|mimetypes:application/pdf|max:10240']);
        $file = $request->file('file');
        abort_unless(str_starts_with(file_get_contents($file->getRealPath(), false, null, 0, 5), '%PDF-'), 422, 'Adjunta un PDF válido.');
        $hash = hash_file('sha256', $file->getRealPath());
        $existing = DB::table('document_signed_files')->where('document_version_id', $id)->where('sha256', $hash)->value('id');
        if ($existing) {
            return response()->json(['id' => $existing]);
        }
        $fileId = (string) Str::uuid();
        $path = 'signed-documents/'.$fileId.'.pdf';
        abort_unless(Storage::disk('local')->put($path, file_get_contents($file->getRealPath())), 503, 'No se pudo guardar el archivo.');
        try {
            $saved = DB::transaction(function () use ($request, $id, $hash, $path, $file, $fileId) {
                DB::table('document_versions')->where('id', $id)->lockForUpdate()->first();
                $existing = DB::table('document_signed_files')->where('document_version_id', $id)->where('sha256', $hash)->value('id');
                if ($existing) {
                    return $existing;
                }
                DB::table('document_signed_files')->insert(['id' => $fileId, 'document_version_id' => $id, 'actor_id' => $request->user()->id, 'actor_name' => $request->user()->name, 'path' => $path, 'sha256' => $hash, 'bytes' => $file->getSize(), 'created_at' => now()]);

                return $fileId;
            });
            if ($saved !== $fileId) {
                Storage::disk('local')->delete($path);
            }

            return response()->json(['id' => $saved]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function download(Request $request, DocumentAccess $access, string $id)
    {
        $file = DB::table('document_signed_files')->where('id', $id)->first();
        abort_unless($file, 404);
        $access->document($request->user(), $file->document_version_id);
        $content = Storage::disk('local')->get($file->path);
        abort_unless($content !== null && hash_equals($file->sha256, hash('sha256', $content)), 409, 'El archivo no está disponible o no supera la comprobación de integridad.');

        return response($content)->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="firmado-'.$id.'.pdf"')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
