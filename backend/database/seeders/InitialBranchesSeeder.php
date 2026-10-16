<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class InitialBranchesSeeder extends Seeder
{
    public function run(): void
    {
        // Nombres confirmados por el usuario; localidad y mapeo legacy pendientes.
        foreach (['Carrillo', 'Tulum', 'Morelos', 'Playa 1', 'Playa 2', 'Cancún 1', 'Cancún 2'] as $name) {
            if (! Branch::withTrashed()->where('name', $name)->exists()) {
                Branch::create(['name' => $name, 'city' => null, 'is_active' => true]);
            }
        }
    }
}
