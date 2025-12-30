<?php
// resume_audit_helpers.php
// Helpers to ensure and record download audit entries

/**
 * Ensure the download_audit table exists
 * @param mysqli $conn
 */
function ensure_download_audit_table_exists($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS download_audit (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seeker_id INT NOT NULL,
        file_name VARCHAR(500) NOT NULL,
        downloaded_by INT NOT NULL,
        downloader_role VARCHAR(50) NOT NULL,
        reason VARCHAR(255) DEFAULT NULL,
        ip_address VARCHAR(50) DEFAULT NULL,
        user_agent VARCHAR(1000) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (seeker_id),
        INDEX (downloaded_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql);
}

/**
 * Record a download audit
 */
function record_resume_download($conn, $seeker_id, $file_name, $downloaded_by, $downloader_role = 'employer', $reason = null) {
    ensure_download_audit_table_exists($conn);

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $conn->prepare("INSERT INTO download_audit (seeker_id, file_name, downloaded_by, downloader_role, reason, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("record_resume_download prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("isissss", $seeker_id, $file_name, $downloaded_by, $downloader_role, $reason, $ip, $ua);
    $ok = $stmt->execute();
    if (!$ok) {
        error_log("record_resume_download execute failed: " . $stmt->error);
    }
    $stmt->close();
    return $ok;
}
