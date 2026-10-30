<?php
if (!defined('ABSPATH')) exit;

/**
 * Demandes de contact et de partenariat (cahier des charges §6) :
 * enregistrement privé avant tout envoi de courriel, jeton anti-falsification,
 * champ piège, limitation des tentatives, notification à l'école et alerte
 * visible dans l'administration si la notification échoue.
 */

const BEOG_INQUIRY_LIMIT = 5;          // demandes au plus…
const BEOG_INQUIRY_WINDOW = 600;       // …par adresse IP sur 10 minutes

function beog_inquiry_states(): array {
	return ['nouvelle' => 'Nouvelle', 'en_cours' => 'En cours', 'traitee' => 'Traitée', 'sans_suite' => 'Sans suite'];
}
function beog_support_types(): array {
	return ['materiel' => 'Don de matériel', 'competences' => 'Partage de compétences', 'financier' => 'Partenariat financier', 'information' => 'Informations sur l’école'];
}

add_action('init', function () {
	register_post_type('beog_inquiry', [
		'labels' => ['name' => 'Demandes', 'singular_name' => 'Demande', 'edit_item' => 'Traiter la demande', 'menu_name' => 'Demandes', 'not_found' => 'Aucune demande pour le moment.'],
		'public' => false, 'show_ui' => true, 'show_in_menu' => false,
		'supports' => ['title'], 'capability_type' => ['beog_inquiry', 'beog_inquiries'], 'map_meta_cap' => true,
		'capabilities' => ['create_posts' => 'beog_create_inquiries'], 'exclude_from_search' => true, 'show_in_rest' => false,
	]);
});

// Menu propre : le menu automatique des types de contenus refuse l'accès aux
// comptes sans « edit_posts », comme le rôle Partenariats.
add_action('admin_menu', function () {
	add_menu_page('Demandes', 'Demandes', 'edit_beog_inquiries', 'edit.php?post_type=beog_inquiry', '', 'dashicons-email-alt', 26);
	add_submenu_page('edit.php?post_type=beog_inquiry', 'Toutes les demandes', 'Toutes les demandes', 'edit_beog_inquiries', 'edit.php?post_type=beog_inquiry');
	// Une seconde entrée est nécessaire : WordPress ignore un sous-menu unique identique à son parent.
	add_submenu_page('edit.php?post_type=beog_inquiry', 'Exporter les demandes', 'Exporter', 'beog_export_inquiries', 'beog-inquiries-export', function () {
		echo '<div class="wrap"><h1>Exporter les demandes</h1><p>Fichier CSV (séparateur point-virgule, UTF-8) contenant toutes les demandes et leur suivi. Les cellules commençant par =, +, - ou @ sont neutralisées pour les tableurs.</p><p><a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=beog_export_inquiries'), 'beog_export_inquiries')) . '">Télécharger le CSV</a></p></div>';
	});
});

// Le JavaScript du thème active l'envoi serveur quand BEOG_FORM est défini.
add_action('wp_enqueue_scripts', function () {
	if (!wp_script_is('beog', 'enqueued')) return;
	$config = ['endpoint' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('beog_inquiry')];
	wp_add_inline_script('beog', 'window.BEOG_FORM=' . wp_json_encode($config) . ';', 'before');
}, 20);

add_action('wp_ajax_beog_inquiry', 'beog_handle_inquiry');
add_action('wp_ajax_nopriv_beog_inquiry', 'beog_handle_inquiry');

function beog_handle_inquiry(): void {
	$lang = (isset($_POST['lang']) && $_POST['lang'] === 'en') ? 'en' : 'fr';
	$result = beog_process_inquiry(wp_unslash($_POST), beog_client_ip(), $lang);
	if (is_wp_error($result)) {
		$status = (int) ($result->get_error_data()['status'] ?? 400);
		wp_send_json_error(['message' => $result->get_error_message(), 'fields' => $result->get_error_data()['fields'] ?? []], $status);
	}
	wp_send_json_success(['message' => beog_t($lang, 'Merci. Votre demande est bien enregistrée et transmise à la direction de l’école. Nous visons une réponse sous deux jours ouvrés.', 'Thank you. Your enquiry has been recorded and passed to the school leadership. We aim to reply within two working days.')]);
}

/** Adresse IP du visiteur, uniquement pour la limitation (jamais enregistrée en clair). */
function beog_client_ip(): string {
	return sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

/**
 * Valide et enregistre une demande. Renvoie l'identifiant créé ou une WP_Error.
 * Séparée de la requête HTTP pour être testable.
 */
function beog_process_inquiry(array $in, string $ip, string $lang = 'fr', bool $check_nonce = true) {
	if ($check_nonce && !wp_verify_nonce((string) ($in['nonce'] ?? ''), 'beog_inquiry')) {
		return new WP_Error('beog_nonce', beog_t($lang, 'La page a expiré. Rechargez-la puis renvoyez votre message.', 'This page has expired. Reload it and send your message again.'), ['status' => 403]);
	}
	// Champ piège rempli : réponse neutre, rien n'est enregistré.
	if (!empty($in['website'])) return 0;

	$bucket = 'beog_rl_' . md5(wp_salt('nonce') . $ip);
	$hits = (int) get_transient($bucket);
	if ($hits >= BEOG_INQUIRY_LIMIT) {
		return new WP_Error('beog_rate', beog_t($lang, 'Trop de demandes envoyées depuis cette connexion. Réessayez dans quelques minutes.', 'Too many enquiries from this connection. Please try again in a few minutes.'), ['status' => 429]);
	}
	set_transient($bucket, $hits + 1, BEOG_INQUIRY_WINDOW);

	$d = [
		'kind' => ($in['kind'] ?? '') === 'partnership' ? 'partnership' : 'contact',
		'name' => sanitize_text_field((string) ($in['name'] ?? '')),
		'organisation' => sanitize_text_field((string) ($in['organisation'] ?? '')),
		'email' => sanitize_email((string) ($in['email'] ?? '')),
		'phone' => preg_replace('/[^0-9+ ().-]/', '', (string) ($in['phone'] ?? '')),
		'support' => sanitize_key((string) ($in['support'] ?? '')),
		'project' => sanitize_key((string) ($in['project'] ?? 'general')),
		'message' => sanitize_textarea_field((string) ($in['message'] ?? '')),
	];
	$errors = [];
	if ($d['name'] === '' || mb_strlen($d['name']) > 120) $errors['name'] = beog_t($lang, 'Indiquez votre nom.', 'Enter your name.');
	if ($d['email'] === '' && $d['phone'] === '') $errors['email'] = beog_t($lang, 'Indiquez un courriel ou un téléphone.', 'Enter an email address or phone number.');
	if (!empty($in['email']) && !is_email($d['email'])) $errors['email'] = beog_t($lang, 'Vérifiez l’adresse de courriel.', 'Check the email address.');
	if (!isset(beog_support_types()[$d['support']])) $errors['support'] = beog_t($lang, 'Choisissez le type de soutien ou de demande.', 'Choose the type of support or enquiry.');
	if (mb_strlen($d['message']) < 10 || mb_strlen($d['message']) > 3000) $errors['message'] = beog_t($lang, 'Écrivez un message de 10 à 3 000 caractères.', 'Write a message of 10 to 3,000 characters.');
	if ($d['project'] !== 'general' && !isset(beog_project_keys()[$d['project']])) $d['project'] = 'general';
	if ($errors) {
		return new WP_Error('beog_invalid', beog_t($lang, 'Certains champs sont à corriger.', 'Some fields need correcting.'), ['status' => 422, 'fields' => $errors]);
	}

	$title = ($d['kind'] === 'partnership' ? 'Partenariat' : 'Contact') . ' — ' . $d['name'] . ($d['organisation'] ? ' (' . $d['organisation'] . ')' : '');
	$id = wp_insert_post(wp_slash(['post_type' => 'beog_inquiry', 'post_status' => 'private', 'post_title' => $title, 'post_author' => 0]), true);
	if (is_wp_error($id)) {
		return new WP_Error('beog_store', beog_t($lang, 'Enregistrement impossible pour le moment. Réessayez plus tard.', 'Your enquiry could not be saved. Please try again later.'), ['status' => 500]);
	}
	foreach ($d as $k => $v) update_post_meta($id, '_beog_' . $k, $v);
	update_post_meta($id, '_beog_lang', $lang);
	update_post_meta($id, '_beog_state', 'nouvelle');
	beog_notify_inquiry($id);
	return $id;
}

/** Notification à l'école. Un échec n'efface jamais la demande : il déclenche une alerte. */
function beog_notify_inquiry(int $id): bool {
	$to = get_option('beog_notify_email') ?: get_option('admin_email');
	$g = fn($k) => (string) get_post_meta($id, '_beog_' . $k, true);
	$body = "Nouvelle demande reçue sur le site Beog-Zaadba.\n\n"
		. 'Type : ' . ($g('kind') === 'partnership' ? 'Partenariat' : 'Contact') . "\n"
		. 'Nom : ' . $g('name') . "\nOrganisation : " . ($g('organisation') ?: '—') . "\n"
		. 'Courriel : ' . ($g('email') ?: '—') . "\nTéléphone : " . ($g('phone') ?: '—') . "\n"
		. 'Soutien : ' . (beog_support_types()[$g('support')] ?? '—') . "\nProjet : " . (beog_project_keys()[$g('project')] ?? 'Programme dans son ensemble') . "\n\n"
		. $g('message') . "\n\nTraiter la demande : " . admin_url('post.php?post=' . $id . '&action=edit') . "\nObjectif : réponse humaine sous deux jours ouvrés.";
	$headers = [];
	if (is_email($g('email'))) $headers[] = 'Reply-To: ' . $g('name') . ' <' . $g('email') . '>';
	$sent = wp_mail($to, '[Beog-Zaadba] ' . get_the_title($id), $body, $headers);
	update_post_meta($id, '_beog_notified', $sent ? 'oui' : 'echec');
	if (!$sent) {
		update_option('beog_mail_failures', (int) get_option('beog_mail_failures', 0) + 1, false);
		do_action('beog_inquiry_mail_failed', $id);
	}
	return $sent;
}

// Alerte persistante tant que des notifications ont échoué.
add_action('admin_notices', function () {
	$n = (int) get_option('beog_mail_failures', 0);
	if (!$n || !current_user_can('beog_manage_inquiries')) return;
	$reset = wp_nonce_url(admin_url('admin-post.php?action=beog_mail_ack'), 'beog_mail_ack');
	echo '<div class="notice notice-error"><p><strong>Courriel en panne :</strong> ' . sprintf(esc_html(_n('%d demande n’a pas pu être notifiée par courriel.', '%d demandes n’ont pas pu être notifiées par courriel.', $n)), $n)
		. ' Elles sont bien enregistrées dans <a href="' . esc_url(admin_url('edit.php?post_type=beog_inquiry')) . '">Demandes</a>. Vérifiez la configuration d’envoi de courriel. <a href="' . esc_url($reset) . '">J’ai pris connaissance</a></p></div>';
});
add_action('admin_post_beog_mail_ack', function () {
	check_admin_referer('beog_mail_ack');
	if (!current_user_can('beog_manage_inquiries')) wp_die('Accès refusé.', 403);
	update_option('beog_mail_failures', 0, false);
	wp_safe_redirect(wp_get_referer() ?: admin_url());
	exit;
});

// ---------- Traitement dans l'administration ----------

add_action('add_meta_boxes_beog_inquiry', function () {
	add_meta_box('beog_inquiry_detail', 'Contenu de la demande', 'beog_inquiry_box', 'beog_inquiry', 'normal', 'high');
	add_meta_box('beog_inquiry_follow', 'Suivi', 'beog_inquiry_follow_box', 'beog_inquiry', 'side', 'high');
});

function beog_inquiry_box(WP_Post $post): void {
	$g = fn($k) => (string) get_post_meta($post->ID, '_beog_' . $k, true);
	$rows = ['Type' => $g('kind') === 'partnership' ? 'Partenariat' : 'Contact', 'Nom' => $g('name'), 'Organisation' => $g('organisation') ?: '—', 'Courriel' => $g('email') ?: '—', 'Téléphone' => $g('phone') ?: '—', 'Soutien' => beog_support_types()[$g('support')] ?? '—', 'Projet' => beog_project_keys()[$g('project')] ?? 'Programme dans son ensemble', 'Langue' => strtoupper($g('lang')), 'Reçue le' => get_the_date('Y-m-d H:i', $post), 'Notification' => $g('notified') === 'echec' ? 'Échec de l’envoi du courriel' : 'Envoyée'];
	echo '<table class="widefat striped"><tbody>';
	foreach ($rows as $k => $v) echo '<tr><th style="width:160px">' . esc_html($k) . '</th><td>' . esc_html($v) . '</td></tr>';
	echo '</tbody></table><h4>Message</h4><div style="white-space:pre-wrap;background:#f6f7f7;padding:12px;border:1px solid #dcdcde">' . esc_html($g('message')) . '</div>';
}

function beog_inquiry_follow_box(WP_Post $post): void {
	wp_nonce_field('beog_inquiry_follow', 'beog_inquiry_nonce');
	$state = get_post_meta($post->ID, '_beog_state', true) ?: 'nouvelle';
	echo '<p><label for="beog_state"><strong>État</strong></label><select id="beog_state" name="beog_state" class="widefat">';
	foreach (beog_inquiry_states() as $k => $l) echo '<option value="' . esc_attr($k) . '"' . selected($state, $k, false) . '>' . esc_html($l) . '</option>';
	echo '</select></p><p><label for="beog_owner"><strong>Responsable</strong></label><select id="beog_owner" name="beog_owner" class="widefat"><option value="0">Non attribuée</option>';
	$owner = (int) get_post_meta($post->ID, '_beog_owner', true);
	foreach (get_users(['capability' => 'beog_manage_inquiries', 'fields' => ['ID', 'display_name']]) as $u) echo '<option value="' . (int) $u->ID . '"' . selected($owner, (int) $u->ID, false) . '>' . esc_html($u->display_name) . '</option>';
	echo '</select></p><p><label for="beog_next"><strong>Prochaine action</strong></label><textarea id="beog_next" name="beog_next" class="widefat" rows="3">' . esc_textarea((string) get_post_meta($post->ID, '_beog_next', true)) . '</textarea></p>';
}

add_action('save_post_beog_inquiry', function (int $id) {
	if (!isset($_POST['beog_inquiry_nonce']) || !wp_verify_nonce(sanitize_key($_POST['beog_inquiry_nonce']), 'beog_inquiry_follow')) return;
	if (!current_user_can('edit_post', $id)) return;
	$state = sanitize_key(wp_unslash($_POST['beog_state'] ?? ''));
	if (isset(beog_inquiry_states()[$state])) update_post_meta($id, '_beog_state', $state);
	update_post_meta($id, '_beog_owner', absint($_POST['beog_owner'] ?? 0));
	update_post_meta($id, '_beog_next', sanitize_textarea_field(wp_unslash($_POST['beog_next'] ?? '')));
});

// Les demandes restent privées quelle que soit l'action d'enregistrement.
add_filter('wp_insert_post_data', function ($data) {
	if ($data['post_type'] === 'beog_inquiry' && !in_array($data['post_status'], ['private', 'trash', 'auto-draft'], true)) $data['post_status'] = 'private';
	return $data;
});

add_filter('manage_beog_inquiry_posts_columns', fn($c) => ['cb' => $c['cb'], 'title' => 'Demande', 'beog_state' => 'État', 'beog_support' => 'Soutien', 'beog_mail' => 'Notification', 'date' => 'Reçue']);
add_action('manage_beog_inquiry_posts_custom_column', function ($col, $id) {
	if ($col === 'beog_state') echo esc_html(beog_inquiry_states()[get_post_meta($id, '_beog_state', true)] ?? 'Nouvelle');
	if ($col === 'beog_support') echo esc_html(beog_support_types()[get_post_meta($id, '_beog_support', true)] ?? '—');
	if ($col === 'beog_mail') echo get_post_meta($id, '_beog_notified', true) === 'echec' ? '<strong style="color:#b32d2e">Échec</strong>' : 'Envoyée';
}, 10, 2);

// Export CSV protégé contre les formules malveillantes.
add_action('restrict_manage_posts', function ($type) {
	if ($type !== 'beog_inquiry' || !current_user_can('beog_export_inquiries')) return;
	echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=beog_export_inquiries'), 'beog_export_inquiries')) . '">Exporter en CSV</a>';
});
add_action('admin_post_beog_export_inquiries', function () {
	check_admin_referer('beog_export_inquiries');
	if (!current_user_can('beog_export_inquiries')) wp_die('Accès refusé.', 403);
	beog_send_csv('demandes-beog-zaadba-' . wp_date('Y-m-d') . '.csv', ...beog_inquiry_export_rows());
});

/** En-tête et lignes de l'export des demandes. */
function beog_inquiry_export_rows(): array {
	$header = ['Reçue le', 'Type', 'Nom', 'Organisation', 'Courriel', 'Téléphone', 'Soutien', 'Projet', 'Langue', 'État', 'Responsable', 'Prochaine action', 'Message'];
	$rows = [];
	foreach (get_posts(['post_type' => 'beog_inquiry', 'post_status' => 'private', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC']) as $p) {
		$g = fn($k) => (string) get_post_meta($p->ID, '_beog_' . $k, true);
		$owner = get_userdata((int) $g('owner'));
		$rows[] = [get_the_date('Y-m-d H:i', $p), $g('kind') === 'partnership' ? 'Partenariat' : 'Contact', $g('name'), $g('organisation'), $g('email'), $g('phone'), beog_support_types()[$g('support')] ?? '', beog_project_keys()[$g('project')] ?? 'Programme', strtoupper($g('lang')), beog_inquiry_states()[$g('state')] ?? '', $owner ? $owner->display_name : '', $g('next'), $g('message')];
	}
	return [$header, $rows];
}

// Réglage de l'adresse de notification.
add_action('admin_init', function () {
	register_setting('general', 'beog_notify_email', ['type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => '']);
	add_settings_field('beog_notify_email', 'Courriel des demandes Beog-Zaadba', function () {
		echo '<input type="email" class="regular-text" name="beog_notify_email" id="beog_notify_email" value="' . esc_attr((string) get_option('beog_notify_email')) . '"><p class="description">Reçoit chaque nouvelle demande. Vide : adresse d’administration du site.</p>';
	}, 'general', 'default', ['label_for' => 'beog_notify_email']);
});
