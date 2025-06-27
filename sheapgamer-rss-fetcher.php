<?php
/**
 * Plugin Name: SheapGamer RSS Content Fetcher
 * Plugin URI: https://sheapgamer.com/
 * Description: Fetches posts from a specified RSS Feed URL and creates WordPress posts with featured images and descriptions. Now with automatic tagging, custom slugs, and loading indicator.
 * Version: 1.1.1
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
        // NEW AJAX handler for fetching logs
        add_action( 'wp_ajax_sheapgamer_rss_fetcher_get_logs', array( $this, 'ajax_get_logs' ) );
    }

    /**
     * Adds the plugin settings page to the WordPress admin menu.
     */
    public function add_admin_menu() {
        // Changed from add_options_page to add_menu_page to create a top-level menu item
        add_menu_page(
            __( 'Sheapgamer RSS Content Fetcher Settings', 'sheapgamer-rss-fetcher' ), // Page title
            __( 'RSS Fetcher', 'sheapgamer-rss-fetcher' ),                  // Menu title
            'edit_published_posts',                                              // Capability required to access
            'sheapgamer-rss-fetcher',                                      // Menu slug
            array( $this, 'settings_page_html' ),                         // Callback function to render page
            'dashicons-rss',                                              // Icon URL (using a Dashicon for RSS)
            6                                                             // Position in the menu order (e.g., just below Posts)
        );
    }

    /**
     * Registers the plugin settings.
     */
    public function register_settings() {
        register_setting( 'sheapgamer_rss_fetcher_group', 'sheapgamer_rss_feed_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
        register_setting( 'sheapgamer_rss_fetcher_group', 'sheapgamer_rss_post_limit', array( 'sanitize_callback' => 'absint', 'default' => 5 ) );

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
        // Updated the hook to match the new top-level menu slug
        if ( 'toplevel_page_sheapgamer-rss-fetcher' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'sheapgamer-rss-fetcher-admin-script',
            plugin_dir_url( __FILE__ ) . 'admin_rss_fetcher.js', // NEW JS FILE NAME
            array( 'jquery' ),
            '1.0.0',
            true
        );

        wp_localize_script(
            'sheapgamer-rss-fetcher-admin-script',
            'sheapgamerRssFetcher', // NEW JS OBJECT NAME
            array(
                'ajax_url'            => admin_url( 'admin-ajax.php' ),
                'nonce_fetch_posts'   => wp_create_nonce( 'sheapgamer_rss_fetch_posts_nonce' ),
                'nonce_clear_logs'    => wp_create_nonce( 'sheapgamer_rss_fetcher_clear_logs_nonce' ),
                'nonce_get_logs'      => wp_create_nonce( 'sheapgamer_rss_fetcher_get_logs_nonce' ), // NEW NONCE for fetching logs
                'fetching_message'    => esc_html__( 'Fetching posts... Please wait.', 'sheapgamer-rss-fetcher' ),
                'fetch_success_message' => esc_html__( 'Successfully fetched and created posts!', 'sheapgamer-rss-fetcher' ),
                'fetch_error_message' => esc_html__( 'Error fetching posts.', 'sheapgamer-rss-fetcher' ),
            )
        );

        wp_enqueue_style(
            'sheapgamer-rss-fetcher-admin-style',
            plugin_dir_url( __FILE__ ) . 'admin_rss_fetcher.css', // NEW CSS FILE NAME
            array(),
            '1.0.0'
        );
    }

    /**
     * Displays the recent logs from the database.
     */
    private function display_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sheapgamer_rss_fetcher_logs'; // NEW LOG TABLE

        // Ensure table exists before querying. Call the static activation function directly.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            echo '<p>' . esc_html__( 'Log table does not exist yet. Perform a fetch to create it.', 'sheapgamer-rss-fetcher' ) . '</p>';
            return;
        }
        
        // Fetch last 50 logs, ordered by newest first
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
            echo '<td>' . esc_html( $log['message'] ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }


    /**
     * Logs a message to the custom database table.
     *
     * @param string $message The log message.
     * @param string $type    The type of log (e.g., 'success', 'error', 'info').
     */
    private function _log_message( $message, $type = 'info' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sheapgamer_rss_fetcher_logs';

        // Check if the table exists. If not, log to error_log, don't attempt DB insert.
        // The activation hook should ensure this table exists.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            error_log( '[SheapGamer RSS Fetcher] Log table does not exist. Cannot log message: ' . $message );
            return;
        }

        $wpdb->insert(
            $table_name,
            array(
                'timestamp' => current_time( 'mysql' ),
                'type'      => sanitize_text_field( $type ),
                'message'   => sanitize_textarea_field( $message ),
            ),
            array( '%s', '%s', '%s' )
        );
    }

    /**
     * NEW AJAX handler to get and display logs.
     * This will be called by JavaScript to refresh the log display.
     */
    public function ajax_get_logs() {
        check_ajax_referer( 'sheapgamer_rss_fetcher_get_logs_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_published_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to view logs.', 'sheapgamer-rss-fetcher' ) ) );
        }

        ob_start(); // Start output buffering
        $this->display_logs(); // Call the display function
        $logs_html = ob_get_clean(); // Get the buffered output

        wp_send_json_success( array( 'logs_html' => $logs_html ) );
    }


    /**
     * Fetches posts from the specified RSS Feed URL.
     *
     * @param string $feed_url The RSS Feed URL.
     * @param int $limit The number of posts to fetch.
     * @return array|false Array of posts on success, false on failure.
     */
    private function _fetch_rss_posts( $feed_url, $limit ) {
        if ( ! class_exists( 'SimplePie' ) ) {
            require_once( ABSPATH . WPINC . '/class-simplepie.php' );
        }
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
     *
     * @param string $image_url The URL of the image to download.
     * @param int $post_id The ID of the WordPress post.
     * @param string $alt_text Alt text for the image.
     * @return int|bool The ID of the attached image on success, false on failure.
     */
    private function _set_featured_image_from_url( $image_url, $post_id, $alt_text = '' ) {
        if ( empty( $image_url ) ) {
            return false;
        }

        // Do NOT strip query parameters from the URL, as they contain necessary hash for Facebook CDN.
        // Instead, we will extract the proper filename for WordPress.
        
        // Include necessary files for media handling
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        // Set up arguments for wp_remote_get to mimic a browser request
        $args = array(
            'timeout'   => 30, // seconds
            'sslverify' => false, // IMPORTANT: Set to true in production if your server has proper certs.
            'headers'   => array(
                'Referer'    => get_site_url(), // Spoof referrer to avoid 403 Forbidden
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36', // Mimic a common browser User-Agent
            ),
        );

        // Fetch the image data using the ORIGINAL image_url (with hash)
        $response = wp_remote_get( $image_url, $args );

        if ( is_wp_error( $response ) ) {
            $this->_log_message( sprintf( __( 'Failed to download image from %s via wp_remote_get: %s', 'sheapgamer-rss-fetcher' ), $image_url, $response->get_error_message() ), 'error' );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            $this->_log_message( sprintf( __( 'Failed to download image from %s. HTTP Status: %d. Response: %s', 'sheapgamer-rss-fetcher' ), $image_url, $response_code, wp_remote_retrieve_body($response) ), 'error' );
            return false;
        }

        $image_data = wp_remote_retrieve_body( $response );

        // Get upload directory info
        $upload_dir = wp_upload_dir();
        
        // --- NEW FILENAME GENERATION LOGIC ---
        // Extract filename and extension from the URL path, ignoring query string for this.
        $parsed_url_path = parse_url($image_url, PHP_URL_PATH);
        $original_filename = wp_basename($parsed_url_path);
        $path_info = pathinfo($original_filename);
        $extension = isset($path_info['extension']) ? $path_info['extension'] : 'jpg'; // Default to jpg if no extension found

        // Create a base filename (without extension initially) using a unique ID or hash
        // Using md5 hash of the original URL for a unique base filename
        $base_filename = md5($image_url); 
        $filename_with_ext = $base_filename . '.' . $extension;

        // Ensure the filename is unique in the upload directory
        $unique_filename = wp_unique_filename( $upload_dir['path'], $filename_with_ext );
        $new_file = $upload_dir['path'] . '/' . $unique_filename;
        // --- END NEW FILENAME GENERATION LOGIC ---

        // Save the image data to a temporary file
        if ( false === file_put_contents( $new_file, $image_data ) ) {
            $this->_log_message( sprintf( __( 'Failed to save downloaded image to %s for %s.', 'sheapgamer-rss-fetcher' ), $new_file, $image_url ), 'error' );
            return false;
        }

        // Prepare file array for media_handle_sideload
        $file_array = array(
            'name'     => $unique_filename, // Use the unique and correctly formatted filename
            'tmp_name' => $new_file,
        );

        // Do the actual sideloading
        // media_handle_sideload will move the file, check its type, and create attachment.
        $attachment_id = media_handle_sideload( $file_array, $post_id, $alt_text );

        // Clean up the temporary file if it still exists (media_handle_sideload usually deletes it)
        if ( file_exists( $new_file ) ) {
            @unlink( $new_file );
        }

        if ( is_wp_error( $attachment_id ) ) {
            $this->_log_message( sprintf( __( 'Failed to sideload image from %s: %s', 'sheapgamer-rss-fetcher' ), $image_url, $attachment_id->get_error_error_message() ), 'error' );
            return false;
        }

        // Set the featured image
        set_post_thumbnail( $post_id, $attachment_id );
        
        // Update alt text for the image if provided
        if ( ! empty( $alt_text ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
        }

        $this->_log_message( sprintf( __( 'Successfully set featured image for post ID %d from %s.', 'sheapgamer-rss-fetcher' ), $post_id, $image_url ), 'success' );
        return $attachment_id;
    }

    /**
     * Creates a new WordPress post from RSS data.
     *
     * @param array $rss_item The RSS item data.
     * @return int|bool The ID of the new WordPress post on success, false on failure.
     */
    private function _create_wordpress_post( $rss_item ) {
        // Prevent duplicates using GUID
        $existing_post_id = $this->_get_existing_post_by_rss_guid( $rss_item['id'] );
        if ( $existing_post_id ) {
            $this->_log_message( sprintf( __( 'RSS item GUID %s already exists as WordPress post ID %d. Skipping.', 'sheapgamer-rss-fetcher' ), $rss_item['id'], $existing_post_id ), 'info' );
            return false;
        }

        // --- TITLE LOGIC ---
        $raw_title = ! empty( $rss_item['title'] ) ? $rss_item['title'] : '';
        $temp_title_parts = explode('.', $raw_title, 2); // Split at the first dot
        $post_title = trim($temp_title_parts[0]); // Use the part before the first dot

        if ( empty($post_title) ) { // Fallback if title is empty after splitting, or if it was empty to begin with
            $post_title = __( 'RSS Post', 'sheapgamer-rss-fetcher' ) . ' ' . uniqid();
        } else {
             // Ensure it's not too long after dot splitting if the part before dot is long
             $post_title = wp_trim_words( $post_title, 20, '' ); // Limit to 20 words as a safeguard
        }
        $post_title = sanitize_text_field( $post_title ); // Final sanitization
        // --- END TITLE LOGIC ---

        $raw_post_content = ! empty( $rss_item['content'] ) ? $rss_item['content'] : '';
        
        // --- CONTENT PROCESSING LOGIC ---
        // 1. Remove duplicate title from the beginning of the content, case-insensitively
        //    Ensure we compare a cleaned version of the content start with the cleaned title.
        $cleaned_title_for_comparison = strip_tags(html_entity_decode($post_title, ENT_QUOTES, 'UTF-8'));
        $cleaned_content_start_for_comparison = strip_tags(html_entity_decode(substr($raw_post_content, 0, strlen($cleaned_title_for_comparison) + 20), ENT_QUOTES, 'UTF-8')); // Check a bit more than title length

        // Check if the cleaned content starts with the cleaned title (case-insensitive)
        if ( ! empty($cleaned_title_for_comparison) && stripos($cleaned_content_start_for_comparison, $cleaned_title_for_comparison) === 0 ) {
            // If it does, remove the title part from the raw content
            $raw_post_content = substr($raw_post_content, strlen($cleaned_title_for_comparison));
            $this->_log_message( sprintf( __( 'Removed duplicate title "%s" from content.', 'sheapgamer-rss-fetcher' ), $post_title ), 'info' );
        }


        // 2. Replace <br> tags with newlines
        $processed_content = str_replace( array('<br>', '<br/>', '<br />'), "\n", $raw_post_content );
        // 3. Strip all other HTML tags
        $processed_content = strip_tags( $processed_content );
        // 4. Normalize multiple newlines to two newlines (paragraph break) for readability
        $processed_content = preg_replace("/\n{3,}/", "\n\n", $processed_content);
        // 5. Trim any leading/trailing whitespace
        $processed_content = trim($processed_content);

        // 6. Automatically add <a> for each URL in description
        // This regex matches URLs starting with http://, https://, ftp:// or www.
        // It's a common pattern, but might not catch all edge cases.
        $url_pattern = '/\b((https?|ftp):\/\/|www\.)[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}(\/\S*)?\b/i';
        $processed_content = preg_replace_callback( $url_pattern, function($matches) {
            $url = $matches[0];
            // Prepend 'http://' if URL starts with 'www.' but not 'http(s)://'
            if (strpos($url, 'www.') === 0 && strpos($url, 'http') !== 0) {
                $url = 'http://' . $url;
            }
            return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $matches[0] ) . '</a>';
        }, $processed_content );

        // --- END CONTENT PROCESSING LOGIC ---

        // Add "Source" link at the end of the post content if link exists
        if ( ! empty( $rss_item['link'] ) ) {
            $processed_content .= "\n\nSource: <a href=\"" . esc_url( $rss_item['link'] ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">" . __( 'Source', 'sheapgamer-rss-fetcher' ) . "</a>";
        }


        // Initial post data
        $new_post_data = array(
            'post_title'   => $post_title, // Use the processed title
            'post_content' => wp_kses_post( $processed_content ), // Sanitize content (though most tags are gone)
            'post_status'  => 'publish', // You can change this to 'draft' for review
            'post_type'    => 'post',
            'post_date'    => wp_date( 'Y-m-d H:i:s', $rss_item['date_timestamp'] ), // Use wp_date for site timezone
            'post_date_gmt'=> gmdate( 'Y-m-d H:i:s', $rss_item['date_timestamp'] ), // Use gmdate for UTC
        );

        // Insert the post to get an ID first
        $post_id = wp_insert_post( $new_post_data, true ); // Pass true to return WP_Error on failure

        if ( is_wp_error( $post_id ) ) {
            $this->_log_message( sprintf( __( 'Failed to create WordPress post for RSS GUID %s: %s', 'sheapgamer-rss-fetcher' ), $rss_item['id'], $post_id->get_error_message() ), 'error' );
            return false;
        }

        // --- TAGS & SLUG LOGIC ---
        // Extract English words from title for tags
        preg_match_all('/\b[a-zA-Z]+\b/', $post_title, $matches);
        $tags = array_map('strtolower', array_unique($matches[0])); // Convert to lowercase and ensure unique tags

        if (!empty($tags)) {
            // Assign tags to the post
            wp_set_post_tags( $post_id, $tags, false ); // 'false' to replace existing tags
            $this->_log_message( sprintf( __( 'Added tags: %s to post ID %d.', 'sheapgamer-rss-fetcher' ), implode(', ', $tags), $post_id ), 'info' );
        }

        // Create custom slug: /post_id-tag1-tag2
        $slug_tags = !empty($tags) ? implode('-', $tags) : sanitize_title($post_title); // Fallback to sanitized title if no tags
        $new_slug = $post_id . '-' . $slug_tags;
        
        // Ensure slug is URL-friendly and unique
        $new_slug = sanitize_title($new_slug);
        
        // Update the post with the custom slug
        wp_update_post( array(
            'ID'        => $post_id,
            'post_name' => $new_slug,
        ));
        $this->_log_message( sprintf( __( 'Updated post ID %d with custom slug: %s.', 'sheapgamer-rss-fetcher' ), $post_id, $new_slug ), 'info' );
        // --- END TAGS & SLUG LOGIC ---


        // Store RSS GUID as meta to prevent duplicates
        update_post_meta( $post_id, '_sheapgamer_rss_guid', $rss_item['id'] );
        
        // Store original RSS link as meta
        if ( ! empty( $rss_item['link'] ) ) {
            update_post_meta( $post_id, '_sheapgamer_rss_original_link', esc_url_raw( $rss_item['link'] ) );
        }

        $this->_log_message( sprintf( __( 'Created WordPress post "%s" (ID: %d) from RSS GUID %s.', 'sheapgamer-rss-fetcher' ), $post_title, $post_id, $rss_item['id'] ), 'success' );

        // Handle featured image
        if ( ! empty( $rss_item['image_url'] ) ) {
            $this->_set_featured_image_from_url( $rss_item['image_url'], $post_id, $post_title );
        }

        return $post_id;
    }

    /**
     * Checks if a WordPress post with the given RSS GUID already exists.
     *
     * @param string $rss_guid The RSS item GUID.
     * @return int|bool The ID of the existing WordPress post, or false if not found.
     */
    private function _get_existing_post_by_rss_guid( $rss_guid ) {
        $args = array(
            'post_type'  => 'post',
            'meta_key'   => '_sheapgamer_rss_guid',
            'meta_value' => $rss_guid,
            'fields'     => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => true, // Optimize query
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
            $message = sprintf( __( 'Fetched posts successfully. %d new WordPress posts created.', 'sheapgamer-rss-fetcher' ), $created_count );
            $this->_log_message( $message, 'success' );
            wp_send_json_success( array( 'message' => $message ) );
        } else {
            $message = __( 'Fetched posts successfully, but no new WordPress posts were created (they might already exist or had issues).', 'sheapgamer-rss-fetcher' );
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
 * Creates the custom database table for logs.
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

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); // This ensures dbDelta is available
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'sheapgamer_rss_fetcher_activate' );

/**
 * Function to run on plugin deactivation.
 * Cleans up plugin options.
 */
function sheapgamer_rss_fetcher_deactivate() {
    delete_option( 'sheapgamer_rss_feed_url' );
    delete_option( 'sheapgamer_rss_post_limit' );

    // Optionally, delete logs or drop the table on deactivation
    // global $wpdb;
    // $table_name = $wpdb->prefix . 'sheapgamer_rss_fetcher_logs';
    // $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
}
register_deactivation_hook( __FILE__, 'sheapgamer_rss_fetcher_deactivate' );
