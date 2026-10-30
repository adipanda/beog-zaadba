<?php
if (!defined('ABSPATH')) exit;

/**
 * Pièces privées (cahier des charges §6 et §12) : devis, conventions, justificatifs.
 * Les fichiers sont rangés hors de la médiathèque publique, sous un nom aléatoire,
 * et ne sont servis qu'après vérification des droits, à chaque téléchargement.
 *
 * Emplacement : constante BEOG_PRIVATE_DIR dans wp-config.php, de préférence hors
 * de la racine web. À défaut : wp-content/beog-private/ protégé par .htaccess
 * (Apache) ; sous Nginx, ajouter une règle « deny all » sur ce dossier.
 */

const BEOG_DOC_MAX_BYTES = 10 * 1024 * 1024;

function beog_doc_mimes(): array {
	return ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'csv' => 'text/csv'];
}

function beog_private_dir(): string {
	$dir = defined('BEOG_PRIVATE_DIR') ? BEOG_PRIVATE_DIR : WP_CONTENT_DIR . '/beog-private';
	$dir = rtrim($dir, '/\\');
	if (!is_dir($dir)) wp_mkdir_p($dir);
	if (!file_exists($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
	if (!file_exists($dir . '/index.php')) @file_put_contents($dir . '/index.php', "<?php // Silence.\n");
	return $dir;
}

add_action('init', function () {
	register_post_type('beog_private_doc', [
		'labels' => ['name' => 'Pièces privées', 'singular_name' => 'Pièce privée', 'add_new_item' => 'Déposer une pièce privée', 'edit_item' => 'Modifier la pièce privée', 'menu_name' => 'Pièces privées'],
		'public' => false, 'show_ui' => true, 'menu_icon' => 'dashicons-lock', 'menu_position' => 27,
		'supports' => ['title'], 'capability_type' => ['beog_doc', 'beog_docs'], 'map_meta_cap' => true,
		'exclude_from_search' => true, 'show_in_rest' => false,
	]);
});

// Les pièces privées restent au statut « privé ».
add_filter('wp_insert_post_data', function ($data) {
	if ($data['post_type'] === 'beog_private_doc' && in_array($data['post_status'], ['publish', 'future', 'pending'], true)) $data['post_status'] = 'private';
	return $data;
});

add_action('post_edit_form_tag', function (WP_Post $post) {
	if ($post->post_type === 'beog_private_doc') echo ' enctype="multipart/form-data"';
});

add_action('add_meta_boxes_beog_private_doc', function () {
	add_meta_box('beog_doc_file', 'Fichier', 'beog_doc_box', 'beog_private_doc', 'normal', 'high');
});

function beog_doc_box(WP_Post $post): void {
	wp_nonce_field('beog_doc_save', 'beog_doc_nonce');
	$name = (string) get_post_meta($post->ID, '_beog_doc_name', true);
	if ($name) {
		echo '<p>Fichier actuel : <strong>' . esc_html($name) . '</strong> (' . esc_html(size_format((int) get_post_meta($post->ID, '_beog_doc_size', true))) . ') — <a href="' . esc_url(beog_doc_url($post->ID)) . '">Télécharger</a></p>';
	}
	echo '<p><label for="beog_doc">' . ($name ? 'Remplacer le fichier' : 'Choisir le fichier') . '</label><br><input type="file" id="beog_doc" name="beog_doc" accept=".' . esc_attr(implode(',.', array_keys(beog_doc_mimes()))) . '"></p>';
	echo '<p class="description">PDF, images, Word, Excel ou CSV, 10 Mo au plus. Le fichier n’a pas d’adresse publique : seules les personnes autorisées peuvent le télécharger depuis cette administration.</p>';
	$projects = beog_project_keys();
	$current = (string) get_post_meta($post->ID, '_beog_project', true);
	echo '<p><label for="beog_doc_project">Projet concerné</label><br><select id="beog_doc_project" name="beog_doc_project"><option value="">Aucun</option>';
	foreach ($projects as $k => $l) echo '<option value="' . esc_attr($k) . '"' . selected($current, $k, false) . '>' . esc_html($l) . '</option>';
	echo '</select></p>';
}

add_action('save_post_beog_private_doc', function (int $id) {
	if (!isset($_POST['beog_doc_nonce']) || !wp_verify_nonce(sanitize_key($_POST['beog_doc_nonce']), 'beog_doc_save')) return;
	if (!current_user_can('beog_manage_private_docs') || !current_user_can('edit_post', $id)) return;
	$key = sanitize_key(wp_unslash($_POST['beog_doc_project'] ?? ''));
	update_post_meta($id, '_beog_project', isset(beog_project_keys()[$key]) ? $key : '');
	if (empty($_FILES['beog_doc']['tmp_name'])) return;
	$stored = beog_store_private_file($_FILES['beog_doc']['tmp_name'], (string) $_FILES['beog_doc']['name'], (int) $_FILES['beog_doc']['size'], true);
	if (is_wp_error($stored)) {
		set_transient('beog_notice_' . get_current_user_id(), $stored->get_error_message(), 60);
		return;
	}
	beog_attach_private_file($id, $stored);
});

/**
 * Contrôle et range un fichier dans le dossier privé. Renvoie [chemin, nom, taille, type].
 */
function beog_store_private_file(string $tmp, string $original, int $size, bool $uploaded) {
	if ($uploaded && !is_uploaded_file($tmp)) return new WP_Error('beog_doc', 'Envoi de fichier non valide.');
	if ($size <= 0 || $size > BEOG_DOC_MAX_BYTES) return new WP_Error('beog_doc', 'Fichier refusé : 10 Mo au plus.');
	$check = wp_check_filetype_and_ext($tmp, $original, beog_doc_mimes());
	if (!$check['ext'] || !$check['type']) return new WP_Error('beog_doc', 'Type de fichier refusé : PDF, image, Word, Excel ou CSV uniquement.');
	$dest = beog_private_dir() . '/' . wp_generate_password(32, false) . '.' . $check['ext'];
	$ok = $uploaded ? move_uploaded_file($tmp, $dest) : copy($tmp, $dest);
	if (!$ok) return new WP_Error('beog_doc', 'Le fichier n’a pas pu être enregistré.');
	@chmod($dest, 0640);
	return [$dest, sanitize_file_name($original), $size, $check['type']];
}

function beog_attach_private_file(int $id, array $stored): void {
	[$path, $name, $size, $type] = $stored;
	$old = (string) get_post_meta($id, '_beog_doc_path', true);
	if ($old && $old !== $path && str_starts_with($old, beog_private_dir()) && is_file($old)) wp_delete_file($old);
	update_post_meta($id, '_beog_doc_path', $path);
	update_post_meta($id, '_beog_doc_name', $name);
	update_post_meta($id, '_beog_doc_size', $size);
	update_post_meta($id, '_beog_doc_type', $type);
}

function beog_doc_url(int $id): string {
	return wp_nonce_url(admin_url('admin-post.php?action=beog_doc&doc=' . $id), 'beog_doc_' . $id);
}

/** Droit de télécharger une pièce : vérifié à chaque accès. */
function beog_can_view_doc(int $id, ?int $user_id = null): bool {
	$user_id = $user_id ?? get_current_user_id();
	return $user_id && get_post_type($id) === 'beog_private_doc' && user_can($user_id, 'beog_view_private_docs');
}

add_action('admin_post_beog_doc', function () {
	$id = absint($_GET['doc'] ?? 0);
	check_admin_referer('beog_doc_' . $id);
	if (!beog_can_view_doc($id)) wp_die('Accès refusé.', 403);
	$path = (string) get_post_meta($id, '_beog_doc_path', true);
	$real = realpath($path);
	if (!$real || !str_starts_with($real, realpath(beog_private_dir())) || !is_readable($real)) wp_die('Fichier introuvable.', 404);
	nocache_headers();
	header('Content-Type: ' . (get_post_meta($id, '_beog_doc_type', true) ?: 'application/octet-stream'));
	header('Content-Length: ' . filesize($real));
	header('Content-Disposition: attachment; filename="' . sanitize_file_name((string) get_post_meta($id, '_beog_doc_name', true)) . '"');
	header('X-Content-Type-Options: nosniff');
	readfile($real);
	exit;
});

// Suppression définitive d'une pièce : le fichier part avec elle.
add_action('before_delete_post', function (int $id) {
	if (get_post_type($id) !== 'beog_private_doc') return;
	$path = (string) get_post_meta($id, '_beog_doc_path', true);
	if ($path && str_starts_with($path, beog_private_dir()) && is_file($path)) wp_delete_file($path);
});

add_filter('manage_beog_private_doc_posts_columns', fn($c) => ['cb' => $c['cb'], 'title' => 'Pièce', 'beog_file' => 'Fichier', 'beog_project' => 'Projet', 'date' => 'Date']);
add_action('manage_beog_private_doc_posts_custom_column', function ($col, $id) {
	if ($col === 'beog_file') {
		$name = (string) get_post_meta($id, '_beog_doc_name', true);
		echo $name && current_user_can('beog_view_private_docs') ? '<a href="' . esc_url(beog_doc_url($id)) . '">' . esc_html($name) . '</a>' : esc_html($name ?: '—');
	}
	if ($col === 'beog_project') echo esc_html(beog_project_keys()[get_post_meta($id, '_beog_project', true)] ?? '—');
}, 10, 2);

// La finance peut consulter la liste sans pouvoir modifier : menu de lecture dédié.
add_action('admin_menu', function () {
	if (current_user_can('edit_beog_docs') || !current_user_can('beog_view_private_docs')) return;
	add_menu_page('Pièces privées', 'Pièces privées', 'beog_view_private_docs', 'beog-docs', function () {
		echo '<div class="wrap"><h1>Pièces privées</h1><table class="widefat striped"><thead><tr><th>Pièce</th><th>Projet</th><th>Date</th></tr></thead><tbody>';
		foreach (get_posts(['post_type' => 'beog_private_doc', 'post_status' => 'private', 'posts_per_page' => -1]) as $p) {
			echo '<tr><td><a href="' . esc_url(beog_doc_url($p->ID)) . '">' . esc_html(get_the_title($p)) . '</a></td><td>' . esc_html(beog_project_keys()[get_post_meta($p->ID, '_beog_project', true)] ?? '—') . '</td><td>' . esc_html(get_the_date('Y-m-d', $p)) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}, 'dashicons-lock', 27);
});
