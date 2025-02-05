<?php
/*
Plugin Name: Live Search
Plugin URI: http://anbarestany.ir
Description: A plugin to add live search functionality to your WordPress site.
Version: 1.0.0
Author: Mohammad Anbarestany
Author URI: http://anbarestany.ir
Text Domain: live-search
Domain Path: /languages
License: Commercial
*/

// Enqueue the necessary scripts
function live_search_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('live-search', plugin_dir_url(__FILE__) . 'assets/js/live-search.js', array('jquery'), '1.0', true);
    wp_enqueue_style('live-search-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_localize_script('live-search', 'ajaxurl', admin_url('admin-ajax.php'));
}
add_action('wp_enqueue_scripts', 'live_search_enqueue_scripts');

// Load the text domain
function live_search_load_textdomain() {
    load_plugin_textdomain('live-search', false, plugin_dir_path(__FILE__) . '/languages/');
}
add_action('plugins_loaded', 'live_search_load_textdomain');

// Handle the AJAX request
function live_search_ajax() {
    $search_query = $_GET['s'];
    $args = array(
        's' => $search_query,
        'post_status' => 'publish',
        'posts_per_page' => 10,
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            ?>
            <div class="live-search-result">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </div>
            <?php
        }
    } else {
        echo '<div class="live-search-no-results">';
        echo esc_html__('No results found', 'live-search'); 
        echo '</div>';
    }
    wp_reset_postdata();
    wp_die();
}
add_action('wp_ajax_live_search', 'live_search_ajax');
add_action('wp_ajax_nopriv_live_search', 'live_search_ajax');

