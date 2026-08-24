<?php
/**
 * Indra Hotel - Database-Backed Session Handler
 * Ensures PHP sessions persist seamlessly across multi-pod Kubernetes containers & restarts
 */

class PdoSessionHandler implements SessionHandlerInterface {
    private ?PDO $pdo = null;

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
            $cutoff = time() - $max_lifetime;
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE last_activity < ?");
            $stmt->execute([$cutoff]);
            return $stmt->rowCount();
        } catch (Throwable $t) {
            return 0;
        }
    }
}
