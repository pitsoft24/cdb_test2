<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Add debug logging at the start of the file
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= "\nData: " . print_r($data, true);
    }
    $logMessage .= "\n-------------------\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
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
    $data = json_decode(file_get_contents('php://input'), true);
    
    $requiredFields = ['name', 'ip', 'username', 'password', 'enablePassword', 'osType', 'access', 'clear', 'pollInterval', 'locationId', 'info', 'ticketId'];
    $missingFields = array_diff($requiredFields, array_keys($data));
    
    if (!empty($missingFields)) {
        return ['error' => 'Missing required fields: ' . implode(', ', $missingFields)];
    }
    
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
    
    if (file_put_contents(DB_FILE, $entry, FILE_APPEND) === false) {
        return ['error' => 'Failed to write to database'];
    }
    
    createBackup();
    return ['success' => true];
}

function deleteEntry($lineNumber) {
    $lines = file(DB_FILE);
    $dataLines = array_filter($lines, function($line) {
        return !str_starts_with(trim($line), '#');
    });
    $dataLines = array_values($dataLines);
    
    if (!isset($dataLines[$lineNumber])) {
        return ['error' => 'Entry not found'];
    }
    
    // Get all header lines
    $headerLines = array_filter($lines, function($line) {
        return str_starts_with(trim($line), '#');
    });
    
    // Remove the entry from data lines
    unset($dataLines[$lineNumber]);
    
    // Combine headers and remaining data
    $newContent = implode('', $headerLines) . implode('', $dataLines);
    
    if (file_put_contents(DB_FILE, $newContent) === false) {
        return ['error' => 'Failed to delete entry'];
    }
    
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
    $timestamp = date('Y_m_d_H_i');
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
        'file' => $backupFile
    ]);
    return true;
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api.php', '', $path);

debugLog("Incoming request", [
    'method' => $method,
    'path' => $path,
    'request_uri' => $_SERVER['REQUEST_URI'],
    'raw_input' => file_get_contents('php://input')
]);

// Get the line number from the path for PUT and DELETE requests
$lineNumber = null;
if ($method === 'PUT' || $method === 'DELETE') {
    // Remove any trailing slashes and split the path
    $pathParts = explode('/', trim($path, '/'));
    $lineNumber = isset($pathParts[count($pathParts) - 1]) ? intval($pathParts[count($pathParts) - 1]) : null;
    debugLog("Extracted line number", ['lineNumber' => $lineNumber, 'pathParts' => $pathParts]);
}

switch ($method) {
    case 'GET':
        echo json_encode(getEntries());
        break;
    case 'POST':
        echo json_encode(addEntry());
        break;
    case 'DELETE':
        if ($lineNumber === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Line number is required for DELETE']);
            break;
        }
        echo json_encode(deleteEntry($lineNumber));
        break;
    case 'PUT':
        if ($lineNumber === null) {
            debugLog("PUT request missing line number", $_SERVER);
            http_response_code(400);
            echo json_encode(['error' => 'Line number is required for PUT']);
            break;
        }
        $result = editEntry($lineNumber);
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