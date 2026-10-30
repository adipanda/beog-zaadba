<?php
if (!defined('ABSPATH')) exit;

/**
 * Galerie photos : albums (titre, date, courte description FR/EN, lot de photos
 * et de vidéos). Tous les rôles de l'école peuvent créer un album et le soumettre ;
 * la direction le publie, conformément à la règle de validation des images.
 * Chaque enregistrement exige la confirmation des autorisations d'image.
 */

function beog_album_caps(): array {
	return ['edit_beog_albums', 'edit_others_beog_albums', 'publish_beog_albums', 'read_private_beog_albums', 'delete_beog_albums', 'delete_others_beog_albums', 'delete_published_beog_albums', 'delete_private_beog_albums', 'edit_published_beog_albums', 'edit_private_beog_albums'];
}
/** Capacités d'un contributeur d'albums (rôles autres que la direction). */
function beog_album_contributor_caps(): array {
	return ['read' => true, 'upload_files' => true, 'edit_beog_albums' => true, 'delete_beog_albums' => true];
}

add_action('init', function () {
	register_post_type('beog_album', [
		'labels' => ['name' => 'Albums photos', 'singular_name' => 'Album', 'add_new' => 'Créer un album', 'add_new_item' => 'Créer un album', 'edit_item' => 'Modifier l’album', 'all_items' => 'Tous les albums', 'menu_name' => 'Galerie photos', 'not_found' => 'Aucun album pour le moment.'],
		'public' => true, 'publicly_queryable' => true, 'show_ui' => true, 'show_in_rest' => false, 'menu_icon' => 'dashicons-format-gallery', 'menu_position' => 21,
		'supports' => ['title', 'revisions'], 'rewrite' => false, 'has_archive' => false,
		'capability_type' => ['beog_album', 'beog_albums'], 'map_meta_cap' => true,
	]);
});

add_filter('post_type_link', function ($url, $post) {
	if ($post->post_type !== 'beog_album') return $url;
	// Un brouillon n'a pas encore d'adresse définitive : lien technique pour l'aperçu.
	return ($post->post_name !== '' && $post->post_status === 'publish') ? home_url('/fr/galerie/' . $post->post_name . '/') : add_query_arg(['post_type' => 'beog_album', 'p' => $post->ID], home_url('/'));
}, 20, 2);

/** Album publié par son identifiant d'adresse (ou en aperçu pour un éditeur). */
function beog_album_by_slug(string $slug, bool $preview = false): ?WP_Post {
	$posts = get_posts(['post_type' => 'beog_album', 'name' => $slug, 'post_status' => $preview ? ['publish', 'draft', 'pending', 'future', 'private'] : 'publish', 'posts_per_page' => 1]);
	return $posts[0] ?? null;
}

// ---------- Saisie ----------

/** Message de consentement, repris dans l'administration. */
function beog_consent_message(): string {
	return '<strong>Autorisation obligatoire avant toute mise en ligne.</strong> Ajoutez une photo ou une vidéo seulement si chaque personne reconnaissable a donné son accord écrit ; pour un enfant, cet accord vient de ses parents ou responsables légaux. Sans accord, choisissez une vue où personne n’est identifiable : de dos, de loin, les mains, les lieux. N’associez jamais le nom d’un enfant à son visage et n’indiquez rien qui permette de le retrouver. Conservez les autorisations (finalité, durée) et retirez sans attendre une image contestée.';
}

add_action('admin_notices', function () {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || !in_array($screen->id, ['beog_album', 'edit-beog_album', 'upload', 'media'], true)) return;
	echo '<div class="notice notice-warning"><p>' . wp_kses(beog_consent_message(), ['strong' => []]) . '</p></div>';
});

add_action('add_meta_boxes_beog_album', function () {
	add_meta_box('beog_album_content', 'Contenu de l’album', 'beog_album_box', 'beog_album', 'normal', 'high');
	add_meta_box('beog_album_consent', 'Autorisations d’image', 'beog_album_consent_box', 'beog_album', 'side', 'high');
});

add_action('admin_enqueue_scripts', function ($hook) {
	if (!in_array($hook, ['post.php', 'post-new.php'], true) || get_current_screen()->post_type !== 'beog_album') return;
	wp_enqueue_media();
	wp_enqueue_script('jquery-ui-sortable');
	wp_add_inline_script('jquery-ui-sortable', beog_album_admin_js());
});

function beog_album_box(WP_Post $post): void {
	wp_nonce_field('beog_album_save', 'beog_album_nonce');
	$g = fn($k) => (string) get_post_meta($post->ID, '_beog_' . $k, true);
	$date = $g('album_date') ?: wp_date('Y-m-d');
	echo '<style>.beog-album-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px 20px}.beog-album-grid label{font-weight:600;display:block;margin-bottom:4px}.beog-album-grid textarea,.beog-album-grid input[type=text]{width:100%}#beog-album-items{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0;padding:0;list-style:none}#beog-album-items li{position:relative;width:120px;cursor:move}#beog-album-items img{width:120px;height:90px;object-fit:cover;display:block;border-radius:4px;background:#f0f0f1}#beog-album-items .is-video{display:flex;align-items:center;justify-content:center;width:120px;height:90px;background:#1d2327;color:#fff;border-radius:4px;font-size:12px}#beog-album-items button{position:absolute;top:4px;right:4px}</style>';
	echo '<p>Le titre ci-dessus est le titre français. Décrivez l’album en deux ou trois phrases : ce qui se passe, où et quand, sans nommer d’enfant.</p><div class="beog-album-grid">';
	echo '<p><label for="beog_album_date">Date des prises de vue</label><input type="date" id="beog_album_date" name="beog_album[date]" value="' . esc_attr($date) . '" required></p>';
	echo '<p><label for="beog_album_title_en">Titre en anglais (facultatif)</label><input type="text" id="beog_album_title_en" name="beog_album[title_en]" value="' . esc_attr($g('album_title_en')) . '"></p>';
	echo '<p><label for="beog_album_desc_fr">Description (français)</label><textarea id="beog_album_desc_fr" name="beog_album[desc_fr]" rows="4" maxlength="600">' . esc_textarea($g('album_desc_fr')) . '</textarea></p>';
	echo '<p><label for="beog_album_desc_en">Description (anglais, facultatif)</label><textarea id="beog_album_desc_en" name="beog_album[desc_en]" rows="4" maxlength="600">' . esc_textarea($g('album_desc_en')) . '</textarea></p>';
	echo '</div><h4>Photos et vidéos</h4><p class="description">Glissez les vignettes pour changer l’ordre. La légende et le texte alternatif de chaque photo se modifient dans la médiathèque (champs « Légende » et « Texte alternatif », versions anglaises en dessous).</p><ul id="beog-album-items">';
	foreach (beog_album_item_ids($post->ID) as $id) echo beog_album_admin_item($id);
	echo '</ul><input type="hidden" id="beog-album-ids" name="beog_album[items]" value="' . esc_attr(implode(',', beog_album_item_ids($post->ID))) . '"><p><button type="button" class="button button-secondary" id="beog-album-add">Ajouter des photos ou vidéos</button></p>';
}

function beog_album_admin_item(int $id): string {
	$is_video = str_starts_with((string) get_post_mime_type($id), 'video/');
	$thumb = $is_video ? '<span class="is-video">Vidéo</span>' : wp_get_attachment_image($id, 'thumbnail', false, ['alt' => '']);
	return '<li data-id="' . $id . '">' . $thumb . '<button type="button" class="button-link-delete beog-album-remove" aria-label="Retirer de l’album">×</button></li>';
}

function beog_album_admin_js(): string {
	return <<<'JS'
jQuery(function ($) {
	var list = $('#beog-album-items'), field = $('#beog-album-ids'), frame;
	if (!list.length) return;
	function sync() { field.val(list.children().map(function () { return $(this).data('id'); }).get().join(',')); }
	list.sortable({ update: sync });
	list.on('click', '.beog-album-remove', function () { $(this).closest('li').remove(); sync(); });
	$('#beog-album-add').on('click', function (e) {
		e.preventDefault();
		if (!frame) {
			frame = wp.media({ title: 'Ajouter à l’album', button: { text: 'Ajouter à l’album' }, multiple: 'add', library: { type: ['image', 'video'] } });
			frame.on('select', function () {
				frame.state().get('selection').each(function (a) {
					if (list.children('[data-id="' + a.id + '"]').length) return;
					var t = a.get('type') === 'video' ? '<span class="is-video">Vidéo</span>' : '<img alt="" src="' + ((a.get('sizes') && a.get('sizes').thumbnail) ? a.get('sizes').thumbnail.url : a.get('url')) + '">';
					list.append('<li data-id="' + a.id + '">' + t + '<button type="button" class="button-link-delete beog-album-remove" aria-label="Retirer de l’album">×</button></li>');
				});
				sync();
			});
		}
		frame.open();
	});
});
JS;
}

function beog_album_consent_box(WP_Post $post): void {
	$confirmed = get_post_meta($post->ID, '_beog_consent', true);
	echo '<p style="font-size:13px">Relisez l’avertissement en haut de la page avant d’enregistrer.</p>';
	echo '<p><label><input type="checkbox" name="beog_consent" value="1" required> Je confirme que chaque personne reconnaissable dans cet album a donné son accord écrit (pour un enfant, ses parents ou responsables légaux), ou que personne n’y est identifiable.</label></p>';
	if (is_array($confirmed)) {
		$u = get_userdata((int) $confirmed['user']);
		echo '<p class="description">Dernière confirmation : ' . esc_html(($u ? $u->display_name : '—') . ', ' . wp_date('d/m/Y H:i', (int) $confirmed['at'])) . '.</p>';
	}
	echo '<p class="description">À cocher à chaque enregistrement. Sans confirmation, l’album reste en brouillon.</p>';
}

// Sans confirmation des autorisations, l'album ne peut être ni soumis ni publié.
add_filter('wp_insert_post_data', function ($data, $raw) {
	if ($data['post_type'] !== 'beog_album' || !isset($_POST['beog_album_nonce'])) return $data;
	if (empty($_POST['beog_consent']) && in_array($data['post_status'], ['publish', 'pending', 'future', 'private'], true)) {
		$data['post_status'] = 'draft';
		set_transient('beog_notice_' . get_current_user_id(), 'Album enregistré en brouillon : cochez la confirmation des autorisations d’image pour le soumettre ou le publier.', 60);
	}
	return $data;
}, 10, 2);

add_action('save_post_beog_album', function (int $id) {
	if (!isset($_POST['beog_album_nonce']) || !wp_verify_nonce(sanitize_key($_POST['beog_album_nonce']), 'beog_album_save')) return;
	if (wp_is_post_revision($id) || !current_user_can('edit_post', $id)) return;
	$in = wp_unslash((array) ($_POST['beog_album'] ?? []));
	$date = (string) ($in['date'] ?? '');
	update_post_meta($id, '_beog_album_date', preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : wp_date('Y-m-d'));
	update_post_meta($id, '_beog_album_title_en', sanitize_text_field((string) ($in['title_en'] ?? '')));
	update_post_meta($id, '_beog_album_desc_fr', sanitize_textarea_field((string) ($in['desc_fr'] ?? '')));
	update_post_meta($id, '_beog_album_desc_en', sanitize_textarea_field((string) ($in['desc_en'] ?? '')));
	$ids = array_values(array_filter(array_map('absint', explode(',', (string) ($in['items'] ?? ''))), function ($a) {
		$mime = (string) get_post_mime_type($a);
		return get_post_type($a) === 'attachment' && (str_starts_with($mime, 'image/') || $mime === 'video/mp4');
	}));
	update_post_meta($id, '_beog_album_items', $ids);
	if (!empty($_POST['beog_consent'])) update_post_meta($id, '_beog_consent', ['user' => get_current_user_id(), 'at' => time()]);
});

/** Identifiants des médias de l'album, dans l'ordre choisi. */
function beog_album_item_ids(int $album_id): array {
	$ids = get_post_meta($album_id, '_beog_album_items', true);
	return is_array($ids) ? array_values(array_filter(array_map('intval', $ids), fn($a) => get_post_type($a) === 'attachment')) : [];
}

// Versions anglaises de la légende et du texte alternatif, dans la médiathèque.
add_filter('attachment_fields_to_edit', function ($fields, $post) {
	foreach (['caption_en' => 'Légende (anglais)', 'alt_en' => 'Texte alternatif (anglais)'] as $k => $label) {
		$fields['beog_' . $k] = ['label' => $label, 'input' => 'text', 'value' => (string) get_post_meta($post->ID, '_beog_' . $k, true)];
	}
	return $fields;
}, 10, 2);
add_filter('attachment_fields_to_save', function ($post, $attachment) {
	foreach (['caption_en', 'alt_en'] as $k) {
		if (isset($attachment['beog_' . $k])) update_post_meta($post['ID'], '_beog_' . $k, sanitize_text_field($attachment['beog_' . $k]));
	}
	return $post;
}, 10, 2);

add_filter('manage_beog_album_posts_columns', fn($c) => ['cb' => $c['cb'], 'title' => 'Album', 'beog_items' => 'Contenu', 'beog_consent' => 'Autorisations', 'author' => 'Créé par', 'date' => 'Date']);
add_action('manage_beog_album_posts_custom_column', function ($col, $id) {
	if ($col === 'beog_items') echo esc_html(beog_album_count($id, 'fr'));
	if ($col === 'beog_consent') echo get_post_meta($id, '_beog_consent', true) ? 'Confirmées' : '<strong style="color:#b32d2e">À confirmer</strong>';
}, 10, 2);

// ---------- Rendu public ----------

function beog_album_title(WP_Post $album, string $lang): string {
	$en = (string) get_post_meta($album->ID, '_beog_album_title_en', true);
	return ($lang === 'en' && $en !== '') ? $en : get_the_title($album);
}
function beog_album_desc(WP_Post $album, string $lang): string {
	$fr = (string) get_post_meta($album->ID, '_beog_album_desc_fr', true);
	$en = (string) get_post_meta($album->ID, '_beog_album_desc_en', true);
	return ($lang === 'en' && $en !== '') ? $en : $fr;
}
function beog_media_text(int $id, string $field, string $lang): string {
	if ($lang === 'en' && ($v = (string) get_post_meta($id, '_beog_' . $field . '_en', true)) !== '') return $v;
	return $field === 'alt' ? (string) get_post_meta($id, '_wp_attachment_image_alt', true) : (string) wp_get_attachment_caption($id);
}
function beog_album_count(int $album_id, string $lang): string {
	$photos = $videos = 0;
	foreach (beog_album_item_ids($album_id) as $id) str_starts_with((string) get_post_mime_type($id), 'video/') ? $videos++ : $photos++;
	$parts = [];
	if ($photos) $parts[] = $photos . ' photo' . ($photos > 1 ? 's' : '');
	if ($videos) $parts[] = $videos . ' ' . beog_t($lang, 'vidéo', 'video') . ($videos > 1 ? 's' : '');
	return implode(' · ', $parts) ?: beog_t($lang, 'Album vide', 'Empty album');
}
function beog_album_url(WP_Post $album, string $lang): string {
	return home_url('/' . $lang . '/galerie/' . $album->post_name . '/');
}

function beog_consent_note(string $lang): string {
	$contact = esc_url(home_url('/' . $lang . '/contact/'));
	return '<aside class="consent-note"><p>' . beog_t($lang,
		'Nous publions le visage reconnaissable d’un enfant uniquement avec l’accord écrit de ses parents ou responsables légaux, sans jamais l’associer à son nom. Vous figurez sur une image et souhaitez son retrait ? <a href="' . $contact . '">Écrivez-nous</a> : elle sera retirée pendant l’examen de votre demande.',
		'We only publish a child’s recognisable face with written consent from their parents or guardians, and never next to their name. If you appear in an image and want it removed, <a href="' . $contact . '">contact us</a>: it will be taken down while we review your request.') . '</p></aside>';
}

/** Cartes des albums publiés (page Galerie). */
function beog_album_cards(string $lang): string {
	$albums = get_posts(['post_type' => 'beog_album', 'post_status' => 'publish', 'posts_per_page' => 60, 'meta_key' => '_beog_album_date', 'orderby' => 'meta_value', 'order' => 'DESC']);
	if (!$albums) return '<div class="album-grid" data-beog-albums><p>' . esc_html(beog_t($lang, 'Les premiers albums seront publiés ici.', 'The first albums will be published here.')) . '</p></div>';
	$html = '<div class="album-grid" data-beog-albums>';
	foreach ($albums as $a) {
		$items = beog_album_item_ids($a->ID);
		$cover = 0;
		foreach ($items as $id) if (wp_attachment_is_image($id)) { $cover = $id; break; }
		if (!$cover && $items) $cover = (int) get_post_meta($items[0], '_beog_poster', true);
		$date = (string) get_post_meta($a->ID, '_beog_album_date', true);
		$html .= '<article class="album-card"><a href="' . esc_url(beog_album_url($a, $lang)) . '">'
			. ($cover ? wp_get_attachment_image($cover, 'medium_large', false, ['alt' => '', 'loading' => 'lazy']) : '')
			. '<div class="album-card-body"><p class="eyebrow"><time datetime="' . esc_attr($date) . '">' . esc_html(beog_date($date, $lang)) . '</time></p><h2>' . esc_html(beog_album_title($a, $lang)) . '</h2><p>' . esc_html(beog_album_count($a->ID, $lang)) . '</p></div></a></article>';
	}
	return $html . '</div>';
}

/** Page d'un album. */
function beog_render_album(WP_Post $a, string $lang): string {
	$date = (string) get_post_meta($a->ID, '_beog_album_date', true);
	$title = beog_album_title($a, $lang);
	$html = '<section class="page-intro container"><nav class="breadcrumb" aria-label="' . esc_attr(beog_t($lang, 'Fil d’Ariane', 'Breadcrumb')) . '"><a href="' . esc_url(home_url('/' . $lang . '/')) . '">' . esc_html(beog_t($lang, 'Accueil', 'Home')) . '</a><span aria-hidden="true">/</span><a href="' . esc_url(home_url('/' . $lang . '/galerie/')) . '">' . esc_html(beog_t($lang, 'Galerie photos', 'Photo gallery')) . '</a></nav>'
		. '<p class="eyebrow">' . esc_html(beog_t($lang, 'Galerie photos', 'Photo gallery')) . '</p><h1>' . esc_html($title) . '</h1><p class="lead">' . esc_html(beog_album_desc($a, $lang)) . '</p></section>';
	$html .= '<section class="container section compact-top album-detail"><p class="album-meta"><time datetime="' . esc_attr($date) . '">' . esc_html(beog_date($date, $lang)) . '</time><span>' . esc_html(beog_album_count($a->ID, $lang)) . '</span></p><ul class="photo-grid">';
	foreach (beog_album_item_ids($a->ID) as $k => $id) {
		$caption = beog_media_text($id, 'caption', $lang);
		$alt = beog_media_text($id, 'alt', $lang);
		if (str_starts_with((string) get_post_mime_type($id), 'video/')) {
			$poster = (int) get_post_meta($id, '_beog_poster', true);
			$img = $poster ? wp_get_attachment_image($poster, 'medium_large', false, ['alt' => $alt, 'loading' => 'lazy']) : '';
			$html .= $img
				? '<li class="photo-item"><figure><button type="button" class="photo-video" data-video="' . esc_url(wp_get_attachment_url($id)) . '" data-video-label="' . esc_attr($alt) . '" data-video-caption="' . esc_attr($caption) . '">' . $img . '<span class="play-badge"><span>' . esc_html(beog_t($lang, 'Lire la vidéo', 'Play video')) . '</span></span></button><figcaption>' . esc_html($caption) . '</figcaption></figure></li>'
				: '<li class="photo-item"><figure><video controls preload="none" src="' . esc_url(wp_get_attachment_url($id)) . '" aria-label="' . esc_attr($alt) . '"></video><figcaption>' . esc_html($caption) . '</figcaption></figure></li>';
			continue;
		}
		$full = wp_get_attachment_image_url($id, 'large') ?: wp_get_attachment_url($id);
		$thumb = wp_get_attachment_image($id, 'medium_large', false, ['alt' => $alt, 'loading' => $k < 2 ? 'eager' : 'lazy']);
		$html .= '<li class="photo-item"><figure><a href="' . esc_url($full) . '" data-lightbox data-caption="' . esc_attr($caption) . '">' . $thumb . '</a>' . ($caption !== '' ? '<figcaption>' . esc_html($caption) . '</figcaption>' : '') . '</figure></li>';
	}
	$html .= '</ul>' . beog_consent_note($lang) . '<a class="text-link" href="' . esc_url(home_url('/' . $lang . '/galerie/')) . '">' . esc_html(beog_t($lang, 'Tous les albums', 'All albums')) . '</a></section>';
	return $html;
}
