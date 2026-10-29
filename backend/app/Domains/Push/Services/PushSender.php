<?php
namespace App\Domains\Push\Services;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class PushSender {
    public function ready(string $platform): bool {
        if (!config('client_push.enabled')) return false;
        if ($platform === 'android') return is_readable((string) config('client_push.fcm_credentials'));
        return $platform === 'ios' && is_readable((string) config('client_push.apns_key')) && config('client_push.apns_key_id') && config('client_push.apns_team_id') && config('client_push.apns_topic');
    }
    public function send(string $platform, string $token, string $dedup): array {
        if (!$this->ready($platform)) throw new RuntimeException('Push not configured.');
        $title = 'Crece Impulso';
        $body = 'Tienes un pago programado para mañana. Consulta tu estado de cuenta en la app.';
        if ($platform === 'android') {
            $credentials = json_decode(file_get_contents(config('client_push.fcm_credentials')), true, 512, JSON_THROW_ON_ERROR);
            $project = $credentials['project_id'];
            if (!preg_match('/^[a-z0-9-]+$/', $project)) throw new RuntimeException('Invalid FCM project.');
            $access = Cache::remember('client-fcm-access-'.hash('sha256', $credentials['private_key']), 3000, function () use ($credentials) {
                $jwt = $this->jwt(['alg'=>'RS256','typ'=>'JWT'], ['iss'=>$credentials['client_email'], 'scope'=>'https://www.googleapis.com/auth/firebase.messaging', 'aud'=>'https://oauth2.googleapis.com/token','iat'=>time(),'exp'=>time()+3500], $credentials['private_key']);
                $result = Http::timeout(15)->asForm()->post('https://oauth2.googleapis.com/token', ['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt]);
                if (!$result->successful() || !$result->json('access_token')) throw new RuntimeException('FCM authentication failed.');
                return $result->json('access_token');
            });
            $response = Http::timeout(20)->withToken($access)->post('https://fcm.googleapis.com/v1/projects/'.$project.'/messages:send', ['message'=>[
                'token'=>$token, 'notification'=>['title'=>$title,'body'=>$body],
                'data'=>['destination'=>'statement'], 'android'=>['ttl'=>'43200s','collapse_key'=>$dedup],
            ]]);
            $invalid = collect($response->json('error.details', []))->contains(fn ($d) => ($d['errorCode'] ?? null) === 'UNREGISTERED');
            return ['status'=>$response->successful()?'accepted':($invalid?'invalid_device':'rejected'), 'reference'=>$response->successful()?$response->json('name'):null];
        }
        $jwt = Cache::remember('client-apns-jwt-'.hash('sha256', (string)config('client_push.apns_key_id')), 2400, fn () => $this->jwt(['alg'=>'ES256','kid'=>config('client_push.apns_key_id')], ['iss'=>config('client_push.apns_team_id'),'iat'=>time()], file_get_contents(config('client_push.apns_key')), true));
        $host = config('client_push.apns_sandbox') ? 'https://api.sandbox.push.apple.com' : 'https://api.push.apple.com';
        $response = Http::timeout(20)->withOptions(['version'=>'2.0'])->withToken($jwt)->withHeaders([
            'apns-topic'=>config('client_push.apns_topic'),'apns-push-type'=>'alert','apns-priority'=>'5',
            'apns-collapse-id'=>$dedup, 'apns-expiration'=>(string) (time()+43200),
        ])->post($host.'/3/device/'.$token, ['aps'=>['alert'=>['title'=>$title,'body'=>$body],'sound'=>'default'],'destination'=>'statement']);
        $invalid = in_array($response->json('reason'), ['Unregistered','BadDeviceToken'], true);
        return ['status'=>$response->successful()?'accepted':($invalid?'invalid_device':'rejected'), 'reference'=>$response->successful()?$response->header('apns-id'):null];
    }
    private function jwt(array $header, array $claims, string $key, bool $ec = false): string {
        $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $input = $b64(json_encode($header, JSON_THROW_ON_ERROR)).'.'.$b64(json_encode($claims, JSON_THROW_ON_ERROR));
        if (!openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) throw new RuntimeException('Push signing failed.');
        if ($ec) {
            // OpenSSL emits ASN.1 DER; ES256 JWT requires the two 32-byte coordinates.
            $offset = 2;
            if (ord($signature[0]) !== 48 || ord($signature[$offset++]) !== 2) throw new RuntimeException('Invalid ECDSA signature.');
            $len = ord($signature[$offset++]); $r = substr($signature, $offset, $len); $offset += $len;
            if (ord($signature[$offset++]) !== 2) throw new RuntimeException('Invalid ECDSA signature.');
            $len = ord($signature[$offset++]); $s = substr($signature, $offset, $len);
            $signature = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT).str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
            if (strlen($signature) !== 64) throw new RuntimeException('Invalid ES256 key.');
        }
        return $input.'.'.$b64($signature);
    }
}
