<?php
require_once 'config.php';

echo "<h2>Atualizando Banco de Dados (Features PRO)...</h2>";

try {
    // 1. Add 'comprovante_url' to 'financeiro_basico'
    echo "Verificando tabela financeiro_basico... ";
    $cols = $pdo->query("SHOW COLUMNS FROM financeiro_basico LIKE 'comprovante_url'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE financeiro_basico ADD COLUMN comprovante_url VARCHAR(255) DEFAULT NULL AFTER descricao");
        echo "<span style='color:green'>Coluna 'comprovante_url' adicionada!</span><br>";
    } else {
        echo "<span style='color:blue'>Coluna já existe.</span><br>";
    }

    // 2. Create 'pix_keys' table
    echo "Verificando tabela pix_keys... ";
    $sql = "
        CREATE TABLE IF NOT EXISTS pix_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            igreja_id INT NOT NULL,
            filial_id INT DEFAULT NULL,
            tipo ENUM('cpf', 'cnpj', 'email', 'telefone', 'aleatoria') NOT NULL,
            chave VARCHAR(255) NOT NULL,
            titular VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (igreja_id) REFERENCES igrejas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($sql);
    echo "<span style='color:green'>Tabela 'pix_keys' verificada/criada!</span><br>";

    echo "<hr><h3>Sucesso! Banco de dados atualizado.</h3>";
    echo "<a href='index.php'>Voltar para Home</a>";

} catch (PDOException $e) {
    die("<br><span style='color:red'>Erro: " . $e->getMessage() . "</span>");
}
?>
