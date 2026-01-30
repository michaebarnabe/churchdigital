<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual do Tesoureiro - Church Digital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
        .accordion-content { transition: max-height 0.3s ease-out, padding 0.3s ease; max-height: 0; overflow: hidden; }
        .accordion-open .accordion-content { max-height: 800px; } /* Increased for longer content */
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
                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-4">Manual do Tesoureiro</h1>
                <p class="text-gray-600 text-lg">Guia completo para a gestão financeira do seu ministério.</p>
            </div>

            <div class="space-y-4">

                <!-- 1. Acesso e Segurança -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden group">
                    <button onclick="toggleAccordion(this)" class="w-full text-left p-6 flex justify-between items-center focus:outline-none hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold">1</div>
                            <h2 class="font-bold text-lg text-gray-800">Acesso ao Sistema e Segurança</h2>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 accordion-icon"></i>
                    </button>
                    <div class="accordion-content px-6 bg-gray-50/50">
                        <div class="pb-6 text-gray-600 space-y-3 leading-relaxed">
                            <p class="bg-yellow-50 p-3 rounded-lg border-l-4 border-yellow-500 text-sm mb-4">
                                <i class="fas fa-exclamation-triangle mr-1"></i> <strong>Atenção:</strong> O Tesoureiro deverá solicitar sua senha de acesso ao administrador.
                            </p>
                            <p>Para acessar o painel administrativo:</p>
                            <ol class="list-decimal ml-5 space-y-1">
                                <li>Acesse a página de <strong>Login</strong>: <a href="https://churchdigital.pro/login.php" class="text-blue-600 underline" target="_blank">churchdigital.pro/login.php</a></li>
                                <li>Insira seu e-mail e senha.</li>
                                <li>Ao entrar, você verá o Painel Principal (Dashboard).</li>
                            </ol>
                            
                            <h3 class="font-bold text-gray-800 mt-4"><i class="fas fa-shield-alt text-blue-500 mr-2"></i>Segurança Extra (2FA)</h3>
                            <p>Recomendamos fortemente ativar a Autenticação em Duas Etapas para proteger os dados financeiros:</p>
                            <ol class="list-decimal ml-5 space-y-1">
                                <li>No topo direito, clique no ícone de <strong>Perfil</strong> <i class="fas fa-user"></i>.</li>
                                <li>Role até a seção de Segurança e clique em <strong>"Ativar 2FA"</strong>.</li>
                                <li>Escaneie o QR Code com seu aplicativo autenticador (Google Authenticator ou Authy).</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- 2. Visão Geral do Financeiro -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden group">
                    <button onclick="toggleAccordion(this)" class="w-full text-left p-6 flex justify-between items-center focus:outline-none hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-green-50 text-green-600 flex items-center justify-center font-bold">2</div>
                            <h2 class="font-bold text-lg text-gray-800">Visão Geral do Financeiro</h2>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 accordion-icon"></i>
                    </button>
                    <div class="accordion-content px-6 bg-gray-50/50">
                        <div class="pb-6 text-gray-600 space-y-3 leading-relaxed">
                            <p>Na tela inicial (Dashboard), você terá acesso imediato aos indicadores financeiros:</p>
                            <ul class="list-disc ml-5 space-y-1">
                                <li><strong>Saldo em Caixa:</strong> O valor líquido atual (Entradas - Saídas).</li>
                                <li><strong>Gráfico Mensal:</strong> Um gráfico de rosca mostrando a proporção entre Dízimos, Ofertas e Despesas no mês atual.</li>
                            </ul>
                            <p class="mt-2">Para acessar o módulo completo, clique no card de Saldo ou no ícone de <strong>Ofertas</strong> (Coração) no menu inferior.</p>
                        </div>
                    </div>
                </div>

                <!-- 3. Lançar Entradas (Dízimos e Ofertas) -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden group">
                    <button onclick="toggleAccordion(this)" class="w-full text-left p-6 flex justify-between items-center focus:outline-none hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-green-100 text-green-700 flex items-center justify-center font-bold">3</div>
                            <h2 class="font-bold text-lg text-gray-800">Lançar Dízimos e Ofertas</h2>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 accordion-icon"></i>
                    </button>
                    <div class="accordion-content px-6 bg-gray-50/50">
                        <div class="pb-6 text-gray-600 space-y-3 leading-relaxed">
                            <ol class="list-decimal ml-5 space-y-2">
                                <li>Acesse o menu <strong>Ofertas</strong> / Financeiro.</li>
                                <li>Clique no botão flutuante <strong>+ (Mais)</strong> ou "Novo Lançamento".</li>
                                <li>Selecione o tipo:
                                    <ul class="list-disc ml-5 mt-1 text-sm">
                                        <li><strong>Dízimo:</strong> Para devoluções de membros cadastrados.</li>
                                        <li><strong>Oferta:</strong> Para ofertas voluntárias gerais ou específicas.</li>
                                    </ul>
                                </li>
                                <li>Insira o <strong>Valor</strong> e a <strong>Data</strong> (padrão é hoje).</li>
                                <li><strong>Membro (Opcional):</strong> Se for dízimo ou oferta identificada, selecione o nome do membro na lista.</li>
                                <li><strong>Descrição:</strong> Adicione uma observação se necessário (ex: "Campanha de Missões").</li>
                                <li>Clique em <strong>Salvar Lançamento</strong>.</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- 4. Lançar Saídas (Despesas) -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden group">
                    <button onclick="toggleAccordion(this)" class="w-full text-left p-6 flex justify-between items-center focus:outline-none hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-red-50 text-red-600 flex items-center justify-center font-bold">4</div>
                            <h2 class="font-bold text-lg text-gray-800">Lançar Despesas</h2>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 accordion-icon"></i>
                    </button>
                    <div class="accordion-content px-6 bg-gray-50/50">
                        <div class="pb-6 text-gray-600 space-y-3 leading-relaxed">
                            <ol class="list-decimal ml-5 space-y-2">
                                <li>Acesse o menu <strong>Ofertas</strong> e clique em <strong>Novo Lançamento (+)</strong>.</li>
                                <li>Selecione o tipo <strong>Despesa</strong> (Ícone Vermelho).</li>
                                <li>Insira o <strong>Valor</strong> e a <strong>Data</strong> do pagamento.</li>
                                <li><strong>Comprovante (Plano PRO):</strong> Você pode anexar uma foto ou PDF do recibo/nota fiscal direto do seu celular ou computador.</li>
                                <li><strong>Descrição:</strong> Descreva o gasto (ex: "Conta de Luz", "Material de Limpeza").</li>
                                <li>Clique em <strong>Salvar Lançamento</strong>.</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- 5. Relatórios -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden group">
                    <button onclick="toggleAccordion(this)" class="w-full text-left p-6 flex justify-between items-center focus:outline-none hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center font-bold">5</div>
                            <h2 class="font-bold text-lg text-gray-800">Relatórios e Conferência</h2>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 accordion-icon"></i>
                    </button>
                    <div class="accordion-content px-6 bg-gray-50/50">
                        <div class="pb-6 text-gray-600 space-y-3 leading-relaxed">
                            <p>Para conferir o caixa e ver o histórico:</p>
                            <ol class="list-decimal ml-5 space-y-1">
                                <li>Na tela principal do Financeiro, você verá uma lista com os <strong>Últimos 50 Lançamentos</strong>.</li>
                                <li>Entradas aparecem em <strong>Verde</strong> e Saídas em <strong>Vermelho</strong>.</li>
                                <li>Você pode clicar no ícone de <strong>Lápis</strong> para corrigir um valor ou na <strong>Lixeira</strong> para excluir um lançamento incorreto.</li>
                                <li>Se houve anexo de comprovante, aparecerá um link "Ver Comprovante".</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- 6. Baixar e Instalar o App -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden group">
                    <button onclick="toggleAccordion(this)" class="w-full text-left p-6 flex justify-between items-center focus:outline-none hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center font-bold">6</div>
                            <h2 class="font-bold text-lg text-gray-800">Baixar e Instalar o APP</h2>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 accordion-icon"></i>
                    </button>
                    <div class="accordion-content px-6 bg-gray-50/50">
                        <div class="pb-6 text-gray-600 space-y-3 leading-relaxed">
                            <p>Recomendamos instalar o APP para facilitar lançamentos rápidos pelo celular.</p>
                            <h3 class="font-bold text-gray-800 mt-2">No Android (Chrome):</h3>
                            <ul class="list-disc ml-5 space-y-1">
                                <li>Acesse o sistema pelo navegador <strong>Google Chrome</strong>.</li>
                                <li>Toque na notificação <strong>"Instalar App"</strong> ou no menu (três pontos) > <strong>"Instalar aplicativo"</strong>.</li>
                            </ul>
                            <h3 class="font-bold text-gray-800 mt-2">No iPhone (Safari):</h3>
                            <ul class="list-disc ml-5 space-y-1">
                                <li>Acesse pelo <strong>Safari</strong>.</li>
                                <li>Toque em <strong>Compartilhar</strong> <i class="fas fa-share-square"></i>.</li>
                                <li>Selecione <strong>"Adicionar à Tela de Início"</strong>.</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="mt-12 text-center">
                <p class="text-gray-500">Precisa de ajuda com permissões?</p>
                <p class="font-bold text-gray-900">Fale com o Administrador da Igreja.</p>
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
            // Toggle current
            parent.classList.toggle('accordion-open');
        }
    </script>
</body>
</html>
