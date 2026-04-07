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
        User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User', 
            'email' => 'admin@admin',
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
