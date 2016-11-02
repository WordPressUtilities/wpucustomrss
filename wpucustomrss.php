<?php

/*
Plugin Name: WPU Custom RSS
Plugin URI: https://github.com/WordPressUtilities/wpucustomrss
Version: 0.4
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

        $this->values = get_option($this->option_id);

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

        // Init RSS
        add_action('wp', array(&$this, 'launch_rss'));

        // Add new route
        add_action('init', array(&$this, 'add_custom_rules'));
        add_filter('query_vars', array(&$this, 'add_query_vars'));

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
            'load_post_format' => array(
                'section' => 'content',
                'type' => 'checkbox',
                'label' => __('Display post format', 'wpucustomrss'),
                'label_check' => __('Post format is visible in the RSS feed', 'wpucustomrss')
            ),
            'content_after_feed' => array(
                'section' => 'content',
                'type' => 'editor',
                'label' => __('Additional item content - after', 'wpucustomrss')
            ),
            'content_before_feed' => array(
                'section' => 'content',
                'type' => 'editor',
                'label' => __('Additional item content - before', 'wpucustomrss')
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
        add_action('rss_item', array(&$this, 'after_item'));
        add_action('atom_entry', array(&$this, 'after_item'));
        add_action('rss_item', array(&$this, 'after_item'));
        add_action('rss2_item', array(&$this, 'after_item'));
        /* Before / After content */
        add_filter('the_content_feed', array(&$this, 'content_before_feed'));
        add_filter('the_content_feed', array(&$this, 'content_after_feed'));
        $this->do_rss();
    }

    public function after_item() {
        if (isset($this->values['load_future_posts']) && $this->values['load_future_posts'] == '1') {
            echo '<postFormat>' . (get_post_format($post->ID) ?: 'standard') . '</postFormat>';
        }
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

        do_action("do_feed_{$feed_format}", false, $feed);
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

    /* ----------------------------------------------------------
      Activation
    ---------------------------------------------------------- */

    public function activation() {
        $this->add_custom_rules();
        flush_rewrite_rules(true);
    }

}

$WPUCustomRSS = new WPUCustomRSS();
register_activation_hook(__FILE__, array(&$WPUCustomRSS, 'activation'));
