<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual do Membro - Church Digital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
        .accordion-content { transition: max-height 0.3s ease-out, padding 0.3s ease; max-height: 0; overflow: hidden; }
        .accordion-open .accordion-content { max-height: 500px; } /* Adjust max-height as needed */
        .accordion-icon { transition: transform 0.3s ease; }
        .accordion-open .accordion-icon { transform: rotate(180deg); }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Header -->
    <header class="bg-white/90 backdrop-blur-md shadow-sm sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-black rounded-lg flex items-center justify-center text-white">
                     <i class="fas fa-church"></i>
                </div>
                <span class="font-extrabold text-lg tracking-tighter text-gray-900">ChurchDigital</span>
            </div>
            <a href="pricing.php" class="text-sm font-bold text-gray-600 hover:text-black transition flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow py-12 px-4">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-12">
                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-4">Manual do Membro</h1>
                <p class="text-gray-600 text-lg">Guia passo a passo para acessar e utilizar o sistema da sua igreja.</p>
            </div>

            <div class="space-y-4">

                <!-- 1. Acessando a Primeira Vez -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden group">
                    <button onclick="toggleAccordion(this)" class="w-full text-left p-6 flex justify-between items-center focus:outline-none hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold">1</div>
                            <h2 class="font-bold text-lg text-gray-800">Primeiro Acesso ao Sistema</h2>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 accordion-icon"></i>
                    </button>
                    <div class="accordion-content px-6 bg-gray-50/50">
                        <div class="pb-6 text-gray-600 space-y-3 leading-relaxed">
                            <p>Para acessar o sistema pela primeira vez, você precisará dos seus dados de acesso.</p>
                            <p class="bg-blue-50 p-3 rounded-lg border-l-4 border-blue-500 text-sm">
                                <i class="fas fa-info-circle mr-1"></i> <strong>Importante:</strong> Seu <strong>Login</strong> (E-mail) e <strong>Senha</strong> iniciais serão fornecidos pelo administrador da secretaria da sua igreja.
                            </p>
                            <ol class="list-decimal ml-5 space-y-1">
                                <li>Acesse a página de <strong>Login</strong>: <a href="https://churchdigital.pro/login.php" class="text-blue-600 underline" target="_blank">churchdigital.pro/login.php</a></li>
                                <li>Insira o <strong>e-mail</strong> e a <strong>senha</strong> recebidos.</li>
                                <li>Clique em <strong>Entrar</strong>.</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- 2. Baixar e Instalar o App -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden group">
                    <button onclick="toggleAccordion(this)" class="w-full text-left p-6 flex justify-between items-center focus:outline-none hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center font-bold">2</div>
                            <h2 class="font-bold text-lg text-gray-800">Baixar e Instalar o APP</h2>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 accordion-icon"></i>
                    </button>
                    <div class="accordion-content px-6 bg-gray-50/50">
                        <div class="pb-6 text-gray-600 space-y-3 leading-relaxed">
                            <p>Nosso sistema utiliza uma tecnologia moderna (PWA) que não requer download nas lojas de aplicativo tradicionais.</p>
                            <h3 class="font-bold text-gray-800 mt-2">No Android (Chrome):</h3>
                            <ul class="list-disc ml-5 space-y-1">
                                <li>Acesse o sistema pelo navegador <strong>Google Chrome</strong>.</li>
                                <li>Uma barra inferior ou notificação pode aparecer sugerindo <strong>"Adicionar à Tela Inicial"</strong> ou <strong>"Instalar App"</strong>. Clique nela.</li>
                                <li>Caso não apareça, toque nos <strong>três pontinhos</strong> (menu) no canto superior direito e selecione <strong>"Instalar aplicativo"</strong>.</li>
                            </ul>
                            <h3 class="font-bold text-gray-800 mt-2">No iPhone (Safari):</h3>
                            <ul class="list-disc ml-5 space-y-1">
                                <li>Acesse o sistema pelo navegador <strong>Safari</strong>.</li>
                                <li>Toque no botão <strong>Compartilhar</strong> <i class="fas fa-share-square"></i> (quadrado com seta pra cima).</li>
                                <li>Role as opções e toque em <strong>"Adicionar à Tela de Início"</strong>.</li>
                                <li>Confirme clicando em <strong>Adicionar</strong>.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- 3. Abrir Carteirinha -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden group">
                    <button onclick="toggleAccordion(this)" class="w-full text-left p-6 flex justify-between items-center focus:outline-none hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-green-50 text-green-600 flex items-center justify-center font-bold">3</div>
                            <h2 class="font-bold text-lg text-gray-800">Visualizar Carteirinha Digital</h2>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 accordion-icon"></i>
                    </button>
                    <div class="accordion-content px-6 bg-gray-50/50">
                        <div class="pb-6 text-gray-600 space-y-3 leading-relaxed">
                            <p>Sua carteirinha digital prova sua filiação ao ministério.</p>
                            <ol class="list-decimal ml-5 space-y-1">
                                <li>Faça login no sistema ou abra o APP instalado.</li>
                                <li>No <strong>Menu Inferior</strong> (em celulares), toque no ícone central de <strong>Cartão/Identidade</strong> <i class="fas fa-id-card"></i>.</li>
                                <li>Sua carteirinha será exibida na tela com sua foto e informações.</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- 4. Alterar Perfil -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden group">
                    <button onclick="toggleAccordion(this)" class="w-full text-left p-6 flex justify-between items-center focus:outline-none hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center font-bold">4</div>
                            <h2 class="font-bold text-lg text-gray-800">Alterar Dados do Perfil</h2>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 accordion-icon"></i>
                    </button>
                    <div class="accordion-content px-6 bg-gray-50/50">
                        <div class="pb-6 text-gray-600 space-y-3 leading-relaxed">
                            <p>Mantenha seus dados sempre atualizados.</p>
                            <ol class="list-decimal ml-5 space-y-1">
                                <li>No APP ou site, toque no ícone de <strong>Perfil</strong> <i class="fas fa-user"></i> (geralmente no topo ou menu).</li>
                                <li>Selecione a opção <strong>"Meus Dados"</strong> ou <strong>"Editar Perfil"</strong>.</li>
                                <li>Atualize as informações desejadas (foto, telefone, endereço).</li>
                                <li>Clique no botão <strong>Salvar</strong> para confirmar as alterações.</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- 5. Redefinição de Senha -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden group">
                    <button onclick="toggleAccordion(this)" class="w-full text-left p-6 flex justify-between items-center focus:outline-none hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-red-50 text-red-600 flex items-center justify-center font-bold">5</div>
                            <h2 class="font-bold text-lg text-gray-800">Redefinir Senha</h2>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 accordion-icon"></i>
                    </button>
                    <div class="accordion-content px-6 bg-gray-50/50">
                        <div class="pb-6 text-gray-600 space-y-3 leading-relaxed">
                            <h3 class="font-bold text-gray-800">Esqueci minha senha:</h3>
                            <p>Na tela de Login, clique em <strong>"Esqueceu a senha?"</strong>. Digite seu e-mail cadastrado e siga as instruções enviadas para o seu e-mail.</p>
                            
                            <h3 class="font-bold text-gray-800 mt-4">Estou logado e quero mudar:</h3>
                            <ol class="list-decimal ml-5 space-y-1">
                                <li>Acesse seu <strong>Perfil</strong>.</li>
                                <li>Procure pela seção <strong>Segurança</strong> ou <strong>Alterar Senha</strong>.</li>
                                <li>Digite sua <strong>senha atual</strong> e a <strong>nova senha</strong> desejada.</li>
                                <li>Salve as alterações.</li>
                            </ol>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="mt-12 text-center">
                <p class="text-gray-500">Ainda tem dúvidas?</p>
                <p class="font-bold text-gray-900">Procure a secretaria da sua igreja.</p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-8 border-t border-gray-800">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <p class="text-sm">&copy; <?php echo date('Y'); ?> ChurchDigital. Todos os direitos reservados.</p>
        </div>
    </footer>

    <script>
        function toggleAccordion(button) {
            const parent = button.parentElement;
            // Close others (optional - remove this block if you want multiple open)
            const allParents = document.querySelectorAll('.group');
            allParents.forEach(p => {
                if (p !== parent) {
                    p.classList.remove('accordion-open');
                }
            });

            // Toggle current
            parent.classList.toggle('accordion-open');
        }
    </script>
</body>
</html>
