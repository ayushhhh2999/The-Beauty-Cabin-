CREATE DATABASE IF NOT EXISTS beauty_cabin_v2
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE beauty_cabin_v2;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('OWNER', 'MANAGER', 'WORKER', 'CUSTOMER') NOT NULL,
    singleton_role VARCHAR(7)
        GENERATED ALWAYS AS (
            CASE WHEN role IN ('OWNER', 'MANAGER') THEN role ELSE NULL END
        ) STORED,
    status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_singleton_role (singleton_role),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    address VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_customers_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS manager (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    aadhaar_number VARCHAR(20) NOT NULL,
    address VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_manager_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    image VARCHAR(255) NULL,
    status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_services_status (status),
    CONSTRAINT chk_services_price CHECK (price >= 0),
    CONSTRAINT chk_services_duration CHECK (duration_minutes > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS workers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    worker_code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    aadhaar_number VARCHAR(20) NOT NULL,
    address VARCHAR(500) NOT NULL,
    joining_date DATE NOT NULL,
    status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_workers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_workers_status (status),
    KEY idx_workers_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS worker_services (
    worker_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (worker_id, service_id),
    CONSTRAINT fk_worker_services_worker FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
    CONSTRAINT fk_worker_services_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    KEY idx_worker_services_service (service_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_number VARCHAR(24) NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    worker_id BIGINT UNSIGNED DEFAULT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(254) NOT NULL,
    customer_mobile VARCHAR(20) NOT NULL,
    customer_address VARCHAR(500) NOT NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED', 'NO_SHOW') NOT NULL DEFAULT 'PENDING',
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_appointments_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_appointments_worker FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE SET NULL,
    CONSTRAINT fk_appointments_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
    CONSTRAINT chk_appointments_time CHECK (end_time > start_time),
    KEY idx_appointments_customer_date (customer_id, appointment_date),
    KEY idx_appointments_worker_date_time (worker_id, appointment_date, start_time, end_time),
    KEY idx_appointments_service_date (service_id, appointment_date),
    KEY idx_appointments_status_date (status, appointment_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointment_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id BIGINT UNSIGNED NOT NULL,
    old_status ENUM('PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED', 'NO_SHOW') DEFAULT NULL,
    new_status ENUM('PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED', 'NO_SHOW') NOT NULL,
    changed_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_status_history_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_status_history_appointment_created (appointment_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS working_hours (
    weekday TINYINT UNSIGNED PRIMARY KEY COMMENT 'MySQL WEEKDAY(): Monday=0 through Sunday=6',
    opens_at TIME DEFAULT NULL,
    closes_at TIME DEFAULT NULL,
    is_closed BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT chk_working_hours_weekday CHECK (weekday BETWEEN 0 AND 6),
    CONSTRAINT chk_working_hours_range CHECK (
        (is_closed = TRUE AND opens_at IS NULL AND closes_at IS NULL)
        OR (is_closed = FALSE AND opens_at IS NOT NULL AND closes_at > opens_at)
    )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS salon_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;