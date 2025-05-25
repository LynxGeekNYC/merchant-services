/* DATABASE */
CREATE DATABASE IF NOT EXISTS leadgen
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE leadgen;

/* LEADS TABLE */
CREATE TABLE IF NOT EXISTS leads (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_name    VARCHAR(100)  NOT NULL,
    business_name   VARCHAR(150)  NOT NULL,
    business_type   VARCHAR(100)  NOT NULL,
    address_line1   VARCHAR(150)  NOT NULL,
    address_line2   VARCHAR(150),
    city            VARCHAR(100)  NOT NULL,
    state           VARCHAR(50)   NOT NULL,
    zip             VARCHAR(20)   NOT NULL,
    phone           VARCHAR(25)   NOT NULL,
    email           VARCHAR(150)  NOT NULL,
    accepts_cc      ENUM('Yes','No') NOT NULL,
    monthly_volume  DECIMAL(12,2),
    avg_ticket      DECIMAL(12,2) NOT NULL,
    cc_statement    VARCHAR(255),
    extra_pdf       VARCHAR(255),
    ip_address      VARCHAR(45)   NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

/* ADMINS TABLE */
CREATE TABLE IF NOT EXISTS admins (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL
);

/* FIRST ADMIN  (change the hash later) */
INSERT INTO admins (username, password_hash)
VALUES ('admin',
'$2y$10$yq9P3T3wLzYOCx0lKQ3Rtu5GmGJeDQtp7CnXrO8JRwW4Fj9wWUtiO'); -- password = admin

/* echo password_hash('My$trongP4ss!', PASSWORD_DEFAULT);

