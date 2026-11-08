<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Auto-start MariaDB if it is not running.
 * Launches mysqld.exe as a hidden background process using VBScript
 * and waits up to 8 seconds for port 3306 to become available.
 */
function ensure_mariadb_running(): void {
    // Path to MariaDB installation — adjust if yours differs
    $mysqld  = 'C:\Program Files\MariaDB 13.0\bin\mysqld.exe';
    $iniFile = 'C:\Program Files\MariaDB 13.0\data\my.ini';
    $mariaHome = 'C:\Program Files\MariaDB 13.0';

    if (!file_exists($mysqld)) return; // MariaDB not installed at expected path

    // Quick TCP probe to see if port 3306 is already listening
    $sock = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 1);
    if ($sock) { fclose($sock); return; } // Already running — nothing to do

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Launch mysqld fully detached via a temporary VBS script (hidden, no console)
        $vbs = sys_get_temp_dir() . '\rasoi_start_mariadb.vbs';
        $lines = [
            'Set objShell = CreateObject("WScript.Shell")',
            'objShell.CurrentDirectory = "' . $mariaHome . '"',
            'objShell.Run """' . $mysqld . '"" --defaults-file=""' . $iniFile . '""", 0, False',
        ];
        file_put_contents($vbs, implode("\r\n", $lines) . "\r\n");
        pclose(popen('cscript //nologo "' . $vbs . '"', 'r'));
    } else {
        // Linux / Mac fallback
        exec('"' . $mysqld . '" --defaults-file="' . $iniFile . '" > /dev/null 2>&1 &');
    }

    // Wait up to 8 seconds for MariaDB to become ready
    for ($i = 0; $i < 16; $i++) {
        usleep(500_000); // 0.5 second
        $sock = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 1);
        if ($sock) { fclose($sock); return; }
    }
    // If still not up after 8s, the caller's PDO will throw and show the error page
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // Try to auto-start MariaDB before connecting
        ensure_mariadb_running();

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
            <meta http-equiv="refresh" content="5">
            </head>
            <body>
            <div class="card p-4 p-md-5 text-center">
              <div class="icon-box"><i class="bi bi-database-gear"></i></div>
              <h3 class="fw-bold mb-2">Starting Database Server&hellip;</h3>
              <p class="text-muted mb-3">MariaDB is being launched automatically. This page will refresh in a few seconds.</p>

              <div class="d-flex justify-content-center mb-4">
                <div class="spinner-border text-danger" role="status"><span class="visually-hidden">Loading...</span></div>
              </div>

              <div class="d-grid gap-2">
                <button onclick="location.reload()" class="btn btn-danger py-2 fw-semibold"><i class="bi bi-arrow-clockwise me-1"></i> Refresh Now</button>
              </div>

              <details class="text-start mt-3 small text-muted">
                <summary class="cursor-pointer">Technical error details</summary>
                <pre class="bg-light p-2 rounded mt-2 text-danger mb-0" style="font-size: 0.78rem; overflow-x: auto;"><?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?></pre>
                <div class="mt-2">
                  <strong>Manual fix:</strong> Double-click <code>start-database.bat</code> or <code>run.bat</code> in your project folder.
                </div>
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
