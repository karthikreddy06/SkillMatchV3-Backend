CREATE TABLE IF NOT EXISTS download_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seeker_id INT NOT NULL,
    downloader_id INT NOT NULL,
    file VARCHAR(255) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (seeker_id),
    INDEX (downloader_id)
);
