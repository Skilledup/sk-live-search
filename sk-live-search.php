<?php
/*
Plugin Name: SK Live Search
Plugin URI: 
Description: A plugin to add live search functionality to your WordPress site.
Version: 1.0.3-beta2
Author: Mohammad Anbarestany
Author URI: https://anbarestany.ir
Text Domain: live-search
Domain Path: /languages
License: GPL-3.0
*/

// Enqueue the necessary scripts
function live_search_enqueue_scripts()
{
    wp_enqueue_script('jquery');
    wp_enqueue_script('live-search', plugin_dir_url(__FILE__) . 'assets/js/live-search.js', array('jquery'), '1.0.3', true);
    wp_enqueue_style('live-search-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), '1.0.3');

    // Set cache control headers for dynamic content
    if (!headers_sent()) {
        // Don't cache pages with live search for logged-in users
        if (is_user_logged_in()) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        } else {
            // For non-logged-in users, set shorter cache time
            header('Cache-Control: public, max-age=3600'); // 1 hour cache
        }
    }

    wp_localize_script('live-search', 'liveSearchData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('live_search_nonce'),
        'nonce_timestamp' => time(),
        'refresh_nonce_action' => 'live_search_refresh_nonce',
        'cache_aware_mode' => true
    ));
}
add_action('wp_enqueue_scripts', 'live_search_enqueue_scripts');

// Load the text domain
function live_search_load_textdomain()
{
    load_plugin_textdomain('live-search', false, plugin_dir_path(__FILE__) . '/languages/');
}
add_action('plugins_loaded', 'live_search_load_textdomain');

/**
 * Get multilingual search URL that works with Polylang and WPML
 * 
 * @param string $search_query The search query to append to the URL
 * @return string The complete search URL with language support
 */
function sk_live_search_get_multilingual_search_url($search_query) {
    $base_url = home_url('/');
    
    // Check for Polylang plugin
    if (function_exists('pll_current_language') && function_exists('pll_home_url')) {
        $current_lang = call_user_func('pll_current_language');
        if ($current_lang) {
            $base_url = call_user_func('pll_home_url', $current_lang);
        }
    }
    // Check for WPML plugin
    elseif (function_exists('apply_filters') && has_filter('wpml_current_language')) {
        $current_lang = apply_filters('wpml_current_language', null);
        if ($current_lang) {
            // For WPML, we need to get the home URL for the current language
            if (function_exists('apply_filters') && has_filter('wpml_home_url')) {
                $base_url = apply_filters('wpml_home_url', home_url('/'), $current_lang);
            } else {
                // Fallback: construct URL manually for WPML
                $base_url = home_url('/' . $current_lang . '/');
            }
        }
    }
    // fallback
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
    if (!isset($_POST['nonce'])) {
        wp_send_json_error(array(
            'message' => 'Missing nonce',
            'code' => 'missing_nonce'
        ));
    }

    if (!wp_verify_nonce($_POST['nonce'], 'live_search_nonce')) {
        wp_send_json_error(array(
            'message' => 'Invalid nonce',
            'code' => 'invalid_nonce'
        ));
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
        $result_index = 0;
        while ($query->have_posts()) {
            $query->the_post();
            $result_index++;
?>
            <div class="live-search-result" role="option" tabindex="-1" aria-selected="false" data-result-index="<?php echo esc_attr($result_index); ?>">
                <a href="<?php the_permalink(); ?>" tabindex="-1" aria-label="<?php echo esc_attr(sprintf(__('Search result %d: %s', 'live-search'), $result_index, get_the_title())); ?>">
                    <?php the_title(); ?>
                </a>
            </div>
        <?php
        }

        // Add "More results..." link if there are more than 10 results
        if ($total_posts > 10) {
            $result_index++;
        ?>
            <div class="live-search-more-results" role="option" tabindex="-1" aria-selected="false" data-result-index="<?php echo esc_attr($result_index); ?>">
                <a href="<?php echo esc_url(sk_live_search_get_multilingual_search_url($search_query)); ?>" tabindex="-1" aria-label="<?php echo esc_attr(sprintf(__('View all %d search results for: %s', 'live-search'), $total_posts, $search_query)); ?>">
                    <?php echo esc_html__('More results...', 'live-search'); ?>
                </a>
            </div>
<?php
        }
    } else {
        echo '<div class="live-search-no-results" role="status" aria-live="polite">';
        echo esc_html__('no results found...', 'live-search');
        echo '</div>';
    }
    wp_reset_postdata();
    wp_die();
}
add_action('wp_ajax_live_search', 'live_search_ajax');
add_action('wp_ajax_nopriv_live_search', 'live_search_ajax');

// Add cache exclusion notice for administrators
function live_search_cache_exclusion_notice() {
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }

    $cache_plugins = array();
    
    // Check for common caching plugins
    if (is_plugin_active('w3-total-cache/w3-total-cache.php')) {
        $cache_plugins[] = 'W3 Total Cache';
    }
    if (is_plugin_active('wp-super-cache/wp-cache.php')) {
        $cache_plugins[] = 'WP Super Cache';
    }
    if (is_plugin_active('wp-fastest-cache/wpFastestCache.php')) {
        $cache_plugins[] = 'WP Fastest Cache';
    }
    if (is_plugin_active('litespeed-cache/litespeed-cache.php')) {
        $cache_plugins[] = 'LiteSpeed Cache';
    }
    if (is_plugin_active('wp-rocket/wp-rocket.php')) {
        $cache_plugins[] = 'WP Rocket';
    }

    if (!empty($cache_plugins)) {
        ?>
        <div class="notice notice-info is-dismissible" id="live-search-cache-notice">
            <p><strong>SK Live Search Cache Configuration:</strong></p>
            <p>We detected caching plugin(s): <strong><?php echo esc_html(implode(', ', $cache_plugins)); ?></strong></p>
            <p>For optimal performance with live search, consider excluding these URLs from caching:</p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><code>/wp-admin/admin-ajax.php*</code></li>
                <li><code>*action=live_search*</code></li>
                <li><code>*action=live_search_refresh_nonce*</code></li>
            </ul>
            <p>Also consider setting cache expiration to 1 hour or less for pages with search functionality.</p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '#live-search-cache-notice .notice-dismiss', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'live_search_dismiss_cache_notice',
                        nonce: '<?php echo wp_create_nonce('dismiss_cache_notice'); ?>'
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Handle dismissing the cache notice
function live_search_dismiss_cache_notice() {
    if (wp_verify_nonce($_POST['nonce'], 'dismiss_cache_notice')) {
        update_option('live_search_cache_notice_dismissed', true);
    }
    wp_die();
}
add_action('wp_ajax_live_search_dismiss_cache_notice', 'live_search_dismiss_cache_notice');

// Show cache notice only if not dismissed
if (!get_option('live_search_cache_notice_dismissed')) {
    add_action('admin_notices', 'live_search_cache_exclusion_notice');
}
