CREATE TABLE IF NOT EXISTS local_files (
    name VARCHAR(60) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
