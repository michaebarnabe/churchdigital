<?php
// plugins/financeiro/index.php

// Registra o item no menu global com restrição de acesso
// Apenas 'admin' e 'tesoureiro' podem ver.
register_menu_item('Financeiro', 'index.php?page=financeiro', 'fas fa-wallet', 'admin,tesoureiro');
?>
