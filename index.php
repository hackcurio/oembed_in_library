<?php
/*
Plugin Name: OEmbed in Library
Description: Easily add external files in library using OEmbed API.
Version: 1.0.2
Author URI: http://bazalt.fr/
License: GPL2
Text Domain: oembed-in-library
Domain Path: /languages
*/

/*  Copyright 2013 Cyril Batillat (email : contact@bazalt.fr)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Modified by Joel Auerbach 2018 */

if ( ! class_exists('OEmbedInLibrary' ) ) :

require_once ABSPATH . WPINC . '/class-oembed.php';
class OEmbedInLibrary {

    private static $_instance;

    /**
     * Singlton pattern
     * @return OEmbedInLibrary
     */
    public static function getInstance () {
        if ( self::$_instance instanceof self) return self::$_instance;

        spl_autoload_register(function ($class) {
            if(file_exists(dirname(__FILE__) . '/lib/' . $class . '.php'))
                include dirname(__FILE__) . '/lib/' . $class . '.php';
        });

        self::$_instance = new self();
        return self::$_instance;
    }

    /**
     * Avoid creation of an instance from outside
     */
    private function __clone () {}

    /**
     * Private constructor (part of singleton pattern)
     * Declare WordPress Hooks
     */
    private function __construct() {
        // Load a custom text domain
        add_action( 'plugins_loaded', array($this, 'plugins_loaded') );


        add_action( 'admin_enqueue_scripts', array($this, 'admin_scripts' ) );
        add_action('wp_ajax_oembed_in_library_preview', array( $this, 'ajax_preview') );

        add_action( 'admin_init', array( $this, 'init_session' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'edit_form_after_title', array( $this, 'edit_form_after_title' ) );
    }
    public function OEmbedInLibrary(){}

    /**
     * Load plugin text domain
     */
    public function plugins_loaded() {
        load_plugin_textdomain( 'oembed-in-library', false, basename( dirname(__FILE__) ) . '/languages/' );
    }

    public function init_session() {
        if(!session_id())  session_start();
        if(empty($_SESSION['oembed_in_library'])) $this->empty_session();

        if(!empty($_SESSION['oembed_in_library']))
            add_action( 'admin_notices', array( $this, 'admin_notice') );
    }

    /**
     * Reset plugin variables in session
     */
    public function empty_session() {
        $_SESSION['oembed_in_library'] = array(
            'errors' => array(),
            'notices' => array(),
        );
    }

    /**
     * Add some JS & CSS in admin
     * @param $hook
     */
    public function admin_scripts($hook) {
        if($hook != 'media_page_oembedinlibrary') return;
        wp_enqueue_script(
            'oembed_js',
            plugins_url( '/js/oembed_in_library.js', __FILE__ ),
            array('jquery'),
            '1.0'
        );
        wp_localize_script(
            'oembed_js', 'ajax_object',
            array( 'ajax_url' => admin_url( 'admin-ajax.php' ) )
        );

        wp_enqueue_style(
            'oembed_css',
            plugins_url( '/css/oembed_in_library.css', __FILE__ ),
            array(),
            '1.0'
        );
    }

    /**
     * Action ajax returnin a preview of a media, based on its URL
     */
    public function ajax_preview() {
        try {
            echo $this->__getpreview($_POST['media_url']);
        } catch(Exception $e) {
            echo $e->getMessage();
        }
        die();
    }

    /**
     * Return a preview code
     * @param $url
     * @return mixed|string
     */
    private function __getpreview($url) {
        $oembed = new WP_oEmbed();
        return $oembed->get_html($url, array(
            'width' => 400,
            'height' => 300
        ));
    }

    /**
     * Acces via Medias sub-menu
     */
    public function admin_menu() {
        add_submenu_page("upload.php", __('Embed in Library', 'oembed-in-library'), __('Embed from URL', 'oembed-in-library'), 'upload_files', 'oembedinlibrary', array($this, 'page_embed'));
        add_action( 'admin_action_oembed_add_in_library', array($this, 'add_in_library') );
        add_filter( 'icon_dirs', array( $this, 'add_icons_dir') );
    }

    /**
     * Plugin Page
     */
    public function page_embed() { ?>
        <div class="wrap">
            <div id="icon-upload" class="icon32"><br></div>	<h2><?php _e('Embed in Library', 'oembed-in-library'); ?></h2>

            <p><?php _e('Use this form to add external media to your library.', 'oembed-in-library'); ?></p>

            <form action="<?php echo admin_url( 'admin.php' ); ?>" method="post">
                <p>
                    <label for="oembed_url"><?php _e('URL', 'oembed-in-library') ?></label>
                    <input type="text" name="oembed_url" id="oembed_url"/>
                    <input type="hidden" name="action" value="oembed_add_in_library" />
                    <input type="button" value="<?php _e('Preview', 'oembed-in-library') ?>" class="button hide-if-no-js" id="oembed_in_library_btn_preview"/>
                    <input type="submit" value="<?php _e('Add in library', 'oembed-in-library') ?>" class="button button-primary"/>
                </p>
            </form>

            <div id="oembed_in_library_preview" class="hide-if-no-js"></div>
        </div>
        <?php
    }

    /**
     * Admin notices
     */
    public function admin_notice() {
        foreach((array) $_SESSION['oembed_in_library']['errors'] as $msg) : ?>
            <div class="error">
                <p><?php echo $msg; ?></p>
            </div>
            <?php
        endforeach;

        foreach((array) $_SESSION['oembed_in_library']['notices'] as $msg) : ?>
            <div class="updated">
                <p><?php echo $msg; ?></p>
            </div>
            <?php
        endforeach;

        $this->empty_session();
    }

    /**
     * Register an external media in library using oEmbed
     */
    public function add_in_library() {

        $url = $_POST['oembed_url'];
        $oembed = new WP_oEmbed();
        $provider = $oembed->discover($url);
        if($provider === false && substr($url, 0, 5) === 'https') {
            $url = str_replace('https', 'http', $url);
            $provider = $oembed->discover($url);
        }
        if($provider === false) {
            $_SESSION['oembed_in_library']['errors'][] = __('No provider found for this URL.', 'oembed-in-library') . ' ' . $url . ' ' . implode(',',array_keys(get_object_vars($oembed))) . '</a>';
            wp_safe_redirect( admin_url('upload.php?page=oembedinlibrary') );
        }

        $response = $oembed->fetch($provider, $url);
        if( $response === false) {
            $_SESSION['oembed_in_library']['errors'][] = __('Bad response from the provider for this URL.', 'oembed-in-library') . '</a>';
            wp_safe_redirect( admin_url('upload.php?page=oembedinlibrary') );
        }

        $my_post = array(
            'post_title'    => '[embed]' . $url . '[/embed]',
            'post_status'   => 'inherit',
            'post_author'   => get_current_user_id(),
            'post_type'     => 'attachment',
            'guid'          => $_POST['oembed_url'],
            'post_mime_type'=> 'oembed/' . $response->provider_name,
            'meta_input' => array('thumbnail_url' => $this->set_thumbnail($url))
        );
        $post_id = wp_insert_post( $my_post );
        if( ! is_int($post_id) ) {
            $_SESSION['oembed_in_library']['errors'][] = __('An error occured during media saving.', 'oembed-in-library');
        }

        $_SESSION['oembed_in_library']['notices'][] = __('Media inserted in library with success.', 'oembed-in-library') . ' <a href="' . get_edit_post_link( $post_id ) . '" title="">' . __('View', 'oembed-in-library') . '</a>';
        wp_safe_redirect( admin_url('upload.php?page=oembedinlibrary') );
        exit();
    }

    public function set_thumbnail( $url) {
        require_once( get_template_directory() . '/lib/vimeo/autoload.php' );

        if (strpos($url, 'vimeo') !== false) {

            $vimeo = new \Vimeo\Vimeo('VIMEO_CLIENT_ID', 'VIMEO_CLIENT_SECRET');

            $vimeo->setToken(VIMEO_ACCESS_TOKEN);

            $id_count = substr_count(end(explode('vimeo.com', $url)), '/');

            if ($id_count == 2) {
                $video_id = implode(':', array_slice(explode('/', $url), -2, 2));
            }
            elseif ($id_count == 1) {
                $video_id = end(explode('/', $url));
            };

            $response = $vimeo->request('/videos/' . $video_id);
            $thumbnail = $response['body']['pictures']['sizes'][1]['link'];

        }
        elseif (strpos($url, 'youtu') !== false) {
            $thumbnail = 'https://img.youtube.com/vi/' . end(explode('/', $url)) . '/0.jpg';
        }

        return $thumbnail;
    }

    /**
     * Add a folder for icons
     * Does not work in WP <3.6
     * @param $dirs
     * @return mixed
     */
    public function add_icons_dir($dirs) {
        $dirs[plugin_dir_path(__FILE__)] = untrailingslashit(plugin_dir_url(__FILE__));
        return $dirs;
    }

    /**
     * Embed attachment on the edit form
     * @param $post
     */
    public function edit_form_after_title($post) {
        if($post->post_type != 'attachment') return;
        if(substr(strtolower($post->post_mime_type), 0, 7) !== 'oembed/') return;

        echo $this->__getpreview($post->guid);
    }
}
OEmbedInLibrary::getInstance();
endif;