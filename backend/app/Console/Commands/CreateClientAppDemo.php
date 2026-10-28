<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CreateClientAppDemo extends Command
{
    protected $signature = 'client-app:create-demo';

    protected $description = 'Crea una cuenta ficticia nueva para probar la app en un entorno local.';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Disponible solamente en local o testing.');

            return self::FAILURE;
        }
        $connection = config('database.default');
        if ($connection !== 'sqlite' && (! in_array(config("database.connections.$connection.host"), ['localhost', '127.0.0.1', '::1'], true) || config("database.connections.$connection.url"))) {
            $this->error('Solo se admite una base local.');

            return self::FAILURE;
        }
        $password = 'Demo'.bin2hex(random_bytes(6)).'9';
        $client = DB::transaction(function () use ($password) {
            $branch = Branch::create(['name' => 'DEMO APP — Solo pruebas', 'city' => 'Datos ficticios', 'is_active' => true]);
            $client = Client::create(['branch_id' => $branch->id, 'curp' => 'DEMO'.strtoupper(bin2hex(random_bytes(7))), 'first_name' => 'María DEMO', 'last_name' => 'Cliente de prueba', 'status' => 'activo']);
            ClientAccount::create(['client_id' => $client->id, 'password' => $password]);

            return $client;
        });
        $this->info('Cuenta ficticia creada. No tiene créditos ni pagos.');
        $this->line('Número de cliente: '.$client->folio);
        $this->line('Contraseña: '.$password);

        return self::SUCCESS;
    }
}
