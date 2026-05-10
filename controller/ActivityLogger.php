<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

final class ActivityLogger
{
    private const MAX_EVENTS = 40;

    private static ?PDO $conn = null;
    private static bool $tableReady = false;

    private static function getConnection(): ?PDO
    {
        if (self::$conn instanceof PDO) {
            return self::$conn;
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
            self::$conn = $GLOBALS['conn'];
            return self::$conn;
        }

        if (class_exists('Config') && method_exists('Config', 'getConnexion')) {
            $configConnection = Config::getConnexion();
            if ($configConnection instanceof PDO) {
                self::$conn = $configConnection;
                return self::$conn;
            }
        }

        return null;
    }

    private static function ensureTable(): bool
    {
        if (self::$tableReady) {
            return true;
        }

        $conn = self::getConnection();
        if (!$conn) {
            return false;
        }

        try {
            $conn->exec(
                'CREATE TABLE IF NOT EXISTS activity_logs (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    type VARCHAR(100) NOT NULL,
                    detail TEXT NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_activity_logs_user_created (user_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            self::$tableReady = true;
            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    private static function normalizeDateForOutput(string $value): string
    {
        try {
            return (new DateTime($value))->format('c');
        } catch (Throwable $exception) {
            return date('c');
        }
    }

    private static function trimUserEvents(int $userId): void
    {
        $conn = self::getConnection();
        if (!$conn || !self::ensureTable()) {
            return;
        }

        try {
            $stmt = $conn->prepare(
                'DELETE FROM activity_logs
                 WHERE user_id = :user_id
                   AND id NOT IN (
                       SELECT id FROM (
                           SELECT id
                           FROM activity_logs
                           WHERE user_id = :user_id_inner
                           ORDER BY created_at DESC, id DESC
                           LIMIT :max_events
                       ) AS kept
                   )'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id_inner', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':max_events', self::MAX_EVENTS, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $exception) {
            // Ignore trim failures.
        }
    }

    public static function log(int $userId, string $type, string $detail): void
    {
        if ($userId <= 0 || !self::ensureTable()) {
            return;
        }

        $conn = self::getConnection();
        if (!$conn) {
            return;
        }

        try {
            $stmt = $conn->prepare(
                'INSERT INTO activity_logs (user_id, type, detail, created_at)
                 VALUES (:user_id, :type, :detail, :created_at)'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':type' => trim($type) !== '' ? trim($type) : 'Activite',
                ':detail' => trim($detail) !== '' ? trim($detail) : '-',
                ':created_at' => date('Y-m-d H:i:s')
            ]);

            self::trimUserEvents($userId);
        } catch (Throwable $exception) {
            // Ignore logging failures to avoid blocking user actions.
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function getRecent(int $userId, int $limit = 5): array
    {
        if ($userId <= 0 || !self::ensureTable()) {
            return [];
        }

        $conn = self::getConnection();
        if (!$conn) {
            return [];
        }

        try {
            if ($limit <= 0) {
                $stmt = $conn->prepare(
                    'SELECT type, detail, created_at
                     FROM activity_logs
                     WHERE user_id = :user_id
                     ORDER BY created_at DESC, id DESC'
                );
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            } else {
                $stmt = $conn->prepare(
                    'SELECT type, detail, created_at
                     FROM activity_logs
                     WHERE user_id = :user_id
                     ORDER BY created_at DESC, id DESC
                     LIMIT :limit_count'
                );
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':limit_count', $limit, PDO::PARAM_INT);
            }

            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(
                static fn(array $row): array => [
                    'type' => (string) ($row['type'] ?? 'Activite'),
                    'detail' => (string) ($row['detail'] ?? '-'),
                    'at' => self::normalizeDateForOutput((string) ($row['created_at'] ?? ''))
                ],
                $rows
            );
        } catch (Throwable $exception) {
            return [];
        }
    }
}
