<?php
if (!defined('ABSPATH')) exit;

/**
 * Commandes WP-CLI : wp beog import | publish | export-inquiries
 */
class Beog_CLI {
	/** Importe les contenus FR/EN en brouillon. */
	public function import(): void {
		$r = beog_import_pages();
		if (!empty($r['error'])) WP_CLI::error($r['error']);
		WP_CLI::success(sprintf('%d contenu(s) créé(s), %d déjà présent(s).', $r['created'], $r['skipped']));
	}

	/**
	 * Met à jour les pages existantes dont le texte diffère du thème.
	 *
	 * ## OPTIONS
	 *
	 * [--include-edited]
	 * : Met aussi à jour les pages modifiées dans WordPress (anciennes versions gardées en révisions).
	 *
	 * [--dry-run]
	 * : Affiche la liste sans rien modifier.
	 */
	public function resync(array $args, array $assoc): void {
		$done = 0;
		foreach (beog_resync_candidates() as $c) {
			$skip = $c['edited'] && empty($assoc['include-edited']);
			WP_CLI::log(sprintf('%s /%s/ %s', $skip ? '[ignorée]' : (!empty($assoc['dry-run']) ? '[à mettre à jour]' : '[mise à jour]'), $c['route'], $c['edited'] ? '(modifiée dans WordPress)' : ''));
			if (!$skip && empty($assoc['dry-run']) && beog_resync_post($c)) $done++;
		}
		WP_CLI::success($done . ' page(s) mise(s) à jour.');
	}

	/** Publie tous les brouillons importés (environnement de test uniquement). */
	public function publish(): void {
		if (wp_get_environment_type() === 'production') WP_CLI::error('Refusé en production : la direction valide et publie depuis l’administration.');
		$ids = get_posts(['post_type' => beog_routed_types(), 'post_status' => 'draft', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_beog_route', 'meta_compare' => 'EXISTS']);
		$ids = array_merge($ids, get_posts(['post_type' => 'beog_album', 'post_status' => 'draft', 'posts_per_page' => -1, 'fields' => 'ids']));
		foreach ($ids as $id) wp_update_post(['ID' => $id, 'post_status' => 'publish']);
		WP_CLI::success(count($ids) . ' contenu(s) publié(s).');
	}

	/**
	 * Écrit l'export CSV des demandes dans un fichier.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Chemin du fichier CSV à créer.
	 *
	 * @subcommand export-inquiries
	 */
	public function export_inquiries(array $args): void {
		[$header, $rows] = beog_inquiry_export_rows();
		$out = fopen($args[0], 'w');
		fwrite($out, "\xEF\xBB\xBF");
		fputcsv($out, array_map('beog_csv_cell', $header), ';', '"', '');
		foreach ($rows as $row) fputcsv($out, array_map('beog_csv_cell', $row), ';', '"', '');
		fclose($out);
		WP_CLI::success(count($rows) . ' demande(s) exportée(s).');
	}
}
WP_CLI::add_command('beog', 'Beog_CLI');
