<?php

namespace App\Support\Install;

/**
 * Prüft Datenbank-Zugangsdaten, ohne etwas zu verändern.
 */
class DatabaseProbe
{
    /** @return array{ok: bool, message: string} */
    public static function test(array $db): array
    {
        if (($db['driver'] ?? '') === 'sqlite') {
            return self::sqlite((string) $db['name']);
        }
        if (! extension_loaded('pdo_mysql')) {
            return ['ok' => false, 'message' => 'Die PHP-Erweiterung pdo_mysql ist nicht aktiviert.'];
        }

        $host = (string) ($db['host'] ?? '127.0.0.1');
        $port = (int) ($db['port'] ?? 3306);
        $name = (string) ($db['name'] ?? '');

        try {
            $pdo = new \PDO("mysql:host={$host};port={$port};charset=utf8mb4", (string) ($db['user'] ?? ''), (string) ($db['pass'] ?? ''), [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (\PDOException $e) {
            return ['ok' => false, 'message' => self::friendly($e)];
        }

        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $st->execute([$name]);
        if ((int) $st->fetchColumn() === 0) {
            return ['ok' => true, 'message' => "Verbunden mit {$version}. Die Datenbank „{$name}“ gibt es noch nicht – sie wird bei der Installation angelegt, sofern der Benutzer das darf."];
        }

        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
        $st->execute([$name]);
        $tables = (int) $st->fetchColumn();

        return ['ok' => true, 'message' => "Verbunden mit {$version}. Datenbank „{$name}“ gefunden"
            .($tables > 0 ? " – Achtung: sie enthält bereits {$tables} Tabellen. Fehlende Tabellen der Anwendung werden ergänzt, nichts wird gelöscht." : ' (leer).')];
    }

    private static function sqlite(string $path): array
    {
        if (! extension_loaded('pdo_sqlite')) {
            return ['ok' => false, 'message' => 'Die PHP-Erweiterung pdo_sqlite ist nicht aktiviert.'];
        }
        $writable = is_file($path) ? is_writable($path) : is_writable(dirname($path));

        return $writable
            ? ['ok' => true, 'message' => 'SQLite ist verfügbar, die Datei database/database.sqlite kann angelegt werden. (Für mehrere Benutzer ist MySQL/MariaDB die bessere Wahl.)']
            : ['ok' => false, 'message' => 'Der Ordner database/ ist nicht beschreibbar.'];
    }

    private static function friendly(\PDOException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        $text = match (true) {
            $code === 1045 => 'Benutzername oder Passwort stimmen nicht.',
            $code === 2002 || $code === 2006 => 'Der Datenbankserver ist unter dieser Adresse und diesem Port nicht erreichbar.',
            $code === 1044 => 'Der Benutzer hat keinen Zugriff auf diese Datenbank.',
            default => 'Die Verbindung ist fehlgeschlagen.',
        };

        return $text.' ('.\Illuminate\Support\Str::limit($e->getMessage(), 160).')';
    }
}
