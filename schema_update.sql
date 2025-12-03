-- Schema pour Messages Annonces.nc
-- Version 2.0 avec support annonces supprimées
-- Table des annonces
CREATE TABLE IF NOT EXISTS annonces (
    id VARCHAR(100) PRIMARY KEY,
    url VARCHAR(500),
    title VARCHAR(500),
    site VARCHAR(100) DEFAULT 'annonces.nc',
    description TEXT,
    is_deleted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY,
    user_name VARCHAR(255),
    name VARCHAR(255),
    photo_url TEXT,
    phone VARCHAR(50),
    facebook VARCHAR(255),
    whatsapp VARCHAR(50),
    commentaire TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table des conversations
CREATE TABLE IF NOT EXISTS conversations (
    id INT PRIMARY KEY,
    annonce_id VARCHAR(100),
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE
    SET
        NULL,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table des messages
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY,
    conversation_id INT NOT NULL,
    from_me BOOLEAN DEFAULT FALSE,
    message_text TEXT,
    message_date VARCHAR(100),
    message_datetime DATETIME,
    api_from_user_id INT,
    api_status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table des images de messages
CREATE TABLE IF NOT EXISTS message_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    full_url TEXT,
    local_path TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_message_image (message_id, full_url(255))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Index pour performances
CREATE INDEX idx_conversations_annonce ON conversations(annonce_id);

CREATE INDEX idx_conversations_user ON conversations(user_id);

CREATE INDEX idx_messages_conversation ON messages(conversation_id);

CREATE INDEX idx_messages_datetime ON messages(message_datetime);

CREATE INDEX idx_annonces_deleted ON annonces(is_deleted);