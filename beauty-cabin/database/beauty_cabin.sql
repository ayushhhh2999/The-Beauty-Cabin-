-- The Beauty Cabin database
-- Import in phpMyAdmin (Import tab) or: mysql -u root < beauty_cabin.sql

CREATE DATABASE IF NOT EXISTS beauty_cabin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE beauty_cabin;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration INT UNSIGNED NOT NULL COMMENT 'minutes',
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('Pending','Confirmed','Cancelled','Completed') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slot (appointment_date, appointment_time),
    CONSTRAINT fk_appt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_appt_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO services (name, description, price, duration) VALUES
('Haircut',  'Professional cut and styling tailored to your face shape.', 300, 30),
('Hair Spa', 'Deep conditioning treatment that restores shine and softness.', 800, 60),
('Facial',   'Cleansing facial for fresh, glowing skin.', 700, 45),
('Manicure', 'Nail shaping, cuticle care and polish for beautiful hands.', 400, 30),
('Pedicure', 'Relaxing foot soak, scrub and polish.', 500, 40),
('Makeup',   'Occasion makeup by our expert artists.', 1500, 90);

-- Default admin: admin@beautycabin.com / admin123  (change after first login)
INSERT INTO admins (name, email, password) VALUES
('Admin', 'admin@beautycabin.com', '$2y$12$RU6.R7bXYQQ3FZX1IZCHpug1EbsSlpj/rj1IGz9p.AqCpLOncaki6');
