<?php
// plugins/aniversariantes/index.php
// Acesso liberado para Admin, Secretário e Membros
register_menu_item('Aniversariantes', 'index.php?page=aniversariantes', 'fas fa-birthday-cake', 'admin,secretario,tesoureiro,membro');
?>
