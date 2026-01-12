<?php
// Script utilitário para gerar hash de senha (BCRYPT)
// Uso: Acesso via navegador. Padrão: 123456

$senha = isset($_GET['senha']) ? $_GET['senha'] : '123456';
$hash = password_hash($senha, PASSWORD_DEFAULT);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Senha</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded shadow-md max-w-lg w-full">
        <h1 class="text-xl font-bold mb-4">Gerador de Hash PHP</h1>
        
        <div class="mb-4">
            <label class="block text-gray-700 font-bold mb-2">Senha (Texto Claro):</label>
            <div class="p-3 bg-gray-50 border rounded text-lg"><?php echo htmlspecialchars($senha); ?></div>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 font-bold mb-2">Hash (Copie para o Banco de Dados):</label>
            <div class="p-3 bg-gray-50 border rounded text-xs break-all font-mono select-all"><?php echo $hash; ?></div>
        </div>

        <hr class="my-4">
        
        <p class="text-sm text-gray-500 mb-2">Para gerar outra senha, use a URL:</p>
        <code class="block bg-black text-white p-2 rounded text-xs">generate_pass.php?senha=suanovaSenha</code>
    </div>
</body>
</html>
