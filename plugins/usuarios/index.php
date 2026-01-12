<?php
// plugins/usuarios/index.php

// Registra o item no menu global com restrição de acesso
// Apenas 'admin' pode ver e acessar.
register_menu_item('Equipe', 'index.php?page=usuarios', 'fas fa-user-shield', 'admin');
?>
