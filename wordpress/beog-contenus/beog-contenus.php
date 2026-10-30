<?php
/**
 * Plugin Name: Beog-Zaadba — Contenus, projets et demandes
 * Description: Types de contenus FR/EN, fiches projet structurées, demandes de contact et de partenariat, rôles et pièces privées. Aucun paiement.
 * Version: 2.0.1
 * Requires PHP: 8.1
 * Requires at least: 6.6
 * License: GPL-2.0-or-later
 * Text Domain: beog
 */
if (!defined('ABSPATH')) exit;

define('BEOG_VERSION', '2.0.1');
define('BEOG_DIR', __DIR__);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/content-types.php';
require_once __DIR__ . '/includes/projects.php';
require_once __DIR__ . '/includes/inquiries.php';
require_once __DIR__ . '/includes/private-docs.php';
require_once __DIR__ . '/includes/import.php';
if (defined('WP_CLI') && WP_CLI) require_once __DIR__ . '/includes/cli.php';

register_activation_hook(__FILE__, function () {
	beog_register_types();
	beog_install_roles();
	beog_private_dir();
	flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () { flush_rewrite_rules(); });

// Mise à niveau des rôles quand la version change sans réactivation.
add_action('admin_init', function () {
	if (get_option('beog_roles_version') !== BEOG_VERSION) beog_install_roles();
});
