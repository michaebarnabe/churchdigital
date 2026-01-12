</main>

<!-- Bottom Navigation Bar (Mobile First) -->
<nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-[0_-2px_10px_rgba(0,0,0,0.05)] z-50 px-6 py-2 flex justify-between items-center h-16 safe-area-bottom">
    
    <!-- 1. Home / Dashboard -->
    <a href="index.php" class="flex flex-col items-center justify-center min-w-[60px] h-full text-gray-400 hover:text-primary transition-colors <?php echo (!isset($_GET['page']) || $_GET['page'] == 'dashboard') ? 'text-primary' : ''; ?>">
        <i class="fas fa-home text-xl mb-1"></i>
        <span class="text-xs font-medium">Início</span>
    </a>

    <!-- 2. Carteirinha (Center Highlight) - Only for Members -->
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'member'): ?>
        <a href="index.php?page=minha_carteirinha" class="-mt-8 bg-primary text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg hover:bg-secondary transition transform hover:scale-105">
            <i class="fas fa-id-card text-xl"></i>
        </a>
    <?php endif; ?>

    <!-- 3. Sair -->
    <a href="logout.php" class="flex flex-col items-center justify-center min-w-[60px] h-full text-gray-400 hover:text-red-500 transition-colors">
        <i class="fas fa-sign-out-alt text-xl mb-1"></i>
        <span class="text-xs font-medium">Sair</span>
    </a>

</nav>

<script>
    // Scripts globais se necessário
</script>
</body>
</html>
