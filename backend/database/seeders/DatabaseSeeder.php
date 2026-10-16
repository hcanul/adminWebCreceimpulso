<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear Roles base
        $roleSuperAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $roleGerente = Role::firstOrCreate(['name' => 'Gerente General']);
        $roleCoordinador = Role::firstOrCreate(['name' => 'Coordinador']);
        $roleCaja = Role::firstOrCreate(['name' => 'Caja']);

        // 2. Crear tu usuario Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@creceimpulso.com'],
            [
                'name' => 'Hugo Canul',
                'password' => Hash::make('hc162628hc'), // Cambia esto en producción
                'is_active' => true,
            ]
        );

        // 3. Asignar el rol
        $superAdmin->assignRole($roleSuperAdmin);
    }
}
