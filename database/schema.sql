CREATE DATABASE IF NOT EXISTS verif_cate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE verif_cate;

CREATE TABLE IF NOT EXISTS national_directorates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS zones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  national_directorate_id INT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_zone (national_directorate_id, name),
  CONSTRAINT fk_zone_directorate FOREIGN KEY (national_directorate_id) REFERENCES national_directorates(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS areas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zone_id INT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_area (zone_id, name),
  CONSTRAINT fk_area_zone FOREIGN KEY (zone_id) REFERENCES zones(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  area_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_service (area_id, name),
  CONSTRAINT fk_service_area FOREIGN KEY (area_id) REFERENCES areas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_number VARCHAR(40) NOT NULL UNIQUE,
  position_number VARCHAR(20) NOT NULL UNIQUE,
  rank_name VARCHAR(80) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  middle_name VARCHAR(80) NULL,
  last_name VARCHAR(80) NOT NULL,
  second_last_name VARCHAR(80) NULL,
  national_id VARCHAR(30) NOT NULL UNIQUE,
  promotion_type ENUM('OFICIAL','TROPA') NOT NULL,
  promotion_number VARCHAR(30) NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  national_directorate_id INT UNSIGNED NOT NULL,
  zone_id INT UNSIGNED NULL,
  area_id INT UNSIGNED NULL,
  service_id INT UNSIGNED NOT NULL,
  card_condition VARCHAR(50) NOT NULL,
  barcode_value VARCHAR(120) NULL,
  barcode_readable TINYINT(1) NOT NULL DEFAULT 1,
  card_front_path VARCHAR(255) NULL,
  card_back_path VARCHAR(255) NULL,
  person_with_card_path VARCHAR(255) NULL,
  loss_report_number VARCHAR(80) NULL,
  notes TEXT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'RECIBIDA',
  admin_observation TEXT NULL,
  submitted_at DATETIME NOT NULL,
  reviewed_at DATETIME NULL,
  reviewed_by VARCHAR(120) NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  UNIQUE KEY uq_barcode (barcode_value),
  CONSTRAINT fk_request_directorate FOREIGN KEY (national_directorate_id) REFERENCES national_directorates(id),
  CONSTRAINT fk_request_zone FOREIGN KEY (zone_id) REFERENCES zones(id),
  CONSTRAINT fk_request_area FOREIGN KEY (area_id) REFERENCES areas(id),
  CONSTRAINT fk_request_service FOREIGN KEY (service_id) REFERENCES services(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT UNSIGNED NOT NULL,
  previous_status VARCHAR(50) NULL,
  new_status VARCHAR(50) NOT NULL,
  observation TEXT NULL,
  changed_by VARCHAR(120) NOT NULL,
  changed_at DATETIME NOT NULL,
  CONSTRAINT fk_history_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO national_directorates (id, name) VALUES
(1, 'Dirección Nacional de Recursos Humanos');
INSERT IGNORE INTO zones (id, national_directorate_id, name) VALUES
(1, 1, 'Sede Central');
INSERT IGNORE INTO areas (id, zone_id, name) VALUES
(1, 1, 'Administración');
INSERT IGNORE INTO services (id, area_id, name) VALUES
(1, 1, 'Departamento por definir');
