<?php
/*
  Plugin Name: Advanced Tag Rule
  Plugin URI: http://bigcodeart.in.ua/
  Description: Add the advanced rules for your tags
  Version: 1.1.1
  Author: Anton Shulga
  Author URI: http://bigcodeart.in.ua/
  License: GPLv2 or later
  Text Domain: atr
 */
/*
    Copyright 2015  Anton Shulga  (email: shandm85@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('ATR_Plugin')) {

    register_uninstall_hook( __FILE__, array( 'ATR_Plugin', 'uninstall' ) );

    class ATR_Plugin {

        /**
         * @var string
         * Name plugin
         */
        static $plugin_name;

        /**
         * @var string
         * Path to plugin
         */
        static $plugin_path;

        /**
         * @var string
         * Holds the absolute path of the main plugin file directory
         */
        static $plugin_url;

        /**
         * @var string
         * Prefix which it use in functions
         */
        static $prefix;

        /**
         * @var string
         * The current version of the plugin
         */
        static $version;

        public function __construct() {
            /* Set the constants needed by the plugin. */
            self::$plugin_name = plugin_basename(__FILE__); // dir/file
            self::$plugin_path = dirname(__FILE__);   // path to dir
            self::$plugin_url = trailingslashit(WP_PLUGIN_URL . '/' . dirname(self::$plugin_name)); //url to dir
            self::$prefix = 'atr';
            self::$version = '1.1.1';
        }

        public function load() {
            /* Register plugin activation hook. */
            register_activation_hook(self::$plugin_name, array(&$this, 'activate'));
            /* Register plugin activation hook. */
            register_deactivation_hook(self::$plugin_name, array(&$this, 'deactivate'));

            if ( !is_admin() ) {
                add_action('init', array( &$this, 'verify_tag_rules') );
                add_filter( 'term_links-post_tag', array (&$this, 'atr_replace_link')  );
            }

            /* "Tag Rules" - page */
            add_action('admin_menu', array(&$this, 'register_atr_page'));
            
            add_action ( 'admin_enqueue_scripts', array (&$this, 'register_admin_assets' ) );

            /* Internationalize the text strings used. */
            add_action('plugins_loaded', array(&$this, 'i18n'));  
        }

        function register_atr_page() {
            add_submenu_page('options-general.php', __('Advansed Tag Rules', self::$prefix), __('Tag Rules', self::$prefix), 'manage_options', 'atr-page', array($this, 'atr_template_output'));
        }

        /**
         * Load the translation of the plugin.
         */
        public function i18n() {
            /* Load the translation of the plugin. */
            load_plugin_textdomain( self::$prefix, false, basename(self::$plugin_path) . '/lang' );
        }
        
        // change the tag-link for theme
        static function change_tag_link( $tag_name = '' ){
            if( $tag_name == '' ) return false;
            
            $array_atr_rules = get_option( 'atr_option' ) ? get_option( 'atr_option' ) : array();
            if( empty($array_atr_rules) ) return false;
            
            
            $new_tag_link = '';
            $new_tags_object = get_term_by('slug', sanitize_title($tag_name), 'post_tag' );
            
            foreach( $array_atr_rules as $key_rule => $rule ){
                if( $new_tags_object->term_id ==  $rule['atr_tag'][0] ){
                    $new_tag_link = get_permalink( intval($rule['atr_redirect'][0]) );
                    break;
                }
            }
            return $new_tag_link;            
        }
        
        #value 'type_rules' can be 'string' or 'array'
        function get_saving_tag_rules( $tag_rules = array(), $type_rules = 'string' ){
            
            if( !is_array($tag_rules) && empty($tag_rules) ) return false;
            
            $rules = '';
            $tag_ids = array();
           
            foreach ($tag_rules as $key => $value) {    
                $tag_ids[] = $value['atr_tag'][0];
            }

            $rules = ($type_rules != 'string') ? $tag_ids : implode(',', $tag_ids);
            return $rules;
        }

        #get attribute to redirect (page_id OR post_id)
        function get_atr_redirect_by_tag_id( $tag_id ){
               $array_atr_rules = get_option( 'atr_option' ) ? get_option( 'atr_option' ) : array();
               if( empty($array_atr_rules) ) return false;

               foreach( $array_atr_rules as $key_rule => $rule ){
                    if( $rule['atr_tag'][0] == $tag_id )   return $rule['atr_redirect'][0];
               }
               return false;
        }

        function atr_template_output() {
            global $title;

            $notice = $rules = '';

            printf('<h2>%s</h2>', $title);

            $array_tags = get_tags() ? : array();
  
            if( empty($array_tags) ){
                printf('<h4>%s</h4>', __('Tags not found.', 'atr'));
                return;
            }
            $args = array( 'posts_per_page' => -1,  'order'=> 'ASC', 'orderby' => 'title' );
            $postslist = new WP_Query( $args );

            if( $array_tags && !empty($postslist) ){
                // ADD
                if( !empty( $_POST['is_atr_submit'] ) ){

                    $array_atr_rules = get_option( 'atr_option' ) ? get_option( 'atr_option' ) : array();
                    $array_atr_rules[] = array(
                        'atr_tag' => $_POST['atr_tag'],
                        'atr_redirect' => $_POST['atr_redirect']
                    );

                    $notice = ( update_option('atr_option', $array_atr_rules ) != false ) ? '<span style="color:green;">'. __('Saved successfully', 'atr').'</span>' : '';
                }
                // REMOVE
                if( !empty( $_POST['is_atr_remove'] ) ){

                    if( $_POST['atr_remove_rule'] == 'atr_all_tags' ){
                        $notice = ( delete_option('atr_option') != false ) ? '<span style="color:green;">'. __('Rules removed successfully.', 'atr'). '</span>' : '<span style="color:red;">'. __('Rule are not removed.', 'atr') .'</span>';
                    }else{
                        $array_atr_rules = get_option( 'atr_option' ) ? get_option( 'atr_option' ) : array();

                        foreach ($array_atr_rules as $key => $rule) {
                            if( $rule['atr_tag'][0] == $_POST['atr_remove_rule'] ){
                                unset($array_atr_rules[$key]);
                                break;
                            }
                        }
                        $notice = ( update_option('atr_option', $array_atr_rules ) != false ) ? '<span style="color:green;">'. __('Current rule removed successfully.', 'atr') .'</span>' : '';
                    }

                }

                $array_atr_rules = get_option( 'atr_option' ) ? get_option( 'atr_option' ) : array();

                $rules = $this->get_saving_tag_rules( $array_atr_rules );

                $args_tag_hide = $args_tag_show = array();
                $not_all_tags = $only_tags = array();

                if( $rules  != false ){
                    $args_tag_hide['exclude'] = $rules;
                    $args_tag_show['include'] = $rules;
                }

                $not_all_tags = get_tags( $args_tag_hide );
                $only_tags = get_tags( $args_tag_show );
                ?>                    
                    <form method="post" action="" name="tag_rule">
                        <table>
                            <?php
                            if( !empty($array_atr_rules) ){ ?>
                                <tr>
                                    <th><?php _e('Tag', 'atr'); ?></th>
                                    <th><?php _e('Post', 'atr'); ?></th>
                                </tr>
                            <?php
                            }
                            foreach( $array_atr_rules as $key_rule => $rule ){
                                ?>
                            <tr>
                                <td>
                                    <select name="atr_tag[]" class="atr_list_tags">
                                      <?php
                                      foreach ($array_tags as $tag) { ?>
                                        <option value="<?php echo $tag->term_id; ?>" <?php selected($rule['atr_tag'][0], $tag->term_id); ?>><?php echo $tag->name; ?></option>
                                      <?php }
                                      ?>  
                                    </select>
                                </td>
                                <td>
                                    <select name="atr_redirect[]" class="atr_redirect">
                                      <?php
                                      foreach ($postslist->posts as $key => $post) {?>
                                        <option value="<?php echo $post->ID; ?>" <?php selected($rule['atr_redirect'][0], $post->ID); ?>><?php echo $post->post_title; ?></option>
                                      <?php }
                                      ?>
                                    </select>
                                </td>                                
                            </tr>
                            
                            <?php    
                            }
                            ?>
                            
                            
                            <tr>
                                <td>
                                    <?php
                                    if(!empty($array_atr_rules)){ ?>
                                        <input type="hidden" name="is_atr_submit" value="1"/>
                                        <input type="submit" name="atr_submit" value="<?php _e('Save', 'atr'); ?>" class="button-primary"/>
                                    <?php
                                    }
                                    ?>
                                    
                                </td>
                                <td>
                                    <?php
                                    if( !empty($not_all_tags) ){
                                        ?>
                                        <button type="button" id="attr_add_new_rule"><?php _e('Add rule', 'atr'); ?></button>
                                    <?php    
                                    }
                                    ?>
                                    
                                </td>
                            </tr>
                            
                            
                        </table>
                        <div class="atr_notice"><?php echo $notice; ?></div>
                    </form>
                
                <form method="post" action="" name="tag_hidden_rule" class="tag_hidden_rule" style="display: none;">
                    <table>                        
                            <tr>
                                <td>
                                    <select name="atr_tag[]" class="atr_list_tags">
                                      <?php
                                      foreach ($not_all_tags as $tag) { ?>
                                        <option value="<?php echo $tag->term_id; ?>"><?php echo $tag->name; ?></option>
                                      <?php }
                                      ?>  
                                    </select>
                                </td>
                                <td>
                                    <select name="atr_redirect[]" class="atr_redirect">
                                      <?php
                                      foreach ($postslist->posts as $key => $post) {?>
                                        <option value="<?php echo $post->ID; ?>"><?php echo $post->post_title; ?></option>
                                      <?php }
                                      ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="hidden" name="is_atr_submit" value="1"/>
                                    <input type="submit" name="atr_submit" value="<?php _e('Save', 'atr'); ?>" class="button-primary"/>
                                </td>
                            </tr>
                    </table>
                </form>
                <?php
                if( !empty($array_atr_rules) ){
                ?>
                    <div>
                        <form method="post" action="" name="tag_rule">
                            <table>
                                <tr>
                                    <td>
                                        <label for="atr_remove_rule"><?php _e('Remove rule', 'atr'); ?>:</label>
                                        <select name="atr_remove_rule" class="atr_list_tags">
                                            <?php
                                            foreach ($only_tags as $tag) { ?>
                                                <option value="<?php echo $tag->term_id; ?>"><?php echo $tag->name; ?></option>
                                            <?php }
                                            ?>
                                                <option value="atr_all_tags"><?php _e('All Tags', 'atr'); ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="hidden" name="is_atr_remove" value="1"/>
                                        <input type="submit" name="atr_remove" value="<?php _e('Remove', 'atr'); ?>"  class="button-primary"/>
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                <?php
                }
            }
        }

        function verify_tag_rules(){
                $array_atr_rules = get_option( 'atr_option' ) ? get_option( 'atr_option' ) : array();
                if( !empty($array_atr_rules) ){
                        foreach ($array_atr_rules as $key => $value) {
                                $post_link = get_permalink( intval($value['atr_redirect'][0]) );
                                $curr_tag = get_term_by('id', intval($value['atr_tag'][0]), 'post_tag' );

                                if( strpos( $_SERVER['REQUEST_URI'], '/tag/' . $curr_tag->slug) !== false || strpos( $_SERVER['REQUEST_URI'], '/tag/' . $curr_tag->name) !== false){
                                    wp_redirect( $post_link );
                                    die();
                                }
                        }
                }
        }
        #showing the converted post tags
        function atr_replace_link( $tags ){
            $array_atr_rules = get_option( 'atr_option' ) ? get_option( 'atr_option' ) : array();

            if( empty( $array_atr_rules ) ) return $tags;
            $custom_tag_ids = $this->get_saving_tag_rules( $array_atr_rules, $type_rules = 'array' );

            $new_tags = $custom_tags = array();

            if( !empty( $tags ) ){
                    foreach ( $tags as $tag ) {
                        preg_match_all( '/href\=\"(.*)\"/U', $tag, $matches );

                        $new_tags = explode( '/tag/', $matches[1][0] );
                        $new_tags = substr( $new_tags[1], 0, -1 );

                        $new_tags_object = get_term_by( 'slug', $new_tags, 'post_tag' );
                        if( in_array( $new_tags_object->term_id, $custom_tag_ids ) ){
                                $atr_redirect = $this->get_atr_redirect_by_tag_id( $new_tags_object->term_id );
                                if( $atr_redirect )     $custom_tags[] = '<a rel="tag" href="'. get_permalink( $atr_redirect ) .'">'. $new_tags_object->name .'</a>';
                        }else{
                                $custom_tags[] = $tag;
                        }
                    }
                    return $custom_tags;
            }

            return $tags;
        }

        function register_admin_assets(){
            // load script
            wp_register_script('atrAdminJs', self::$plugin_url . 'assets/js/admin.js', array( 'jquery' ), self::$version );
            wp_enqueue_script('atrAdminJs');
            // load style
            wp_register_style ( 'atrAdminCss', self::$plugin_url  . 'assets/css/admin.css', array(), self::$version );
            wp_enqueue_style ( 'atrAdminCss' );
        }

        /**
         * Do things on plugin activation.
         */
        function activate() {
            return true;
        }

        /**
         * Flush permalinks on plugin deactivation.
         */
        function deactivate() {
            flush_rewrite_rules();
        }

        /**
         * Do things on plugin uninstall.
         */
        function uninstall() {
            if ( ! current_user_can( 'activate_plugins' ) )     return;
            check_admin_referer( 'bulk-plugins' );

            if ( __FILE__ != WP_UNINSTALL_PLUGIN )  	return;
        }


    }

    $atr_plugin = new ATR_Plugin();
    $atr_plugin->load();
}


?>
