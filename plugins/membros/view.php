<?php
// plugins/membros/view.php

$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';

// Permission Logic
$can_edit = has_role('admin') || has_role('secretario');
$readonly_attr = $can_edit ? '' : 'disabled';


// --- LOGIC: DELETE ---
if ($action === 'delete' && isset($_GET['id'])) {
    if (!$can_edit) die("Acesso Negado.");
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM membros WHERE id = ? AND igreja_id = ?");
    if ($stmt->execute([$id, TenantScope::getId()])) {
         echo "<script>window.location.href='index.php?page=membros&msg=deleted';</script>";
         exit;
    }
}

// --- LOGIC: CREATE / SAVE / UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'save' || $action === 'update')) {
    if (!$can_edit) die("Acesso Negado: Você não tem permissão para editar.");

    $id = $_POST['id'] ?? null;
    $nome = $_POST['nome'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $nascimento = $_POST['nascimento'] ?: null; // Handle empty string as NULL
    $data_batismo = $_POST['data_batismo'] ?: null;
    $data_inicio = $_POST['data_inicio'] ?: null;
    $filial = $_POST['filial'] ?? '';
    $filial = $_POST['filial'] ?? '';
    $cargo = $_POST['cargo'] ?? 'Membro'; // Novo campo
    // Login Data
    $email = !empty($_POST['email']) ? $_POST['email'] : null;
    $senha = $_POST['senha'] ?? '';
    $igreja_id = TenantScope::getId();


    // Upload de Foto (Base64 ou Arquivo Normal)
    $foto_path = $_POST['foto_atual'] ?? '';
    
    // 1. Check for Base64 (Cropped Image)
    if (!empty($_POST['foto_base64'])) {
        $data = $_POST['foto_base64'];
        
        // Remove header data usually found (e.g., "data:image/png;base64,")
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif
            
            if (!in_array($type, [ 'jpg', 'jpeg', 'gif', 'png' ])) {
                // Invalid type
            } else {
                $data = base64_decode($data);
                if ($data !== false) {
                    $uploadDir = 'uploads/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    $fileName = uniqid('membro_') . '.' . $type;
                    file_put_contents($uploadDir . $fileName, $data);
                    $foto_path = $uploadDir . $fileName;
                }
            }
        }
    }
    // 2. Fallback to Standard Upload (if no crop happened but file was sent differently?? Rare with cropper)
    elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('membro_') . '.' . $ext;
        
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $fileName)) {
            $foto_path = $uploadDir . $fileName;
        }
    }

    // --- HANDLE CHURCH SELECTION (Removed - inferred from Context) ---
    // $targetIgrejaId = TenantScope::getId(); 
    // Logic reverted to strict TenantScope::getId() usage below for INSERT. 
    // For UPDATE, we keep the record's ID but ensure we only update what belongs to us.

    try {
        if ($id && $action === 'update') {
            // UPDATE
            // Reverted: Do NOT update igreja_id. Members stay where they were unless admin switches context.
            $sql = "UPDATE membros SET nome=?, telefone=?, data_nascimento=?, data_batismo=?, data_inicio=?, filial=?, cargo=?, foto=?, email=?";
            $params = [$nome, $telefone, $nascimento, $data_batismo, $data_inicio, $filial, $cargo, $foto_path, $email];  // filial param matches $filial (legacy)

            
            // Update password only if provided
            if (!empty($senha)) {
                $sql .= ", senha=?";
                $params[] = password_hash($senha, PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id=? AND igreja_id=?";
            $params[] = $id;
            $params[] = $igreja_id;
            
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                 echo "<script>window.location.href='index.php?page=membros&msg=updated';</script>";
                 exit;
            }
        } else {
            // INSERT
            $hashed_pass = !empty($senha) ? password_hash($senha, PASSWORD_DEFAULT) : null;
            $sql = "INSERT INTO membros (igreja_id, nome, telefone, data_nascimento, sexo, data_batismo, data_inicio, filial, cargo, foto, email, senha) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$igreja_id, $nome, $telefone, $nascimento, $sexo, $data_batismo, $data_inicio, $filial, $cargo, $foto_path, $email, $hashed_pass])) {
                echo "<script>window.location.href='index.php?page=membros&msg=success';</script>";
                exit;
            }
        }
    } catch (PDOException $e) {
        // Handle duplicate entry (mainly for email)
        if ($e->getCode() == 23000) {
             echo "<script>alert('Erro: Este E-mail já está sendo usado por outro membro.'); history.back();</script>";
             exit;
        } else {
             throw $e;
        }
    }
}

// --- VIEW: FORM (NEW / EDIT) ---
if ($action === 'new' || $action === 'edit') {
    $membro = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ? AND igreja_id = ?");
        $stmt->execute([$_GET['id'], TenantScope::getId()]);
        $membro = $stmt->fetch();
    }
    
    // Valores padrão
    $nome = $membro ? $membro['nome'] : '';
    $telefone = $membro ? $membro['telefone'] : '';
    $nascimento = $membro ? $membro['data_nascimento'] : '';
    $data_batismo = $membro['data_batismo'] ?? '';
    $data_inicio = $membro['data_inicio'] ?? '';
    $filial = $membro['filial'] ?? '';
    $cargo = $membro['cargo'] ?? 'Membro';
    $email = $membro['email'] ?? '';
    $foto = $membro['foto'] ?? '';
    $id = $membro['id'] ?? '';
    // --- AVAILABLE TENANTS FOR SELECTION (Matrix Only) ---
    $availableTenants = TenantScope::getAvailableTenants($pdo, $_SESSION['user_id'] ?? 0);
    $canSelectChurch = count($availableTenants) > 1;

    $formAction = $action === 'edit' ? 'update' : 'save';
    
    // Prevent non-editors from accessing 'new' page
    if (($action === 'new' || $action === 'delete') && !$can_edit) {
        die("Acesso Negado.");
    }
    
    // Check Plan Limits
    if ($action === 'new' && !PlanEnforcer::canAdd($pdo, 'membros')) {
        // ... (keep existing limit check)
        // ... (keep existing limit check)
        // echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 m-4 rounded shadow'>";
        // ... (removed manual echo)
        // return; 
        PlanEnforcer::renderUpgradeModal("Sua igreja atingiu o limite de membros do plano atual.");
    }
?>
    <!-- Cropper.js Dependencies -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

    <!-- Crop Modal -->
    <div id="cropModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title">Ajustar Foto</h3>
                    <div class="img-container w-full h-96 bg-gray-100 rounded">
                        <img id="imageToCrop" src="" class="max-w-full max-h-full">
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" id="btnCrop" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:brightness-90 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Confirmar Recorte
                    </button>
                    <button type="button" id="btnCancelCrop" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow p-6 mb-20 fade-in">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">
                <?php 
                    if ($action === 'edit') echo $can_edit ? 'Editar Membro' : 'Visualizar Membro'; 
                    else echo 'Novo Membro';
                ?>
            </h2>
            <a href="index.php?page=membros" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <form action="index.php?page=membros&action=<?php echo $formAction; ?>" method="POST" enctype="multipart/form-data" class="space-y-4">
            
            <?php if ($id): ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
            <?php endif; ?>
            
            <!-- Foto Upload -->
            <div class="flex flex-col items-center mb-6">
                <div class="w-32 h-32 bg-gray-100 rounded-full mb-4 overflow-hidden border-4 border-white shadow-lg relative group">
                    <img id="previewAvatar" src="<?php echo $foto ? $foto : ''; ?>" class="w-full h-full object-cover <?php echo $foto ? '' : 'hidden'; ?>">
                    <i id="defaultAvatarIcon" class="fas fa-user text-5xl text-gray-300 mt-8 ml-9 absolute <?php echo $foto ? 'hidden' : ''; ?>"></i>
                </div>
                
                <input type="hidden" name="foto_atual" value="<?php echo $foto; ?>">
                <input type="hidden" name="foto_base64" id="fotoBase64">
                
                <?php if ($can_edit): ?>
                    <label class="cursor-pointer bg-blue-50 text-blue-600 px-4 py-2 rounded-full font-bold text-sm hover:bg-blue-100 transition shadow-sm border border-blue-200">
                        <i class="fas fa-camera mr-2"></i> 
                        <span id="labelFoto">Alterar Foto</span>
                        <input type="file" id="inputImage" name="foto" class="hidden" accept="image/*">
                    </label>
                    <p class="text-xs text-gray-400 mt-2">Clique para selecionar e ajustar</p>
                <?php endif; ?>
            </div>

            <!-- Cropper Logic Script -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const inputImage = document.getElementById('inputImage');
                    const modal = document.getElementById('cropModal');
                    const imageToCrop = document.getElementById('imageToCrop');
                    const btnCrop = document.getElementById('btnCrop');
                    const btnCancelCrop = document.getElementById('btnCancelCrop');
                    const previewAvatar = document.getElementById('previewAvatar');
                    const defaultAvatarIcon = document.getElementById('defaultAvatarIcon');
                    const fotoBase64 = document.getElementById('fotoBase64');
                    let cropper;

                    if(inputImage) {
                        inputImage.addEventListener('change', function(e) {
                            const files = e.target.files;
                            if (files && files.length > 0) {
                                const file = files[0];
                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    imageToCrop.src = e.target.result;
                                    modal.classList.remove('hidden');
                                    
                                    if(cropper) cropper.destroy();
                                    cropper = new Cropper(imageToCrop, {
                                        aspectRatio: 1, // Quadrado
                                        viewMode: 1,
                                        autoCropArea: 1,
                                    });
                                };
                                reader.readAsDataURL(file);
                                // Clear input value so same file can be selected again if needed
                                inputImage.value = '';
                            }
                        });
                    }

                    btnCrop.addEventListener('click', function() {
                        const canvas = cropper.getCroppedCanvas({
                            width: 300,
                            height: 300,
                        });
                        
                        const base64Url = canvas.toDataURL('image/jpeg');
                        
                        // Update UI
                        previewAvatar.src = base64Url;
                        previewAvatar.classList.remove('hidden');
                        defaultAvatarIcon.classList.add('hidden');
                        
                        // Update Hidden Input
                        fotoBase64.value = base64Url;
                        
                        // Close Modal
                        modal.classList.add('hidden');
                        cropper.destroy();
                        cropper = null;
                        
                        document.getElementById('labelFoto').innerText = 'Foto Definida!';
                    });

                    btnCancelCrop.addEventListener('click', function() {
                        modal.classList.add('hidden');
                        if(cropper) {
                            cropper.destroy();
                            cropper = null;
                        }
                    });
                });
            </script>

            <!-- Gender & Name Row -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-1">
                    <label class="block text-gray-700 font-bold mb-2">Sexo</label>
                    <select name="sexo" id="sexoSelect" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none bg-white">
                        <option value="M" <?php echo $sexo === 'M' ? 'selected' : ''; ?>>Masculino</option>
                        <option value="F" <?php echo $sexo === 'F' ? 'selected' : ''; ?>>Feminino</option>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-gray-700 font-bold mb-2">Nome Completo</label>
                    <input type="text" name="nome" value="<?php echo e($nome); ?>" required <?php echo $readonly_attr; ?> class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none disabled:bg-gray-100 disabled:text-gray-500">
                </div>
            </div>
            
            <!-- Cargo Ministerial (Dynamic) -->
            <div>
                <label class="block text-gray-700 font-bold mb-2">Cargo Eclesiástico</label>
                <select name="cargo" id="cargoSelect" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none bg-white">
                    <!-- Options populated by JS -->
                </select>
                <!-- Hidden input to store current value for JS init -->
                <input type="hidden" id="currentCargo" value="<?php echo $cargo; ?>">
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const sexoSelect = document.getElementById('sexoSelect');
                    const cargoSelect = document.getElementById('cargoSelect');
                    const currentCargo = document.getElementById('currentCargo').value;

                    const rolesM = ['Membro', 'Diácono', 'Presbítero', 'Evangelista', 'Missionário', 'Pastor', 'Bispo', 'Apóstolo', 'Líder', 'Músico'];
                    const rolesF = ['Membro', 'Diaconisa', 'Presbítera', 'Evangelista', 'Missionária', 'Pastora', 'Bispa', 'Apóstola', 'Líder', 'Música'];

                    function updateRoles() {
                        const gender = sexoSelect.value;
                        const roles = gender === 'M' ? rolesM : rolesF;
                        
                        // Save current choice to try and re-select it (if inflected)
                        let selectedValue = cargoSelect.value || currentCargo;

                        cargoSelect.innerHTML = ''; // Clear

                        roles.forEach((role, index) => {
                            const option = document.createElement('option');
                            option.value = role;
                            option.text = role;
                            
                            // Try to match exact or by index
                            // Example: If 'Diácono' was selected, and we switch to F, we want 'Diaconisa' (same index 1)
                            // We need to know the 'index' of the *previous* selection in the *other* list?
                            // Simpler: Just check if the current cargo matches this role.
                            
                            // Logic: If loading page, match currentCargo.
                            if (role === currentCargo) {
                                option.selected = true;
                            }
                            
                            cargoSelect.appendChild(option);
                        });
                        
                        // Auto-correct on gender switch?
                        // If I am "Diácono" (idx 1) and switch to F, I should become "Diaconisa" (idx 1).
                        // To do this, we need to map values.
                    }

                    // Smart Switch Logic
                    sexoSelect.addEventListener('change', function() {
                        const newGender = this.value;
                        const oldIndex = (newGender === 'M' ? rolesF : rolesM).indexOf(cargoSelect.value);
                        
                        updateRoles(); // Rebuild options

                        if (oldIndex !== -1) {
                            // Select equivalent index in new list
                            cargoSelect.selectedIndex = oldIndex;
                        }
                    });

                    // Initial Load
                    updateRoles();
                    
                    // Specific case: If existing cargo didn't match (e.g. Diácono loaded but Gender is F), force correction?
                    // Better to respect DB value initially unless user changes gender.
                    // But 'updateRoles' only adds 'rolesF' if F is selected. So 'Diácono' might not be in the list!
                    // Fix: If currentCargo is not in the generated list, add it as a fallback or try to find its match.
                    const currentList = sexoSelect.value === 'M' ? rolesM : rolesF;
                    const otherList = sexoSelect.value === 'M' ? rolesF : rolesM;
                    
                    // If current db value is "Diácono" but gender is F, we should auto-select "Diaconisa".
                    if (!currentList.includes(currentCargo)) {
                        const idx = otherList.indexOf(currentCargo);
                        if (idx !== -1) {
                             cargoSelect.selectedIndex = idx;
                        } else {
                            // Fallback: Add option just to show it? Or default to Membro?
                            // Let's add it to avoid data loss on view
                            if(currentCargo && currentCargo !== 'Membro') {
                                const opt = document.createElement('option');
                                opt.value = currentCargo;
                                opt.text = currentCargo + ' (Inválido para sexo)';
                                opt.selected = true;
                                cargoSelect.appendChild(opt);
                            }
                        }
                    }
                });
            </script>

            <!-- Dados de Acesso (App) -->
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                <h3 class="text-xs font-bold text-blue-800 uppercase mb-3 flex items-center gap-2">
                    <i class="fas fa-lock"></i> Acesso à Carteirinha / App
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-bold mb-2 text-sm">E-mail (Login)</label>
                        <input type="email" name="email" value="<?php echo e($email); ?>" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none bg-white" placeholder="email@exemplo.com">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-bold mb-2 text-sm">
                            <?php echo $action === 'edit' ? 'Nova Senha (opcional)' : 'Senha'; ?>
                        </label>
                        <input type="password" name="senha" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none bg-white" placeholder="******">
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Telefone</label>
                    <input type="text" name="telefone" value="<?php echo e($telefone); ?>" placeholder="(00) 00000-0000" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none">
                </div>
                <!-- Filial field removed as per request (Legacy field hidden or removed) -->
                <!-- <input type="hidden" name="filial" value="<?php echo e($filial); ?>"> -->
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Data Nascimento</label>
                    <input type="date" name="nascimento" value="<?php echo e($nascimento); ?>" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none">
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Data Batismo</label>
                    <input type="date" name="data_batismo" value="<?php echo e($data_batismo); ?>" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none">
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Membro Desde</label>
                    <input type="date" name="data_inicio" value="<?php echo e($data_inicio); ?>" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none">
                </div>
            </div>

            <?php if ($can_edit): ?>
            <?php if ($can_edit): ?>
                <div class="flex items-center gap-4 mt-6">
                    <button type="submit" class="flex-grow bg-primary text-white font-bold py-3 rounded-lg hover:brightness-90 transition shadow-md">
                        <?php echo $action === 'edit' ? 'Atualizar Dados' : 'Salvar Membro'; ?>
                    </button>
                    
                    <?php if ($action === 'edit'): ?>
                        <a href="index.php?page=membros&action=delete&id=<?php echo $id; ?>" 
                           class="bg-red-100 text-red-600 font-bold py-3 px-6 rounded-lg hover:bg-red-200 transition shadow-sm"
                           onclick="return confirm('ATENÇÃO: Deseja realmente excluir este membro?\n\nEssa ação pode remover também o histórico financeiro vinculado (se houver).')">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </form>
    </div>

    <!-- Histórico Financeiro do Membro -->
    <?php 
    // Apenas Admin e Tesoureiro podem ver o histórico financeiro
    if ($action === 'edit' && $id && (has_role('admin') || has_role('tesoureiro'))): 
        $stmtFin = $pdo->prepare("SELECT * FROM financeiro_basico WHERE membro_id = ? AND igreja_id = ? ORDER BY data_movimento DESC LIMIT 10");
        $stmtFin->execute([$id, TenantScope::getId()]);
        $historico = $stmtFin->fetchAll();
    ?>
    <div class="bg-white rounded-xl shadow p-6 mb-20 fade-in">
        <h3 class="font-bold text-gray-700 mb-4 border-b pb-2">Últimas Contribuições</h3>
        
        <?php if (count($historico) > 0): ?>
            <div class="space-y-3">
                <?php foreach ($historico as $h): ?>
                    <div class="flex justify-between items-center text-sm border-b border-gray-100 pb-2 last:border-0 last:pb-0">
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-400 bg-gray-50 px-2 py-1 rounded">
                                <?php echo date('d/m/Y', strtotime($h['data_movimento'])); ?>
                            </span>
                            <span class="capitalize font-medium text-gray-700">
                                <?php echo $h['tipo']; ?>
                            </span>
                        </div>
                        <span class="font-bold text-green-600">
                            R$ <?php echo number_format($h['valor'], 2, ',', '.'); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-400 text-sm text-center py-4">Nenhum registro encontrado.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php
} 
// --- VIEW: LIST MEMBERS ---
else {
    // Fetch Membros
    $stmt = $pdo->prepare("SELECT * FROM membros WHERE igreja_id = ? ORDER BY nome ASC");
    $stmt->execute([TenantScope::getId()]);
    $membros = $stmt->fetchAll();
?>
    <div class="space-y-4 fade-in">
        
        <!-- Header da Página -->
        <div class="flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800">Membros</h2>
            <?php if ($can_edit): ?>
                <a href="index.php?page=membros&action=new" class="bg-primary text-white p-3 rounded-full w-12 h-12 flex items-center justify-center shadow-lg hover:scale-105 transition transform">
                    <i class="fas fa-plus"></i>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($msg == 'success'): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded-lg text-sm border-l-4 border-green-500">Membro cadastrado com sucesso!</div>
        <?php elseif ($msg == 'updated'): ?>
             <div class="bg-blue-100 text-blue-700 p-3 rounded-lg text-sm border-l-4 border-blue-500">Dados atualizados com sucesso!</div>
        <?php elseif ($msg == 'deleted'): ?>
             <div class="bg-red-100 text-red-700 p-3 rounded-lg text-sm border-l-4 border-red-500">Membro excluído com sucesso.</div>
        <?php endif; ?>

        <!-- Lista de Cards -->
        <div class="grid grid-cols-1 gap-4 pb-20">
            <?php if (count($membros) > 0): ?>
                <?php foreach ($membros as $membro): ?>
                    <div class="bg-white rounded-xl shadow-sm p-4 flex items-center space-x-4 border-l-4 border-transparent hover:border-primary transition relative group">
                        <!-- Link Total no Card para Edição -->
                        <a href="index.php?page=membros&action=edit&id=<?php echo $membro['id']; ?>" class="absolute inset-0 z-0"></a>

                        <!-- Avatar -->
                        <div class="w-14 h-14 bg-gray-200 rounded-full flex items-center justify-center text-gray-400 font-bold text-lg overflow-hidden flex-shrink-0 z-10 relative border border-gray-100">
                            <?php if (!empty($membro['foto'])): ?>
                                <img src="<?php echo e($membro['foto']); ?>" alt="<?php echo e($membro['nome']); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?php echo strtoupper(substr($membro['nome'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Info -->
                        <div class="flex-grow z-10 relative pointer-events-none">
                            <h3 class="font-bold text-gray-800 text-lg leading-tight"><?php echo e($membro['nome']); ?></h3>
                                <?php if (!empty($membro['filial'])): ?>
                                    <span class="bg-gray-100 px-2 py-0.5 rounded text-gray-600"><?php echo e($membro['filial']); ?></span>
                                <?php endif; ?>
                                <span class="bg-primary/10 text-primary font-bold px-2 py-0.5 rounded"><?php echo e($membro['cargo'] ?? 'Membro'); ?></span>
                            </p>
                            
                            <div class="text-sm text-gray-500 flex flex-wrap gap-2 mt-1">
                                <?php if ($membro['telefone']): ?>
                                    <span class="flex items-center"><i class="fab fa-whatsapp text-green-500 mr-1"></i> <?php echo e($membro['telefone']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actions Indicator -->
                        <div class="text-gray-300 z-10 relative group-hover:text-primary transition">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-10 text-gray-500">
                    <i class="fas fa-user-friends text-4xl mb-3 opacity-30"></i>
                    <p>Nenhum membro cadastrado ainda.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php
}
?>
