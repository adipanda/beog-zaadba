<?php
if (!defined('ABSPATH')) exit;
$route=trim((string)(get_query_var('beog_route') ?: get_post_meta(get_queried_object_id(),'_beog_route',true)),'/');
if (!$route && have_posts()) { the_post(); $route=trim(get_post_meta(get_the_ID(),'_beog_route',true),'/'); rewind_posts(); }
$lang=str_starts_with($route,'en')?'en':'fr';
$frame=json_decode(file_get_contents(get_template_directory().'/frame-'.$lang.'.json'),true);
?><!doctype html><html lang="<?php echo esc_attr($lang); ?>"><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><?php wp_head(); ?></head><body <?php body_class(); ?>><?php wp_body_open(); ?>
<?php echo beog_rewrite_assets($frame['header']); ?>
<main id="contenu" tabindex="-1">
<?php if(have_posts()): while(have_posts()): the_post(); $html=(get_post_type()==='beog_album' && function_exists('beog_render_album')) ? beog_render_album(get_post(),$lang) : do_shortcode(get_the_content()); if(function_exists('beog_render_dynamic')) $html=beog_render_dynamic($html,$lang); echo beog_rewrite_assets($html); endwhile; else: ?>
<section class="container page-intro"><h1>Page introuvable</h1><p><a href="<?php echo esc_url(home_url('/fr/')); ?>">Retour à l’accueil</a></p></section>
<?php endif; ?></main><?php echo beog_rewrite_assets($frame['footer']); wp_footer(); ?></body></html>
