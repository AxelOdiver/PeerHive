<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use \App\Models\Schedule;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo users must not be seeded in production.');
        }
        User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User', 
            'role' => 'admin',
            'email' => 'admin@admin',
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
        ]);

        User::factory(50)->create()->each(function ($user) {
            foreach (range(0, 4) as $day) {
                Schedule::factory()
                    ->for($user)
                    ->forDay($day)
                    ->create();
            }
        });
    }
}
