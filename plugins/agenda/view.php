<?php
// plugins/agenda/view.php
$is_admin = has_role('admin') || has_role('secretario');
$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';

// --- LOGIC: SAVE / DELETE (Admin Only) ---
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $titulo = $_POST['titulo'] ?? '';
    $data_inicio = $_POST['data_inicio'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '00:00';
    $local = $_POST['local'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $cor = $_POST['cor'] ?? 'blue';
    
    // Combine Date and Time
    $data_full = $data_inicio . ' ' . $hora_inicio . ':00';
    
    $sql = "INSERT INTO eventos (igreja_id, titulo, data_inicio, local, descricao, cor) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$_SESSION['igreja_id'], $titulo, $data_full, $local, $descricao, $cor])) {
        echo "<script>window.location.href='index.php?page=agenda&msg=created';</script>";
        exit;
    }
}

if ($is_admin && $action === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM eventos WHERE id = ? AND igreja_id = ?");
    if ($stmt->execute([$_GET['id'], $_SESSION['igreja_id']])) {
        echo "<script>window.location.href='index.php?page=agenda&msg=deleted';</script>";
        exit;
    }
}

// --- VIEW ---
?>

<div class="fade-in mb-20">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="far fa-calendar-check text-primary"></i> Agenda da Igreja
        </h2>
        <?php if ($is_admin && $action !== 'new'): ?>
            <a href="index.php?page=agenda&action=new" class="bg-primary text-white font-bold py-2 px-4 rounded-lg shadow hover:opacity-90 transition flex items-center gap-2">
                <i class="fas fa-plus"></i> Novo Evento
            </a>
        <?php endif; ?>
    </div>

    <!-- Mensagens -->
    <?php if ($msg == 'created'): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4 border-l-4 border-green-500">Evento criado com sucesso!</div>
    <?php elseif ($msg == 'deleted'): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 border-l-4 border-red-500">Evento removido.</div>
    <?php endif; ?>

    <!-- FORMULÁRIO (Apenas Admin/Secretaria e Action=new) -->
    <?php if ($is_admin && $action === 'new'): ?>
        <div class="bg-white rounded-xl shadow p-6 max-w-2xl mx-auto">
            <div class="flex justify-between items-center mb-4 pb-2 border-b">
                <h3 class="font-bold text-lg">Criar Novo Evento</h3>
                <a href="index.php?page=agenda" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></a>
            </div>
            
            <form action="index.php?page=agenda&action=save" method="POST" class="space-y-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-1">Título do Evento</label>
                    <input type="text" name="titulo" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none" placeholder="Ex: Culto de Santa Ceia">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-bold mb-1">Data</label>
                        <input type="date" name="data_inicio" required value="<?php echo date('Y-m-d'); ?>" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-bold mb-1">Horário</label>
                        <input type="time" name="hora_inicio" required value="19:00" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 font-bold mb-1">Local</label>
                    <input type="text" name="local" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none" placeholder="Ex: Templo Principal">
                </div>

                <div>
                    <label class="block text-gray-700 font-bold mb-1">Descrição (Opcional)</label>
                    <textarea name="descricao" rows="3" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none"></textarea>
                </div>

                <div>
                    <label class="block text-gray-700 font-bold mb-2">Cor do Card</label>
                    <div class="flex gap-4">
                        <label class="cursor-pointer"><input type="radio" name="cor" value="blue" checked class="accent-blue-500"> <span class="text-blue-600 font-bold">Azul</span></label>
                        <label class="cursor-pointer"><input type="radio" name="cor" value="red" class="accent-red-500"> <span class="text-red-600 font-bold">Vermelho</span></label>
                        <label class="cursor-pointer"><input type="radio" name="cor" value="green" class="accent-green-500"> <span class="text-green-600 font-bold">Verde</span></label>
                        <label class="cursor-pointer"><input type="radio" name="cor" value="purple" class="accent-purple-500"> <span class="text-purple-600 font-bold">Roxo</span></label>
                        <label class="cursor-pointer"><input type="radio" name="cor" value="orange" class="accent-orange-500"> <span class="text-orange-600 font-bold">Laranja</span></label>
                    </div>
                </div>

                <button type="submit" class="w-full bg-primary text-white font-bold py-3 rounded-lg hover:brightness-90 transition mt-4">
                    Salvar Evento
                </button>
            </form>
        </div>
    <?php else: ?>
        <!-- LISTA DE EVENTOS -->
        <?php
        // Busca eventos futuros e de hoje
        $stmt = $pdo->prepare("SELECT * FROM eventos WHERE igreja_id = ? AND data_inicio >= CURDATE() ORDER BY data_inicio ASC");
        $stmt->execute([$_SESSION['igreja_id']]);
        $eventos = $stmt->fetchAll();
        ?>

        <?php if (count($eventos) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($eventos as $evento): 
                    $data = date('d/m', strtotime($evento['data_inicio']));
                    $hora = date('H:i', strtotime($evento['data_inicio']));
                    $dia_semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][date('w', strtotime($evento['data_inicio']))];
                    
                    // Mapa de cores
                    $colors = [
                        'blue' => 'bg-blue-50 border-blue-200 text-blue-800 icon-blue-500',
                        'red' => 'bg-red-50 border-red-200 text-red-800 icon-red-500',
                        'green' => 'bg-green-50 border-green-200 text-green-800 icon-green-500',
                        'purple' => 'bg-purple-50 border-purple-200 text-purple-800 icon-purple-500',
                        'orange' => 'bg-orange-50 border-orange-200 text-orange-800 icon-orange-500',
                    ];
                    $theme = $colors[$evento['cor']] ?? $colors['blue'];
                ?>
                    <div class="relative bg-white rounded-xl shadow-sm border border-l-4 p-4 hover:shadow-md transition <?php echo str_replace('bg-', 'border-', explode(' ', $theme)[0]); ?> border-l-current">
                         <!-- Delete Button (Admin Only) -->
                         <?php if ($is_admin): ?>
                            <a href="index.php?page=agenda&action=delete&id=<?php echo $evento['id']; ?>" class="absolute top-2 right-2 text-gray-300 hover:text-red-500 transition p-2" onclick="return confirm('Excluir este evento?')">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        <?php endif; ?>

                        <div class="flex items-start gap-4">
                            <!-- Date Box -->
                            <div class="flex flex-col items-center justify-center bg-gray-100 rounded-lg p-2 min-w-[3.5rem] border border-gray-200">
                                <span class="text-xs font-bold text-gray-500 uppercase"><?php echo $dia_semana; ?></span>
                                <span class="text-xl font-black text-gray-800 leading-none"><?php echo substr($data, 0, 2); ?></span> <!-- Apenas Dia -->
                                <span class="text-[10px] text-gray-400"><?php echo substr($data, 3, 2); ?></span> <!-- Mês -->
                            </div>

                            <div>
                                <h3 class="font-bold text-gray-800 text-lg leading-tight mb-1"><?php echo e($evento['titulo']); ?></h3>
                                <div class="flex items-center gap-2 text-sm text-gray-600 mb-1">
                                    <i class="far fa-clock text-primary"></i> <?php echo $hora; ?>
                                </div>
                                <?php if (!empty($evento['local'])): ?>
                                    <div class="text-xs text-gray-500 flex items-center gap-1">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo e($evento['local']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($evento['descricao'])): ?>
                            <div class="mt-3 pt-3 border-t border-dashed border-gray-200 text-sm text-gray-600 italic">
                                "<?php echo nl2br(e($evento['descricao'])); ?>"
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <div class="inline-block p-4 rounded-full bg-gray-50 text-gray-300 mb-3">
                    <i class="far fa-calendar-times text-4xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900">Nenhum evento agendado</h3>
                <p class="text-gray-500">A agenda está livre por enquanto.</p>
                <?php if ($is_admin): ?>
                    <a href="index.php?page=agenda&action=new" class="inline-block mt-4 text-primary font-bold hover:underline">Adicionar o primeiro evento</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>
