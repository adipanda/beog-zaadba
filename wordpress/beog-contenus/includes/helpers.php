<?php
if (!defined('ABSPATH')) exit;

/** Choisit le texte selon la langue ('fr' ou 'en'). */
function beog_t(string $lang, string $fr, string $en): string {
	return $lang === 'en' ? $en : $fr;
}

/** Montant XOF entier au format « 4 510 000 FCFA » (FR) ou « 4,510,000 FCFA » (EN). */
function beog_money(int $amount, string $lang): string {
	return number_format($amount, 0, '', $lang === 'en' ? ',' : "\u{202F}") . ' FCFA';
}

/** Date au format lisible dans la langue demandée. */
function beog_date(string $ymd, string $lang): string {
	$ts = strtotime($ymd);
	if (!$ts) return '';
	if ($lang === 'en') return gmdate('j F Y', $ts);
	$mois = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
	$j = (int) gmdate('j', $ts);
	return ($j === 1 ? '1er' : $j) . ' ' . $mois[(int) gmdate('n', $ts) - 1] . ' ' . gmdate('Y', $ts);
}

/** Langue d'un contenu, d'après sa méta ou sa route. */
function beog_post_lang(int $post_id): string {
	$lang = get_post_meta($post_id, '_beog_lang', true);
	if ($lang === 'fr' || $lang === 'en') return $lang;
	return str_starts_with((string) get_post_meta($post_id, '_beog_route', true), 'en') ? 'en' : 'fr';
}

/**
 * Neutralise les cellules CSV qui seraient interprétées comme des formules
 * par un tableur (=, +, -, @, tabulation, retour chariot).
 */
function beog_csv_cell($value): string {
	$value = (string) $value;
	if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) $value = "'" . $value;
	return $value;
}

/** Envoie un CSV (UTF-8 avec BOM, séparateur ;) et termine la requête. */
function beog_send_csv(string $filename, array $header, array $rows): void {
	nocache_headers();
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
	header('X-Content-Type-Options: nosniff');
	$out = fopen('php://output', 'w');
	fwrite($out, "\xEF\xBB\xBF");
	fputcsv($out, array_map('beog_csv_cell', $header), ';', '"', '');
	foreach ($rows as $row) fputcsv($out, array_map('beog_csv_cell', $row), ';', '"', '');
	fclose($out);
	exit;
}
