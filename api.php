<?php
// Set timezone to Europe/Berlin
date_default_timezone_set('Europe/Berlin');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Add debug logging at the start of the file
function debugLog($message, $data = null) {
    try {
        $logFile = __DIR__ . '/debug.log';
        
        // Ensure the log file exists and is writable
        if (!file_exists($logFile)) {
            touch($logFile);
            chmod($logFile, 0666);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message";
        if ($data !== null) {
            $logMessage .= "\nData: " . print_r($data, true);
        }
        $logMessage .= "\n-------------------\n";
        
        error_log($logMessage, 3, $logFile);
        return true;
    } catch (Exception $e) {
        error_log("Failed to write to debug log: " . $e->getMessage());
        return false;
    }
}

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// File paths
define('DB_FILE', __DIR__ . '/db.txt');
define('HISTORY_DIR', __DIR__ . '/history');

// Ensure history directory exists
if (!file_exists(HISTORY_DIR)) {
    mkdir(HISTORY_DIR, 0775, true);
}

// Create db.txt if it doesn't exist
if (!file_exists(DB_FILE)) {
    file_put_contents(DB_FILE, '');
}

function getEntries() {
    if (!file_exists(DB_FILE)) {
        return [];
    }
    
    $entries = [];
    $lines = file(DB_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $lineNumber => $line) {
        $line = trim($line);
        // Skip empty lines, comments and header line
        if (empty($line) || str_starts_with($line, '#') || str_starts_with($line, 'HDR:')) {
            continue;
        }
        
        $fields = explode(':', $line);
        if (count($fields) >= 12) {
            $entries[] = [
                'lineNumber' => $lineNumber,  // Store actual file line number
                'name' => $fields[0],
                'ip' => $fields[1],
                'username' => $fields[2],
                'password' => $fields[3],
                'enablePassword' => $fields[4],
                'osType' => $fields[5],
                'access' => $fields[6],
                'clear' => $fields[7],
                'pollInterval' => $fields[8],
                'locationId' => $fields[9],
                'info' => $fields[10],
                'ticketId' => $fields[11]
            ];
        }
    }
    
    return $entries;
}

function addEntry() {
    // Get the raw input
    $input = file_get_contents('php://input');
    debugLog("Received raw input for new entry", ['input' => $input]);
    
    // Parse the input data
    $data = json_decode($input, true);
    if (!$data) {
        debugLog("Failed to parse JSON input");
        return ['error' => 'Invalid JSON data'];
    }

    // Get the insertion line number if provided
    $insertAfterLine = isset($data['insertAfterLine']) ? $data['insertAfterLine'] : null;
    debugLog("Insert after line", ['insertAfterLine' => $insertAfterLine]);

    // Create the new entry line
    $entry = implode(':', [
        $data['name'],
        $data['ip'],
        $data['username'],
        $data['password'],
        $data['enablePassword'],
        $data['osType'],
        $data['access'],
        $data['clear'],
        $data['pollInterval'],
        $data['locationId'],
        $data['info'],
        $data['ticketId']
    ]) . "\n";

    if ($insertAfterLine !== null) {
        // Read all lines
        $lines = file(DB_FILE, FILE_IGNORE_NEW_LINES);
        $headerLines = [];
        $dataLines = [];
        $inHeader = true;

        // Separate header and data lines
        foreach ($lines as $line) {
            if ($inHeader && (str_starts_with(trim($line), '#') || str_starts_with(trim($line), 'HDR:'))) {
                $headerLines[] = $line;
            } else {
                $inHeader = false;
                $dataLines[] = $line;
            }
        }

        // Find the actual position to insert (accounting for header lines)
        $insertPosition = $insertAfterLine - count($headerLines) + 1;
        array_splice($dataLines, $insertPosition, 0, trim($entry));

        // Combine everything back
        $newContent = implode("\n", $headerLines) . "\n" . implode("\n", $dataLines) . "\n";
        
        if (file_put_contents(DB_FILE, $newContent) === false) {
            debugLog("Failed to write to database");
            return ['error' => 'Failed to write to database'];
        }
    } else {
        // Append to end of file if no insertion point specified
        if (file_put_contents(DB_FILE, $entry, FILE_APPEND) === false) {
            debugLog("Failed to write to database");
            return ['error' => 'Failed to write to database'];
        }
    }
    
    createBackup();
    debugLog("Entry added successfully");
    return ['success' => true];
}

function deleteEntry($lineNumber) {
    debugLog("Starting deletion process", ['lineNumber' => $lineNumber]);
    
    // Read all lines from the file
    $lines = file(DB_FILE, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        $error = error_get_last();
        debugLog("Failed to read file", ['error' => $error]);
        return ['error' => 'Failed to read database file'];
    }
    
    debugLog("Current file contents", ['total_lines' => count($lines)]);
    
    // Find the actual line to delete
    $targetLine = (int)$lineNumber;
    if (!isset($lines[$targetLine])) {
        debugLog("Line not found", ['targetLine' => $targetLine, 'total_lines' => count($lines)]);
        return ['error' => 'Entry not found'];
    }
    
    debugLog("Found line to delete", ['line_content' => $lines[$targetLine]]);
    
    // Remove the line
    unset($lines[$targetLine]);
    
    // Write the file back
    $newContent = implode("\n", $lines) . "\n";
    $writeResult = file_put_contents(DB_FILE, $newContent);
    
    if ($writeResult === false) {
        $error = error_get_last();
        debugLog("Failed to write file", ['error' => $error]);
        return ['error' => 'Failed to delete entry'];
    }
    
    debugLog("Successfully deleted entry", [
        'lineNumber' => $lineNumber,
        'remaining_lines' => count($lines)
    ]);
    
    createBackup();
    return ['success' => true];
}

function editEntry($lineNumber) {
    debugLog("editEntry called", ['lineNumber' => $lineNumber]);
    
    $data = json_decode(file_get_contents('php://input'), true);
    debugLog("Received data for edit", $data);
    
    if ($data === null) {
        debugLog("Invalid JSON data received");
        return ['error' => 'Invalid JSON data received'];
    }
    
    $requiredFields = ['name', 'ip', 'username', 'password', 'enablePassword', 'osType', 'access', 'clear', 'pollInterval', 'locationId', 'info', 'ticketId'];
    $missingFields = array_diff($requiredFields, array_keys($data));
    
    if (!empty($missingFields)) {
        debugLog("Missing fields", ['missing' => $missingFields]);
        return ['error' => 'Missing required fields: ' . implode(', ', $missingFields)];
    }
    
    // Read file contents
    $lines = file(DB_FILE, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        debugLog("Failed to read database file");
        return ['error' => 'Failed to read database file'];
    }
    debugLog("Current file contents", ['lines' => $lines]);
    
    // Check if line number exists and is valid
    if (!isset($lines[$lineNumber])) {
        debugLog("Line number not found", ['lineNumber' => $lineNumber, 'totalLines' => count($lines)]);
        return ['error' => 'Entry not found at line ' . $lineNumber];
    }
    
    // Check if the line is a comment or header
    if (str_starts_with(trim($lines[$lineNumber]), '#') || str_starts_with(trim($lines[$lineNumber]), 'HDR:')) {
        debugLog("Attempted to edit header/comment line", ['line' => $lines[$lineNumber]]);
        return ['error' => 'Cannot edit header or comment line'];
    }
    
    // Create new line
    $newLine = implode(':', [
        trim($data['name']),
        trim($data['ip']),
        trim($data['username']),
        $data['password'],
        $data['enablePassword'],
        trim($data['osType']),
        trim($data['access']),
        trim($data['clear']),
        trim($data['pollInterval']),
        trim($data['locationId']),
        trim($data['info']),
        trim($data['ticketId'])
    ]);
    debugLog("New line created", ['newLine' => $newLine]);
    
    // Replace the line at exact position
    $lines[$lineNumber] = $newLine;
    
    // Write back to file
    $success = file_put_contents(DB_FILE, implode("\n", $lines) . "\n");
    debugLog("File write attempt", ['success' => $success]);
    
    if ($success === false) {
        debugLog("Failed to write to database");
        return ['error' => 'Failed to update entry in database'];
    }
    
    createBackup();
    debugLog("Edit successful");
    return ['success' => true];
}

function createBackup() {
    // Ensure we're using the correct timezone
    $timestamp = date('Y_m_d_H_i', time());  // Using time() to be explicit about using current time
    $backupFile = HISTORY_DIR . '/db_' . $timestamp;
    
    // Ensure history directory exists
    if (!file_exists(HISTORY_DIR)) {
        mkdir(HISTORY_DIR, 0777, true);
    }
    
    // Create backup
    if (!copy(DB_FILE, $backupFile)) {
        debugLog("Failed to create backup", [
            'source' => DB_FILE,
            'destination' => $backupFile,
            'error' => error_get_last()
        ]);
        return false;
    }
    
    debugLog("Backup created successfully", [
        'file' => $backupFile,
        'timestamp' => date('Y-m-d H:i:s', time())  // Log the exact time with timezone
    ]);
    return true;
}

function getBackups() {
    $backups = [];
    
    // Ensure history directory exists
    if (!file_exists(HISTORY_DIR)) {
        mkdir(HISTORY_DIR, 0777, true);
        return $backups;
    }
    
    // Get all backup files
    $files = glob(HISTORY_DIR . '/db_*');
    debugLog("Found backup files", ['files' => $files]);
    
    if ($files === false || empty($files)) {
        debugLog("No backup files found");
        return $backups;
    }
    
    foreach ($files as $file) {
        $filename = basename($file);
        // Extract date from filename (db_YYYY_MM_DD_HH_mm)
        if (preg_match('/db_(\d{4})_(\d{2})_(\d{2})_(\d{2})_(\d{2})/', $filename, $matches)) {
            $datetime = sprintf('%s-%s-%s %s:%s:00',
                $matches[1], // Year
                $matches[2], // Month
                $matches[3], // Day
                $matches[4], // Hour
                $matches[5]  // Minute
            );
            
            $size = filesize($file);
            $sizeKB = number_format($size / 1024, 2); // Format to 2 decimal places
            
            $backups[] = [
                'filename' => $filename,
                'timestamp' => $datetime,
                'size' => $sizeKB
            ];
            debugLog("Added backup", [
                'filename' => $filename,
                'timestamp' => $datetime,
                'size' => $sizeKB
            ]);
        }
    }
    
    // Sort backups by timestamp descending (newest first)
    usort($backups, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    debugLog("Final backup list", $backups);
    return $backups;
}

function restoreBackup($filename) {
    $backupFile = HISTORY_DIR . '/' . $filename;
    
    // Validate filename format
    if (!preg_match('/^db_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}$/', $filename)) {
        debugLog("Invalid backup filename", ['filename' => $filename]);
        return ['error' => 'Invalid backup filename'];
    }
    
    // Check if backup file exists
    if (!file_exists($backupFile)) {
        debugLog("Backup file not found", ['path' => $backupFile]);
        return ['error' => 'Backup file not found'];
    }
    
    // Create backup of current state before restore
    createBackup();
    
    // Restore from backup
    if (!copy($backupFile, DB_FILE)) {
        debugLog("Failed to restore backup", [
            'source' => $backupFile,
            'destination' => DB_FILE,
            'error' => error_get_last()
        ]);
        return ['error' => 'Failed to restore backup'];
    }
    
    debugLog("Backup restored successfully", ['filename' => $filename]);
    return ['success' => true];
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api.php', '', $path);

debugLog("Incoming request", [
    'method' => $method,
    'path' => $path,
    'request_uri' => $_SERVER['REQUEST_URI'],
    'raw_input' => file_get_contents('php://input'),
    'server_vars' => [
        'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? '',
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? '',
        'CONTENT_LENGTH' => $_SERVER['CONTENT_LENGTH'] ?? ''
    ]
]);

// Parse path segments
$pathSegments = array_values(array_filter(explode('/', $path)));
$resource = $pathSegments[0] ?? '';
$id = $pathSegments[1] ?? null;

switch ($method) {
    case 'GET':
        if ($resource === 'backups') {
            echo json_encode(getBackups());
        } else {
            echo json_encode(getEntries());
        }
        break;
    case 'POST':
        if ($resource === 'backups' && $id) {
            echo json_encode(restoreBackup($id));
        } else {
            echo json_encode(addEntry());
        }
        break;
    case 'DELETE':
        if ($id === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Line number is required for DELETE']);
            break;
        }
        echo json_encode(deleteEntry($id));
        break;
    case 'PUT':
        if ($id === null) {
            debugLog("PUT request missing line number", $_SERVER);
            http_response_code(400);
            echo json_encode(['error' => 'Line number is required for PUT']);
            break;
        }
        $result = editEntry($id);
        debugLog("Edit result", $result);
        echo json_encode($result);
        break;
    case 'OPTIONS':
        // Handle CORS preflight request
        http_response_code(200);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
} 