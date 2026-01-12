<?php
// plugins/perfil/view.php
$user_id = $_SESSION['user_id'];
$msg = $_GET['msg'] ?? '';
$must_change = $_SESSION['must_change_password'] ?? false;

// Detect User Type
$is_member = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'member');
$table = $is_member ? 'membros' : 'usuarios';

// --- ACTION: SAVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Validação Básica
    if (!empty($nova_senha)) {
        if ($nova_senha !== $confirmar_senha) {
            echo "<script>alert('As senhas não conferem!'); window.history.back();</script>";
            exit;
        }
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
    }

    try {
        if (!empty($nova_senha)) {
            // Update with password
             $stmt = $pdo->prepare("UPDATE $table SET nome=?, email=?, senha=?, must_change_password=0 WHERE id=?");
            $stmt->execute([$nome, $email, $senha_hash, $user_id]);
        } else {
            // Update without password
            $stmt = $pdo->prepare("UPDATE $table SET nome=?, email=? WHERE id=?");
            $stmt->execute([$nome, $email, $user_id]);
        }
        
        // Atualiza a sessão
        $_SESSION['user_name'] = $nome;
        $_SESSION['user_name'] = $nome;
        // If member changed email, update session?
        // Yes, auth uses session email probably?
        $_SESSION['user_email'] = $email; 
        $_SESSION['must_change_password'] = false; // Clear flag

        echo "<script>window.location.href='index.php?page=perfil&msg=success';</script>";
        exit;

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo "<script>alert('Este e-mail já está em uso.'); window.history.back();</script>";
        } else {
            echo "Erro: " . $e->getMessage();
        }
        exit;
    }
}

// --- ACTION: 2FA TOGGLE ---
require_once 'includes/GoogleAuthenticator.php';
$ga = new GoogleAuthenticator();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'enable_2fa_verify') {
        $secret = $_POST['secret'];
        $code = $_POST['code'];
        
        if ($ga->verifyCode($secret, $code, 2)) {
            // Success! Save secret to DB
            $stmt = $pdo->prepare("UPDATE $table SET two_factor_secret = ?, two_factor_enabled = 1 WHERE id = ?");
            $stmt->execute([$secret, $user_id]);
            echo "<script>alert('2FA Ativado com Sucesso!'); window.location.href='index.php?page=perfil';</script>";
            exit;
        } else {
            echo "<script>alert('Código Incorreto. Tente novamente.'); window.history.back();</script>";
            exit;
        }
    }
    
    if ($_POST['action'] === 'disable_2fa') {
        $stmt = $pdo->prepare("UPDATE $table SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        echo "<script>alert('2FA Desativado.'); window.location.href='index.php?page=perfil';</script>";
        exit;
    }
}

// Fetch User Data
// Note: We need to filter by proper ID.
// For members, tenant scoping is usually important, but ID is primary key.
// But safeguards are good.
$stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "Usuário não encontrado.";
    exit;
}

// Prepare 2FA Data for View
$is_2fa_enabled = !empty($user['two_factor_enabled']);
$new_secret = $ga->createSecret();
$qrCodeUrl = $ga->getQRCodeGoogleUrl('ChurchDigital (' . $user['email'] . ')', $new_secret, 'ChurchDigital');
?>

<div class="fade-in max-w-2xl mx-auto">
    
    <?php if ($must_change): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-6 mb-8 rounded shadow-lg text-center">
            <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-3"></i>
            <h2 class="text-xl font-bold text-red-800 mb-2">Troca de Senha Obrigatória</h2>
            <p class="text-red-700">Por segurança, você precisa alterar sua senha no primeiro acesso.</p>
        </div>
    <?php else: ?>
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800"><i class="fas fa-user-cog text-primary mr-2"></i> Meu Perfil</h2>
        </div>
    <?php endif; ?>

    <?php if ($msg == 'success'): ?>
        <div class="bg-green-100 text-green-700 p-4 rounded mb-6 shadow border-l-4 border-green-500">
            <i class="fas fa-check-circle mr-2"></i> Dados atualizados com sucesso!
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow p-8">
        <form method="POST" action="index.php?page=perfil">
            <input type="hidden" name="action" value="update_profile">

            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Nome Completo</label>
                <input type="text" name="nome" value="<?php echo htmlspecialchars($user['nome']); ?>" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none">
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 font-bold mb-2">E-mail de Acesso</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none bg-gray-50 text-gray-600" <?php echo $must_change ? 'readonly' : ''; ?>>
                <?php if ($must_change): ?>
                    <p class="text-xs text-gray-500 mt-1">O e-mail não pode ser alterado durante a troca obrigatória de senha.</p>
                <?php endif; ?>
            </div>

            <hr class="border-gray-100 my-6">
            
            <h3 class="font-bold text-gray-800 mb-4"><i class="fas fa-lock mr-2"></i> Segurança</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Nova Senha</label>
                    <input type="password" name="nova_senha" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none" placeholder="Mínimo 6 caracteres" <?php echo $must_change ? 'required' : ''; ?>>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Confirmar Senha</label>
                    <input type="password" name="confirmar_senha" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none" placeholder="Repita a senha" <?php echo $must_change ? 'required' : ''; ?>>
                </div>
            </div>

            <button type="submit" class="w-full bg-primary text-white font-bold py-3 rounded-lg hover:brightness-90 transition shadow-lg mt-4">
                Salvar Alterações
            </button>
        </form>
    </div>

    <!-- 2FA SECTION -->
    <?php if (!$must_change): ?>
    <div class="bg-white rounded-xl shadow p-8 mt-8">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-shield-alt text-lg"></i> Autenticação de Dois Fatores (2FA)
        </h3>

        <?php if ($is_2fa_enabled): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-green-500 text-white rounded-full w-10 h-10 flex items-center justify-center">
                        <i class="fas fa-check"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-green-800">2FA Ativado</h4>
                        <p class="text-sm text-green-600">Sua conta está protegida.</p>
                    </div>
                </div>
                <form method="POST" onsubmit="return confirm('Tem certeza que deseja desativar a proteção 2FA?');">
                    <input type="hidden" name="action" value="disable_2fa">
                    <button type="submit" class="bg-white border border-red-200 text-red-500 hover:bg-red-50 px-4 py-2 rounded-lg text-sm font-bold shadow-sm">
                        Desativar
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <div class="flex flex-col md:flex-row gap-8 items-center">
                    <div class="flex-1">
                        <h4 class="font-bold text-blue-900 mb-2">Aumente sua segurança</h4>
                        <p class="text-sm text-blue-700 mb-4">
                            Utilize um aplicativo como o <strong>Google Authenticator</strong> para gerar códigos de acesso.
                        </p>
                        <ol class="list-decimal list-inside text-sm text-blue-800 space-y-2 mb-4">
                            <li>Baixe o app Google Authenticator (Android/iOS).</li>
                            <li>Escaneie o QR Code ao lado.</li>
                            <li>Digite o código gerado abaixo para ativar.</li>
                        </ol>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="enable_2fa_verify">
                            <input type="hidden" name="secret" value="<?php echo $new_secret; ?>">
                            <div class="flex gap-2">
                                <input type="text" name="code" placeholder="Código (6 dígitos)" class="p-3 border rounded-lg w-full max-w-[200px] text-center tracking-widest font-mono text-xl" maxlength="6" required autocomplete="off">
                                <button type="submit" class="bg-blue-600 text-white font-bold px-6 py-2 rounded-lg hover:bg-blue-700 shadow">
                                    Ativar
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="flex-shrink-0 bg-white p-2 rounded shadow">
                        <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code 2FA">
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
