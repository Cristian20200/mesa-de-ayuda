CREATE DATABASE IF NOT EXISTS mesa_de_ayuda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mesa_de_ayuda;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    area VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('requester', 'systems') NOT NULL DEFAULT 'requester',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('baja', 'media', 'alta', 'critica') NOT NULL DEFAULT 'media',
    status ENUM('abierto', 'en_progreso', 'esperando_usuario', 'resuelto', 'cerrado') NOT NULL DEFAULT 'abierto',
    scheduled_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_requester FOREIGN KEY (requester_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS ticket_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    responder_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_responses_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_responses_user FOREIGN KEY (responder_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS ticket_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS message_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attachments_message FOREIGN KEY (message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE
);

INSERT INTO users (full_name, email, area, password_hash, role)
VALUES (
    'Equipo de Sistemas',
    'sistemas@empresa.com',
    'Tecnología',
    '$2y$12$uVJggQGzxwGbLNbNn4Kch.EBNXQWEcSyeuC4JGMQAhc8/DYCcwObC',
    'systems'
)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);
