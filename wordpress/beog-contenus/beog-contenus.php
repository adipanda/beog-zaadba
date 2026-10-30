<?php
/**
 * Plugin Name: Beog-Zaadba — Contenus et import initial
 * Description: Contenus FR/EN et import des pages de présentation. Aucun paiement ni collecte de données.
 * Version: 1.0.0
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;
function beog_types(){
 foreach(['beog_project'=>'Projets Beog-Zaadba','beog_news'=>'Actualités Beog-Zaadba','beog_report'=>'Rapports Beog-Zaadba'] as $type=>$label){
 register_post_type($type,['label'=>$label,'public'=>true,'show_in_rest'=>true,'supports'=>['title','editor','revisions','thumbnail','custom-fields'],'rewrite'=>false,'has_archive'=>false,'capability_type'=>'post','map_meta_cap'=>true]);
 }
 add_rewrite_rule('^((?:fr|en)(?:/[^?]*)?)/?$','index.php?beog_route=$matches[1]','top');
}
add_action('init','beog_types');
add_filter('query_vars',function($v){$v[]='beog_route';return $v;});
add_filter('request',function($q){
 if(empty($q['beog_route']))return $q;
 $route=trim(sanitize_text_field($q['beog_route']),'/');
 $ids=get_posts(['post_type'=>['page','beog_project','beog_news','beog_report'],'post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_beog_route','meta_value'=>$route]);
 return $ids ? ['p'=>$ids[0],'post_type'=>'any'] : ['p'=>0,'error'=>'404'];
});
add_filter('post_type_link',function($url,$post){$route=get_post_meta($post->ID,'_beog_route',true);return $route?home_url('/'.trim($route,'/').'/'):$url;},10,2);
add_filter('page_link',function($url,$id){$route=get_post_meta($id,'_beog_route',true);return $route?home_url('/'.trim($route,'/').'/'):$url;},10,2);
register_activation_hook(__FILE__,function(){beog_types();flush_rewrite_rules();});
register_deactivation_hook(__FILE__,function(){flush_rewrite_rules();});
add_action('admin_menu',function(){
 add_management_page('Import Beog-Zaadba','Import Beog-Zaadba','manage_options','beog-import',function(){
 if(!current_user_can('manage_options'))return;
 echo '<div class="wrap"><h1>Importer la présentation Beog-Zaadba</h1><p>Crée les contenus initiaux FR/EN. Un contenu déjà importé est conservé. La collecte de dons et l’envoi de formulaires restent désactivés. Les pages sont créées en brouillon pour validation.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
 wp_nonce_field('beog_import');
 echo '<input type="hidden" name="action" value="beog_import"><button class="button button-primary">Importer les contenus en brouillon</button></form></div>';
 });
});
add_action('admin_post_beog_import',function(){
 if(!current_user_can('manage_options'))wp_die('Accès refusé.',403);
 check_admin_referer('beog_import');
 $pages=json_decode(file_get_contents(__DIR__.'/pages.json'),true);
 if(!is_array($pages))wp_die('Le fichier de contenus est illisible.');
 $count=0;
 foreach($pages as $p){
 $route=sanitize_text_field($p['route']);
 $existing=get_posts(['post_type'=>['page','beog_project','beog_news'],'post_status'=>'any','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_beog_route','meta_value'=>$route]);
 if($existing)continue;
 $type=$p['type']==='project'?'beog_project':($p['type']==='news'?'beog_news':'page');
 $id=wp_insert_post(wp_slash(['post_type'=>$type,'post_status'=>'draft','post_title'=>sanitize_text_field($p['title']),'post_name'=>sanitize_title(str_replace('/','-',$route)),'post_content'=>'<!-- wp:html -->'.$p['body'].'<!-- /wp:html -->']),true);
 if(is_wp_error($id))continue;
 update_post_meta($id,'_beog_route',$route);update_post_meta($id,'_beog_lang',sanitize_key($p['lang']));
 update_post_meta($id,'_beog_description',sanitize_text_field($p['description']));$count++;
 }
 flush_rewrite_rules();
 wp_safe_redirect(add_query_arg(['page'=>'beog-import','imported'=>$count],admin_url('tools.php')));exit;
});
