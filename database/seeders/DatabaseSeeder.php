<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        foreach (['Desarrollo web', 'Diseño gráfico', 'Edición de video', 'Marketing & CM', 'Redes sociales'] as $category) {
            Category::query()->firstOrCreate(['name' => $category], ['status' => 'active']);
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
