<?php
declare(strict_types=1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['sparrow_admin_logged_in']) || $_SESSION['sparrow_admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized access. Log in first.']);
    exit;
}

$action = $_GET['action'] ?? '';
$file = $_GET['file'] ?? '';

// Set this to true ONLY on your public demo server
$isDemoMode = false; 

// Initialize database tables
if ($action === 'init_db') {
    try {
        require_once __DIR__ . '/../includes/db.php';
        $conn = db_connect();

        $queries = [
            "CREATE SCHEMA IF NOT EXISTS app",

            "CREATE TABLE IF NOT EXISTS app.users (
                id serial4 NOT NULL,
                username varchar(50) NOT NULL,
                password_hash varchar(255) NOT NULL,
                CONSTRAINT users_pkey PRIMARY KEY (id),
                CONSTRAINT users_username_key UNIQUE (username)
            )",

            "CREATE TABLE IF NOT EXISTS app.users_log (
                id serial4 NOT NULL,
                user_id int4 NOT NULL,
                \"action\" varchar(50) NOT NULL,
                target_table varchar(100),
                record_id int4,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT users_log_pkey PRIMARY KEY (id)
            )",

            "CREATE TABLE IF NOT EXISTS app.users_notifications (
                id serial4 NOT NULL,
                user_id int8 NOT NULL,
                title varchar(255) NOT NULL,
                link varchar(255),
                source_table varchar(100),
                source_id int8,
                is_read bool DEFAULT false,
                notify_date date NOT NULL,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT users_notifications_pkey PRIMARY KEY (id),
                CONSTRAINT users_notifications_user_id_source_table_source_id_notify_d_key 
                    UNIQUE (user_id, source_table, source_id, notify_date)
            )",

            "INSERT INTO app.users (username, password_hash)
             SELECT 'test', '\$2a\$12\$oqxkKJu53qLCJSnmyxs1BeIDeP81M.cstuhm7T6hS0HPMXYqaK2Je'
             WHERE NOT EXISTS (SELECT 1 FROM app.users WHERE username = 'test')"
        ];

        foreach ($queries as $q) {
            $res = pg_query($conn, $q);
            if (!$res) {
                throw new Exception(pg_last_error($conn));
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'System tables initialized successfully.']);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
    }
    exit;
}

// Check database connection and system health
if ($action === 'health') {
    $db_connected = false;
    $db_error = 'Unknown error';

    try {
        require_once __DIR__ . '/../includes/db.php';
        $conn = db_connect();
        if ($conn) {
            $db_connected = true;
            $db_error = '';
            pg_close($conn);
        }
    } catch (Exception $e) {
        $db_error = $e->getMessage();
    }

    $data = [
        'php_version' => PHP_VERSION,
        'php_version_ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'pgsql_ok' => extension_loaded('pgsql') || extension_loaded('pdo_pgsql'),
        'dir_writable' => is_writable(__DIR__ . '/../includes'),
        'db_connected' => $db_connected,
        'db_error' => $db_error
    ];
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Export JSON configurations as a ZIP file
if ($action === 'export') {
    $zip = new ZipArchive();
    $zipFile = sys_get_temp_dir() . '/sparrow_config_' . time() . '.zip';

    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $includesDir = __DIR__ . '/../includes/';
        $filesToBackup = ['schema.json', 'dashboard.json', 'calendar.json', 'database.json', 'security.json'];

        foreach ($filesToBackup as $f) {
            if (file_exists($includesDir . $f)) {
                $zip->addFile($includesDir . $f, $f);
            }
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="sparrow_backup.zip"');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);
        unlink($zipFile);
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Cannot create zip file']);
        exit;
    }
}

// Import JSON configurations from a ZIP file
if ($action === 'import' && isset($_FILES['backup_file'])) {
    $zip = new ZipArchive();
    if ($zip->open($_FILES['backup_file']['tmp_name']) === TRUE) {
        $zip->extractTo(__DIR__ . '/../includes/');
        $zip->close();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid zip file']);
    exit;
}

// Scan directories for available icons
if ($action === 'list_icons') {
    $icons = [];
    $dirsToScan = [
        'assets/icons' => __DIR__ . '/../assets/icons',
        'assets/img' => __DIR__ . '/../assets/img'
    ];

    foreach ($dirsToScan as $prefix => $dirPath) {
        if (is_dir($dirPath)) {
            $files = scandir($dirPath);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'gif'])) {
                        $icons[] = $prefix . '/' . $file;
                    }
                }
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'icons' => array_values(array_unique($icons))]);
    exit;
}

// Allowed config files for read and write operations
$allowedFiles = ['schema', 'dashboard', 'calendar', 'database', 'security'];

// Get content of a JSON config file
if ($action === 'get' && in_array($file, $allowedFiles, true)) {
    $filePath = __DIR__ . '/../includes/' . $file . '.json';
    header('Content-Type: application/json');
    
    if (file_exists($filePath)) {
        $fileContent = file_get_contents($filePath);
        
        // Mask sensitive data in Demo Mode
        if ($isDemoMode && $file === 'database') {
            $dbData = json_decode($fileContent, true);
            $dbData['host'] = 'hidden-for-demo.postgres.database.azure.com';
            $dbData['user'] = 'demo_user_hidden';
            $dbData['password'] = '********';
            $dbData['dbname'] = 'demo_db';
            echo json_encode($dbData);
        } 
        // Mask admin password in Demo Mode
        elseif ($isDemoMode && $file === 'security') {
            $secData = json_decode($fileContent, true);
            if (isset($secData['admin_password'])) {
                $secData['admin_password'] = '********';
            }
            echo json_encode($secData);
        }
        else {
            echo $fileContent;
        }
    } else {
        echo json_encode(new stdClass());
    }
    exit;
}

// Save content to a JSON config file
if ($action === 'save' && in_array($file, $allowedFiles, true)) {
    
    // Block saving sensitive files in Demo Mode
    if ($isDemoMode && in_array($file, ['database', 'security'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'Saving ' . $file . ' configuration is disabled in Demo Mode.']);
        exit;
    }

    $data = file_get_contents('php://input');
    $filePath = __DIR__ . '/../includes/' . $file . '.json';

    header('Content-Type: application/json');
    if (json_decode($data) !== null) {
        if (!is_dir(__DIR__ . '/../includes/')) {
            mkdir(__DIR__ . '/../includes/', 0777, true);
        }
        file_put_contents($filePath, $data);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'error' => 'Invalid JSON']);
    }
    exit;
}

// Fetch all tables from a specific database schema
if ($action === 'sync_schema') {
    header('Content-Type: application/json');
    try {
        require_once __DIR__ . '/../includes/db.php';
        $conn = db_connect();
        $schemaName = $_GET['schema_name'] ?? 'public';

        $sql = "
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = $1 
              AND table_type = 'BASE TABLE'
        ";

        $res = pg_query_params($conn, $sql, [$schemaName]);

        if (!$res) {
            throw new Exception(pg_last_error($conn));
        }

        $tables = [];
        while ($row = pg_fetch_assoc($res)) {
            $tables[] = $row['table_name'];
        }

        echo json_encode(['status' => 'success', 'tables' => $tables]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch all columns and their data types for a specific table
if ($action === 'get_db_columns') {
    header('Content-Type: application/json');
    try {
        require_once __DIR__ . '/../includes/db.php';
        $conn = db_connect();
        $tableName = $_GET['table'] ?? '';
        $schemaName = $_GET['schema_name'] ?? 'public';

        $sql = "
            SELECT column_name, data_type, is_nullable, udt_name
            FROM information_schema.columns 
            WHERE table_schema = $1 AND table_name = $2
        ";
        $res = pg_query_params($conn, $sql, [$schemaName, $tableName]);

        if (!$res) {
            throw new Exception(pg_last_error($conn));
        }

        $columns = [];
        while ($row = pg_fetch_assoc($res)) {
            $colName = $row['column_name'];
            $dataType = $row['data_type'];
            $udtName = $row['udt_name'];
            
            $enumValues = null;

            // Fetch ENUM values only for user-defined types
            if ($dataType === 'USER-DEFINED') {
                $enumSql = "SELECT unnest(enum_range(NULL::\"$schemaName\".\"$udtName\"))::varchar AS enum_value";
                $enumRes = @pg_query($conn, $enumSql);
                if ($enumRes) {
                    $enumValues = [];
                    while ($e = pg_fetch_assoc($enumRes)) {
                        $enumValues[] = $e['enum_value'];
                    }
                }
            }

            // Create standard array and append column name
            $colData = [
                'column_name' => $colName,
                'type' => $dataType,
                'not_null' => ($row['is_nullable'] === 'NO'),
                'display_name' => ucfirst(str_replace('_', ' ', $colName))
            ];

            if ($enumValues !== null) {
                $colData['enum_values'] = $enumValues;
            }

            // Append element to array to force PHP to output JSON Array
            $columns[] = $colData;
        }

        echo json_encode(['status' => 'success', 'columns' => $columns]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
    }
    exit;
}