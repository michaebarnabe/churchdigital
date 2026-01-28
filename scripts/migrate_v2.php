<?php
// scripts/migrate_v2.php
// Consolidated Migration Script for V2 Release (Patrimonio + Subscription Extras + CNPJ)
// Run this script to bring the database up to spec.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';

echo "<h2>[Migration V2] Atualizando Banco de Dados...</h2>";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    function safeSQL($pdo, $sql, $desc) {
        try {
            $pdo->exec($sql);
            echo "<div style='color:green; margin-bottom:5px;'>[OK] $desc</div>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "Duplicate column") !== false || 
                strpos($e->getMessage(), "Duplicate key") !== false || 
                strpos($e->getMessage(), "already exists") !== false) {
                echo "<div style='color:orange; margin-bottom:5px;'>[SKIP] $desc (Já existe)</div>";
            } else {
                echo "<div style='color:red; margin-bottom:5px;'>[ERROR] $desc: " . $e->getMessage() . "</div>";
            }
        }
    }

    // 1. IGREJAS: CNPJ (Já executado anteriormente, removido daqui)
    // safeSQL($pdo, "ALTER TABLE igrejas ADD COLUMN cnpj VARCHAR(20) DEFAULT NULL", "Coluna CNPJ em igrejas");


    // 2. ASSINATURAS: Extras & Stripe Items
    safeSQL($pdo, "ALTER TABLE assinaturas ADD COLUMN extra_membros INT DEFAULT 0", "Coluna extra_membros");
    safeSQL($pdo, "ALTER TABLE assinaturas ADD COLUMN extra_filiais INT DEFAULT 0", "Coluna extra_filiais");
    safeSQL($pdo, "ALTER TABLE assinaturas ADD COLUMN extra_patrimonio INT DEFAULT 0", "Coluna extra_patrimonio");
    safeSQL($pdo, "ALTER TABLE assinaturas ADD COLUMN stripe_item_membros VARCHAR(255) DEFAULT NULL", "Col stripe_item_membros");
    safeSQL($pdo, "ALTER TABLE assinaturas ADD COLUMN stripe_item_filiais VARCHAR(255) DEFAULT NULL", "Col stripe_item_filiais");

    // 3. PLANOS: Preços Extras
    safeSQL($pdo, "ALTER TABLE planos ADD COLUMN preco_extra_membro DECIMAL(10,2) DEFAULT 0.30", "Col preco_extra_membro");
    safeSQL($pdo, "ALTER TABLE planos ADD COLUMN preco_extra_filial DECIMAL(10,2) DEFAULT 12.90", "Col preco_extra_filial");
    safeSQL($pdo, "ALTER TABLE planos ADD COLUMN preco_extra_patrimonio DECIMAL(10,2) DEFAULT 0.10", "Col preco_extra_patrimonio");
    
    // Update Default Prices
    $pdo->exec("UPDATE planos SET preco_extra_membro = 0.30, preco_extra_filial = 12.90, preco_extra_patrimonio = 0.10 WHERE preco > 0");
    echo "<div>[UPDATE] Preços padrão atualizados.</div>";

    // 4. PATRIMONIO TABLES (V2)
    safeSQL($pdo, "
        CREATE TABLE IF NOT EXISTS `patrimonio_itens` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `igreja_id` INT NOT NULL,
          `tipo` ENUM('individual','lote') NOT NULL DEFAULT 'individual',
          `nome` VARCHAR(255) NOT NULL,
          `categoria` VARCHAR(100) NULL,
          `local` VARCHAR(100) NULL,
          `data_aquisicao` DATE NULL,
          `valor_estimado` DECIMAL(10,2) NULL,
          `status` ENUM('ativo','em_uso','manutencao','baixado') NOT NULL DEFAULT 'ativo',
          `quantidade_total` INT NOT NULL DEFAULT 1,
          `quantidade_uso` INT NOT NULL DEFAULT 0,
          `quantidade_manutencao` INT NOT NULL DEFAULT 0,
          `observacoes` TEXT NULL,
          `codigo_patrimonio` VARCHAR(50) NULL,
          `foto` VARCHAR(255) NULL,
          `ativo` TINYINT(1) DEFAULT 1,
          `deleted_at` DATETIME NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_pat_igreja` (`igreja_id`),
          INDEX `idx_pat_tipo` (`tipo`),
          INDEX `idx_pat_ativo` (`ativo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ", "Tabela patrimonio_itens");

    safeSQL($pdo, "
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
    ", "Tabela patrimonio_historico");

    echo "<hr><h3>Processo Finalizado.</h3>";
    echo "<a href='../index.php'>Voltar para o Sistema</a>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Erro Fatal: " . $e->getMessage() . "</h3>";
}
?>
