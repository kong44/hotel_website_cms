<?php
/**
 * Indra Hotel - Database-Backed Session Handler
 * Ensures PHP sessions persist seamlessly across multi-pod Kubernetes containers & restarts
 */

class PdoSessionHandler implements SessionHandlerInterface {
    private ?PDO $pdo = null;
    private static bool $tableChecked = false;

    public function __construct() {}

    private function getPdo(): ?PDO {
        if ($this->pdo === null) {
            try {
                if (function_exists('getDB')) {
                    $this->pdo = getDB();
                }
            } catch (Throwable $t) {
                return null;
            }
        }
        return $this->pdo;
    }

    private function ensureTable(PDO $pdo): void {
        if (self::$tableChecked) return;
        try {
            $driver = class_exists('Database') ? Database::getDriver() : 'mysql';
            if ($driver === 'mysql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `sessions` (
                    `id` VARCHAR(191) PRIMARY KEY,
                    `data` LONGTEXT NOT NULL,
                    `last_activity` INT NOT NULL,
                    INDEX `idx_sessions_last_activity` (`last_activity`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
                    id TEXT PRIMARY KEY,
                    data TEXT NOT NULL,
                    last_activity INTEGER NOT NULL
                )");
            }
            self::$tableChecked = true;
        } catch (Throwable $t) {}
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        try {
            $pdo = $this->getPdo();
            if (!$pdo) return '';
            $this->ensureTable($pdo);
            $stmt = $pdo->prepare("SELECT data FROM sessions WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $res = $stmt->fetchColumn();
            return $res !== false ? (string)$res : '';
        } catch (Throwable $t) {
            return '';
        }
    }

    public function write(string $id, string $data): bool {
        try {
            $pdo = $this->getPdo();
            if (!$pdo) return false;
            $this->ensureTable($pdo);
            $now = time();
            $driver = class_exists('Database') ? Database::getDriver() : 'mysql';
            
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO sessions (id, data, last_activity) VALUES (?, ?, ?)");
            } else {
                $stmt = $pdo->prepare("INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)");
            }
            return $stmt->execute([$id, $data, $now]);
        } catch (Throwable $t) {
            return false;
        }
    }

    public function destroy(string $id): bool {
        try {
            $pdo = $this->getPdo();
            if (!$pdo) return false;
            $this->ensureTable($pdo);
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Throwable $t) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false {
        try {
            $pdo = $this->getPdo();
            if (!$pdo) return 0;
            $this->ensureTable($pdo);
            $effectiveLifetime = max($max_lifetime, 604800); // 7 days minimum
            $cutoff = time() - $effectiveLifetime;
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE last_activity < ?");
            $stmt->execute([$cutoff]);
            return $stmt->rowCount();
        } catch (Throwable $t) {
            return 0;
        }
    }
}
