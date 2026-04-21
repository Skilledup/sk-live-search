<?php
/*
Plugin Name: SK Live Search
Plugin URI: 
Description: A plugin to add live search functionality to your WordPress site.
Version: 1.0.8
Author: Mohammad Anbarestany
Author URI: https://anbarestany.ir
Text Domain: sk-live-search
Domain Path: /languages
License: GPL-3.0
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue the necessary scripts
function live_search_enqueue_scripts()
{
    wp_enqueue_script('jquery');
    wp_enqueue_script('live-search', plugin_dir_url(__FILE__) . 'assets/js/live-search.js', array('jquery'), '1.0.8', true);
    wp_enqueue_style('live-search-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), '1.0.8');

    wp_localize_script('live-search', 'liveSearchData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('live_search_nonce'),
        'refresh_nonce_action' => 'live_search_refresh_nonce',
        'i18n' => array(
            'search_suggestions' => __('Search suggestions', 'sk-live-search'),
            'one_suggestion' => __('1 suggestion available', 'sk-live-search'),
            // translators: %d is the number of suggestions available.
            'suggestions_available' => __('%d suggestions available', 'sk-live-search'),
            'search_unavailable' => __('Search temporarily unavailable. Please try again.', 'sk-live-search'),
            'nonce_refresh_failed' => __('Search security token refresh failed', 'sk-live-search'),
            'search_failed' => __('Search request failed', 'sk-live-search')
        )
    ));
}
add_action('wp_enqueue_scripts', 'live_search_enqueue_scripts');



/**
 * Get multilingual search URL that works with Polylang and WPML
 * 
 * This function automatically strips front page slugs from language URLs to ensure
 * search URLs point to the language root (e.g., /fr/ instead of /fr/home_page/).
 * 
 * @param string $search_query The search query to append to the URL
 * @return string The complete search URL with language support
 */
function sk_live_search_get_multilingual_search_url($search_query) {
    $base_url = home_url('/');
    
    // Check for Polylang plugin
    if (function_exists('pll_current_language') && function_exists('pll_home_url')) {
        $current_lang = pll_current_language();
        if ($current_lang) {
            $lang_home_url = pll_home_url($current_lang);

            // If static front page and slug present, strip it
            if (get_option('show_on_front') === 'page' && get_option('page_on_front')) {
                $front_page = get_post(get_option('page_on_front'));
                if ($front_page) {
                    $parsed = wp_parse_url($lang_home_url);
                    $parts  = explode('/', trim($parsed['path'], '/'));
                    if (end($parts) === $front_page->post_name) {
                        array_pop($parts); // remove slug
                        $parsed['path'] = '/' . implode('/', $parts) . '/';
                        
                        // Preserve port (query and fragment will be handled at the end)
                        $host = $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
                        
                        $lang_home_url = untrailingslashit($parsed['scheme'] . '://' . $host . $parsed['path']);
                    }
                }
            }
            $base_url = $lang_home_url;
        }
    }
    // Check for WPML plugin
    elseif (has_filter('wpml_current_language')) {
        $current_lang = apply_filters('wpml_current_language', null);
        if ($current_lang) {
            // For WPML, we need to get the home URL for the current language
            if (has_filter('wpml_home_url')) {
                $base_url = apply_filters('wpml_home_url', home_url('/'), $current_lang);
            } else {
                // Fallback: construct URL manually for WPML
                $base_url = home_url('/' . $current_lang . '/');
            }
        }
    }
    // fallback for non-multilingual sites
    else {
        $base_url = home_url('/');
    }
    
    // Ensure the base URL ends with a slash
    $base_url = trailingslashit($base_url);
    
    // Append the search query
    return $base_url . '?s=' . urlencode($search_query);
}

// Handle nonce refresh AJAX request
function live_search_refresh_nonce_ajax()
{
    // Allow both logged in and non-logged in users to refresh nonce
    $new_nonce = wp_create_nonce('live_search_nonce');
    
    // Set cache control headers to prevent caching of nonce refresh
    if (!headers_sent()) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    wp_send_json_success(array(
        'nonce' => $new_nonce,
        'timestamp' => time()
    ));
}
add_action('wp_ajax_live_search_refresh_nonce', 'live_search_refresh_nonce_ajax');
add_action('wp_ajax_nopriv_live_search_refresh_nonce', 'live_search_refresh_nonce_ajax');

// Handle the AJAX request
function live_search_ajax()
{
    // admin-ajax may use the logged-in user's profile language; force site locale for frontend search responses.
    switch_to_locale(get_locale());

    if (!isset($_POST['nonce'])) {
        wp_send_json_error(array(
            'message' => __('Missing nonce', 'sk-live-search'),
            'code' => 'missing_nonce'
        ));
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'live_search_nonce')) {
        wp_send_json_error(array(
            'message' => __('Invalid nonce', 'sk-live-search'),
            'code' => 'invalid_nonce'
        ));
    }

    $search_query = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';

    if (empty($search_query) || strlen($search_query) < 3) {
        // Return no results for empty or too short queries
        echo '<div class="live-search-no-results">';
        echo esc_html__('no results found...', 'sk-live-search');
        echo '</div>';
        wp_die();
    }

    $args = array(
        's' => $search_query,
        'post_status' => 'publish',
        'posts_per_page' => 11, // Get one extra to check if more exist
        'no_found_rows' => true, // Don't calculate total rows - major performance boost
        'update_post_meta_cache' => false, // Don't load post meta - we only need title and permalink
        'update_post_term_cache' => false, // Don't load taxonomy terms - not needed for search results
    );

    $query = new WP_Query($args);
    $has_more_results = ($query->post_count > 10);
    if ($query->have_posts()) {
        $result_index = 0;
        while ($query->have_posts() && $result_index < 10) { // Limit to 10 results
            $query->the_post();
            $result_index++;
?>
            <div class="live-search-result" role="option" tabindex="-1" aria-selected="false" data-result-index="<?php echo esc_attr($result_index); ?>">
                <?php
                // translators: 1: result index number, 2: post title.
                $aria_label = esc_attr(sprintf(__('Search result %1$d: %2$s', 'sk-live-search'), $result_index, get_the_title()));
                ?>
                <a href="<?php the_permalink(); ?>" tabindex="-1" aria-label="<?php echo esc_attr($aria_label); ?>">
                    <?php the_title(); ?>
                </a>
            </div>
        <?php
        }

        // Add "More results..." link if there are more than 10 results
        if ($has_more_results) {
            $result_index++;
        ?>
            <div class="live-search-more-results" role="option" tabindex="-1" aria-selected="false" data-result-index="<?php echo esc_attr($result_index); ?>">
                <?php
                // translators: %s is the search query.
                $more_aria_label = esc_attr(sprintf(__('View more search results for: %s', 'sk-live-search'), $search_query));
                ?>
                <a href="<?php echo esc_url(sk_live_search_get_multilingual_search_url($search_query)); ?>" tabindex="-1" aria-label="<?php echo esc_attr($more_aria_label); ?>">
                    <?php echo esc_html__('More results...', 'sk-live-search'); ?>
                </a>
            </div>
<?php
        }
    } else {
        echo '<div class="live-search-no-results" role="status" aria-live="polite">';
        echo esc_html__('no results found...', 'sk-live-search');
        echo '</div>';
    }
    wp_reset_postdata();
    wp_die();
}
add_action('wp_ajax_live_search', 'live_search_ajax');
add_action('wp_ajax_nopriv_live_search', 'live_search_ajax');
