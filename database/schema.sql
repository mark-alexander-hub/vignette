-- God's Eye OSINT Platform — Database Schema
-- Run: mysql -u mark -p vignette_db < schema.sql

CREATE DATABASE IF NOT EXISTS vignette_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vignette_db;

-- Searches table
CREATE TABLE IF NOT EXISTS searches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query_value VARCHAR(255) NOT NULL,
    query_type ENUM('name','email','phone','username','ip','domain') NOT NULL,
    bulk_id VARCHAR(36) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_query_type (query_type),
    INDEX idx_created_at (created_at),
    INDEX idx_bulk_id (bulk_id)
) ENGINE=InnoDB;

-- Raw data from each source
CREATE TABLE IF NOT EXISTS data_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    search_id INT NOT NULL,
    source_name VARCHAR(100) NOT NULL,
    raw_data JSON,
    status ENUM('success','error','timeout','skipped') DEFAULT 'success',
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (search_id) REFERENCES searches(id) ON DELETE CASCADE,
    INDEX idx_search_source (search_id, source_name)
) ENGINE=InnoDB;

-- Aggregated profiles
CREATE TABLE IF NOT EXISTS profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    search_id INT NOT NULL,
    display_name VARCHAR(255),
    avatar_url TEXT,
    location VARCHAR(255),
    bio TEXT,
    known_emails JSON,
    known_usernames JSON,
    social_links JSON,
    ai_summary TEXT,
    risk_score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (search_id) REFERENCES searches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Intelligence reports
CREATE TABLE IF NOT EXISTS intelligence_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    search_id INT NOT NULL,
    risk_score INT DEFAULT 0,
    summary TEXT,
    model_used VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (search_id) REFERENCES searches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Watch list
CREATE TABLE IF NOT EXISTS watchlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query_value VARCHAR(255) NOT NULL,
    query_type ENUM('name','email','phone','username','ip','domain') NOT NULL,
    last_checked TIMESTAMP NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Search history / saved profiles
CREATE TABLE IF NOT EXISTS saved_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    search_id INT NOT NULL,
    label VARCHAR(255),
    notes TEXT,
    tags VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (search_id) REFERENCES searches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Conversations (for AI chat)
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    search_id INT NOT NULL,
    user_message TEXT,
    ai_response TEXT,
    model_used VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (search_id) REFERENCES searches(id) ON DELETE CASCADE
) ENGINE=InnoDB;
