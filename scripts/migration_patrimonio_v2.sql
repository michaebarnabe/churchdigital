-- Migration V2: Módulo de Patrimônio

CREATE TABLE IF NOT EXISTS `patrimonio_itens` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `igreja_id` INT NOT NULL,
  `tipo` ENUM('individual','lote') NOT NULL DEFAULT 'individual',
  `nome` VARCHAR(255) NOT NULL,
  `categoria` VARCHAR(100) NULL,
  `local` VARCHAR(100) NULL,
  `data_aquisicao` DATE NULL,
  `valor_estimado` DECIMAL(10,2) NULL,
  `status` ENUM('ativo','em_uso','manutencao','baixado') NOT NULL DEFAULT 'ativo', -- Para individual
  `quantidade_total` INT NOT NULL DEFAULT 1, -- Para lote
  `quantidade_uso` INT NOT NULL DEFAULT 0, -- Para lote
  `quantidade_manutencao` INT NOT NULL DEFAULT 0, -- Para lote
  `observacoes` TEXT NULL,
  `codigo_patrimonio` VARCHAR(50) NULL,
  `foto` VARCHAR(255) NULL,
  `ativo` TINYINT(1) DEFAULT 1, -- Soft Delete
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pat_igreja` (`igreja_id`),
  INDEX `idx_pat_tipo` (`tipo`),
  INDEX `idx_pat_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patrimonio_historico` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `item_id` INT NOT NULL,
  `tipo_evento` ENUM('uso','manutencao','retorno','baixa','criacao') NOT NULL,
  `data_evento` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `responsavel` VARCHAR(255) NULL,
  `observacao` TEXT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_hist_item` (`item_id`),
  CONSTRAINT `fk_hist_item` FOREIGN KEY (`item_id`) REFERENCES `patrimonio_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
