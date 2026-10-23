-- Migración 2 (recuperar contraseña + inicio de sesión con Google).
-- Para una base que YA tiene las tablas users y orders. Importar UNA sola vez en phpMyAdmin.

ALTER TABLE users MODIFY pass_hash VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN google_sub VARCHAR(64) NULL UNIQUE AFTER pass_hash;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_pr_hash (token_hash),
  KEY idx_pr_user (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;