-- foto-doksli schema
-- Jalankan sekali di database yang sudah dibuat via CloudPanel

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) DEFAULT NULL, -- nama fisik di disk, NULL kalau folder
    is_folder TINYINT(1) NOT NULL DEFAULT 0,
    size BIGINT DEFAULT 0,
    mime_type VARCHAR(150) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed user: username "shania", password "shaniapassword"
-- (hash sudah di-generate pakai password_hash bcrypt PHP)
INSERT INTO users (username, password_hash)
VALUES ('shania', '$2y$12$K7cwY/s.R5L1SWafFGa3iuhWDmUSPtkddF0T1AC20XO/Dt49lHTba')
ON DUPLICATE KEY UPDATE username = username;
