<?php
/**
 * Indra Hotel - Health Check Endpoint for Kubernetes & Docker
 */

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/includes/db.php';
    
    // Check DB connection
    $pdo = Database::getConnection();
    $driver = Database::getDriver();
    
    // Simple query test
    $stmt = $pdo->query("SELECT 1");
    $dbOk = (bool)$stmt->fetchColumn();

    if ($dbOk) {
        http_response_code(200);
        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('c'),
            'database' => 'connected',
            'driver' => $driver,
            'environment' => getenv('APP_ENV') ?: 'production'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Database query failed'
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
