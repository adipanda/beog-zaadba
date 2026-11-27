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
		if (($p['type'] ?? '') === 'album') {
			if (($p['lang'] ?? '') === 'fr' && !empty($p['album'])) beog_import_album((array) $p['album']) ? $created++ : $skipped++;
			continue;
		}
		$route = sanitize_text_field($p['route']);
		$exists = get_posts(['post_type' => beog_routed_types(), 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_beog_route', 'meta_value' => $route]);
		if ($exists) { $skipped++; continue; }
		$type = ['project' => 'beog_project', 'news' => 'beog_news'][$p['type']] ?? 'page';
		$id = wp_insert_post(wp_slash([
			'post_type' => $type, 'post_status' => 'draft', 'post_title' => sanitize_text_field($p['title']),
			'post_name' => sanitize_title(str_replace('/', '-', $route)),
			'post_content' => beog_import_content($p['body']),
		]), true);
		if (is_wp_error($id)) continue;
		update_post_meta($id, '_beog_route', $route);
		update_post_meta($id, '_beog_lang', sanitize_key($p['lang']));
		update_post_meta($id, '_beog_description', sanitize_text_field($p['description'] ?? ''));
		update_post_meta($id, '_beog_source_hash', md5(beog_import_content($p['body'])));
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
		echo '<input type="hidden" name="action" value="beog_import"><button class="button button-primary">Importer les contenus en brouillon</button></form>';
		beog_resync_screen();
		echo '</div>';
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

/**
 * Crée un album en brouillon, avec ses médias copiés depuis le thème
 * (assets/galerie). Renvoie false si l'album existe déjà.
 */
function beog_import_album(array $album): bool {
	$slug = sanitize_title($album['id'] ?? '');
	if ($slug === '' || get_posts(['post_type' => 'beog_album', 'name' => $slug, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids'])) return false;
	$dir = get_template_directory() . '/assets/galerie/';
	$ids = [];
	foreach ((array) ($album['items'] ?? []) as $item) {
		$alt = (array) ($item['alt'] ?? ['', '']); $cap = (array) ($item['caption'] ?? ['', '']);
		if (!empty($item['video'])) {
			$poster = beog_import_media($dir . $item['poster'] . '.webp', $alt, $cap);
			$vid = beog_import_media($dir . $item['video'] . '.mp4', $alt, $cap);
			if ($vid) { if ($poster) update_post_meta($vid, '_beog_poster', $poster); $ids[] = $vid; }
		} elseif ($id = beog_import_media($dir . $item['file'] . '.webp', $alt, $cap)) {
			$ids[] = $id;
		}
	}
	$title = (array) ($album['title'] ?? ['', '']); $desc = (array) ($album['description'] ?? ['', '']);
	$post = wp_insert_post(wp_slash(['post_type' => 'beog_album', 'post_status' => 'draft', 'post_name' => $slug, 'post_title' => sanitize_text_field($title[0] ?? '')]), true);
	if (is_wp_error($post)) return false;
	update_post_meta($post, '_beog_album_date', (string) ($album['date'] ?? ''));
	update_post_meta($post, '_beog_album_title_en', sanitize_text_field($title[1] ?? ''));
	update_post_meta($post, '_beog_album_desc_fr', sanitize_textarea_field($desc[0] ?? ''));
	update_post_meta($post, '_beog_album_desc_en', sanitize_textarea_field($desc[1] ?? ''));
	update_post_meta($post, '_beog_album_items', $ids);
	update_post_meta($post, '_beog_consent', ['user' => get_current_user_id(), 'at' => time(), 'note' => 'Sélection initiale : aucun enfant reconnaissable.']);
	foreach ($ids as $id) wp_update_post(['ID' => $id, 'post_parent' => $post]);
	return true;
}

/** Copie un fichier du thème dans la médiathèque (une seule fois par fichier). */
function beog_import_media(string $path, array $alt, array $caption): int {
	if (!is_readable($path)) return 0;
	$name = basename($path);
	$found = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_beog_source', 'meta_value' => $name]);
	if ($found) return (int) $found[0];
	$upload = wp_upload_bits($name, null, (string) file_get_contents($path));
	if (!empty($upload['error'])) return 0;
	$type = wp_check_filetype($upload['file']);
	$id = wp_insert_attachment(['post_mime_type' => $type['type'], 'post_title' => sanitize_text_field($caption[0] ?? $name), 'post_excerpt' => sanitize_text_field($caption[0] ?? ''), 'post_status' => 'inherit'], $upload['file']);
	if (is_wp_error($id) || !$id) return 0;
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
	update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($alt[0] ?? ''));
	update_post_meta($id, '_beog_alt_en', sanitize_text_field($alt[1] ?? ''));
	update_post_meta($id, '_beog_caption_en', sanitize_text_field($caption[1] ?? ''));
	update_post_meta($id, '_beog_source', $name);
	return (int) $id;
}

/** Contenu enregistré pour un corps de page issu de pages.json. */
function beog_import_content(string $body): string {
	return '<!-- wp:html -->' . $body . '<!-- /wp:html -->';
}

/**
 * Pages déjà en ligne dont le texte diffère de la version du thème (pages.json).
 * « edited » signale une page modifiée dans WordPress depuis l'import : la mettre
 * à jour remplacerait ces modifications (elles restent dans les révisions).
 */
function beog_resync_candidates(): array {
	$pages = json_decode((string) file_get_contents(BEOG_DIR . '/pages.json'), true);
	if (!is_array($pages)) return [];
	$out = [];
	foreach ($pages as $p) {
		if (($p['type'] ?? '') === 'album' || empty($p['route'])) continue;
		$ids = get_posts(['post_type' => beog_routed_types(), 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_beog_route', 'meta_value' => $p['route']]);
		if (!$ids) continue;
		$post = get_post($ids[0]);
		$content = beog_import_content($p['body']);
		$title = sanitize_text_field($p['title']);
		if ($post->post_content === $content && $post->post_title === $title) continue;
		$hash = (string) get_post_meta($post->ID, '_beog_source_hash', true);
		$edited = $hash !== '' ? md5($post->post_content) !== $hash : count(wp_get_post_revisions($post->ID, ['fields' => 'ids'])) > 1;
		$out[] = ['id' => $post->ID, 'route' => $p['route'], 'title' => $title, 'content' => $content, 'description' => sanitize_text_field($p['description'] ?? ''), 'edited' => $edited];
	}
	return $out;
}

/** Met à jour une page depuis pages.json ; l'ancienne version reste dans les révisions. */
function beog_resync_post(array $c): bool {
	$r = wp_update_post(wp_slash(['ID' => $c['id'], 'post_title' => $c['title'], 'post_content' => $c['content']]), true);
	if (is_wp_error($r)) return false;
	update_post_meta($c['id'], '_beog_description', $c['description']);
	update_post_meta($c['id'], '_beog_source_hash', md5($c['content']));
	return true;
}

function beog_resync_screen(): void {
	$list = beog_resync_candidates();
	echo '<h2 style="margin-top:2em">Mettre à jour les textes des pages existantes</h2>';
	if (isset($_GET['resynced'])) echo '<div class="notice notice-success"><p>' . (int) $_GET['resynced'] . ' page(s) mise(s) à jour. Les versions précédentes restent disponibles dans les révisions.</p></div>';
	if (!$list) { echo '<p>Toutes les pages correspondent à la version actuelle du thème.</p>'; return; }
	echo '<p>Ces pages diffèrent de la version actuelle du thème. Les pages modifiées dans WordPress sont décochées : les mettre à jour remplacerait ces modifications, qui resteraient récupérables dans les révisions.</p>';
	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	wp_nonce_field('beog_resync');
	echo '<input type="hidden" name="action" value="beog_resync"><table class="widefat striped" style="max-width:900px"><thead><tr><td class="check-column"></td><th>Page</th><th>Adresse</th><th>État</th></tr></thead><tbody>';
	foreach ($list as $c) {
		echo '<tr><th class="check-column"><input type="checkbox" name="routes[]" value="' . esc_attr($c['route']) . '"' . checked(!$c['edited'], true, false) . ' aria-label="' . esc_attr($c['title']) . '"></th><td>' . esc_html($c['title']) . '</td><td><code>/' . esc_html($c['route']) . '/</code></td><td>' . ($c['edited'] ? '<strong style="color:#b32d2e">Modifiée dans WordPress</strong>' : 'Texte du thème plus récent') . '</td></tr>';
	}
	echo '</tbody></table><p><button class="button button-primary">Mettre à jour les pages cochées</button></p></form>';
}

add_action('admin_post_beog_resync', function () {
	if (!current_user_can('manage_options')) wp_die('Accès refusé.', 403);
	check_admin_referer('beog_resync');
	$wanted = array_map('sanitize_text_field', (array) wp_unslash($_POST['routes'] ?? []));
	$done = 0;
	foreach (beog_resync_candidates() as $c) if (in_array($c['route'], $wanted, true) && beog_resync_post($c)) $done++;
	wp_safe_redirect(add_query_arg(['page' => 'beog-import', 'resynced' => $done], admin_url('tools.php')));
	exit;
});
