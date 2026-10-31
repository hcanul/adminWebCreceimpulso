<?php

namespace App\Http\Controllers\Api;

use App\Domains\Messages\Services\MessageAccess;
use App\Domains\Messages\Services\MessageWriter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AppMessageController extends Controller
{
    public function inbox(Request $request, MessageAccess $access)
    {
        [$type,$id] = $access->identity($request->user());
        $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:10000']]);
        $query = DB::table('app_messages')->where('recipient_type', $type)->where('recipient_id', $id)->whereNull('cancelled_at');
        $page = (clone $query)->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(20, ['id', 'kind', 'title', 'body', 'created_at', 'read_at']);
        $rows = collect($page->items())->map(fn ($m) => ['id' => $m->id, 'kind' => $m->kind, 'title' => $m->title, 'body' => Crypt::decryptString($m->body), 'created_at' => $m->created_at, 'read_at' => $m->read_at]);

        return response()->json(['data' => $rows, 'has_more' => $page->hasMorePages(), 'unread' => (clone $query)->whereNull('read_at')->count()])->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request, MessageAccess $access, string $id)
    {
        [$type,$recipient] = $access->identity($request->user());
        $q = DB::table('app_messages')->where('id', $id)->where('recipient_type', $type)->where('recipient_id', $recipient);
        abort_unless($q->exists(), 404);
        $q->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->noContent();
    }

    public function recipients(Request $request, MessageAccess $access)
    {
        $v = $request->validate(['type' => ['required', 'in:client,staff'], 'search' => ['required', 'string', 'min:3', 'max:80']]);
        $q = $access->recipients($request->user(), $v['type']);
        $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($v['search'])).'%';
        $q->where(function ($w) use ($v, $term) {
            if ($v['type'] === 'client') {
                $w->where('c.first_name', 'like', $term)->orWhere('c.last_name', 'like', $term)->orWhere('c.folio', 'like', $term);
            } else {
                $w->where('a.name', 'like', $term)->orWhere('a.email', 'like', $term);
            }
        });

        return response()->json(['data' => $q->orderBy($v['type'] === 'client' ? 'c.first_name' : 'a.name')->limit(20)->get()->map(function ($r) {
            if (isset($r->last_name)) {
                $r->name .= ' '.$r->last_name;
                unset($r->last_name);
            }

            return $r;
        })])->header('Cache-Control', 'no-store');
    }

    public function send(Request $request, MessageAccess $access, MessageWriter $writer)
    {
        $actor = $request->user();
        $access->authorizeSend($actor);
        $v = $request->validate(['id' => ['required', 'uuid'], 'type' => ['required', 'in:client,staff'], 'recipient_id' => ['required', 'uuid'], 'title' => ['required', 'string', 'max:100'], 'body' => ['required', 'string', 'max:1000']]);
        abort_unless(trim($v['title']) !== '' && trim($v['body']) !== '', 422);
        $result = DB::transaction(function () use ($actor, $access, $writer, $v) {
            // Lock before the first consistent read so MySQL retries see the committed audit.
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $recipient = $access->recipients($actor, $v['type'])->where('a.id', $v['recipient_id'])->first();
            abort_unless($recipient, 404);
            // UUID retries are scoped to sender and must preserve recipient/content.
            $event = 'custom:'.$actor->id.':'.$v['id'];
            $prior = DB::table('access_audits')->where('actor_id', $actor->id)->where('subject_type', 'message')->where('action', 'sent')->where('subject_id', $v['id'])->first();
            $fingerprint = hash('sha256', json_encode([$v['type'], $v['recipient_id'], $v['title'], $v['body']]));
            if ($prior) {
                $saved = json_decode($prior->changes, true);
                abort_unless(hash_equals($saved['fingerprint'], $fingerprint), 409);

                return $saved['message_id'];
            }
            $id = $writer->create($v['type'], $recipient->id, $recipient->branch_id, $event, 'custom', $v['title'], $v['body'], sender: $actor->id, expires: now()->addDay());
            DB::table('access_audits')->insert(['id' => (string) Str::uuid(), 'actor_id' => $actor->id, 'subject_type' => 'message', 'subject_id' => $v['id'], 'action' => 'sent', 'changes' => json_encode(['message_id' => $id, 'fingerprint' => $fingerprint]), 'created_at' => now()]);

            return $id;
        }, 3);

        return response()->json(['id' => $result, 'status' => 'queued', 'message' => 'Guardado en la bandeja. El aviso push depende de la configuración y permisos del dispositivo.'], 201)->header('Cache-Control', 'no-store');
    }
}
