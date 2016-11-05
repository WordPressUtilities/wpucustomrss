<?php

/*
Plugin Name: WPU Custom RSS
Plugin URI: https://github.com/WordPressUtilities/wpucustomrss
Version: 0.6
Description: Create a second custom RSS feed
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUCustomRSS {

    public $route = 'wpucustomrss';
    private $option_id = 'wpucustomrss_options';
    private $messages = array();
    public $values;

    public function __construct() {
        $this->update_values();

        $this->options = array(
            'plugin_publicname' => 'WPU Custom RSS',
            'plugin_name' => 'WPU Custom RSS',
            'plugin_shortname' => 'Custom RSS',
            'plugin_userlevel' => 'manage_options',
            'plugin_id' => 'wpucustomrss',
            'plugin_pageslug' => 'wpucustomrss',
            'admin_parent' => 'tools.php',
            'admin_url' => admin_url('tools.php?page=wpucustomrss')
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
        load_plugin_textdomain('wpucustomrss', false, dirname(plugin_basename(__FILE__)) . '/lang/');

        // Admin page
        add_action('admin_menu', array(&$this,
            'admin_menu'
        ));
        add_filter("plugin_action_links_" . plugin_basename(__FILE__), array(&$this,
            'add_settings_link'
        ));
    }

    public function init() {

        /* Messages */
        if (is_admin()) {
            include 'inc/WPUBaseMessages.php';
            $this->messages = new \wpucustomrss\WPUBaseMessages($this->options['plugin_id']);
        }

        /* Settings */
        $this->settings_details = array(
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

        if (is_admin()) {
            include 'inc/WPUBaseSettings.php';
            new \wpucustomrss\WPUBaseSettings($this->settings_details, $this->settings);
        }

    }

    /* ----------------------------------------------------------
      Admin page
    ---------------------------------------------------------- */

    public function admin_menu() {
        add_submenu_page($this->options['admin_parent'], $this->options['plugin_name'] . ' - ' . __('Settings'), $this->options['plugin_shortname'], $this->options['plugin_userlevel'], $this->options['plugin_pageslug'], array(&$this,
            'admin_settings'
        ), '', 110);
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . $this->options['admin_url'] . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function admin_settings() {

        echo '<div class="wrap"><h1>' . get_admin_page_title() . '</h1>';

        echo '<p><a target="_blank" href="' . get_site_url(null, $this->route) . '"><span style="text-decoration:none;padding-right:0.2em" class="dashicons dashicons-rss"></span>' . __('Preview RSS Feed', 'wpucustomrss') . '</a></p>';

        echo '<hr />';
        settings_errors($this->settings_details['option_id']);

        echo '<form action="' . admin_url('options.php') . '" method="post">';
        settings_fields($this->settings_details['option_id']);
        do_settings_sections($this->options['plugin_id']);
        echo submit_button(__('Save Changes', 'wpucustomrss'));
        echo '</form>';

        echo '</div>';
    }

    /* ----------------------------------------------------------
      RSS Feed
    ---------------------------------------------------------- */

    public function launch_rss() {
        if (is_admin() || !get_query_var('wpucustomrss')) {
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
        echo $this->load_post_format();
        echo $this->load_featured_image();
    }

    public function load_post_format() {
        if (isset($this->values['load_post_format']) && $this->values['load_post_format'] == '1') {
            return '<postFormat>' . (get_post_format(get_the_ID()) ?: 'standard') . '</postFormat>';
        }
        return '';
    }

    public function load_featured_image() {
        if (!isset($this->values['load_featured_image']) || $this->values['load_featured_image'] != '1' || !has_post_thumbnail()) {
            return '';
        }
        $_thumb = get_post_thumbnail_id($post->ID);
        if (!is_numeric($_thumb)) {
            return '';
        }
        $_attachment = wp_get_attachment_image_src($_thumb, 'large', false, '');
        $_length = filesize(get_attached_file($_thumb));
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

        // Number of posts
        $_query['posts_per_page'] = 10;
        if (isset($this->values['posts_per_page']) && is_numeric($this->values['posts_per_page'])) {
            $_query['posts_per_page'] = $this->values['posts_per_page'];
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
      Custom Route
    ---------------------------------------------------------- */

    public function add_query_vars($query_vars) {
        $query_vars[] = 'wpucustomrss';
        return $query_vars;
    }

    public function add_custom_rules() {
        add_rewrite_rule(
            $this->route . '$',
            'index.php?wpucustomrss=1',
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
