<?php
/*
Plugin Name: WP Posts Re-order
Plugin URI: http://suoling.net/wp-posts-re-order
Description:  Drag and Drop to sort your posts.
Author: suifengtec
Author URI: http://suoling.net/
Version: 1.0
*/

define('WPPRO_PATH',   plugin_dir_path(__FILE__));
define('WPPRO_URL',    plugins_url('', __FILE__));
define('WPPRO_NAME',   'WP Posts Re-order');
define('WPPRO_SLUG',   'wppro');
register_deactivation_hook(__FILE__, 'WP_P_R_O_deactivated');
register_activation_hook(__FILE__, 'WP_P_R_O_activated');

function WP_P_R_O_activated(){
        $options=null;
        $options = get_option('wppro_options');
        if (!isset($options['autosort'])){
            $options['autosort'] = '1';
        }

        if (!isset($options['adminsort'])){
            $options['adminsort'] = '1';
        }

        if (!isset($options['capability'])){
            $options['capability'] = 'install_plugins';
        }
        update_option('wppro_options', $options);
}

function WP_P_R_O_deactivated(){
    delete_option( 'wppro_options' );
}

add_action( 'plugins_loaded', 'wppro_load_textdomain');
function wppro_load_textdomain(){
        load_plugin_textdomain('wppro', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/lang');
}

add_action('admin_menu', 'wppro_get_plugin_menu');
function wppro_get_plugin_menu(){
$plugin_name=__('Posts Re-order','wppro');
        add_options_page($plugin_name, $plugin_name, 'manage_options', 'wppro-options', 'cpt_plugin_options');
}



add_filter('pre_get_posts', 'WP_P_R_O_pre_get_posts');
function WP_P_R_O_pre_get_posts($query){
    global $post;
    if(is_object($post) && isset($post->ID) && $post->ID < 1){
        return $query;
    }
    $options = get_option('wppro_options');
    if (is_admin()){
            return false;
        }
    if ($options['autosort'] == "1"){
            if (isset($query->query['suppress_filters'])){

                $query->query['suppress_filters'] = FALSE;
            }
            if (isset($query->query_vars['suppress_filters'])){
                $query->query_vars['suppress_filters'] = FALSE;
            }

        }

    return $query;
}

add_filter('posts_orderby', 'WP_P_R_OrderPosts', 99, 2);
function WP_P_R_OrderPosts($orderBy, $query){
    global $wpdb;

    $options = get_option('wppro_options');

    //ignore the bbpress
    if (isset($query->query_vars['post_type']) && ((is_array($query->query_vars['post_type']) && in_array("reply", $query->query_vars['post_type'])) || ($query->query_vars['post_type'] == "reply")))
        return $orderBy;
    if (isset($query->query_vars['post_type']) && ((is_array($query->query_vars['post_type']) && in_array("topic", $query->query_vars['post_type'])) || ($query->query_vars['post_type'] == "topic")))
        return $orderBy;

    if (is_admin()){

        if ($options['adminsort'] == "1" &&
            //ignore when ajax Gallery Edit default functionality
            !($options['adminsort'] == "1" && defined('DOING_AJAX') && isset($_REQUEST['action']) && $_REQUEST['action'] == 'query-attachments')
            ){
                $orderBy = "{$wpdb->posts}.menu_order, {$wpdb->posts}.post_date DESC";
        }
    }else{
        //ignore search
        if($query->is_search()){
            return($orderBy);
        }

        if ($options['autosort'] == "1"){
            $orderBy = "{$wpdb->posts}.menu_order, " . $orderBy;
        }
    }

    return($orderBy);
}

$be_configured = null;
$be_configured = get_option('WPPRO__configured');
if ($be_configured == ''){
    add_action( 'admin_notices', 'WP_P_R_O_admin_notices');
}
function WP_P_R_O_admin_notices(){
        if (isset($_POST['form_submit'])){
            return;
        }
        ?>
            <div class="error fade">
                <p><strong><?php echo WPPRO_NAME.__(' must be configured. Please go to', 'wppro'); ?> <a href="<?php echo get_admin_url() ?>options-general.php?page=wppro_get-options"><?php _e('Settings Page', 'wppro') ?></a> <?php _e('make the configuration and save', 'wppro') ?></strong></p>
            </div>
        <?php
}





add_action('wp_loaded', 'initWP_P_R_O' );
function initWP_P_R_O(){
    global $custom_post_type_order, $userdata;

    $options = get_option('wppro_options');

    if (is_admin()){
            if(isset($options['capability']) && !empty($options['capability'])){
                if(current_user_can($options['capability'])){
                    $custom_post_type_order = new WP_P_R_O();
                }
            }else if (is_numeric($options['level'])){
                if (userdata_get_user_level(true) >= $options['level']){
                    $custom_post_type_order = new WP_P_R_O();
                }
            }else{
                $custom_post_type_order = new WP_P_R_O();
            }
    }
}

add_filter('get_previous_post_where', 'wppro_get_get_previous_post_where');
add_filter('get_previous_post_sort', 'wppro_get_get_previous_post_sort');
add_filter('get_next_post_where', 'wppro_get_get_next_post_where');
add_filter('get_next_post_sort', 'wppro_get_get_next_post_sort');
function wppro_get_get_previous_post_where($where){
        global $post, $wpdb;

        if ( empty( $post ) ){
            return $where;
        }

        $current_post_date = $post->post_date;

        $join = '';
        $posts_in_ex_cats_sql = '';
        if (isset($in_same_cat))
        if ( $in_same_cat || !empty($excluded_categories) ){
                $join = " INNER JOIN $wpdb->term_relationships AS tr ON p.ID = tr.object_id INNER JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";

                if ( $in_same_cat ) {
                    $cat_array = wp_get_object_terms($post->ID, 'category', array('fields' => 'ids'));
                    $join .= " AND tt.taxonomy = 'category' AND tt.term_id IN (" . implode(',', $cat_array) . ")";
                }

                $posts_in_ex_cats_sql = "AND tt.taxonomy = 'category'";
                if ( !empty($excluded_categories) ) {
                    $excluded_categories = array_map('intval', explode(' and ', $excluded_categories));
                    if ( !empty($cat_array) ) {
                        $excluded_categories = array_diff($excluded_categories, $cat_array);
                        $posts_in_ex_cats_sql = '';
                    }

                    if ( !empty($excluded_categories) ) {
                        $posts_in_ex_cats_sql = " AND tt.taxonomy = 'category' AND tt.term_id NOT IN (" . implode($excluded_categories, ',') . ')';
                    }
                }
            }
        $current_menu_order = $post->menu_order;

        //check if there are more posts with lower menu_order
        $query = "SELECT p.* FROM $wpdb->posts AS p
                    WHERE p.menu_order < '".$current_menu_order."' AND p.post_type = '". $post->post_type ."' AND p.post_status = 'publish' $posts_in_ex_cats_sql";
        $results = $wpdb->get_results($query);

        if (count($results) > 0){
            $where = "WHERE p.menu_order < '".$current_menu_order."' AND p.post_type = '". $post->post_type ."' AND p.post_status = 'publish' $posts_in_ex_cats_sql";
        }else{
            $where = "WHERE p.post_date < '".$current_post_date."' AND p.post_type = '". $post->post_type ."' AND p.post_status = 'publish' AND p.ID != '". $post->ID ."' $posts_in_ex_cats_sql";
        }

        return $where;
    }

function wppro_get_get_previous_post_sort($sort){
        global $post, $wpdb;
        $posts_in_ex_cats_sql = '';

        $current_menu_order = $post->menu_order;

        $query = "SELECT p.* FROM $wpdb->posts AS p
                    WHERE p.menu_order < '".$current_menu_order."' AND p.post_type = '". $post->post_type ."' AND p.post_status = 'publish' $posts_in_ex_cats_sql";
        $results = $wpdb->get_results($query);

        if (count($results) > 0)
                {
                    $sort = 'ORDER BY p.menu_order DESC, p.post_date ASC LIMIT 1';
                }
            else
                {
                    $sort = 'ORDER BY p.post_date DESC LIMIT 1';
                }

        return $sort;
    }

function wppro_get_get_next_post_where($where){
        global $post, $wpdb;

        if ( empty( $post ) )
            return null;

        $current_post_date = $post->post_date;

        $join = '';
        $posts_in_ex_cats_sql = '';
        if (isset($in_same_cat))
        if ( $in_same_cat || !empty($excluded_categories) )
            {
                $join = " INNER JOIN $wpdb->term_relationships AS tr ON p.ID = tr.object_id INNER JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";

                if ( $in_same_cat ) {
                    $cat_array = wp_get_object_terms($post->ID, 'category', array('fields' => 'ids'));
                    $join .= " AND tt.taxonomy = 'category' AND tt.term_id IN (" . implode(',', $cat_array) . ")";
                }

                $posts_in_ex_cats_sql = "AND tt.taxonomy = 'category'";
                if ( !empty($excluded_categories) ) {
                    $excluded_categories = array_map('intval', explode(' and ', $excluded_categories));
                    if ( !empty($cat_array) ) {
                        $excluded_categories = array_diff($excluded_categories, $cat_array);
                        $posts_in_ex_cats_sql = '';
                    }

                    if ( !empty($excluded_categories) ) {
                        $posts_in_ex_cats_sql = " AND tt.taxonomy = 'category' AND tt.term_id NOT IN (" . implode($excluded_categories, ',') . ')';
                    }
                }
            }

        $current_menu_order = $post->menu_order;

        //check if there are more posts with lower menu_order
        $query = "SELECT p.* FROM $wpdb->posts AS p
                    WHERE p.menu_order > '".$current_menu_order."' AND p.post_type = '". $post->post_type ."' AND p.post_status = 'publish' $posts_in_ex_cats_sql";
        $results = $wpdb->get_results($query);

        if (count($results) > 0)
            {
                $where = "WHERE p.menu_order > '".$current_menu_order."' AND p.post_type = '". $post->post_type ."' AND p.post_status = 'publish' $posts_in_ex_cats_sql";
            }
            else
                {
                    $where = "WHERE p.post_date > '".$current_post_date."' AND p.post_type = '". $post->post_type ."' AND p.post_status = 'publish' AND p.ID != '". $post->ID ."' $posts_in_ex_cats_sql";
                }

        return $where;
    }

function wppro_get_get_next_post_sort($sort){
        global $post, $wpdb;
        $posts_in_ex_cats_sql = '';

        $current_menu_order = $post->menu_order;

        $query = "SELECT p.* FROM $wpdb->posts AS p
                    WHERE p.menu_order > '".$current_menu_order."' AND p.post_type = '". $post->post_type ."' AND p.post_status = 'publish' $posts_in_ex_cats_sql";
        $results = $wpdb->get_results($query);
        if (count($results) > 0)
                {
                    $sort = 'ORDER BY p.menu_order ASC, p.post_date DESC LIMIT 1';
                }
            else
                {
                    $sort = 'ORDER BY p.post_date ASC LIMIT 1';
                }

        return $sort;
    }


class WP_P_R_O_Walker extends Walker{

        var $db_fields = array ('parent' => 'post_parent', 'id' => 'ID');


        function start_lvl(&$output, $depth = 0, $args = array()) {
            $indent = str_repeat("\t", $depth);
            $output .= "\n$indent<ul class='children'>\n";
        }


        function end_lvl(&$output, $depth = 0, $args = array()) {
            $indent = str_repeat("\t", $depth);
            $output .= "$indent</ul>\n";
        }


        function start_el(&$output, $page, $depth = 0, $args = array(), $id = 0) {
            if ( $depth )
                $indent = str_repeat("\t", $depth);
            else
                $indent = '';

            extract($args, EXTR_SKIP);

            $output .= $indent . '<li id="item_'.$page->ID.'"><span>'.apply_filters( 'the_title', $page->post_title, $page->ID ).'</span>';
        }


        function end_el(&$output, $page, $depth = 0, $args = array()) {
            $output .= "</li>\n";
        }

    }


class WP_P_R_O{
	    var $current_post_type = null;
	    function WP_P_R_O(){
		        add_action( 'admin_init', array(&$this, 'reg_scripts'), 11 );
                add_action( 'admin_init', array(&$this, 'check_pt'), 10 );
		        add_action( 'admin_menu', array(&$this, 'add_admin_menu') );
		        add_action( 'wp_ajax_update-wp-posts-re-order', array(&$this, 'save_order') );
	        }

	    function reg_scripts(){
		        if ( $this->current_post_type != null ){
                        wp_enqueue_script('jQuery');
                        wp_enqueue_script('jquery-ui-sortable');
		            }
                wp_enqueue_style( 'wppro-css',WPPRO_URL . '/wppro.css');
	        }

	    function check_pt(){
		        if ( isset($_GET['page']) && substr($_GET['page'], 0, 17) == 'wp-posts-re-order-' ){
			            $this->current_post_type = get_post_type_object(str_replace( 'wp-posts-re-order-', '', $_GET['page'] ));
			            if ( $this->current_post_type == null){
                                $invalid_pt=__('Invalid post type','wppro');
				                wp_die($invalid_pt);
			                }
		            }
	        }

	    function save_order(){
		        global $wpdb;

		        parse_str($_POST['order'], $data);

		        if (is_array($data))
                foreach($data as $key => $values ){
			            if ( $key == 'item' ){
			                foreach( $values as $position => $id ){
				                    $wpdb->update( $wpdb->posts, array('menu_order' => $position, 'post_parent' => 0), array('ID' => $id) );
			                    }
			            }else{
			                foreach( $values as $position => $id ){
				                    $wpdb->update( $wpdb->posts, array('menu_order' => $position, 'post_parent' => str_replace('item_', '', $key)), array('ID' => $id) );
			                }
		                }
	            }
	        }


	    function add_admin_menu(){
		        global $userdata;
                //put a menu for all custom_type
                $post_types = get_post_types();

                $options = get_option('wppro_options');
                //get the required user capability
                $capability = '';
                if(isset($options['capability']) && !empty($options['capability'])){
                        $capability = $options['capability'];
                }else if (is_numeric($options['level'])){
                        $capability = userdata_get_user_level();
                }else{
                            $capability = 'install_plugins';
                }

                foreach( $post_types as $post_type_name ){
                        if ($post_type_name == 'page'){
                            continue;
                        }

                        //ignore bbpress
                        if ($post_type_name == 'reply' || $post_type_name == 'topic'){
                            continue;
                        }

                        if ($post_type_name == 'post'){
                            $wppro_menu_text=__('Re-Order', 'wppro');
                            add_submenu_page('edit.php', $wppro_menu_text, $wppro_menu_text, $capability, 'wp-posts-re-order-'.$post_type_name, array(&$this, 'page_re_order') );
                        }else{
                                if (!is_post_type_hierarchical($post_type_name)){
                                    add_submenu_page('edit.php?post_type='.$post_type_name, __('Re-Order', 'wppro'), __('Re-Order', 'wppro'), $capability, 'wp-posts-re-order-'.$post_type_name, array(&$this, 'page_re_order') );
                                }
                            }
		            }
	        }


	    function page_re_order(){
		        ?>
		        <div class="wrap">

                    <h2><?php $post_type_name = $this->current_post_type->labels->singular_name;
                    if(!empty($post_type_name)) {
                      echo $this->current_post_type->labels->singular_name . ' -  ';
                    } _e('Re-Order', 'wppro');?></h2>

			        <div id="ajax-response"></div>
			        <noscript>
				        <div class="error message">
					        <p><?php _e('This plugin can\'t work without javascript, because it\'s use drag and drop and AJAX.', 'wppro') ?></p>
				        </div>
			        </noscript>

			        <div id="wp-posts-re-order">
				        <ul id="sortable">
					        <?php $this->list_pages('hide_empty=0&title_li=&post_type='.$this->current_post_type->name); ?>
				        </ul>
				        <div class="clear"></div>
			        </div>

			        <p class="submit">
				        <a href="#" id="save-order" class="button-primary"><?php _e('Save Changes' ) ?></a>
			        </p>

			        <script type="text/javascript">
				        jQuery(document).ready(function() {
					        jQuery("#sortable").sortable({
						        'tolerance':'intersect',
						        'cursor':'pointer',
						        'items':'li',
						        'placeholder':'placeholder',
						        'nested': 'ul'
					        });

					        jQuery("#sortable").disableSelection();
					        jQuery("#save-order").bind( "click", function() {
						        jQuery.post( ajaxurl, { action:'update-wp-posts-re-order', order:jQuery("#sortable").sortable("serialize") }, function() {
							        jQuery("#ajax-response").html('<div class="message updated fade"><p><?php _e('Items Order Updated', 'wppro') ?></p></div>');
							        jQuery("#ajax-response div").delay(3000).hide("slow");
						        });
					        });
				        });
			        </script>

		        </div>
		        <?php
	        }

	    function list_pages($args = ''){
	        $defaults = array(
		        'depth' => 0, 'show_date' => '',
		        'date_format' => get_option('date_format'),
		        'child_of' => 0, 'exclude' => '',
		        'title_li' => __('Pages','wppro'), 'echo' => 1,
		        'authors' => '', 'sort_column' => 'menu_order',
		        'link_before' => '', 'link_after' => '', 'walker' => ''
	        );

	        $r = wp_parse_args( $args, $defaults );
	        extract( $r, EXTR_SKIP );

	        $output = '';

	        $r['exclude'] = preg_replace('/[^0-9,]/', '', $r['exclude']);
	        $exclude_array = ( $r['exclude'] ) ? explode(',', $r['exclude']) : array();
	        $r['exclude'] = implode( ',', apply_filters('wp_list_pages_excludes', $exclude_array) );

	        // Query pages.
	        $r['hierarchical'] = 0;
            $args = array(
                        'sort_column'   =>  'menu_order',
                        'post_type'     =>  $post_type,
                        'posts_per_page' => -1,
                        'orderby'        => 'menu_order',
                        'order'         => 'ASC'
            );

            $the_query = new WP_Query($args);
            $pages = $the_query->posts;

	        if ( !empty($pages) ) {
		        if ( $r['title_li'] ){
			        $output .= '<li class="pagenav intersect">' . $r['title_li'] . '<ul>';
                }

		        $output .= $this->walkTree($pages, $r['depth'], $r);

		        if ( $r['title_li'] ){
			        $output .= '</ul></li>';
                }
	        }

	        $output = apply_filters('wp_list_pages', $output, $r);

	        if ( $r['echo'] ){
		        echo $output;
            }else{
		        return $output;
            }
        }

	    function walkTree($pages, $depth, $r){
	        if ( empty($r['walker']) ){
		        $walker = new WP_P_R_O_Walker;
            }else{
		        $walker = $r['walker'];
            }

	        $args = array($pages, $depth, $r);
	        return call_user_func_array(array(&$walker, 'walk'), $args);
        }
    }


/*-------------Options page----------------*/
/**
* Return  user levels
*
* This is deprecated, will be removed in the next versions
*
* @param mixed $return_as_numeric
*/
function userdata_get_user_level($return_as_numeric = FALSE){
    global $userdata;
    $user_level = '';
    for ($i=10; $i >= 0;$i--){
            if (current_user_can('level_' . $i) === TRUE){
                    $user_level = $i;
                    if ($return_as_numeric === FALSE)
                        $user_level = 'level_'.$i;
                    break;
                }
        }
    return ($user_level);
}

function cpt_info_box() {?>
    <div id="cpt_info_box">

        <div class="clear"></div>
    </div><?php
}


function cpt_plugin_options(){
        $options = get_option('wppro_options');

        if (isset($_POST['form_submit'])){
                $options['capability'] = $_POST['capability'];
                $options['autosort']    = isset($_POST['autosort'])     ? $_POST['autosort']    : '';
                $options['adminsort']   = isset($_POST['adminsort'])    ? $_POST['adminsort']   : '';
                echo '<div class="updated fade"><p>' . __('Settings Saved', 'wppro') . '</p></div>';
                update_option('wppro_options', $options);
                update_option('WPPRO__configured', 'TRUE');
            } ?>
                      <div class="wrap wp-re-order-options">
                             <h2 class="subtitle"><?php _e('General Settings', 'wppro') ?></h2>
                            <form id="form_data" name="form" method="post">
                                <table class="form-table">
                                    <tbody>

                                        <tr valign="top">
                                            <th scope="row" style="text-align: right;"><label><?php _e('Minimum Level to use this plugin', 'wppro') ?></label></th>
                                            <td>
                                                <select id="role" name="capability">
                                                    <option value="read" <?php if (isset($options['capability']) && $options['capability'] == "read") echo 'selected="selected"'?>><?php _e('Subscriber', 'wppro') ?></option>
                                                    <option value="edit_posts" <?php if (isset($options['capability']) && $options['capability'] == "edit_posts") echo 'selected="selected"'?>><?php _e('Contributor', 'wppro') ?></option>
                                                    <option value="publish_posts" <?php if (isset($options['capability']) && $options['capability'] == "publish_posts") echo 'selected="selected"'?>><?php _e('Author', 'wppro') ?></option>
                                                    <option value="publish_pages" <?php if (isset($options['capability']) && $options['capability'] == "publish_pages") echo 'selected="selected"'?>><?php _e('Editor', 'wppro') ?></option>
                                                    <option value="switch_themes" <?php if (!isset($options['capability']) || empty($options['capability']) || (isset($options['capability']) && $options['capability'] == "switch_themes")) echo 'selected="selected"'?>><?php _e('Administrator', 'wppro') ?></option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr valign="top">
                                            <th scope="row" style="text-align: right;"><label><?php _e('Auto Sort', 'wppro') ?></label></th>
                                            <td>
                                                <label for="users_can_register">
                                                <input type="checkbox" <?php if ($options['autosort'] == "1") {echo ' checked="checked"';} ?> value="1" name="autosort">
                                                <?php _e("If checked, this plugin will automatically update the wp-queries to use the new order.<br /> If you need more order customizations, you can uncheck this and include 'menu_order' into your  queries", 'wppro') ?>.</label>
                                                <p><a href="javascript:;" class='wp-re-order button-primary' onclick="jQuery('#an-example').slideToggle();return false;"><?php _e('Examples', 'wppro') ?></a></p>
                                                <div id="an-example" style="display: none">

                                                <p class="example"><br /><?php _e('The following PHP code will still return the post in the set-up Order', 'wppro') ?>:</p>
<pre class="example">
    $args = array(
                  'post_type' => 'feature'
                );

    $my_query = new WP_Query($args);
    while ($my_query->have_posts())
        {
            $my_query->the_post();
            (..your code..)
        }
</pre>
                                                <p class="example"><br /><?php _e('Or', 'wppro') ?>:</p>
<pre class="example">
    $posts = get_posts($args);
    foreach ($posts as $post)
    {
        (..your code..)
    }
</pre>

                                                <p class="example"><br /><?php _e('If the Auto Sort is uncheck you will need to use the "orderby" and "order" parameters', 'wppro') ?>:</p>
<pre class="example">
    $args = array(
                  'post_type' => 'feature',
                  'orderby'   => 'menu_order',
                  'order'     => 'ASC'
                );
</pre>

                                                </div>
                                            </td>
                                        </tr>

                                        <tr valign="top">
                                            <th scope="row" style="text-align: right;"><label><?php _e('Sort Backend post list?', 'wppro') ?></label></th>
                                            <td>
                                                <label for="users_can_register">
                                                <input type="checkbox" <?php if ($options['adminsort'] == "1") {echo ' checked="checked"';} ?> value="1" name="adminsort">
                                                <?php _e("To affect the backend post list, this need to be checked", 'wppro') ?>.</label>
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>

                                <p class="submit">
                                    <input type="submit" name="Submit" class="button-primary" value="<?php
                                    _e('Save Changes') ?>">
                               </p>

                                <input type="hidden" name="form_submit" value="true" />
                            </form>

                    <br />

                    <?php
            echo '</div>';
}