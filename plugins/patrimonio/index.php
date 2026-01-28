<?php
// plugins/patrimonio/index.php

// Apenas usuários autorizados podem ver no menu
if (has_role('admin') || has_role('tesoureiro') || has_role('secretario')) {
    register_menu_item('Patrimônio', 'index.php?page=patrimonio', 'fa-boxes', 'admin,tesoureiro,secretario');
}
?>
