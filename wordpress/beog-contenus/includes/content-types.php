<?php
if (!defined('ABSPATH')) exit;

/** Types de contenus éditoriaux et routes /fr/… /en/… */
function beog_register_types(): void {
	$types = [
		'beog_project' => ['Projets', 'Projet', 'dashicons-portfolio'],
		'beog_news'    => ['Actualités', 'Actualité', 'dashicons-megaphone'],
		'beog_report'  => ['Rapports', 'Rapport', 'dashicons-media-document'],
	];
	foreach ($types as $type => [$plural, $singular, $icon]) {
		register_post_type($type, [
			'labels' => ['name' => $plural, 'singular_name' => $singular, 'add_new_item' => 'Ajouter : ' . strtolower($singular), 'edit_item' => 'Modifier : ' . strtolower($singular), 'menu_name' => $plural],
			'public' => true, 'show_in_rest' => true, 'menu_icon' => $icon,
			'supports' => ['title', 'editor', 'revisions', 'thumbnail', 'excerpt'],
			'rewrite' => false, 'has_archive' => false,
			'capability_type' => 'post', 'map_meta_cap' => true,
		]);
	}
	register_post_status('beog_archive', [
		'label' => 'Archivé', 'public' => false, 'internal' => false, 'protected' => true,
		'exclude_from_search' => true, 'show_in_admin_all_list' => false, 'show_in_admin_status_list' => true,
		'label_count' => _n_noop('Archivé <span class="count">(%s)</span>', 'Archivés <span class="count">(%s)</span>'),
	]);
	foreach (['_beog_route', '_beog_lang', '_beog_description', '_beog_project'] as $key) {
		register_post_meta('', $key, ['type' => 'string', 'single' => true, 'show_in_rest' => false, 'auth_callback' => fn() => current_user_can('edit_posts')]);
	}
	add_rewrite_rule('^((?:fr|en)(?:/[^?]*)?)/?$', 'index.php?beog_route=$matches[1]', 'top');
}
add_action('init', 'beog_register_types');

function beog_routed_types(): array { return ['page', 'beog_project', 'beog_news', 'beog_report']; }

add_filter('query_vars', function ($vars) { $vars[] = 'beog_route'; return $vars; });

// Une route publique ne sert que du contenu publié ; l'aperçu d'un brouillon reste réservé aux éditeurs.
add_filter('request', function ($q) {
	if (empty($q['beog_route'])) return $q;
	$route = trim(sanitize_text_field($q['beog_route']), '/');
	$preview = isset($_GET['preview']) && current_user_can('edit_posts');
	$ids = get_posts(['post_type' => beog_routed_types(), 'post_status' => $preview ? ['publish', 'draft', 'pending', 'future', 'private'] : 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_beog_route', 'meta_value' => $route, 'suppress_filters' => false]);
	return $ids ? ['p' => $ids[0], 'post_type' => 'any', 'post_status' => $preview ? 'any' : 'publish'] : ['p' => 0, 'error' => '404'];
});
add_filter('post_type_link', fn($url, $post) => ($r = get_post_meta($post->ID, '_beog_route', true)) ? home_url('/' . trim($r, '/') . '/') : $url, 10, 2);
add_filter('page_link', fn($url, $id) => ($r = get_post_meta($id, '_beog_route', true)) ? home_url('/' . trim($r, '/') . '/') : $url, 10, 2);

/** Contenu de l'autre langue (même route, préfixe fr/en inversé). */
function beog_sibling_id(int $post_id): int {
	$route = (string) get_post_meta($post_id, '_beog_route', true);
	if (!preg_match('#^(fr|en)(/.*)?$#', $route, $m)) return 0;
	$other = ($m[1] === 'fr' ? 'en' : 'fr') . ($m[2] ?? '');
	$ids = get_posts(['post_type' => beog_routed_types(), 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_beog_route', 'meta_value' => $other, 'post__not_in' => [$post_id]]);
	return $ids ? (int) $ids[0] : 0;
}

// Boîte « Langue et adresse » : route, langue, lien vers la traduction ou création de celle-ci.
add_action('add_meta_boxes', function () {
	add_meta_box('beog_route', 'Langue et adresse', 'beog_route_box', beog_routed_types(), 'side', 'high');
	add_meta_box('beog_news_project', 'Projet associé', 'beog_news_project_box', ['beog_news', 'beog_report'], 'side');
});

function beog_route_box(WP_Post $post): void {
	wp_nonce_field('beog_route_save', 'beog_route_nonce');
	$route = (string) get_post_meta($post->ID, '_beog_route', true);
	$lang = beog_post_lang($post->ID);
	echo '<p><label for="beog_route_field"><strong>Adresse</strong> (commence par fr/ ou en/)</label><br><input type="text" class="widefat" id="beog_route_field" name="beog_route" value="' . esc_attr($route) . '" placeholder="fr/actualites/mon-article"></p>';
	echo '<p>Langue : <strong>' . ($lang === 'en' ? 'anglais' : 'français') . '</strong></p>';
	if (!$route) return;
	$sibling = beog_sibling_id($post->ID);
	$other = $lang === 'fr' ? 'anglaise' : 'française';
	if ($sibling) {
		echo '<p><a href="' . esc_url(get_edit_post_link($sibling)) . '">Modifier la version ' . $other . '</a> (' . esc_html(get_post_status_object(get_post_status($sibling))->label) . ')</p>';
	} elseif (current_user_can('edit_posts')) {
		$url = wp_nonce_url(admin_url('admin-post.php?action=beog_translate&post=' . $post->ID), 'beog_translate_' . $post->ID);
		echo '<p><a class="button" href="' . esc_url($url) . '">Créer la version ' . $other . ' en brouillon</a></p>';
	}
}

add_action('save_post', function (int $post_id) {
	if (!isset($_POST['beog_route_nonce']) || !wp_verify_nonce(sanitize_key($_POST['beog_route_nonce']), 'beog_route_save')) return;
	if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) return;
	$route = trim(strtolower(sanitize_text_field(wp_unslash($_POST['beog_route'] ?? ''))), '/');
	if ($route === '') return;
	if (!preg_match('#^(fr|en)(/[a-z0-9-]+)*$#', $route)) {
		set_transient('beog_notice_' . get_current_user_id(), 'Adresse refusée : utilisez fr/… ou en/… avec des lettres minuscules, chiffres et tirets.', 60);
		return;
	}
	$clash = get_posts(['post_type' => beog_routed_types(), 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_beog_route', 'meta_value' => $route, 'post__not_in' => [$post_id]]);
	if ($clash) {
		set_transient('beog_notice_' . get_current_user_id(), 'Adresse déjà utilisée par un autre contenu : ' . $route, 60);
		return;
	}
	update_post_meta($post_id, '_beog_route', $route);
	update_post_meta($post_id, '_beog_lang', substr($route, 0, 2));
});

add_action('admin_notices', function () {
	$key = 'beog_notice_' . get_current_user_id();
	if ($msg = get_transient($key)) {
		delete_transient($key);
		echo '<div class="notice notice-error"><p>' . esc_html($msg) . '</p></div>';
	}
});

// Création d'une traduction : copie du contenu en brouillon, adresse fr/… ↔ en/…
add_action('admin_post_beog_translate', function () {
	$id = absint($_GET['post'] ?? 0);
	check_admin_referer('beog_translate_' . $id);
	if (!$id || !current_user_can('edit_post', $id)) wp_die('Accès refusé.', 403);
	$new = beog_create_translation($id);
	if (is_wp_error($new)) wp_die(esc_html($new->get_error_message()));
	wp_safe_redirect(get_edit_post_link($new, 'raw'));
	exit;
});

function beog_create_translation(int $id) {
	if ($existing = beog_sibling_id($id)) return $existing;
	$post = get_post($id);
	$route = (string) get_post_meta($id, '_beog_route', true);
	if (!$post || !preg_match('#^(fr|en)(/.*)?$#', $route, $m)) return new WP_Error('beog_route', 'Ce contenu n’a pas d’adresse fr/ ou en/.');
	$lang = $m[1] === 'fr' ? 'en' : 'fr';
	$new = wp_insert_post(wp_slash(['post_type' => $post->post_type, 'post_status' => 'draft', 'post_title' => $post->post_title . ' [' . strtoupper($lang) . ' — à traduire]', 'post_content' => $post->post_content, 'post_excerpt' => $post->post_excerpt]), true);
	if (is_wp_error($new)) return $new;
	foreach (get_post_meta($id) as $key => $values) {
		if (in_array($key, ['_beog_route', '_beog_lang', '_edit_lock', '_edit_last'], true)) continue;
		if (str_starts_with($key, '_beog_')) update_post_meta($new, $key, maybe_unserialize($values[0]));
	}
	update_post_meta($new, '_beog_route', $lang . ($m[2] ?? ''));
	update_post_meta($new, '_beog_lang', $lang);
	return $new;
}

// Projet associé à une actualité ou un rapport : la fiche projet liste ces publications.
function beog_news_project_box(WP_Post $post): void {
	wp_nonce_field('beog_news_project_save', 'beog_news_project_nonce');
	$current = (string) get_post_meta($post->ID, '_beog_project', true);
	echo '<label class="screen-reader-text" for="beog_project_field">Projet associé</label><select id="beog_project_field" name="beog_project" class="widefat"><option value="">Aucun</option>';
	foreach (beog_project_keys() as $key => $label) echo '<option value="' . esc_attr($key) . '"' . selected($current, $key, false) . '>' . esc_html($label) . '</option>';
	echo '</select><p class="description">La publication apparaîtra dans le suivi de la fiche projet, dans la même langue.</p>';
}
add_action('save_post', function (int $post_id) {
	if (!isset($_POST['beog_news_project_nonce']) || !wp_verify_nonce(sanitize_key($_POST['beog_news_project_nonce']), 'beog_news_project_save')) return;
	if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) return;
	$key = sanitize_key(wp_unslash($_POST['beog_project'] ?? ''));
	if ($key === '' || isset(beog_project_keys()[$key])) update_post_meta($post_id, '_beog_project', $key);
});

// Archivage : retire un contenu du site sans le supprimer.
add_filter('post_row_actions', 'beog_archive_row_action', 10, 2);
add_filter('page_row_actions', 'beog_archive_row_action', 10, 2);
function beog_archive_row_action(array $actions, WP_Post $post): array {
	if (!in_array($post->post_type, beog_routed_types(), true) || !current_user_can('publish_posts') || !current_user_can('edit_post', $post->ID)) return $actions;
	$archived = $post->post_status === 'beog_archive';
	$url = wp_nonce_url(admin_url('admin-post.php?action=beog_archive&post=' . $post->ID), 'beog_archive_' . $post->ID);
	$actions['beog_archive'] = '<a href="' . esc_url($url) . '">' . ($archived ? 'Restaurer en brouillon' : 'Archiver') . '</a>';
	return $actions;
}
add_action('admin_post_beog_archive', function () {
	$id = absint($_GET['post'] ?? 0);
	check_admin_referer('beog_archive_' . $id);
	if (!$id || !current_user_can('publish_posts') || !current_user_can('edit_post', $id)) wp_die('Accès refusé.', 403);
	$post = get_post($id);
	wp_update_post(['ID' => $id, 'post_status' => $post->post_status === 'beog_archive' ? 'draft' : 'beog_archive']);
	wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=' . $post->post_type));
	exit;
});
