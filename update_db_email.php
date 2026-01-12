<?php
require_once 'config.php';

echo "<h2>Atualizando Banco de Dados para Emails</h2>";

try {
    // Tabela password_resets
    echo "Verificando tabela 'password_resets'...<br>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(191) NOT NULL,
        token VARCHAR(191) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        used TINYINT(1) DEFAULT 0,
        INDEX (email),
        INDEX (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo " - Tabela 'password_resets' verificada/criada.<br>";

    echo "<h3 style='color:green'>Sucesso! Banco de dados atualizado.</h3>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Erro: " . $e->getMessage() . "</h3>";
}
?>
