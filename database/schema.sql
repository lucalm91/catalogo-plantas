CREATE TABLE IF NOT EXISTS plants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner VARCHAR(64) NOT NULL,
  legacy_num INT NOT NULL,
  identificacion TEXT NOT NULL,
  zona VARCHAR(191) NOT NULL,
  estado TEXT NOT NULL,
  descripcion TEXT NOT NULL,
  riego VARCHAR(191) NOT NULL DEFAULT '',
  sistema_riego VARCHAR(191) NOT NULL DEFAULT '',
  orden INT NOT NULL DEFAULT 0,
  orden_zona INT NOT NULL DEFAULT 9999,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plants_owner_num (owner, legacy_num),
  KEY idx_plants_owner_zone_order (owner, zona, orden),
  KEY idx_plants_owner_zone_order_zone (owner, orden_zona)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plant_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plant_id BIGINT UNSIGNED NOT NULL,
  image_path VARCHAR(512) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plant_images_path (plant_id, image_path),
  KEY idx_plant_images_order (plant_id, sort_order),
  CONSTRAINT fk_plant_images_plant
    FOREIGN KEY (plant_id) REFERENCES plants(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plant_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner VARCHAR(64) NOT NULL,
  plant_num INT NOT NULL,
  fecha DATETIME NOT NULL,
  usuario VARCHAR(64) NOT NULL,
  accion VARCHAR(191) NOT NULL,
  detalles TEXT NULL,
  old_value MEDIUMTEXT NULL,
  new_value MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_history_owner_plant_fecha (owner, plant_num, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
