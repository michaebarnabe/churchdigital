<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Cancelado - ChurchDigital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center text-red-500 text-2xl mx-auto mb-6">
            <i class="fas fa-times"></i>
        </div>
        
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Pedido Cancelado</h1>
        <p class="text-gray-600 mb-6">O processo de pagamento foi interrompido. Nenhuma cobrança foi realizada no seu cartão.</p>

        <div class="space-y-3">
            <a href="pricing.php" class="block w-full bg-indigo-600 text-white font-bold py-3 rounded-lg hover:bg-indigo-700 transition">
                Tentar Novamente
            </a>
            <a href="index.php" class="block w-full bg-gray-100 text-gray-700 font-bold py-3 rounded-lg hover:bg-gray-200 transition">
                Voltar ao Início
            </a>
        </div>
    </div>

</body>
</html>
