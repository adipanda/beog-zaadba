<?php
if (!defined('ABSPATH')) exit;

/**
 * Fiches projet structurées (cahier des charges §6 et §10).
 * Les chiffres et l'état sont communs aux deux langues : enregistrés sur une
 * version, ils sont recopiés sur l'autre. Les textes restent propres à chaque langue.
 */

/** Les six états et leur message public. */
function beog_project_states(): array {
	return [
		'preparation' => ['En préparation', 'In preparation', 'Le projet se prépare : diagnostic, devis et validation. Aucune collecte n’est ouverte.', 'This project is being prepared: assessment, quotations and approval. No fundraising is open.'],
		'ouvert'      => ['Ouvert au soutien', 'Open for support', 'Le budget et les règles de collecte sont validés. Vous pouvez proposer votre soutien.', 'The budget and fundraising rules are approved. You can offer your support.'],
		'atteint'     => ['Financement atteint', 'Fully funded', 'L’objectif validé est atteint. Les nouveaux soutiens sont orientés vers d’autres besoins.', 'The approved target has been reached. New support is directed to other needs.'],
		'realisation' => ['En réalisation', 'Under way', 'Les travaux ou les achats sont en cours. Les avancées sont publiées sur cette page.', 'Work or purchasing is under way. Progress is published on this page.'],
		'termine'     => ['Terminé', 'Completed', 'Le projet est réalisé. Son bilan est publié dans la page Transparence.', 'The project is complete. Its report is published on the Transparency page.'],
		'suspendu'    => ['Suspendu', 'On hold', 'Le projet est suspendu pour le moment. Aucun nouveau soutien n’est sollicité.', 'This project is on hold. No new support is being sought.'],
	];
}

/** Clés de projet connues (identifiant → libellé), d'après les fiches existantes. */
function beog_project_keys(): array {
	static $keys = null;
	if ($keys !== null) return $keys;
	$keys = [];
	foreach (get_posts(['post_type' => 'beog_project', 'post_status' => 'any', 'posts_per_page' => -1, 'meta_key' => '_beog_lang', 'meta_value' => 'fr', 'orderby' => 'menu_order title', 'order' => 'ASC']) as $p) {
		if ($k = get_post_meta($p->ID, '_beog_project', true)) $keys[$k] = $p->post_title;
	}
	return $keys;
}

/** Champs communs aux deux langues. */
function beog_shared_fields(): array {
	return ['status', 'budget', 'budget_date', 'secured', 'secured_date', 'objective_ok', 'objective', 'confirmed', 'confirmed_date'];
}
/** Champs propres à chaque langue. */
function beog_text_fields(): array {
	return ['lead' => 'Responsable opérationnel', 'location' => 'Lieu', 'timing' => 'Calendrier envisagé', 'beneficiaries' => 'Bénéficiaires (chiffres agrégés uniquement)'];
}

/** Données d'une fiche (valeurs par défaut prudentes). */
function beog_project_facts(int $post_id): array {
	$facts = get_post_meta($post_id, '_beog_facts', true);
	return wp_parse_args(is_array($facts) ? $facts : [], [
		'status' => 'preparation', 'budget' => 0, 'budget_date' => '', 'secured' => null, 'secured_date' => '',
		'objective_ok' => false, 'objective' => 0, 'confirmed' => 0, 'confirmed_date' => '',
		'lead' => '', 'location' => '', 'timing' => '', 'beneficiaries' => '',
	]);
}

/** Fiche d'un projet dans une langue, publiée de préférence. */
function beog_project_post(string $key, string $lang): ?WP_Post {
	foreach ([['publish'], ['draft', 'pending', 'future', 'private']] as $statuses) {
		$posts = get_posts(['post_type' => 'beog_project', 'post_status' => $statuses, 'posts_per_page' => 1, 'meta_query' => [['key' => '_beog_project', 'value' => $key], ['key' => '_beog_lang', 'value' => $lang]]]);
		if ($posts) return $posts[0];
	}
	return null;
}

// ---------- Administration ----------

add_action('add_meta_boxes_beog_project', function () {
	add_meta_box('beog_facts', 'Fiche projet : état, budget et suivi', 'beog_facts_box', 'beog_project', 'normal', 'high');
});

function beog_facts_box(WP_Post $post): void {
	$f = beog_project_facts($post->ID);
	$can = current_user_can('beog_edit_project_facts');
	$ro = $can ? '' : ' disabled';
	wp_nonce_field('beog_facts_save', 'beog_facts_nonce');
	$key = (string) get_post_meta($post->ID, '_beog_project', true);
	echo '<style>.beog-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px 20px}.beog-grid label{font-weight:600;display:block;margin-bottom:4px}.beog-grid input,.beog-grid select,.beog-grid textarea{width:100%}.beog-sep{margin:18px 0 8px;padding-top:12px;border-top:1px solid #dcdcde}</style>';
	if (!$can) echo '<p class="notice notice-info inline" style="padding:8px 12px">Les chiffres et l’état sont fixés par la direction. Vous pouvez modifier les textes de la fiche.</p>';
	echo '<div class="beog-grid">';
	echo '<p><label for="beog_key">Identifiant du projet</label><input id="beog_key" name="beog_facts[key]" value="' . esc_attr($key) . '" pattern="[a-z0-9-]+"' . ($can ? '' : ' readonly') . '><span class="description">Commun aux versions FR et EN.</span></p>';
	echo '<p><label for="beog_status">État public</label><select id="beog_status" name="beog_facts[status]"' . $ro . '>';
	foreach (beog_project_states() as $k => $s) echo '<option value="' . esc_attr($k) . '"' . selected($f['status'], $k, false) . '>' . esc_html($s[0]) . '</option>';
	echo '</select><span class="description">' . esc_html(beog_project_states()[$f['status']][2] ?? '') . '</span></p>';
	echo '</div><h4 class="beog-sep">Budget (FCFA, montants entiers)</h4><div class="beog-grid">';
	beog_number_field('budget', 'Budget indicatif', $f['budget'], $ro);
	beog_date_field('budget_date', 'Date du budget', $f['budget_date'], $ro);
	beog_number_field('secured', 'Ressources sécurisées (vide = non renseigné)', $f['secured'], $ro, true);
	beog_date_field('secured_date', 'Date des ressources', $f['secured_date'], $ro);
	echo '</div><p>Solde à financer : <strong>' . esc_html(beog_gap_label($f, 'fr')) . '</strong></p>';
	echo '<h4 class="beog-sep">Jauge publique</h4><p class="description">La jauge n’apparaît que si l’objectif et sa règle de calcul sont validés, et seulement aux états « Ouvert au soutien » et « Financement atteint ». Le montant confirmé correspond aux contributions confirmées, diminuées des remboursements ; les promesses n’y figurent pas.</p><div class="beog-grid">';
	echo '<p><label><input type="checkbox" name="beog_facts[objective_ok]" value="1"' . checked($f['objective_ok'], true, false) . $ro . '> Objectif et règle validés par la direction</label></p>';
	beog_number_field('objective', 'Objectif validé', $f['objective'], $ro);
	beog_number_field('confirmed', 'Montant confirmé', $f['confirmed'], $ro);
	beog_date_field('confirmed_date', 'Date du montant confirmé', $f['confirmed_date'], $ro);
	echo '</div><h4 class="beog-sep">Textes de la fiche (' . (beog_post_lang($post->ID) === 'en' ? 'anglais' : 'français') . ')</h4><div class="beog-grid">';
	foreach (beog_text_fields() as $k => $label) {
		echo '<p><label for="beog_' . $k . '">' . esc_html($label) . '</label><textarea id="beog_' . $k . '" name="beog_facts[' . $k . ']" rows="2">' . esc_textarea($f[$k]) . '</textarea></p>';
	}
	echo '</div>';
	$history = get_post_meta($post->ID, '_beog_budget_history', true);
	if (is_array($history) && $history) {
		echo '<h4 class="beog-sep">Historique du budget</h4><table class="widefat striped"><thead><tr><th>Montant</th><th>Date du budget</th><th>Modifié par</th><th>Le</th></tr></thead><tbody>';
		foreach (array_reverse($history) as $h) {
			$u = get_userdata((int) $h['user']);
			echo '<tr><td>' . esc_html(beog_money((int) $h['budget'], 'fr')) . '</td><td>' . esc_html($h['budget_date']) . '</td><td>' . esc_html($u ? $u->display_name : '—') . '</td><td>' . esc_html(wp_date('Y-m-d H:i', (int) $h['at'])) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}

function beog_number_field(string $k, string $label, $value, string $ro, bool $nullable = false): void {
	$v = ($nullable && ($value === null || $value === '')) ? '' : (string) (int) $value;
	echo '<p><label for="beog_' . $k . '">' . esc_html($label) . '</label><input type="number" min="0" step="1" inputmode="numeric" id="beog_' . $k . '" name="beog_facts[' . $k . ']" value="' . esc_attr($v) . '"' . $ro . '></p>';
}
function beog_date_field(string $k, string $label, string $value, string $ro): void {
	echo '<p><label for="beog_' . $k . '">' . esc_html($label) . '</label><input type="date" id="beog_' . $k . '" name="beog_facts[' . $k . ']" value="' . esc_attr($value) . '"' . $ro . '></p>';
}

add_action('save_post_beog_project', function (int $post_id) {
	if (!isset($_POST['beog_facts_nonce']) || !wp_verify_nonce(sanitize_key($_POST['beog_facts_nonce']), 'beog_facts_save')) return;
	if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) return;
	$in = wp_unslash((array) ($_POST['beog_facts'] ?? []));
	beog_save_facts($post_id, $in, current_user_can('beog_edit_project_facts'));
});

/**
 * Enregistre une fiche. Les champs communs ne sont modifiés qu'avec la capacité
 * « beog_edit_project_facts » et sont recopiés sur la version de l'autre langue.
 */
function beog_save_facts(int $post_id, array $in, bool $can_edit_shared, int $user_id = 0): void {
	$old = beog_project_facts($post_id);
	$new = $old;
	foreach (array_keys(beog_text_fields()) as $k) {
		if (array_key_exists($k, $in)) $new[$k] = sanitize_textarea_field((string) $in[$k]);
	}
	if ($can_edit_shared) {
		$key = sanitize_key((string) ($in['key'] ?? get_post_meta($post_id, '_beog_project', true)));
		if ($key !== '') update_post_meta($post_id, '_beog_project', $key);
		if (isset($in['status']) && isset(beog_project_states()[$in['status']])) $new['status'] = $in['status'];
		foreach (['budget', 'objective', 'confirmed'] as $k) if (array_key_exists($k, $in)) $new[$k] = max(0, (int) $in[$k]);
		if (array_key_exists('secured', $in)) $new['secured'] = ($in['secured'] === '' || $in['secured'] === null) ? null : max(0, (int) $in['secured']);
		foreach (['budget_date', 'secured_date', 'confirmed_date'] as $k) if (array_key_exists($k, $in)) $new[$k] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in[$k]) ? (string) $in[$k] : '';
		$new['objective_ok'] = !empty($in['objective_ok']);
	}
	update_post_meta($post_id, '_beog_facts', $new);

	if (!$can_edit_shared) return;
	$history = get_post_meta($post_id, '_beog_budget_history', true);
	$history = is_array($history) ? $history : [];
	if ($new['budget'] !== $old['budget'] || $new['budget_date'] !== $old['budget_date']) {
		$history[] = ['budget' => $new['budget'], 'budget_date' => $new['budget_date'], 'user' => $user_id ?: get_current_user_id(), 'at' => time()];
		update_post_meta($post_id, '_beog_budget_history', $history);
	}
	// Recopie des chiffres et de l'état sur la version de l'autre langue.
	$key = (string) get_post_meta($post_id, '_beog_project', true);
	if ($key === '') return;
	$other = beog_project_post($key, beog_post_lang($post_id) === 'fr' ? 'en' : 'fr');
	if (!$other || $other->ID === $post_id) return;
	$o = beog_project_facts($other->ID);
	foreach (beog_shared_fields() as $k) $o[$k] = $new[$k];
	update_post_meta($other->ID, '_beog_facts', $o);
	update_post_meta($other->ID, '_beog_budget_history', $history);
}

add_filter('manage_beog_project_posts_columns', function ($cols) {
	return array_slice($cols, 0, 2, true) + ['beog_state' => 'État', 'beog_lang' => 'Langue', 'beog_budget' => 'Budget'] + $cols;
});
add_action('manage_beog_project_posts_custom_column', function ($col, $id) {
	$f = beog_project_facts($id);
	if ($col === 'beog_state') echo esc_html(beog_project_states()[$f['status']][0] ?? '');
	if ($col === 'beog_lang') echo esc_html(strtoupper(beog_post_lang($id)));
	if ($col === 'beog_budget') echo esc_html($f['budget'] ? beog_money((int) $f['budget'], 'fr') : '—');
}, 10, 2);

// ---------- Rendu public ----------

function beog_gap_label(array $f, string $lang): string {
	if ($f['secured'] === null || $f['secured'] === '') return beog_t($lang, 'non établi : ressources sécurisées non renseignées', 'not established: secured resources not documented');
	return beog_money(max(0, (int) $f['budget'] - (int) $f['secured']), $lang);
}

function beog_status_chip(string $key, string $lang): string {
	$post = beog_project_post($key, $lang);
	$state = $post ? beog_project_facts($post->ID)['status'] : 'preparation';
	$s = beog_project_states()[$state] ?? beog_project_states()['preparation'];
	return '<span class="status" data-state="' . esc_attr($state) . '"><span></span>' . esc_html(beog_t($lang, $s[0], $s[1])) . '</span>';
}

function beog_facts_aside(string $key, string $lang): string {
	$post = beog_project_post($key, $lang);
	if (!$post) return '';
	$f = beog_project_facts($post->ID);
	$s = beog_project_states()[$f['status']] ?? beog_project_states()['preparation'];
	$dash = '—';
	$rows = [
		[beog_t($lang, 'État', 'Status'), beog_t($lang, $s[2], $s[3])],
		[beog_t($lang, 'Lieu', 'Location'), $f['location'] ?: 'Komsilga, Burkina Faso'],
		[beog_t($lang, 'Porteur', 'Project owner'), beog_t($lang, 'École primaire privée Beog-Zaadba', 'Beog-Zaadba private primary school')],
		[beog_t($lang, 'Responsable opérationnel', 'Operational lead'), $f['lead'] ?: beog_t($lang, 'À désigner par la direction', 'To be appointed by school leadership')],
		[beog_t($lang, 'Bénéficiaires', 'Beneficiaries'), $f['beneficiaries'] ?: $dash],
		[beog_t($lang, 'Calendrier envisagé', 'Proposed timeline'), $f['timing'] ?: $dash],
		[beog_t($lang, 'Budget indicatif', 'Indicative budget'), $f['budget'] ? beog_money((int) $f['budget'], $lang) . ($f['budget_date'] ? ' · ' . beog_t($lang, 'au ', 'as of ') . beog_date($f['budget_date'], $lang) : '') : $dash],
		[beog_t($lang, 'Financement sécurisé', 'Secured funding'), ($f['secured'] === null || $f['secured'] === '') ? beog_t($lang, 'Non renseigné', 'Not documented') : beog_money((int) $f['secured'], $lang) . ($f['secured_date'] ? ' · ' . beog_t($lang, 'au ', 'as of ') . beog_date($f['secured_date'], $lang) : '')],
		[beog_t($lang, 'Solde à financer', 'Funding gap'), beog_gap_label($f, $lang)],
	];
	$html = '<aside class="project-aside" data-beog-facts="' . esc_attr($key) . '">' . beog_status_chip($key, $lang);
	$html .= '<h2>' . esc_html(beog_t($lang, 'La fiche du projet', 'Project facts')) . '</h2><dl>';
	foreach ($rows as [$dt, $dd]) $html .= '<dt>' . esc_html($dt) . '</dt><dd>' . nl2br(esc_html($dd)) . '</dd>';
	$html .= '</dl>' . beog_gauge($f, $lang);
	$open = in_array($f['status'], ['preparation', 'ouvert'], true);
	if ($open) {
		$href = home_url('/' . $lang . '/partenaires/') . '?projet=' . rawurlencode($key);
		$html .= '<a class="button" href="' . esc_url($href) . '">' . esc_html(beog_t($lang, 'Proposer mon soutien', 'Offer support')) . '</a>';
	}
	$html .= '<p class="fine">' . esc_html(beog_t($lang, 'Aucun paiement en ligne sur ce site pour le moment.', 'No online payments on this site at present.')) . '</p></aside>';
	return $html;
}

/** Jauge : seulement avec un objectif validé, à l'état ouvert ou atteint, avec règle et date. */
function beog_gauge(array $f, string $lang): string {
	if (!$f['objective_ok'] || (int) $f['objective'] <= 0 || !in_array($f['status'], ['ouvert', 'atteint'], true)) return '';
	$pct = min(100, (int) floor(100 * (int) $f['confirmed'] / (int) $f['objective']));
	$label = beog_money((int) $f['confirmed'], $lang) . ' / ' . beog_money((int) $f['objective'], $lang);
	$date = $f['confirmed_date'] ? beog_date($f['confirmed_date'], $lang) : '';
	return '<div class="gauge"><p class="gauge-figures"><strong>' . esc_html($label) . '</strong></p>'
		. '<div class="gauge-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $pct . '" aria-label="' . esc_attr(beog_t($lang, 'Avancement du financement', 'Funding progress')) . '"><span style="width:' . $pct . '%"></span></div>'
		. '<p class="fine">' . esc_html(beog_t($lang, 'Contributions confirmées, diminuées des remboursements', 'Confirmed contributions, less refunds') . ($date ? beog_t($lang, ', au ', ', as of ') . $date : '') . beog_t($lang, '. Les promesses ne sont pas comptées.', '. Pledges are not included.')) . '</p></div>';
}

function beog_updates_section(string $key, string $lang, string $fallback): string {
	$posts = get_posts(['post_type' => ['beog_news', 'beog_report'], 'post_status' => 'publish', 'posts_per_page' => 10, 'meta_query' => [['key' => '_beog_project', 'value' => $key], ['key' => '_beog_lang', 'value' => $lang]]]);
	if (!$posts) return $fallback;
	$html = '<section class="section container" data-beog-updates="' . esc_attr($key) . '"><h2>' . esc_html(beog_t($lang, 'Suivi du projet', 'Project updates')) . '</h2><ul class="update-list">';
	foreach ($posts as $p) {
		$type = $p->post_type === 'beog_report' ? beog_t($lang, 'Rapport', 'Report') : beog_t($lang, 'Actualité', 'News');
		$html .= '<li><time datetime="' . esc_attr(get_the_date('Y-m-d', $p)) . '">' . esc_html(beog_date(get_the_date('Y-m-d', $p), $lang)) . '</time> <span class="eyebrow">' . esc_html($type) . '</span> <a href="' . esc_url(get_permalink($p)) . '">' . esc_html(get_the_title($p)) . '</a></li>';
	}
	return $html . '</ul></section>';
}

/**
 * Remplace les zones marquées dans le HTML importé par les données à jour :
 * data-beog-status (pastille d'état), data-beog-facts (fiche), data-beog-updates (suivi).
 * Le thème appelle cette fonction sur chaque page.
 */
function beog_render_dynamic(string $html, string $lang): string {
	$html = preg_replace_callback('#<span class="status" data-beog-status="([a-z0-9-]+)"[^>]*><span></span>[^<]*</span>#', fn($m) => beog_status_chip($m[1], $lang), $html);
	$html = preg_replace_callback('#<aside class="project-aside" data-beog-facts="([a-z0-9-]+)">.*?</aside>#s', fn($m) => beog_facts_aside($m[1], $lang) ?: $m[0], $html);
	if (function_exists('beog_album_cards')) $html = preg_replace_callback('#<div class="album-grid" data-beog-albums>.*?(?=<aside class="consent-note">)#s', fn() => beog_album_cards($lang), $html);
	$html = preg_replace_callback('#<section class="section container" data-beog-updates="([a-z0-9-]+)">.*?</section>#s', fn($m) => beog_updates_section($m[1], $lang, $m[0]), $html);
	return $html;
}
