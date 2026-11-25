CREATE DATABASE IF NOT EXISTS iqms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iqms;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS system_settings, queue_logs, queues, counters, immigration_services, applicants, users, activity_logs;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(120)  NOT NULL,
    username    VARCHAR(60)   NOT NULL,
    email       VARCHAR(120)  NULL,
    password    VARCHAR(255)  NOT NULL,
    role        ENUM('admin','officer') NOT NULL DEFAULT 'officer',
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_role (role),
    KEY idx_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE applicants (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name             VARCHAR(150)  NOT NULL,
    passport_number       VARCHAR(30)    NULL,
    id_number             VARCHAR(30)    NULL,
    phone                 VARCHAR(30)    NULL,
    email                 VARCHAR(120)   NULL,
    nationality           VARCHAR(80)    NULL,
    date_of_birth         DATE           NULL,
    registered_by_user    INT UNSIGNED   NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_applicants_passport (passport_number),
    UNIQUE KEY uq_applicants_id_number (id_number),
    KEY idx_applicants_name (full_name),
    KEY idx_applicants_created (created_at),
    CONSTRAINT fk_applicants_reguser FOREIGN KEY (registered_by_user)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE immigration_services (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120)  NOT NULL,
    code            CHAR(2)       NOT NULL COMMENT 'Queue prefix, e.g. P, V, W',
    description     VARCHAR(255)  NULL,
    est_minutes     SMALLINT UNSIGNED NOT NULL DEFAULT 15 COMMENT 'Estimated service minutes per applicant',
    active          TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_services_code (code),
    UNIQUE KEY uq_services_name (name),
    KEY idx_services_active (active)
) ENGINE=InnoDB;

CREATE TABLE counters (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    counter_number  VARCHAR(20)   NOT NULL,
    location        VARCHAR(120)  NULL,
    status          ENUM('available','busy','closed') NOT NULL DEFAULT 'available',
    active          TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_counters_number (counter_number),
    KEY idx_counters_status (status)
) ENGINE=InnoDB;

CREATE TABLE queues (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_number        VARCHAR(10)   NOT NULL COMMENT 'e.g. P001',
    service_id          INT UNSIGNED  NOT NULL,
    applicant_id        INT UNSIGNED  NULL,
    counter_id          INT UNSIGNED  NULL,
    assigned_officer_id INT UNSIGNED  NULL,
    status              ENUM('waiting','called','serving','completed','skipped','cancelled')
                                      NOT NULL DEFAULT 'waiting',
    operating_date      DATE          NOT NULL,
    issued_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    called_at           TIMESTAMP NULL DEFAULT NULL,
    serving_at          TIMESTAMP NULL DEFAULT NULL,
    completed_at        TIMESTAMP NULL DEFAULT NULL,
    cancelled_at        TIMESTAMP NULL DEFAULT NULL,
    cancellation_reason VARCHAR(255)  NULL,
    notes               VARCHAR(255)  NULL,
    UNIQUE KEY uq_queues_service_date (service_id, operating_date, queue_number),
    KEY idx_queues_status (status),
    KEY idx_queues_operating_date (operating_date),
    KEY idx_queues_counter (counter_id),
    KEY idx_queues_applicant (applicant_id),
    KEY idx_queues_officer (assigned_officer_id),
    CONSTRAINT fk_queues_service  FOREIGN KEY (service_id)
        REFERENCES immigration_services(id) ON DELETE CASCADE,
    CONSTRAINT fk_queues_applicant FOREIGN KEY (applicant_id)
        REFERENCES applicants(id) ON DELETE SET NULL,
    CONSTRAINT fk_queues_counter   FOREIGN KEY (counter_id)
        REFERENCES counters(id) ON DELETE SET NULL,
    CONSTRAINT fk_queues_officer  FOREIGN KEY (assigned_officer_id)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE queue_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id    INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(50)  NOT NULL COMMENT 'issued, called, serving, completed, skipped, cancelled, recalled, reset',
    details     VARCHAR(255) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_queue_logs_queue (queue_id),
    KEY idx_queue_logs_created (created_at),
    CONSTRAINT fk_queue_logs_queue FOREIGN KEY (queue_id)
        REFERENCES queues(id) ON DELETE CASCADE,
    CONSTRAINT fk_queue_logs_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    username    VARCHAR(60)  NULL,
    action      VARCHAR(80)  NOT NULL,
    details     VARCHAR(255) NULL,
    ip_address  VARCHAR(45)  NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_user (user_id),
    KEY idx_activity_action (action),
    KEY idx_activity_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE system_settings (
    setting_key   VARCHAR(60)  NOT NULL PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO users (full_name, username, email, password, role, status) VALUES
('System Administrator', 'admin',   'admin@iqms.local',   '$2a$12$28NDeM7KgYxX8p6OUsvok.LgNm/7972edqeIZwvYLcQlgike.Ic6G', 'admin',   'active'),
('Sarah Okafor',         'officer1','officer1@iqms.local', '$2a$12$8uOHWNTT37Dsfm0M.nAPReIHEfGn1E2XXnutMu3o1DRd21wWLueKq', 'officer', 'active'),
('Daniel Mensah',        'officer2','officer2@iqms.local', '$2a$12$C3qy3p5PxVq3ZmCE84MW1ulEBOrzg9erquAdv3XFTQpKLiJFP740W', 'officer', 'active'),
('Amina Diallo',         'officer3','officer3@iqms.local', '$2a$12$dWGz4pu/ntjHiCaNBacTFulP/xF3I5Te/nG1XXWvkWw5A.3t2AYLW', 'officer', 'active');

INSERT INTO immigration_services (name, code, description, est_minutes, active) VALUES
('Passport Application',  'P', 'New passport applications',                15, 1),
('Passport Renewal',      'R', 'Renewal of existing passports',            10, 1),
('Visa Application',      'V', 'New visa applications',                    20, 1),
('Visa Extension',        'E', 'Extension of existing visas',              15, 1),
('Work Permit',           'W', 'Employment and work permits',             20, 1),
('Student Pass',          'S', 'Student entry permits',                    15, 1),
('Permanent Residence',   'M', 'Permanent residence applications',         30, 1),
('Other Immigration',     'O', 'Miscellaneous immigration services',      20, 1);

INSERT INTO counters (counter_number, location, status, active) VALUES
('01','Main Hall - Window 1','available',1),
('02','Main Hall - Window 2','available',1),
('03','Main Hall - Window 3','available',1),
('04','Second Hall - Window 1','available',1),
('05','Second Hall - Window 2','available',1),
('06','Second Hall - Window 3','available',1);

INSERT INTO system_settings (setting_key, setting_value) VALUES
('office_name',      'National Immigration Office'),
('daily_reset_time', '00:00:00'),
('avg_service_min',  '15'),
('next_queue_poll',  '3');
