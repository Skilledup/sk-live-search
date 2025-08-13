<?php
/*
Plugin Name: SK Live Search
Plugin URI: 
Description: A plugin to add live search functionality to your WordPress site.
Version: 1.0.1
Author: Mohammad Anbarestany
Author URI: http://anbarestany.ir
Text Domain: live-search
Domain Path: /languages
License: GPL-3.0
*/

// Enqueue the necessary scripts
function live_search_enqueue_scripts()
{
    wp_enqueue_script('jquery');
    wp_enqueue_script('live-search', plugin_dir_url(__FILE__) . 'assets/js/live-search.js', array('jquery'), '1.0', true);
    wp_enqueue_style('live-search-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');

    wp_localize_script('live-search', 'liveSearchData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' =>  wp_create_nonce('live_search_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'live_search_enqueue_scripts');

// Load the text domain
function live_search_load_textdomain()
{
    load_plugin_textdomain('live-search', false, plugin_dir_path(__FILE__) . '/languages/');
}
add_action('plugins_loaded', 'live_search_load_textdomain');

// Handle the AJAX request
function live_search_ajax()
{
    if (!isset($_POST['nonce'])) {
        wp_die('Missing nonce');
    }

    if (!wp_verify_nonce($_POST['nonce'], 'live_search_nonce')) {
        wp_die('Invalid nonce');
    }

    $plugin_dir = dirname(plugin_basename(__FILE__));
    load_plugin_textdomain('live-search', false, $plugin_dir . '/languages');

    $search_query = sanitize_text_field($_POST['s']);

    if (empty($search_query) || strlen($search_query) < 3) {
        // Return no results for empty or too short queries
        echo '<div class="live-search-no-results">';
        echo esc_html__('no results found...', 'live-search');
        echo '</div>';
        wp_die();
    }

    $args = array(
        's' => $search_query,
        'post_status' => 'publish',
        'posts_per_page' => 10,
    );

    // Get total number of posts for this search
    $total_query = new WP_Query(array_merge($args, array('posts_per_page' => -1)));
    $total_posts = $total_query->found_posts;
    wp_reset_postdata();
    
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

        // Add "More results..." link if there are more than 10 results
        if ($total_posts > 10) {
        ?>
            <div class="live-search-more-results">
                <a href="<?php echo esc_url(home_url('/?s=' . urlencode($search_query))); ?>">
                    <?php echo esc_html__('More results...', 'live-search'); ?>
                </a>
            </div>
<?php
        }
    } else {
        echo '<div class="live-search-no-results">';
        echo esc_html__('no results found...', 'live-search');
        echo '</div>';
    }
    wp_reset_postdata();
    wp_die();
}
add_action('wp_ajax_live_search', 'live_search_ajax');
add_action('wp_ajax_nopriv_live_search', 'live_search_ajax');
