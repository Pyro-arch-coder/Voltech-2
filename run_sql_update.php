<?php
require_once 'config.php';

// SQL command to add forecasted_cost column
$sql = "ALTER TABLE `projects` ADD COLUMN `forecasted_cost` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `budget`";

try {
    if (mysqli_query($con, $sql)) {
        echo "Success: forecasted_cost column added to projects table\n";
    } else {
        echo "Error: " . mysqli_error($con) . "\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

mysqli_close($con);
?>
