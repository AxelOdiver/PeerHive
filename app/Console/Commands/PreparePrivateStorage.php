<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Storage};

class PreparePrivateStorage extends Command
{
    protected $signature = 'security:prepare';
    protected $description = 'Back up MySQL and transfer confidential uploads to private storage with checksum verification';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('This backup command requires MySQL. No files were changed.');
            return self::FAILURE;
        }
        $backup = 'security-backups/'.now()->format('Ymd-His');
        $disk = Storage::disk('local');
        $pdo = DB::connection()->getPdo();
        $sql = "SET FOREIGN_KEY_CHECKS=0;\n";
        foreach (DB::select('SHOW TABLES') as $row) {
            $table = array_values((array) $row)[0];
            $quoted = '`'.str_replace('`', '``', $table).'`';
            $definition = array_values((array) DB::selectOne('SHOW CREATE TABLE '.$quoted))[1];
            $sql .= "DROP TABLE IF EXISTS $quoted;\n$definition;\n";
            foreach (DB::table($table)->get() as $record) {
                $values = array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values((array) $record));
                $sql .= "INSERT INTO $quoted VALUES (".implode(',', $values).");\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        if (!$disk->put($backup.'/database.sql', $sql)) throw new \RuntimeException('Database backup failed.');
        $count = 0;
        foreach (['qualifications', 'message_attachments'] as $folder) {
            foreach (Storage::disk('public')->allFiles($folder) as $path) {
                $bytes = Storage::disk('public')->get($path);
                if (!$disk->put($backup.'/'.$path, $bytes)) throw new \RuntimeException('File backup failed.');
                if ($disk->exists($path) && hash('sha256', $disk->get($path)) !== hash('sha256', $bytes)) {
                    throw new \RuntimeException('A conflicting private file exists; original retained.');
                }
                if (!$disk->put($path, $bytes) || hash('sha256', $disk->get($path)) !== hash('sha256', $bytes)) {
                    throw new \RuntimeException('Transfer verification failed; original retained.');
                }
                if (!Storage::disk('public')->delete($path)) throw new \RuntimeException('Could not retire the public copy.');
                $count++;
            }
        }
        $this->info("Database and upload backup: storage/app/private/$backup. Transferred $count files.");
        return self::SUCCESS;
    }
}
