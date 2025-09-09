<?php
// Temporary debug script - remove after testing
echo "<h2>Path Debugging</h2>";

echo "<h3>Current Directory</h3>";
echo "Current script directory: " . dirname(__FILE__) . "<br>";
echo "Current working directory: " . getcwd() . "<br>";

echo "<h3>Uploads Directory</h3>";
$uploadsDir = realpath('../uploads/');
echo "Raw path: ../uploads/<br>";
echo "Resolved path: " . ($uploadsDir ?: 'FAILED') . "<br>";

echo "<h3>Test Paths</h3>";
$testPaths = [
    '../uploads/proof_of_payments/test.pdf',
    'uploads/proof_of_payments/test.pdf',
    'proof_of_payments/test.pdf'
];

foreach ($testPaths as $path) {
    echo "<br>Testing: $path<br>";
    
    if (strpos($path, '../uploads/') === 0) {
        $resolved = realpath($path);
        echo "  - ../uploads/ pattern<br>";
        echo "  - Resolved: " . ($resolved ?: 'FAILED') . "<br>";
    } elseif (strpos($path, 'uploads/') === 0) {
        $resolved = realpath('../' . $path);
        echo "  - uploads/ pattern<br>";
        echo "  - Resolved: " . ($resolved ?: 'FAILED') . "<br>";
    } else {
        $resolved = realpath('../uploads/' . $path);
        echo "  - other pattern<br>";
        echo "  - Resolved: " . ($resolved ?: 'FAILED') . "<br>";
    }
    
    if ($resolved) {
        echo "  - File exists: " . (file_exists($resolved) ? 'Yes' : 'No') . "<br>";
        echo "  - Is within uploads: " . (strpos($resolved, $uploadsDir) === 0 ? 'Yes' : 'No') . "<br>";
    }
}

echo "<h3>Directory Contents</h3>";
$uploadsPath = '../uploads/';
if (is_dir($uploadsPath)) {
    echo "Uploads directory exists<br>";
    $contents = scandir($uploadsPath);
    echo "Contents: " . implode(', ', $contents) . "<br>";
    
    if (is_dir($uploadsPath . 'proof_of_payments/')) {
        $proofContents = scandir($uploadsPath . 'proof_of_payments/');
        echo "Proof of payments contents: " . implode(', ', $proofContents) . "<br>";
    } else {
        echo "Proof of payments directory does not exist<br>";
    }
} else {
    echo "Uploads directory does not exist<br>";
}
?>
