<?php
defined('ABSPATH') || die;

/*
Plugin Name: WPU Custom RSS
Plugin URI: https://github.com/WordPressUtilities/wpucustomrss
Update URI: https://github.com/WordPressUtilities/wpucustomrss
Version: 0.9.1
Description: Create a second custom RSS feed
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpucustomrss
Domain Path: /lang
Requires at least: 6.2
Requires PHP: 8.0
Network: Optional
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class WPUCustomRSS {
    public $options;
    public $plugin_description;
    public $settings_update;
    public $settings_details;
    public $settings;

    public $route = 'wpucustomrss';
    public $values;
    public $plugin_id = 'wpucustomrss';
    public $plugin_version = '0.9.1';
    private $option_id = 'wpucustomrss_options';
    private $plugin_basename;

    public function __construct() {
        $this->update_values();
        $this->plugin_basename = plugin_basename(__FILE__);
        $this->options = array(
            'plugin_publicname' => 'WPU Custom RSS',
            'plugin_name' => 'WPU Custom RSS',
            'plugin_shortname' => 'Custom RSS',
            'plugin_userlevel' => 'manage_options',
            'plugin_id' => $this->plugin_id,
            'plugin_pageslug' => $this->plugin_id,
            'admin_parent' => 'tools.php',
            'admin_url' => admin_url('tools.php?page=' . $this->plugin_id)
        );

        //  Action hooks
        add_action('plugins_loaded', array(&$this,
            'plugins_loaded'
        ));
        add_action('init', array(&$this,
            'init'
        ));
        add_action('updated_option', array(&$this,
            'after_option_update'
        ));

        // Init RSS
        add_action('wp', array(&$this, 'launch_rss'));

        // Add new route
        add_action('init', array(&$this, 'add_custom_rules'));
        add_filter('query_vars', array(&$this, 'add_query_vars'));

    }

    public function update_values() {
        $this->values = get_option($this->option_id);
        if (isset($this->values['feed_route']) && !empty($this->values['feed_route'])) {
            $this->route = $this->values['feed_route'];
        }
    }

    public function plugins_loaded() {

        $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
        if (!load_plugin_textdomain('wpucustomrss', false, $lang_dir)) {
            load_muplugin_textdomain('wpucustomrss', $lang_dir);
        }
        $this->plugin_description = __('Create a second custom RSS feed', 'wpucustomrss');

        add_action('wpubasesettings_before_content_settings_page_' . $this->options['plugin_id'], array(&$this,
            'admin_page_content'
        ));

        // Feed visibility
        if (isset($this->values['declare_feed']) && ($this->values['declare_feed'] == '1')) {
            add_action('wp_head', array(&$this, 'declare_feed'));
        }
        if (isset($this->values['hide_native_feed']) && ($this->values['hide_native_feed'] == '1')) {
            remove_action('wp_head', 'feed_links', 2);
        }

    }

    public function init() {

        /* Update */
        require_once __DIR__ . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
        $this->settings_update = new \wpucustomrss\WPUBaseUpdate(
            'WordPressUtilities',
            'wpucustomrss',
            $this->plugin_version);

        /* Settings */
        $this->settings_details = array(
            'create_page' => true,
            'plugin_name' => $this->options['plugin_shortname'],
            'plugin_basename' => $this->plugin_basename,
            'plugin_id' => 'wpucustomrss',
            'option_id' => $this->option_id,
            'sections' => array(
                'main' => array(
                    'name' => __('Feed Settings', 'wpucustomrss')
                ),
                'content' => array(
                    'name' => __('Content Settings', 'wpucustomrss')
                ),
                'future' => array(
                    'name' => __('Future Posts', 'wpucustomrss')
                )
            )
        );

        $this->settings = array(
            'posts_per_page' => array(
                'section' => 'main',
                'type' => 'number',
                'label' => __('Number of posts', 'wpucustomrss')
            ),
            'feed_format' => array(
                'section' => 'main',
                'type' => 'select',
                'label' => __('Feed format', 'wpucustomrss'),
                'datas' => array(
                    'rss2' => 'RSS2',
                    'rss' => 'RSS',
                    'atom' => 'Atom',
                    'rdf' => 'RDF'
                )
            ),
            'feed_route' => array(
                'section' => 'main',
                'type' => 'text',
                'default' => 'wpucustomrss',
                'regex' => '/^([a-z0-9]+){6,18}/',
                'label' => __('Custom URL', 'wpucustomrss'),
                'help' => __('Public URL for this feed. Default is wpucustomrss. 6 to 18 alphanumeric chars only.', 'wpucustomrss')
            ),
            'declare_feed' => array(
                'section' => 'main',
                'type' => 'checkbox',
                'label' => __('Declare feed', 'wpucustomrss'),
                'label_check' => __('Declare feed link in site source', 'wpucustomrss')
            ),
            'hide_native_feed' => array(
                'section' => 'main',
                'type' => 'checkbox',
                'label' => __('Hide native feed', 'wpucustomrss'),
                'label_check' => __('Hide native feed link in site source', 'wpucustomrss')
            ),
            'load_post_format' => array(
                'section' => 'content',
                'type' => 'checkbox',
                'label' => __('Display post format', 'wpucustomrss'),
                'label_check' => __('Post format is visible in the RSS feed', 'wpucustomrss')
            ),
            'load_featured_image' => array(
                'section' => 'content',
                'type' => 'checkbox',
                'label' => __('Load featured image', 'wpucustomrss'),
                'label_check' => __('The featured image is loaded as an enclosure', 'wpucustomrss')
            ),
            'content_before_feed' => array(
                'section' => 'content',
                'type' => 'editor',
                'label' => __('Additional item content - before', 'wpucustomrss')
            ),
            'content_after_feed' => array(
                'section' => 'content',
                'type' => 'editor',
                'label' => __('Additional item content - after', 'wpucustomrss')
            ),
            'load_future_posts' => array(
                'section' => 'future',
                'type' => 'checkbox',
                'label_check' => __('Load future posts into the RSS Feed', 'wpucustomrss'),
                'label' => __('Load future posts', 'wpucustomrss')
            ),
            'future_posts_hours_limit' => array(
                'section' => 'future',
                'type' => 'number',
                'label' => __('Limit of hours', 'wpucustomrss'),
                'help' => __('Load future posts for the next # hours. 0 load every post.', 'wpucustomrss')
            )
        );

        require_once __DIR__ . '/inc/WPUBaseSettings/WPUBaseSettings.php';
        new \wpucustomrss\WPUBaseSettings($this->settings_details, $this->settings);

    }

    /* ----------------------------------------------------------
      Admin page
    ---------------------------------------------------------- */

    public function admin_page_content() {
        echo '<p><a target="_blank" href="' . get_site_url(null, $this->route) . '"><span style="text-decoration:none;padding-right:0.2em" class="dashicons dashicons-rss"></span>' . __('Preview RSS Feed', 'wpucustomrss') . '</a></p>';
    }

    /* ----------------------------------------------------------
      RSS Feed
    ---------------------------------------------------------- */

    public function launch_rss() {
        if (is_admin() || !get_query_var($this->plugin_id)) {
            return;
        }
        /* After item */
        add_action('rdf_item', array(&$this, 'after_item'));
        add_action('atom_entry', array(&$this, 'after_item'));
        add_action('rss_item', array(&$this, 'after_item'));
        add_action('rss2_item', array(&$this, 'after_item'));
        /* Before / After content */
        add_filter('the_content_feed', array(&$this, 'content_before_feed'));
        add_filter('the_content_feed', array(&$this, 'content_after_feed'));
        $this->do_rss();
    }

    public function after_item() {
        do_action('wpucustomrss_after_item_start');
        echo $this->load_post_format();
        echo $this->load_featured_image();
        do_action('wpucustomrss_after_item_end');
    }

    public function load_post_format() {
        if (isset($this->values['load_post_format']) && $this->values['load_post_format'] == '1') {
            return '<postFormat>' . (get_post_format(get_the_ID()) ?: 'standard') . '</postFormat>';
        }
        return '';
    }

    public function load_featured_image() {
        global $post;
        if (!isset($this->values['load_featured_image']) || $this->values['load_featured_image'] != '1' || !is_object($post) || !has_post_thumbnail()) {
            return '';
        }
        $_thumb = get_post_thumbnail_id($post->ID);
        if (!is_numeric($_thumb)) {
            return '';
        }
        $_attachment = wp_get_attachment_image_src($_thumb, 'large', false, '');
        $_attached_file = get_attached_file($_thumb);
        $_length = is_readable($_attached_file) ? filesize($_attached_file) : 0;
        $_type = get_post_mime_type($_thumb);
        if (!is_array($_attachment) || !isset($_attachment[0])) {
            return '';
        }
        return '<enclosure url="' . esc_attr($_attachment[0]) . '" length="' . $_length . '" type="' . $_type . '" />';
    }

    public function content_before_feed($content) {
        if (isset($this->values['content_before_feed']) && !empty($this->values['content_before_feed'])) {
            $content = wpautop($this->values['content_before_feed']) . $content;
        }
        return $content;
    }

    public function content_after_feed($content) {
        if (isset($this->values['content_after_feed']) && !empty($this->values['content_after_feed'])) {
            $content .= wpautop($this->values['content_after_feed']);
        }
        return $content;
    }

    public function do_rss() {
        $feed_format = 'rss2';
        if (isset($this->values['feed_format']) && array_key_exists($this->values['feed_format'], $this->settings['feed_format']['datas'])) {
            $feed_format = $this->values['feed_format'];
        }
        $_query = array();

        // Ignore sticky posts
        $_query['ignore_sticky_posts'] = true;

        // Number of posts
        $_query['posts_per_page'] = 10;
        if (isset($this->values['posts_per_page']) && is_numeric($this->values['posts_per_page'])) {
            $_query['posts_per_page'] = intval($this->values['posts_per_page'],10);
        }

        // Post status
        $_query['post_status'] = array('publish');

        // Load future posts
        if (isset($this->values['load_future_posts']) && $this->values['load_future_posts'] == '1') {
            $_query['post_status'][] = 'future';
        }

        // Limit future posts
        $time_limite_before = 0;
        if (isset($this->values['future_posts_hours_limit']) && is_numeric($this->values['future_posts_hours_limit'])) {
            $time_limite_before = intval($this->values['future_posts_hours_limit'], 10);
        }
        if ($time_limite_before > 0) {
            $before = current_time('timestamp') + 3600 * $time_limite_before;
            $_query['date_query'] = array(
                'before' => array(
                    'year' => date('Y', $before),
                    'month' => date('m', $before),
                    'day' => date('d', $before),
                    'hour' => date('H', $before),
                    'min' => date('i', $before)
                ),
                'inclusive' => true
            );
        }

        // Trigger custom query
        query_posts($_query);

        do_action("do_feed_{$feed_format}", false, '');
        die;
    }

    /* ----------------------------------------------------------
      Declare link
    ---------------------------------------------------------- */

    public function declare_feed() {
        echo '<link rel="alternate" type="application/rss+xml" href="' . get_site_url(null, $this->route) . '" />';
    }

    /* ----------------------------------------------------------
      Custom Route
    ---------------------------------------------------------- */

    public function add_query_vars($query_vars) {
        $query_vars[] = $this->plugin_id;
        return $query_vars;
    }

    public function add_custom_rules() {
        add_rewrite_rule(
            $this->route . '$',
            'index.php?' . $this->plugin_id . '=1',
            'top');
    }

    public function set_rules() {
        global $wp_rewrite;
        /* Load new route */
        $this->add_custom_rules();
        /* Update rules */
        flush_rewrite_rules(true);
        $wp_rewrite->flush_rules(true);
    }

    /* ----------------------------------------------------------
      Activation
    ---------------------------------------------------------- */

    public function after_option_update($option_name) {
        if ($option_name == $this->option_id) {
            $this->update_values();
            $this->set_rules();
        }
    }

    public function activation() {
        $this->update_values();
        $this->set_rules();
    }

}

$WPUCustomRSS = new WPUCustomRSS();
register_activation_hook(__FILE__, array(&$WPUCustomRSS, 'activation'));
