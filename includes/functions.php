<?php
if (!defined('ABSPATH')) exit;

// Array global para armazenar itens do menu
$global_menu_items = [];

/**
 * Adiciona um item ao menu principal
 *
 * @param string $label Nome do item
 * @param string $url Link do destino
 * @param string $icon Classe do ícone (ex: FontAwesome ou similar, aqui placeholder)
 * @param string $role Nível de acesso necessário (opcional)
 */
function register_menu_item($label, $url, $icon = 'fa-circle', $role = null) {
    global $global_menu_items;
    $global_menu_items[] = [
        'label' => $label,
        'url' => $url,
        'icon' => $icon,
        'role' => $role
    ];
}

/**
 * Carrega automaticamente os plugins da pasta /plugins
 */
function load_plugins() {
    $plugins_dir = __DIR__ . '/../plugins';
    
    if (!is_dir($plugins_dir)) {
        return;
    }

    $files = scandir($plugins_dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $plugin_index = $plugins_dir . '/' . $file . '/index.php';
        
        if (file_exists($plugin_index)) {
            include_once $plugin_index;
        }
    }
}

/**
 * Helper para escapar strings HTML
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Returns the gender-correct label for a system role
 */
function get_role_label($level, $sexo = 'M') {
    $roleMap = [
        'admin' => ['M' => 'Administrador', 'F' => 'Administradora'],
        'tesoureiro' => ['M' => 'Tesoureiro', 'F' => 'Tesoureira'],
        'secretario' => ['M' => 'Secretário', 'F' => 'Secretária'],
    ]; // Default to capitalized if not found
    return $roleMap[$level][$sexo] ?? ucfirst($level);
}
?>
