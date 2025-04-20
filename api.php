<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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
    
    // Skip header lines (starting with #)
    $dataLines = array_filter($lines, function($line) {
        return !str_starts_with(trim($line), '#');
    });
    
    foreach ($dataLines as $lineNumber => $line) {
        $fields = explode(':', $line);
        if (count($fields) >= 12) {
            $entries[] = [
                'lineNumber' => $lineNumber,
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
    if (!isset($lines[$lineNumber]) || str_starts_with(trim($lines[$lineNumber]), '#')) {
        return ['error' => 'Entry not found'];
    }
    
    unset($lines[$lineNumber]);
    if (file_put_contents(DB_FILE, implode('', $lines)) === false) {
        return ['error' => 'Failed to delete entry'];
    }
    
    createBackup();
    return ['success' => true];
}

function editEntry($lineNumber) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $requiredFields = ['name', 'ip', 'username', 'password', 'enablePassword', 'osType', 'access', 'clear', 'pollInterval', 'locationId', 'info', 'ticketId'];
    $missingFields = array_diff($requiredFields, array_keys($data));
    
    if (!empty($missingFields)) {
        return ['error' => 'Missing required fields: ' . implode(', ', $missingFields)];
    }
    
    $lines = file(DB_FILE);
    if (!isset($lines[$lineNumber]) || str_starts_with(trim($lines[$lineNumber]), '#')) {
        return ['error' => 'Entry not found'];
    }
    
    $lines[$lineNumber] = implode(':', [
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
    
    if (file_put_contents(DB_FILE, implode('', $lines)) === false) {
        return ['error' => 'Failed to update entry'];
    }
    
    createBackup();
    return ['success' => true];
}

function createBackup() {
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = HISTORY_DIR . '/backup_' . $timestamp . '.txt';
    copy(DB_FILE, $backupFile);
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api.php', '', $path);

switch ($method) {
    case 'GET':
        echo json_encode(getEntries());
        break;
    case 'POST':
        echo json_encode(addEntry());
        break;
    case 'DELETE':
        $lineNumber = intval(trim($path, '/'));
        echo json_encode(deleteEntry($lineNumber));
        break;
    case 'PUT':
        $lineNumber = intval(trim($path, '/'));
        echo json_encode(editEntry($lineNumber));
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
} 