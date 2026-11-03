<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $legacy = DB::table('catalog_entries')->where('kind', 'subject')->where('name_key', 'existing teaching subjects')->first();
            if (!$legacy) return;
            $categories = [
                'coding' => 'Programming', 'programming' => 'Programming',
                'design' => 'Design', 'graphic design' => 'Design', 'graphic designing' => 'Design',
                'foreign language' => 'Languages', 'foreign languages' => 'Languages', 'language' => 'Languages',
                'excel' => 'Spreadsheets', 'spreadsheets' => 'Spreadsheets', 'database' => 'Databases', 'databases' => 'Databases',
            ];
            foreach (DB::table('catalog_entries')->where('parent_id', $legacy->id)->get() as $topic) {
                // An unfamiliar broad subject keeps its own name instead of being guessed into an unrelated category.
                $name = $categories[$topic->name_key] ?? $topic->name;
                $key = mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
                $category = DB::table('catalog_entries')->where('kind', 'subject')->where('name_key', $key)->first();
                if ($category && ($category->id === $legacy->id || $category->status !== 'approved')) {
                    throw new RuntimeException('Review the legacy topic category before applying this migration: '.$topic->name);
                }
                $parent = $category?->id ?? DB::table('catalog_entries')->insertGetId([
                    'kind' => 'subject', 'name' => $name, 'name_key' => $key, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('catalog_entries')->where('id', $topic->id)->update(['parent_id' => $parent, 'updated_at' => now()]);
            }
            DB::table('catalog_entries')->where('id', $legacy->id)->delete();
        });
    }

    public function down(): void
    {
        // Retain valid topic classifications and their IDs. Selective restoration uses the pre-change catalog backup.
    }
};
