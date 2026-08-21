<?php
/**
 * Point d'entrée CSS admin FPL — à inclure dans le <head> des pages admin
 */
require_once __DIR__ . '/../../includes/fpl_assets.php';
// La couche FPL des écrans de stock se charge toujours en dernier : elle prime
// sur les feuilles existantes sans en modifier aucune ligne.
$fpl_admin_extra_css = isset($fpl_admin_extra_css) && is_array($fpl_admin_extra_css) ? $fpl_admin_extra_css : [];
$fpl_admin_extra_css[] = 'fpl-stock.css';
fpl_admin_styles($fpl_admin_extra_css);
