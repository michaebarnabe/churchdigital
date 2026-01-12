<?php
session_start();

// --- SECURITY HEADERS ---
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
// header("Content-Security-Policy: default-src 'self' https:; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' https:;"); // Cuidado com CSP estrito demais


// Simple .env parser logic
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
        $_ENV[trim($name)] = trim($value);
    }
}

// Configurações do Banco de Dados
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'church_digital');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Função para obter configurações do Tenant Atual
function get_tenant_config($pdo) {
    // Se estiver logado, usa a igreja do usuário
    if (isset($_SESSION['user_id']) && isset($_SESSION['igreja_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM igrejas WHERE id = ?");
        $stmt->execute([$_SESSION['igreja_id']]);
        $tenant = $stmt->fetch();

        // Lógica de Herança de Tema (Matriz -> Filial)
        if ($tenant && !empty($tenant['parent_id'])) {
            // Busca dados da Matriz
            $stmtP = $pdo->prepare("SELECT nome, logo_url, cor_primaria, cor_secundaria FROM igrejas WHERE id = ?");
            $stmtP->execute([$tenant['parent_id']]);
            $parent = $stmtP->fetch();
            
            if ($parent) {
                // Sobrescreve visual com o da Matriz (mantém nome/endereço da filial)
                $tenant['logo_url'] = $parent['logo_url'];
                $tenant['cor_primaria'] = $parent['cor_primaria'];
                $tenant['cor_secundaria'] = $parent['cor_secundaria'];
                // Opcional: Se quiser que o nome no topo seja "Matriz - Filial", ajuste aqui.
            }
        }
        
        return $tenant;
    }
    
    // Fallback ou lógica de domínio pode ser adicionada aqui
    return [
        'nome' => 'Church Digital',
        'cor_primaria' => '#6366f1', // Indigo
        'cor_secundaria' => '#4338ca',
        'logo_url' => ''
    ];
}

$tenant = get_tenant_config($pdo);
