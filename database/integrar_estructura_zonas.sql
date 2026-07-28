-- INTEGRACIÓN LOCAL: estructura_zonas_test -> verif_cate
-- La base fuente se consulta únicamente con SELECT; no se modifica ni se elimina.
-- Haga respaldo antes de ejecutar. Diseñado para la instalación actual sin solicitudes reales.

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS verif_cate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE verif_cate;

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
  INDEX idx_org_status (status)
) ENGINE=InnoDB;

-- Reemplaza solamente la COPIA dentro de verif_cate.
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE organizational_units;
TRUNCATE TABLE unit_types;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO unit_types (id, name, description, created_at, updated_at)
SELECT id, name, description, created_at, updated_at
FROM estructura_zonas_test.unit_types;

-- Primero se insertan sin parent_id para respetar todas las relaciones jerárquicas.
INSERT INTO organizational_units (
  id, parent_id, unit_type_id, code, name, short_name, level,
  is_operational, is_administrative, status, legacy_table, legacy_id,
  created_at, updated_at
)
SELECT
  id, NULL, unit_type_id, code, name, short_name, level,
  is_operational, is_administrative, status, legacy_table, legacy_id,
  created_at, updated_at
FROM estructura_zonas_test.organizational_units;

UPDATE organizational_units destino
JOIN estructura_zonas_test.organizational_units fuente ON fuente.id = destino.id
SET destino.parent_id = fuente.parent_id;

-- Adaptar la tabla requests creada por la versión anterior.
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE requests
  DROP FOREIGN KEY fk_request_directorate,
  DROP FOREIGN KEY fk_request_zone,
  DROP FOREIGN KEY fk_request_area,
  DROP FOREIGN KEY fk_request_service;

ALTER TABLE requests
  MODIFY national_directorate_id BIGINT UNSIGNED NOT NULL,
  MODIFY zone_id BIGINT UNSIGNED NULL,
  MODIFY area_id BIGINT UNSIGNED NULL,
  MODIFY service_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE requests
  ADD CONSTRAINT fk_request_directorate FOREIGN KEY (national_directorate_id) REFERENCES organizational_units(id),
  ADD CONSTRAINT fk_request_zone FOREIGN KEY (zone_id) REFERENCES organizational_units(id),
  ADD CONSTRAINT fk_request_area FOREIGN KEY (area_id) REFERENCES organizational_units(id),
  ADD CONSTRAINT fk_request_service FOREIGN KEY (service_id) REFERENCES organizational_units(id);
SET FOREIGN_KEY_CHECKS = 1;

-- Los catálogos provisionales ya no son necesarios dentro de verif_cate.
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS areas;
DROP TABLE IF EXISTS zones;
DROP TABLE IF EXISTS national_directorates;

SELECT
  (SELECT COUNT(*) FROM unit_types) AS tipos_copiados,
  (SELECT COUNT(*) FROM organizational_units) AS unidades_copiadas;
