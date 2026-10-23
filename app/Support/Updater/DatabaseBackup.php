<?php

namespace App\Support\Updater;

use Illuminate\Support\Facades\DB;

/**
 * Einfache Datenbank-Sicherung ohne externe Programme (kein mysqldump nötig).
 * Unterstützt MySQL/MariaDB (als .sql.gz) und SQLite (Dateikopie).
 */
class DatabaseBackup
{
    /** @return string|null Pfad der Sicherung oder null, wenn der Datenbanktyp nicht unterstützt wird */
    public function dump(string $dir): ?string
    {
        $conn = DB::connection();
        $driver = $conn->getDriverName();

        if ($driver === 'sqlite') {
            $path = (string) $conn->getDatabaseName();
            if ($path === '' || $path === ':memory:' || ! is_file($path)) {
                return null;
            }
            $target = $dir.'/database.sqlite';
            if (! copy($path, $target)) {
                throw new UpdaterException('Die SQLite-Datenbank konnte nicht gesichert werden.');
            }

            return $target;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->dumpMysql($dir.'/database.sql.gz');
        }

        return null;
    }

    private function dumpMysql(string $file): string
    {
        $pdo = DB::connection()->getPdo();
        $gz = gzopen($file, 'wb9');
        if (! $gz) {
            throw new UpdaterException('Die Sicherungsdatei konnte nicht angelegt werden.');
        }

        try {
            gzwrite($gz, "-- Vylaris Links Sicherung ".now()->toDateTimeString()."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = array_map(fn ($r) => array_values((array) $r)[0], DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
            foreach ($tables as $table) {
                $q = '`'.str_replace('`', '``', $table).'`';
                $create = (array) DB::selectOne("SHOW CREATE TABLE {$q}");
                gzwrite($gz, "DROP TABLE IF EXISTS {$q};\n".array_values($create)[1].";\n\n");

                $batch = [];
                foreach (DB::table($table)->cursor() as $row) {
                    $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values((array) $row));
                    $batch[] = '('.implode(',', $vals).')';
                    if (count($batch) >= 200) {
                        gzwrite($gz, "INSERT INTO {$q} VALUES ".implode(",\n", $batch).";\n");
                        $batch = [];
                    }
                }
                if ($batch) {
                    gzwrite($gz, "INSERT INTO {$q} VALUES ".implode(",\n", $batch).";\n");
                }
                gzwrite($gz, "\n");
            }
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($file);
            throw new UpdaterException('Die Datenbank-Sicherung ist fehlgeschlagen: '.$e->getMessage(), 0, $e);
        }
        gzclose($gz);

        return $file;
    }
}
