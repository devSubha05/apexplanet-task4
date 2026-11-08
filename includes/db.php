<?php
require_once __DIR__ . '/../config/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Database Offline | Rasoi Express</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
            <style>
              body { background: #f8f9fa; font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
              .card { max-width: 580px; width: 100%; border: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border-radius: 16px; }
              .icon-box { width: 64px; height: 64px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.25rem; }
            </style>
            </head>
            <body>
            <div class="card p-4 p-md-5 text-center">
              <div class="icon-box"><i class="bi bi-database-slash"></i></div>
              <h3 class="fw-bold mb-2">MariaDB / MySQL Server is Offline</h3>
              <p class="text-muted mb-4">Your PHP web server is running, but the database server on port 3306 is not turned on.</p>

              <div class="alert alert-warning text-start mb-4">
                <div class="fw-bold mb-2"><i class="bi bi-play-circle-fill me-1"></i> How to start it:</div>
                <ol class="mb-0 ps-3 small">
                  <li class="mb-1">Go to your project folder: <code>E:\ApexPlanet-Internship\Task-4\apexplanet-task4</code></li>
                  <li class="mb-1">Double-click <strong><code>start-database.bat</code></strong> (or <strong><code>run.bat</code></strong>).</li>
                  <li>Keep that command window open and click the button below.</li>
                </ol>
              </div>

              <div class="d-grid gap-2">
                <button onclick="location.reload()" class="btn btn-danger py-2 fw-semibold"><i class="bi bi-arrow-clockwise me-1"></i> Refresh Page</button>
              </div>

              <details class="text-start mt-3 small text-muted">
                <summary class="cursor-pointer">Technical error details</summary>
                <pre class="bg-light p-2 rounded mt-2 text-danger mb-0" style="font-size: 0.78rem; overflow-x: auto;"><?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?></pre>
              </details>
            </div>
            </body>
            </html>
            <?php
            exit;
        }
    }
    return $pdo;
}
