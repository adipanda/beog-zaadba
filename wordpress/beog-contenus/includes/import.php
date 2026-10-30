<?php
if (!defined('ABSPATH')) exit;

/**
 * Import initial des contenus FR/EN générés par build.mjs (pages.json).
 * Les contenus sont créés en brouillon ; un contenu déjà importé n'est pas écrasé.
 * Les fiches projet reçoivent leurs données structurées (état, budget daté, textes).
 */
function beog_import_pages(): array {
	$pages = json_decode((string) file_get_contents(BEOG_DIR . '/pages.json'), true);
	if (!is_array($pages)) return ['created' => 0, 'skipped' => 0, 'error' => 'Le fichier de contenus est illisible.'];
	$created = $skipped = 0;
	foreach ($pages as $p) {
		$route = sanitize_text_field($p['route']);
		$exists = get_posts(['post_type' => beog_routed_types(), 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_beog_route', 'meta_value' => $route]);
		if ($exists) { $skipped++; continue; }
		$type = ['project' => 'beog_project', 'news' => 'beog_news'][$p['type']] ?? 'page';
		$id = wp_insert_post(wp_slash([
			'post_type' => $type, 'post_status' => 'draft', 'post_title' => sanitize_text_field($p['title']),
			'post_name' => sanitize_title(str_replace('/', '-', $route)),
			'post_content' => '<!-- wp:html -->' . $p['body'] . '<!-- /wp:html -->',
		]), true);
		if (is_wp_error($id)) continue;
		update_post_meta($id, '_beog_route', $route);
		update_post_meta($id, '_beog_lang', sanitize_key($p['lang']));
		update_post_meta($id, '_beog_description', sanitize_text_field($p['description'] ?? ''));
		if ($type === 'beog_project' && !empty($p['project'])) {
			update_post_meta($id, '_beog_project', sanitize_key($p['project']));
			$f = (array) ($p['facts'] ?? []);
			update_post_meta($id, '_beog_facts', [
				'status' => 'preparation', 'budget' => (int) ($f['budget'] ?? 0), 'budget_date' => (string) ($f['budgetDate'] ?? ''),
				'secured' => null, 'secured_date' => '', 'objective_ok' => false, 'objective' => 0, 'confirmed' => 0, 'confirmed_date' => '',
				'lead' => sanitize_textarea_field((string) ($f['lead'] ?? '')), 'location' => sanitize_textarea_field((string) ($f['location'] ?? '')),
				'timing' => sanitize_textarea_field((string) ($f['timing'] ?? '')), 'beneficiaries' => sanitize_textarea_field((string) ($f['beneficiaries'] ?? '')),
			]);
			update_post_meta($id, '_beog_budget_history', [['budget' => (int) ($f['budget'] ?? 0), 'budget_date' => (string) ($f['budgetDate'] ?? ''), 'user' => get_current_user_id(), 'at' => time()]]);
		}
		$created++;
	}
	flush_rewrite_rules();
	return ['created' => $created, 'skipped' => $skipped];
}

add_action('admin_menu', function () {
	add_management_page('Import Beog-Zaadba', 'Import Beog-Zaadba', 'manage_options', 'beog-import', function () {
		if (!current_user_can('manage_options')) return;
		echo '<div class="wrap"><h1>Importer la présentation Beog-Zaadba</h1>';
		if (isset($_GET['imported'])) echo '<div class="notice notice-success"><p>' . (int) $_GET['imported'] . ' contenu(s) créé(s) en brouillon.</p></div>';
		echo '<p>Crée les contenus initiaux FR/EN en brouillon pour validation. Un contenu déjà importé est conservé. Aucun paiement n’est activé.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('beog_import');
		echo '<input type="hidden" name="action" value="beog_import"><button class="button button-primary">Importer les contenus en brouillon</button></form></div>';
	});
});

add_action('admin_post_beog_import', function () {
	if (!current_user_can('manage_options')) wp_die('Accès refusé.', 403);
	check_admin_referer('beog_import');
	$r = beog_import_pages();
	if (!empty($r['error'])) wp_die(esc_html($r['error']));
	wp_safe_redirect(add_query_arg(['page' => 'beog-import', 'imported' => $r['created']], admin_url('tools.php')));
	exit;
});
