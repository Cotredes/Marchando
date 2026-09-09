<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupCreate extends Command
{
    protected $signature = 'backup:create {--verify : Restore the backup into a temporary copy and verify table counts}';

    protected $description = 'Create a timestamped backup of the SQLite database and public storage manifest (pilot RPO/RTO support).';

    public function handle(): int
    {
        $dbPath = (string) config('database.connections.sqlite.database');
        if (! is_file($dbPath)) {
            $this->error('Solo soportado con SQLite en esta versión (DB: '.$dbPath.')');

            return self::FAILURE;
        }
        $stamp = now()->format('Ymd-His');
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $wal = $dbPath.'-wal';
        $shm = $dbPath.'-shm';
        DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
        $dbBackup = $dir.'/marchando-'.$stamp.'.sqlite';
        if (! copy($dbPath, $dbBackup)) {
            $this->error('No se pudo copiar la base de datos.');

            return self::FAILURE;
        }
        if (is_file($wal)) {
            copy($wal, $dbBackup.'-wal');
        }
        if (is_file($shm)) {
            copy($shm, $dbBackup.'-shm');
        }

        $manifest = ['version' => config('app.marchando_version'), 'created_at' => now()->toISOString(), 'database' => basename($dbBackup), 'tables' => []];
        foreach (['restaurants', 'orders', 'order_lines', 'payments', 'sale_documents', 'fiscal_records', 'customers', 'products'] as $table) {
            try {
                $manifest['tables'][$table] = DB::table($table)->count();
            } catch (\Throwable) {
                $manifest['tables'][$table] = null;
            }
        }
        $manifestPath = $dir.'/marchando-'.$stamp.'.json';
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info('Backup creado: '.basename($dbBackup));

        if ($this->option('verify')) {
            $tmp = $dir.'/verify-'.$stamp.'.sqlite';
            copy($dbBackup, $tmp);
            try {
                $pdo = new \PDO('sqlite:'.$tmp);
                $ok = true;
                foreach ($manifest['tables'] as $table => $count) {
                    if ($count === null) {
                        continue;
                    }
                    $actual = (int) $pdo->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn();
                    if ($actual !== $count) {
                        $ok = false;
                        $this->error("Verificación fallida en {$table}: manifest={$count} restaurado={$actual}");
                    }
                }
                if (! $ok) {
                    return self::FAILURE;
                }
                $this->info('Restauración verificada en copia temporal: restaurantes='.($manifest['tables']['restaurants'] ?? '?').', pedidos='.($manifest['tables']['orders'] ?? '?'));
            } finally {
                @unlink($tmp);
            }
        }

        $this->prune($dir);

        return self::SUCCESS;
    }

    private function prune(string $dir): void
    {
        $files = glob($dir.'/marchando-*.sqlite') ?: [];
        rsort($files);
        foreach (array_slice($files, 14) as $old) {
            @unlink($old);
            @unlink($old.'-wal');
            @unlink($old.'-shm');
            @unlink(preg_replace('/\.sqlite$/', '.json', (string) $old));
        }
    }
}
