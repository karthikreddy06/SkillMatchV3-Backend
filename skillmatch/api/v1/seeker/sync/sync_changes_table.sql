CREATE TABLE IF NOT EXISTS sync_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seeker_id INT NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    row_id INT NOT NULL,
    operation ENUM('insert','update','delete') NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (seeker_id),
    INDEX (table_name)
);
