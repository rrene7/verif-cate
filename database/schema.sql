CREATE DATABASE IF NOT EXISTS verif_cate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE verif_cate;

-- Copia independiente del catálogo institucional de estructura-zonas.
-- Esta base no modifica ni depende de estructura_zonas_test en producción.
CREATE TABLE IF NOT EXISTS unit_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organizational_units (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id BIGINT UNSIGNED NULL,
  unit_type_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(50) NULL,
  name VARCHAR(200) NOT NULL,
  short_name VARCHAR(100) NULL,
  level INT NULL,
  is_operational TINYINT(1) NOT NULL DEFAULT 0,
  is_administrative TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  legacy_table VARCHAR(100) NULL,
  legacy_id VARCHAR(100) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT fk_org_parent FOREIGN KEY (parent_id) REFERENCES organizational_units(id),
  CONSTRAINT fk_org_type FOREIGN KEY (unit_type_id) REFERENCES unit_types(id),
  INDEX idx_org_parent (parent_id),
  INDEX idx_org_type (unit_type_id),
  INDEX idx_org_code (code),
  INDEX idx_org_status (status)
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
  national_directorate_id BIGINT UNSIGNED NOT NULL,
  zone_id BIGINT UNSIGNED NULL,
  area_id BIGINT UNSIGNED NULL,
  service_id BIGINT UNSIGNED NOT NULL,
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
  CONSTRAINT fk_request_directorate FOREIGN KEY (national_directorate_id) REFERENCES organizational_units(id),
  CONSTRAINT fk_request_zone FOREIGN KEY (zone_id) REFERENCES organizational_units(id),
  CONSTRAINT fk_request_area FOREIGN KEY (area_id) REFERENCES organizational_units(id),
  CONSTRAINT fk_request_service FOREIGN KEY (service_id) REFERENCES organizational_units(id),
  INDEX idx_request_directorate (national_directorate_id),
  INDEX idx_request_zone (zone_id),
  INDEX idx_request_area (area_id),
  INDEX idx_request_service (service_id)
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

INSERT IGNORE INTO unit_types (name, description) VALUES
('institucion', 'Institución principal'),
('direccion_nacional', 'Dirección nacional'),
('zona_policial', 'Zona policial'),
('area', 'Área regional, operativa o administrativa'),
('departamento', 'Departamento'),
('division', 'División'),
('seccion', 'Sección'),
('oficina', 'Oficina'),
('dependencia', 'Dependencia'),
('cuartel', 'Cuartel'),
('estacion', 'Estación'),
('subestacion', 'Subestación'),
('puesto', 'Puesto');
