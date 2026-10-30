<?php
if (!defined('ABSPATH')) exit;

/**
 * Rôles nominatifs prévus par le cahier des charges (§12) :
 * - Rédaction Beog-Zaadba : prépare des brouillons, ne publie pas.
 * - Direction Beog-Zaadba : valide, publie, fixe les chiffres des projets, traite les demandes.
 * - Finance Beog-Zaadba : consulte les pièces privées et exporte les données financières.
 * - Partenariats Beog-Zaadba : traite et exporte les demandes de contact et de partenariat.
 * - Administrateur (WordPress) : maintenance technique ; reçoit toutes les capacités.
 */

/** Capacités propres à l'extension. */
function beog_custom_caps(): array {
	return [
		'beog_edit_project_facts',   // statut, budget, ressources, jauge
		'beog_manage_inquiries',     // lire et traiter les demandes
		'beog_export_inquiries',     // exporter les demandes
		'beog_view_private_docs',    // télécharger les pièces privées
		'beog_manage_private_docs',  // déposer et retirer des pièces privées
		'beog_export_finance',       // exports financiers (lot B)
	];
}

/** Capacités WordPress du type « beog_inquiry » (demandes). */
function beog_inquiry_caps(): array {
	return ['edit_beog_inquiries', 'edit_others_beog_inquiries', 'read_private_beog_inquiries', 'delete_beog_inquiries', 'delete_others_beog_inquiries', 'delete_private_beog_inquiries', 'edit_private_beog_inquiries'];
}

/** Capacités WordPress du type « beog_private_doc » (pièces privées). */
function beog_doc_caps(): array {
	return ['edit_beog_docs', 'edit_others_beog_docs', 'publish_beog_docs', 'read_private_beog_docs', 'delete_beog_docs', 'delete_others_beog_docs', 'delete_private_beog_docs', 'delete_published_beog_docs', 'edit_private_beog_docs', 'edit_published_beog_docs'];
}

function beog_install_roles(): void {
	$redaction = ['read' => true, 'edit_posts' => true, 'edit_pages' => true, 'edit_others_posts' => true, 'edit_others_pages' => true, 'upload_files' => true, 'delete_posts' => true, 'delete_pages' => true];

	$editor = get_role('editor');
	$direction = $editor ? $editor->capabilities : $redaction;
	foreach (array_merge(['beog_edit_project_facts', 'beog_manage_inquiries', 'beog_export_inquiries', 'beog_view_private_docs', 'beog_manage_private_docs'], beog_inquiry_caps(), beog_doc_caps()) as $cap) $direction[$cap] = true;

	// Galerie : tous les rôles créent et soumettent des albums ; la direction publie.
	$redaction = array_merge($redaction, beog_album_contributor_caps());
	foreach (beog_album_caps() as $cap) $direction[$cap] = true;
	$finance = array_merge(beog_album_contributor_caps(), ['read' => true, 'beog_view_private_docs' => true, 'beog_export_finance' => true]);
	$partenariats = array_merge(beog_album_contributor_caps(), ['read' => true, 'beog_manage_inquiries' => true, 'beog_export_inquiries' => true]);
	foreach (beog_inquiry_caps() as $cap) $partenariats[$cap] = true;

	$roles = [
		'beog_redaction'    => ['Rédaction Beog-Zaadba', $redaction],
		'beog_direction'    => ['Direction Beog-Zaadba', $direction],
		'beog_finance'      => ['Finance Beog-Zaadba', $finance],
		'beog_partenariats' => ['Partenariats Beog-Zaadba', $partenariats],
	];
	foreach ($roles as $slug => [$label, $caps]) {
		remove_role($slug);
		add_role($slug, $label, $caps);
	}

	if ($admin = get_role('administrator')) {
		foreach (array_merge(beog_custom_caps(), beog_inquiry_caps(), beog_doc_caps(), beog_album_caps()) as $cap) $admin->add_cap($cap);
	}
	update_option('beog_roles_version', BEOG_VERSION, false);
}
