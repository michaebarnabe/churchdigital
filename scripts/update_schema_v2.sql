-- update_schema_v2.sql
-- Add Member Fields
ALTER TABLE membros 
ADD COLUMN nacionalidade VARCHAR(50) DEFAULT 'Brasileira',
ADD COLUMN nome_pai VARCHAR(100),
ADD COLUMN nome_mae VARCHAR(100);

-- Create Admin Config for Backoffice Auth
CREATE TABLE IF NOT EXISTS admin_config (
    config_key VARCHAR(50) PRIMARY KEY,
    config_value TEXT
);

-- Insert Default Master Password (admin123) if not exists
-- Hash for 'admin123': $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT IGNORE INTO admin_config (config_key, config_value) VALUES 
('master_password_hash', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('2fa_enabled', '0'),
('2fa_secret', '');
