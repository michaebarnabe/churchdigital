<?php
require_once 'config.php';

echo "<h2>Atualizando Banco de Dados para 2FA</h2>";

try {
    // 1. Add columns to 'usuarios'
    echo "Verificando tabela 'usuarios'...<br>";
    $cols = $pdo->query("DESCRIBE usuarios")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('two_factor_secret', $cols)) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN two_factor_secret VARCHAR(32) NULL AFTER senha");
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0 AFTER two_factor_secret");
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN two_factor_recovery_codes TEXT NULL AFTER two_factor_enabled");
        echo " - Colunas adicionadas em 'usuarios'.<br>";
    } else {
        echo " - Colunas já existem em 'usuarios'.<br>";
    }

    // 2. Add columns to 'membros'
    echo "Verificando tabela 'membros'...<br>";
    $colsM = $pdo->query("DESCRIBE membros")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('two_factor_secret', $colsM)) {
        $pdo->exec("ALTER TABLE membros ADD COLUMN two_factor_secret VARCHAR(32) NULL AFTER senha");
        $pdo->exec("ALTER TABLE membros ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0 AFTER two_factor_secret");
        echo " - Colunas adicionadas em 'membros'.<br>";
    } else {
        echo " - Colunas já existem em 'membros'.<br>";
    }

    echo "<h3 style='color:green'>Sucesso! Banco de dados atualizado.</h3>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Erro: " . $e->getMessage() . "</h3>";
}
?>
