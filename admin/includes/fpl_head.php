<?php
/**
 * Point d'entrée CSS admin FPL — à inclure dans le <head> des pages admin
 */
require_once __DIR__ . '/../../includes/fpl_assets.php';
fpl_admin_styles(isset($fpl_admin_extra_css) && is_array($fpl_admin_extra_css) ? $fpl_admin_extra_css : []);
