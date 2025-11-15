<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Debug Information for client_process.php</h1>";

// Test session
session_start();
echo "<h2>Session Status:</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Logged in: " . (isset($_SESSION['logged_in']) ? $_SESSION['logged_in'] : 'Not set') . "\n";
echo "User Level: " . (isset($_SESSION['user_level']) ? $_SESSION['user_level'] : 'Not set') . "\n";
echo "User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "\n";
echo "</pre>";

// Test database connection
echo "<h2>Database Connection:</h2>";
try {
    require_once '../config.php';
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Test a simple query
    $result = $con->query("SELECT COUNT(*) as count FROM projects");
    $row = $result->fetch_assoc();
    echo "<p>Projects in database: " . $row['count'] . "</p>";
    
    // Test project_id parameter
    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    echo "<p>Project ID from URL: " . $project_id . "</p>";
    
    if ($project_id > 0) {
        $stmt = $con->prepare("SELECT project FROM projects WHERE project_id = ?");
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo "<p style='color: green;'>✓ Project found: " . htmlspecialchars($row['project']) . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Project not found</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
}

// Test file permissions
echo "<h2>File Permissions:</h2>";
$paths_to_check = [
    '../config.php',
    '../uploads/proof_of_payments',
    'js/initial_budget_handler.js'
];

foreach ($paths_to_check as $path) {
    $fullPath = __DIR__ . '/' . $path;
    echo "<p>" . $path . ": ";
    if (file_exists($fullPath)) {
        echo "<span style='color: green;'>✓ Exists";
        if (is_readable($fullPath)) {
            echo " (Readable)";
        } else {
            echo " (Not Readable)";
        }
        echo "</span>";
    } else {
        echo "<span style='color: red;'>✗ Not found</span>";
    }
    echo "</p>";
}

// Test PHP version and extensions
echo "<h2>PHP Environment:</h2>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Required extensions:</p>";
$extensions = ['mysqli', 'session', 'json', 'mbstring'];
foreach ($extensions as $ext) {
    echo "<p>" . $ext . ": " . (extension_loaded($ext) ? "<span style='color: green;'>✓ Loaded</span>" : "<span style='color: red;'>✗ Not loaded</span>") . "</p>";
}

// Check error log location
echo "<h2>Error Logging:</h2>";
echo "<p>error_log setting: " . ini_get('error_log') . "</p>";
echo "<p>log_errors setting: " . (ini_get('log_errors') ? 'On' : 'Off') . "</p>";

// Test if we can write to logs directory
$logDir = __DIR__ . '/../logs';
if (is_dir($logDir)) {
    echo "<p>Logs directory: <span style='color: green;'>✓ Exists</span></p>";
    if (is_writable($logDir)) {
        echo "<p>Logs directory: <span style='color: green;'>✓ Writable</span></p>";
    } else {
        echo "<p>Logs directory: <span style='color: red;'>✗ Not writable</span></p>";
    }
} else {
    echo "<p>Logs directory: <span style='color: red;'>✗ Not found</span></p>";
}

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Upload this debug file to your online hosting</li>";
echo "<li>Access it with the same project_id: debug_client_process.php?project_id=YOUR_PROJECT_ID</li>";
echo "<li>Share the output with me to identify the issue</li>";
echo "</ol>";

?>
