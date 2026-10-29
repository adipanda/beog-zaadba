<?php
if (!defined('ABSPATH')) exit;
add_action('after_setup_theme', function() { add_theme_support('title-tag'); add_theme_support('post-thumbnails'); add_theme_support('html5',['search-form','gallery','caption','style','script']); });
add_action('wp_enqueue_scripts', function() {
  wp_enqueue_style('beog',get_stylesheet_uri(),[], '1.1.0');
  wp_enqueue_script('beog',get_template_directory_uri().'/app.js',[], '1.1.0',true);
});
function beog_rewrite_assets($html) {
  $asset=esc_url(get_template_directory_uri());
  $html=str_replace(['/assets/','/documents/'],[$asset.'/assets/',$asset.'/documents/'],$html);
  return preg_replace_callback('~href="/(fr|en)(/[^"]*)?"~',function($m){return 'href="'.esc_url(home_url('/'.$m[1].($m[2]??'/'))).'"';},$html);
}
