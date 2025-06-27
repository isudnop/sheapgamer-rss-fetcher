<?php
/**
 * Plugin Name: SheapGamer RSS Content Fetcher
 * Plugin URI: https://sheapgamer.com/
 * Description: Fetches posts from a specified RSS Feed URL and creates WordPress posts. Now with Gemini AI for automatic slug/tag generation.
 * Version: 1.3.0
 * Author: Nop SheapGamer
 * Author URI: https://sheapgamer.com/
 * License: GPL2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SheapGamer_RSS_Fetcher
 * Handles fetching content from RSS and creating WordPress posts.
 */
class SheapGamer_RSS_Fetcher {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // AJAX handler for fetching posts
        add_action( 'wp_ajax_sheapgamer_rss_fetch_posts', array( $this, 'ajax_fetch_posts' ) );
        add_action( 'wp_ajax_sheapgamer_rss_fetcher_clear_logs', array( $this, 'ajax_clear_logs' ) );
        add_action( 'wp_ajax_sheapgamer_rss_fetcher_get_logs', array( $this, 'ajax_get_logs' ) );
    }

    /**
     * Adds the plugin settings page to the WordPress admin menu.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Sheapgamer RSS Content Fetcher Settings', 'sheapgamer-rss-fetcher' ),
            __( 'RSS Fetcher', 'sheapgamer-rss-fetcher' ),
            'edit_published_posts',
            'sheapgamer-rss-fetcher',
            array( $this, 'settings_page_html' ),
            'dashicons-rss',
            6
        );
    }

    /**
     * Registers the plugin settings.
     */
    public function register_settings() {
        register_setting( 'sheapgamer_rss_fetcher_group', 'sheapgamer_rss_feed_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
        register_setting( 'sheapgamer_rss_fetcher_group', 'sheapgamer_rss_post_limit', array( 'sanitize_callback' => 'absint', 'default' => 5 ) );
        // NEW: Register Gemini API Key setting
        register_setting( 'sheapgamer_rss_fetcher_group', 'sheapgamer_gemini_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );


        add_settings_section(
            'sheapgamer_rss_fetcher_section',
            __( 'RSS Feed Configuration', 'sheapgamer-rss-fetcher' ),
            array( $this, 'settings_section_callback' ),
            'sheapgamer-rss-fetcher'
        );

        add_settings_field(
            'rss_feed_url_field',
            __( 'RSS Feed URL', 'sheapgamer-rss-fetcher' ),
            array( $this, 'rss_feed_url_field_callback' ),
            'sheapgamer-rss-fetcher',
            'sheapgamer_rss_fetcher_section'
        );

        add_settings_field(
            'rss_post_limit_field',
            __( 'Number of Posts to Fetch (Max 25)', 'sheapgamer-rss-fetcher' ),
            array( $this, 'post_limit_field_callback' ),
            'sheapgamer-rss-fetcher',
            'sheapgamer_rss_fetcher_section'
        );
        
        // NEW: Add settings field for Gemini API Key
        add_settings_field(
            'gemini_api_key_field',
            __( 'Gemini API Key', 'sheapgamer-rss-fetcher' ),
            array( $this, 'gemini_api_key_field_callback' ),
            'sheapgamer-rss-fetcher',
            'sheapgamer_rss_fetcher_section'
        );
    }

    /**
     * Callbacks for settings fields.
     */
    public function settings_section_callback() {
        echo '<p><strong>' . esc_html__( 'Note:', 'sheapgamer-rss-fetcher' ) . '</strong> ' . esc_html__( 'If not not sure please ask Nop about RSS URL', 'sheapgamer-rss-fetcher' ) . '</p>';
    }

    public function rss_feed_url_field_callback() {
        $feed_url = get_option( 'sheapgamer_rss_feed_url', '' );
        echo '<input type="url" name="sheapgamer_rss_feed_url" value="' . esc_url( $feed_url ) . '" class="regular-text" placeholder="e.g., https://example.com/feed/" />';
        echo '<p class="description">' . esc_html__( 'The full URL to the RSS feed.', 'sheapgamer-rss-fetcher' ) . '</p>';
    }

    public function post_limit_field_callback() {
        $limit = get_option( 'sheapgamer_rss_post_limit', 5 );
        echo '<input type="number" name="sheapgamer_rss_post_limit" value="' . esc_attr( $limit ) . '" min="1" max="25" class="small-text" />';
        echo '<p class="description">' . esc_html__( 'Maximum number of latest posts to fetch from the RSS feed.', 'sheapgamer-rss-fetcher' ) . '</p>';
    }

    // NEW: Callback for Gemini API Key field
    public function gemini_api_key_field_callback() {
        $api_key = get_option( 'sheapgamer_gemini_api_key', '' );
        echo '<input type="password" name="sheapgamer_gemini_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text" placeholder="Enter your Gemini API Key" />';
        echo '<p class="description">' . esc_html__( 'Get your key from Google AI Studio. This is required for automatic slug and tag generation.', 'sheapgamer-rss-fetcher' ) . '</p>';
    }

    /**
     * Renders the HTML for the plugin settings page.
     */
    public function settings_page_html() {
        if ( ! current_user_can( 'edit_published_posts' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'sheapgamer_rss_fetcher_group' );
                do_settings_sections( 'sheapgamer-rss-fetcher' );
                submit_button( __( 'Save Changes', 'sheapgamer-rss-fetcher' ) );
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e( 'Fetch & Clear Log', 'sheapgamer-rss-fetcher' ); ?></h2>
            <div class="sheapgamer-fb-fetcher-tools">
                <button type="button" id="sheapgamer-rss-fetch-now" class="button button-primary">
                    <?php esc_html_e( 'Fetch Posts Now', 'sheapgamer-rss-fetcher' ); ?>
                </button>
                <button type="button" id="sheapgamer-rss-fetcher-clear-logs" class="button button-secondary button-danger">
                    <?php esc_html_e( 'Clear Fetcher Logs', 'sheapgamer-rss-fetcher' ); ?>
                </button>
                <div id="sheapgamer-rss-fetch-result" class="fetch-result-message"></div>
            </div>

            <h3><?php esc_html_e( 'Activity Log', 'sheapgamer-rss-fetcher' ); ?></h3>
            <div id="sheapgamer-rss-fetcher-log-display" class="sheapgamer-rss-fetcher-log-display">
                <?php $this->display_logs(); ?>
            </div>
            <p class="description"><?php esc_html_e( 'Recent activities and errors related to RSS content fetching and post creation.', 'sheapgamer-rss-fetcher' ); ?></p>
        </div>
        <?php
    }

    /**
     * Enqueues necessary admin scripts for the settings page.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'toplevel_page_sheapgamer-rss-fetcher' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'sheapgamer-rss-fetcher-admin-script',
            plugin_dir_url( __FILE__ ) . 'admin_rss_fetcher.js',
            array( 'jquery' ),
            '1.3.0', // Incremented version
            true
        );

        wp_localize_script(
            'sheapgamer-rss-fetcher-admin-script',
            'sheapgamerRssFetcher',
            array(
                'ajax_url'            => admin_url( 'admin-ajax.php' ),
                'nonce_fetch_posts'   => wp_create_nonce( 'sheapgamer_rss_fetch_posts_nonce' ),
                'nonce_clear_logs'    => wp_create_nonce( 'sheapgamer_rss_fetcher_clear_logs_nonce' ),
                'nonce_get_logs'      => wp_create_nonce( 'sheapgamer_rss_fetcher_get_logs_nonce' ),
                'fetching_message'    => esc_html__( 'Fetching posts... This may take a moment if using AI.', 'sheapgamer-rss-fetcher' ),
                'fetch_success_message' => esc_html__( 'Successfully fetched and created posts!', 'sheapgamer-rss-fetcher' ),
                'fetch_error_message' => esc_html__( 'Error fetching posts.', 'sheapgamer-rss-fetcher' ),
            )
        );

        wp_enqueue_style(
            'sheapgamer-rss-fetcher-admin-style',
            plugin_dir_url( __FILE__ ) . 'admin_rss_fetcher.css',
            array(),
            '1.0.0'
        );
    }

    /**
     * Displays the recent logs from the database.
     */
    private function display_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sheapgamer_rss_fetcher_logs';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            echo '<p>' . esc_html__( 'Log table does not exist yet. Perform a fetch to create it.', 'sheapgamer-rss-fetcher' ) . '</p>';
            return;
        }
        
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} ORDER BY timestamp DESC LIMIT %d",
                50
            ),
            ARRAY_A
        );

        if ( empty( $logs ) ) {
            echo '<p>' . esc_html__( 'No logs available yet.', 'sheapgamer-rss-fetcher' ) . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__( 'Time', 'sheapgamer-rss-fetcher' ) . '</th><th>' . esc_html__( 'Type', 'sheapgamer-rss-fetcher' ) . '</th><th>' . esc_html__( 'Message', 'sheapgamer-rss-fetcher' ) . '</th></tr></thead>';
        echo '<tbody>';
        foreach ( $logs as $log ) {
            $log_type_class = strtolower( esc_attr($log['type']) );
            echo '<tr>';
            echo '<td>' . esc_html( $log['timestamp'] ) . '</td>';
            echo '<td class="log-type-' . $log_type_class . '">' . esc_html( ucfirst($log['type']) ) . '</td>';
            echo '<td>' . wp_kses_post( $log['message'] ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }


    /**
     * Logs a message to the custom database table.
     */
    private function _log_message( $message, $type = 'info' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sheapgamer_rss_fetcher_logs';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            error_log( '[SheapGamer RSS Fetcher] Log table does not exist. Cannot log message: ' . $message );
            return;
        }

        $wpdb->insert(
            $table_name,
            array(
                'timestamp' => current_time( 'mysql' ),
                'type'      => sanitize_text_field( $type ),
                'message'   => $message, // Allow HTML for links in logs
            ),
            array( '%s', '%s', '%s' )
        );
    }

    /**
     * AJAX handler to get and display logs.
     */
    public function ajax_get_logs() {
        check_ajax_referer( 'sheapgamer_rss_fetcher_get_logs_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_published_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to view logs.', 'sheapgamer-rss-fetcher' ) ) );
        }

        ob_start();
        $this->display_logs();
        $logs_html = ob_get_clean();

        wp_send_json_success( array( 'logs_html' => $logs_html ) );
    }

    /**
     * Fetches posts from the specified RSS Feed URL.
     */
    private function _fetch_rss_posts( $feed_url, $limit ) {
        if ( ! function_exists( 'fetch_feed' ) ) {
            require_once( ABSPATH . WPINC . '/feed.php' );
        }

        $rss = fetch_feed( $feed_url );

        if ( is_wp_error( $rss ) ) {
            $this->_log_message( sprintf( __( 'Failed to fetch RSS feed from %s: %s', 'sheapgamer-rss-fetcher' ), $feed_url, $rss->get_error_message() ), 'error' );
            return false;
        }

        $max_items = $rss->get_item_quantity( $limit );
        $rss_items = $rss->get_items( 0, $max_items );

        $posts_data = array();
        foreach ( $rss_items as $item ) {
            $image_url = '';
            // Try to get image from media:content (common for featured images)
            $media_content = $item->get_item_tags('http://search.yahoo.com/mrss/', 'content');
            if ( $media_content && isset( $media_content[0]['attribs']['']['url'] ) ) {
                $image_url = $media_content[0]['attribs']['']['url'];
            }
            // Fallback: Try to get image from enclosure
            if ( empty( $image_url ) ) {
                $enclosure = $item->get_enclosure();
                if ( $enclosure && $enclosure->get_link() ) {
                    $image_url = $enclosure->get_link();
                    // Basic check to ensure it's an image
                    if ( ! preg_match( '/\.(jpe?g|png|gif|webp)$/i', $image_url ) ) {
                        $image_url = ''; // Not an image, clear it
                    }
                }
            }
            // Fallback: Try to extract image from content
            if ( empty( $image_url ) ) {
                $content = $item->get_content();
                if ( preg_match( '/<img[^>]+src="([^">]+)"/i', $content, $img_matches ) ) {
                    $image_url = $img_matches[1];
                }
            }
            
            $posts_data[] = array(
                'id'          => $item->get_id(), // Use GUID as unique ID
                'title'       => $item->get_title(),
                'content'     => $item->get_content(),
                'link'        => $item->get_permalink(),
                'image_url'   => $image_url,
                'date_timestamp' => $item->get_date( 'U' ), // Get Unix timestamp
            );
        }

        $this->_log_message( sprintf( __( 'Successfully fetched %d items from RSS feed %s.', 'sheapgamer-rss-fetcher' ), count( $posts_data ), $feed_url ), 'success' );
        return $posts_data;
    }

    /**
     * Downloads an image and sets it as the featured image for a post.
     */
    private function _set_featured_image_from_url( $image_url, $post_id, $alt_text = '' ) {
        if ( empty( $image_url ) ) {
            return false;
        }
        
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $args = array(
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => array(
                'Referer'    => get_site_url(),
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
            ),
        );

        $response = wp_remote_get( $image_url, $args );

        if ( is_wp_error( $response ) ) {
            $this->_log_message( sprintf( __( 'Failed to download image from %s via wp_remote_get: %s', 'sheapgamer-rss-fetcher' ), esc_url($image_url), $response->get_error_message() ), 'error' );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            $this->_log_message( sprintf( __( 'Failed to download image from %s. HTTP Status: %d.', 'sheapgamer-rss-fetcher' ), esc_url($image_url), $response_code ), 'error' );
            return false;
        }

        $image_data = wp_remote_retrieve_body( $response );
        $upload_dir = wp_upload_dir();
        $parsed_url_path = parse_url($image_url, PHP_URL_PATH);
        $original_filename = wp_basename($parsed_url_path);
        
        // Ensure the filename is unique in the upload directory
        $unique_filename = wp_unique_filename( $upload_dir['path'], $original_filename );
        $new_file = $upload_dir['path'] . '/' . $unique_filename;

        if ( false === file_put_contents( $new_file, $image_data ) ) {
            $this->_log_message( sprintf( __( 'Failed to save downloaded image to %s for %s.', 'sheapgamer-rss-fetcher' ), $new_file, esc_url($image_url) ), 'error' );
            return false;
        }

        $file_array = array(
            'name'     => $unique_filename,
            'tmp_name' => $new_file,
        );

        $attachment_id = media_handle_sideload( $file_array, $post_id, $alt_text );

        if ( file_exists( $new_file ) ) {
            @unlink( $new_file );
        }

        if ( is_wp_error( $attachment_id ) ) {
            $this->_log_message( sprintf( __( 'Failed to sideload image from %s: %s', 'sheapgamer-rss-fetcher' ), esc_url($image_url), $attachment_id->get_error_message() ), 'error' );
            return false;
        }

        set_post_thumbnail( $post_id, $attachment_id );
        
        if ( ! empty( $alt_text ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
        }

        $this->_log_message( sprintf( __( 'Successfully set featured image for post ID %d from %s.', 'sheapgamer-rss-fetcher' ), $post_id, esc_url($image_url) ), 'success' );
        return $attachment_id;
    }
    
    /**
     * NEW: Gets title suggestion from the Gemini API.
     *
     * @param string $original_title The problematic original title.
     * @param string $content_snippet A short snippet of the post content for context.
     * @param string $api_key The Gemini API key.
     * @return string|false A suggested new title on success, false on failure.
     */
    private function _get_gemini_title_suggestion( $original_title, $content_snippet, $api_key ) {
        $this->_log_message( 'Attempting to get title suggestion from Gemini API.', 'info' );

        // Changed model to gemini-2.0-flash
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;
        
        // Truncate content snippet for prompt if too long
        $content_snippet = wp_trim_words(strip_tags($content_snippet), 100, '...');

        $prompt = "Given the original problematic post title and a content snippet, suggest a new, concise, and descriptive English title for a WordPress post. The new title should be human-readable and SEO-friendly (max 15 words). Provide only the new title, nothing else.\n\n"
                . "Original Title: \"{$original_title}\"\n"
                . "Content Snippet: \"{$content_snippet}\"\n\n"
                . "New Title:";

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            )
        );

        $args = array(
            'body'    => json_encode($body),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 60, // Increase timeout for AI API calls
        );

        $response = wp_remote_post( $api_url, $args );

        if ( is_wp_error( $response ) ) {
            $this->_log_message( 'Gemini API request for title failed: ' . $response->get_error_message(), 'error' );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( $response_code !== 200 ) {
            $this->_log_message( "Gemini API for title returned non-200 status: {$response_code}. Body: " . esc_html($response_body), 'error' );
            return false;
        }
        
        $data = json_decode($response_body, true);
        
        $suggested_title_raw = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        if ( empty($suggested_title_raw) ) {
            $this->_log_message( 'Gemini API response for title was empty or in an unexpected format.', 'error' );
            return false;
        }
        
        // Clean up the suggested title (remove quotes if Gemini adds them, trim whitespace, limit words)
        $suggested_title = trim( $suggested_title_raw, '" ' );
        $suggested_title = wp_trim_words( $suggested_title, 15, '' ); // Ensure it's not too long

        return sanitize_text_field($suggested_title);
    }

    /**
     * Gets slug and tag suggestions from the Gemini API.
     *
     * @param string $title The post title.
     * @param string $content The post content.
     * @param string $api_key The Gemini API key.
     * @return array|false Array with 'slug' and 'tags' on success, false on failure.
     */
    private function _get_gemini_suggestions( $title, $content, $api_key ) {
        $this->_log_message( 'Attempting to fetch slug and tag suggestions from Gemini API.', 'info' );

        // Changed model to gemini-2.0-flash
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;

        // Create a precise prompt asking for a JSON response
        $prompt = "Analyze the following WordPress post title and content. Your task is to provide two things:\n"
                . "1. A concise, SEO-friendly, URL-safe slug (all lowercase, words separated by hyphens).\n"
                . "2. A comma-separated list of 5-7 highly relevant tags.\n\n"
                . "IMPORTANT: Your entire response must be ONLY a valid JSON object. Do not include any text before or after the JSON. The JSON object must have two keys: 'slug' and 'tags'.\n\n"
                . "Example Response Format:\n"
                . "{\"slug\":\"example-post-title-for-seo\",\"tags\":\"tag1,tag2,another tag,keyword\"}\n\n"
                . "--- POST DATA ---\n"
                . "Title: \"{$title}\"\n"
                . "Content: \"{$content}\"";

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            )
        );

        $args = array(
            'body'    => json_encode($body),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 60, // Increase timeout for AI API calls
        );

        $response = wp_remote_post( $api_url, $args );

        if ( is_wp_error( $response ) ) {
            $this->_log_message( 'Gemini API request for slug/tags failed: ' . $response->get_error_message(), 'error' );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( $response_code !== 200 ) {
            $this->_log_message( "Gemini API for slug/tags returned non-200 status: {$response_code}. Body: " . esc_html($response_body), 'error' );
            return false;
        }
        
        $data = json_decode($response_body, true);
        
        $text_content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if ( empty($text_content) ) {
            $this->_log_message( 'Gemini API response for slug/tags was empty or in an unexpected format.', 'error' );
            return false;
        }
        
        $json_string = trim( str_replace( array('```json', '```'), '', $text_content ) );
        $suggestions = json_decode( $json_string, true );

        if ( json_last_error() !== JSON_ERROR_NONE || !isset($suggestions['slug']) || !isset($suggestions['tags']) ) {
            $this->_log_message( 'Failed to decode JSON from Gemini slug/tags response or format is incorrect. Response: ' . esc_html($json_string), 'error' );
            return false;
        }

        return $suggestions;
    }

    /**
     * Creates a new WordPress post from RSS data.
     * MODIFIED to include Gemini AI integration for Title, Tags & Slug.
     *
     * @param array $rss_item The RSS item data.
     * @return int|bool The ID of the new WordPress post on success, false on failure.
     */
    private function _create_wordpress_post( $rss_item ) {
        // Check for existing post by GUID first to prevent duplicates
        $existing_post_id = $this->_get_existing_post_by_rss_guid( $rss_item['id'] );
        if ( $existing_post_id ) {
            $log_message = sprintf( 
                __( 'RSS item GUID %s already exists as WordPress post <a href="%s" target="_blank">ID %d</a>. Skipping.', 'sheapgamer-rss-fetcher' ), 
                esc_html($rss_item['id']), 
                esc_url(get_edit_post_link($existing_post_id, 'raw')),
                $existing_post_id 
            );
            $this->_log_message( $log_message, 'info' );
            return false;
        }

        $raw_title = ! empty( $rss_item['title'] ) ? $rss_item['title'] : '';
        $temp_title_parts = explode('.', $raw_title, 2);
        $post_title = trim($temp_title_parts[0]);

        // Clean content for AI analysis and final post
        $raw_post_content = ! empty( $rss_item['content'] ) ? $rss_item['content'] : '';
        $processed_content = str_replace( array('<br>', '<br/>', '<br />'), "\n", $raw_post_content );
        $processed_content = strip_tags( $processed_content, '<a><p><h1><h2><h3><h4><h5><h6>' ); // Keep basic formatting tags
        $processed_content = preg_replace("/\n{3,}/", "\n\n", $processed_content);
        $processed_content = trim($processed_content);
        $plain_text_content = strip_tags($processed_content); // Use plain text for AI analysis

        // --- NEW: Conditional Gemini Title Suggestion ---
        $gemini_api_key = get_option('sheapgamer_gemini_api_key');
        $original_post_title_for_log = $post_title; // Store for logging purposes

        // Check if the title is "weird" (e.g., contains common URL patterns or is very short/generic)
        $is_weird_title = preg_match('/https?:\/\/(www\.)?|www\./i', $post_title) || 
                          strlen($post_title) < 10 || 
                          str_contains(strtolower($post_title), 'untitled') ||
                          str_contains(strtolower($post_title), 'default title'); // Add more patterns if needed

        if ( $is_weird_title && !empty($gemini_api_key) ) {
            $suggested_title = $this->_get_gemini_title_suggestion($post_title, $plain_text_content, $gemini_api_key);
            if ( $suggested_title ) {
                $this->_log_message(sprintf(__('Original title "%s" considered problematic. Replaced with AI-suggested title: "%s".', 'sheapgamer-rss-fetcher'), esc_html($original_post_title_for_log), esc_html($suggested_title)), 'info');
                $post_title = $suggested_title;
            } else {
                $this->_log_message(sprintf(__('Could not get AI title suggestion for "%s". Using original cleaned title.', 'sheapgamer-rss-fetcher'), esc_html($original_post_title_for_log)), 'warning');
                // Fallback to existing cleaning/trimming if AI fails
                if ( empty($post_title) ) {
                    $post_title = __( 'RSS Post', 'sheapgamer-rss-fetcher' ) . ' ' . uniqid();
                } else {
                    $post_title = wp_trim_words( $post_title, 20, '' );
                }
                $post_title = sanitize_text_field( $post_title );
            }
        } else {
            // If not a weird title or no API key, use existing cleaning/trimming
            if ( empty($post_title) ) {
                $post_title = __( 'RSS Post', 'sheapgamer-rss-fetcher' ) . ' ' . uniqid();
            } else {
                $post_title = wp_trim_words( $post_title, 20, '' );
            }
            $post_title = sanitize_text_field( $post_title );
        }
        // --- END: Conditional Gemini Title Suggestion ---


        $source_link_html = '';
        if ( ! empty( $rss_item['link'] ) ) {
            $source_link_html = "\n\n<p>Source: <a href=\"" . esc_url( $rss_item['link'] ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">" . esc_html( $rss_item['link'] ) . "</a></p>";
        }
        $final_content = wp_kses_post( $processed_content . $source_link_html );

        $new_post_data = array(
            'post_title'   => $post_title, // Use potentially AI-enhanced title
            'post_content' => $final_content,
            'post_status'  => 'publish',
            'post_type'    => 'post',
            'post_date'    => wp_date( 'Y-m-d H:i:s', $rss_item['date_timestamp'] ),
            'post_date_gmt'=> gmdate( 'Y-m-d H:i:s', $rss_item['date_timestamp'] ),
        );

        $post_id = wp_insert_post( $new_post_data, true );

        if ( is_wp_error( $post_id ) ) {
            $this->_log_message( sprintf( __( 'Failed to create WordPress post for RSS GUID %s: %s', 'sheapgamer-rss-fetcher' ), esc_html($rss_item['id']), $post_id->get_error_message() ), 'error' );
            return false;
        }

        // --- Gemini Integration for Tags & Slug ---
        $gemini_suggestions = false;
        $tags_array = array();
        $new_slug = '';

        if ( !empty($gemini_api_key) ) {
            // Pass the potentially AI-enhanced $post_title to the slug/tag generation
            $gemini_suggestions = $this->_get_gemini_suggestions($post_title, $plain_text_content, $gemini_api_key);
        }

        if ( $gemini_suggestions && !empty($gemini_suggestions['slug']) && !empty($gemini_suggestions['tags']) ) {
            // --- Use Gemini Suggestions ---
            $this->_log_message(sprintf(__('Successfully received slug and tag suggestions from Gemini for post ID %d.', 'sheapgamer-rss-fetcher'), $post_id), 'info');
            
            $tags_array = array_map('trim', explode(',', $gemini_suggestions['tags']));
            $new_slug = sanitize_title($gemini_suggestions['slug']);

        } else {
            // --- FALLBACK to original logic for tags and slug ---
            if (!empty($gemini_api_key)) {
                 $this->_log_message(sprintf(__('Could not get slug and tag suggestions from Gemini for post ID %d. Using fallback logic.', 'sheapgamer-rss-fetcher'), $post_id), 'info');
            } else {
                 $this->_log_message(sprintf(__('Gemini API key not set. Using fallback logic for post ID %d.', 'sheapgamer-rss-fetcher'), $post_id), 'info');
            }
           
            // Use the (potentially AI-enhanced) $post_title for fallback tag/slug generation
            preg_match_all('/\b[a-zA-Z]+\b/', $post_title, $matches);
            $tags_array = array_map('strtolower', array_unique($matches[0]));
            $slug_tags = !empty($tags_array) ? implode('-', $tags_array) : sanitize_title($post_title);
            $new_slug = $post_id . '-' . $slug_tags;
        }
        
        // --- Apply Tags and Slug ---
        if (!empty($tags_array)) {
            wp_set_post_tags( $post_id, $tags_array, false );
            $this->_log_message( sprintf( __( 'Added tags: %s to post ID %d.', 'sheapgamer-rss-fetcher' ), esc_html(implode(', ', $tags_array)), $post_id ), 'info' );
        }
        
        $final_slug = wp_unique_post_slug( $new_slug, $post_id, 'publish', 'post', 0 );
        wp_update_post( array(
            'ID'        => $post_id,
            'post_name' => $final_slug,
        ));
        $this->_log_message( sprintf( __( 'Updated post ID %d with slug: %s.', 'sheapgamer-rss-fetcher' ), $post_id, esc_html($final_slug) ), 'info' );

        update_post_meta( $post_id, '_sheapgamer_rss_guid', $rss_item['id'] );
        if ( ! empty( $rss_item['link'] ) ) {
            update_post_meta( $post_id, '_sheapgamer_rss_original_link', esc_url_raw( $rss_item['link'] ) );
        }

        $post_link = get_edit_post_link($post_id, 'raw');
        $success_message = sprintf( 
            __( 'Created WordPress post "<a href="%s" target="_blank">%s</a>" (ID: %d) from RSS GUID %s.', 'sheapgamer-rss-fetcher' ), 
            esc_url($post_link),
            esc_html($post_title), // Use the potentially AI-enhanced title for the log message
            $post_id, 
            esc_html($rss_item['id']) 
        );
        $this->_log_message($success_message, 'success');

        if ( ! empty( $rss_item['image_url'] ) ) {
            $this->_set_featured_image_from_url( $rss_item['image_url'], $post_id, $post_title );
        }

        return $post_id;
    }

    /**
     * Checks if a WordPress post with the given RSS GUID already exists.
     */
    private function _get_existing_post_by_rss_guid( $rss_guid ) {
        $args = array(
            'post_type'      => 'post',
            'meta_key'       => '_sheapgamer_rss_guid',
            'meta_value'     => $rss_guid,
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'post_status'    => 'any', // Check against all statuses
            'no_found_rows'  => true,
        );
        $posts = get_posts( $args );
        return ! empty( $posts ) ? $posts[0] : false;
    }


    /**
     * AJAX handler to fetch posts from RSS.
     */
    public function ajax_fetch_posts() {
        check_ajax_referer( 'sheapgamer_rss_fetch_posts_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_published_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to fetch posts.', 'sheapgamer-rss-fetcher' ) ) );
        }

        $feed_url = get_option( 'sheapgamer_rss_feed_url', '' );
        $limit = get_option( 'sheapgamer_rss_post_limit', 5 );

        if ( empty( $feed_url ) || ! filter_var( $feed_url, FILTER_VALIDATE_URL ) ) {
            $this->_log_message( __( 'RSS Feed URL is missing or invalid in settings. Please configure.', 'sheapgamer-rss-fetcher' ), 'error' );
            wp_send_json_error( array( 'message' => __( 'Configuration missing or invalid. Check settings.', 'sheapgamer-rss-fetcher' ) ) );
        }

        $rss_items = $this->_fetch_rss_posts( $feed_url, $limit );

        if ( ! $rss_items ) {
            wp_send_json_error( array( 'message' => __( 'No items fetched from RSS feed or an error occurred during fetching. Check logs for details.', 'sheapgamer-rss-fetcher' ) ) );
        }

        $created_count = 0;
        foreach ( $rss_items as $rss_item ) {
            if ( $this->_create_wordpress_post( $rss_item ) ) {
                $created_count++;
            }
        }

        if ( $created_count > 0 ) {
            $message = sprintf( __( 'Fetch complete. %d new WordPress posts created.', 'sheapgamer-rss-fetcher' ), $created_count );
            $this->_log_message( $message, 'success' );
            wp_send_json_success( array( 'message' => $message ) );
        } else {
            $message = __( 'Fetch complete. No new posts were created (they may already exist or there were issues during creation).', 'sheapgamer-rss-fetcher' );
            $this->_log_message( $message, 'info' );
            wp_send_json_success( array( 'message' => $message ) );
        }
    }

    /**
     * AJAX handler to clear fetcher logs.
     */
    public function ajax_clear_logs() {
        check_ajax_referer( 'sheapgamer_rss_fetcher_clear_logs_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_published_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to clear logs.', 'sheapgamer-rss-fetcher' ) ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sheapgamer_rss_fetcher_logs';
        $result = $wpdb->query( "TRUNCATE TABLE $table_name" );

        if ( false === $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to clear logs.', 'sheapgamer-rss-fetcher' ) . $wpdb->last_error ) );
        } else {
            $this->_log_message( __( 'All fetcher activity logs cleared by user.', 'sheapgamer-rss-fetcher' ), 'info' );
            wp_send_json_success( array( 'message' => __( 'Logs cleared successfully.', 'sheapgamer-rss-fetcher' ) ) );
        }
    }
}

// Instantiate the plugin
new SheapGamer_RSS_Fetcher();

// --- Activation / Deactivation Hooks ---
/**
 * Function to run on plugin activation.
 */
function sheapgamer_rss_fetcher_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sheapgamer_rss_fetcher_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        type varchar(20) NOT NULL,
        message text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'sheapgamer_rss_fetcher_activate' );

/**
 * Function to run on plugin deactivation.
 */
function sheapgamer_rss_fetcher_deactivate() {
    delete_option( 'sheapgamer_rss_feed_url' );
    delete_option( 'sheapgamer_rss_post_limit' );
    // Also delete the new API key option on deactivation
    delete_option( 'sheapgamer_gemini_api_key' );
}
register_deactivation_hook( __FILE__, 'sheapgamer_rss_fetcher_deactivate' );