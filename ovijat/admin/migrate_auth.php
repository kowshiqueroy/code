<?php
/**
 * OVIJAT GROUP — Authentication Consolidation Migration
 * Migrates legacy 'admins' table users to 'admin_users' table.
 */
require_once dirname(__DIR__).'/includes/config.php';

echo "<h2>Starting Auth Migration...</h2>";

try {
    // 1. Check if legacy 'admins' table exists
    $stmt = db()->query("SHOW TABLES LIKE 'admins'");
    if (!$stmt->fetch()) {
        die("<p style='color:orange'>Legacy 'admins' table not found. Migration already complete?</p>");
    }

    // 2. Fetch all legacy admins
    $legacyAdmins = db()->query("SELECT * FROM admins")->fetchAll();
    echo "<p>Found " . count($legacyAdmins) . " legacy admins.</p>";

    $migratedCount = 0;
    $skippedCount = 0;

    foreach ($legacyAdmins as $admin) {
        // Check if username already exists in admin_users
        $check = db()->prepare("SELECT id FROM admin_users WHERE username = ?");
        $check->execute([$admin['username']]);
        
        if ($check->fetch()) {
            echo "<li>Skipping '{$admin['username']}': already exists in admin_users.</li>";
            $skippedCount++;
            continue;
        }

        // Insert into admin_users (assuming legacy 'admins' only had id, username, password)
        // Defaulting role to 'superadmin' for legacy users and active to 1.
        $insert = db()->prepare("INSERT INTO admin_users (username, password, role, active) VALUES (?, ?, ?, ?)");
        $insert->execute([
            $admin['username'],
            $admin['password'],
            'superadmin',
            1
        ]);
        
        echo "<li>Migrated '{$admin['username']}' to admin_users.</li>";
        $migratedCount++;
    }

    echo "<h3>Migration Result:</h3>";
    echo "<ul><li>Migrated: $migratedCount</li><li>Skipped: $skippedCount</li></ul>";

    // 3. Rename/Backup the old table instead of deleting immediately for safety
    db()->exec("RENAME TABLE admins TO admins_backup_" . date('Ymd'));
    echo "<p style='color:green'>Success! 'admins' table has been renamed to 'admins_backup_" . date('Ymd') . "'.</p>";
    echo "<p>Please verify the login works, then manually delete the backup table.</p>";

} catch (Exception $e) {
    die("<p style='color:red'>Migration Failed: " . $e->getMessage() . "</p>");
}
