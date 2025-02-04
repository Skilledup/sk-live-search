<?php
/*
Plugin Name: Live Search
Plugin URI: http://anbarestany.ir
Description: A plugin to add live search functionality to your WordPress site.
Version: 1.0.0
Author: Mohammad Anbarestany
Author URI: http://anbarestany.ir
License: GPL3
*/

// Enqueue the necessary scripts
function live_search_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('live-search', plugin_dir_url(__FILE__) . 'assets/js/live-search.js', array('jquery'), '1.0', true);
    wp_enqueue_style('live-search-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    
    // Add this line to localize the AJAX URL
    wp_localize_script('live-search', 'ajaxurl', admin_url('admin-ajax.php'));
}
add_action('wp_enqueue_scripts', 'live_search_enqueue_scripts');

// Remove the shortcode-based form function since we won't need it
// remove: add_shortcode('live_search', 'live_search_form');

// Add this function to modify existing search forms
function live_search_modify_form() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Convert regular search forms to live search
        $('form[role="search"], .search-form, form.search').each(function() {
            let $form = $(this);
            let $input = $form.find('input[type="search"], input[name="s"]');
            let $submitButton = $form.find('input[type="submit"], button[type="submit"]');
            
            // Add autocomplete=off
            $input.attr('autocomplete', 'off');
            
            // Add results container
            $form.append('<div id="live-search-results" class="live-search-results"></div>');
            
            // Prevent form submission while using live search
            $form.on('submit', function(e) {
                if ($input.val().length >= 3) {
                    e.preventDefault();
                }
            });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'live_search_modify_form');

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
        echo '<div class="live-search-no-results">No results found</div>';
    }
    wp_reset_postdata();
    wp_die();
}
add_action('wp_ajax_live_search', 'live_search_ajax');
add_action('wp_ajax_nopriv_live_search', 'live_search_ajax');

