<?php
/**
 * Destination search shortcode.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the client-side destination search shortcode.
 */
final class DestinationSearch {

    /**
     * Shortcode tag.
     */
    private const SHORTCODE = 'search-destination';

    /**
     * Cache key.
     */
    private const CACHE_KEY = 'traveljabs_destination_search_data';

    /**
     * Cache lifetime.
     */
    private const CACHE_EXPIRATION = DAY_IN_SECONDS;

    /**
     * Default text displayed above the search field.
     */
    private const DEFAULT_TEXT = 'Find out what vaccinations you may need for your trip';

    /**
     * Constructor.
     */
    public function __construct() {

        add_shortcode(
            self::SHORTCODE,
            array( $this, 'render' )
        );

        /**
         * Register custom REST endpoint.
         */
        add_action(
            'rest_api_init',
            array( $this, 'register_rest_route' )
        );

        /**
         * Clear destination cache whenever a destination is saved.
         */
        add_action(
            'save_post_destination',
            array( $this, 'clear_destination_cache' ),
            10,
            3
        );

        /**
         * Clear cache when destination is deleted/trashed/restored.
         */
        add_action(
            'deleted_post',
            array( $this, 'maybe_clear_deleted_destination_cache' )
        );

        add_action(
            'trashed_post',
            array( $this, 'maybe_clear_deleted_destination_cache' )
        );

        add_action(
            'untrashed_post',
            array( $this, 'maybe_clear_deleted_destination_cache' )
        );
    }

    /**
     * Register cached destination REST route.
     *
     * Endpoint:
     * /wp-json/traveljabs/v1/destinations
     *
     * @return void
     */
    public function register_rest_route(): void {

        register_rest_route(
            'traveljabs/v1',
            '/destinations',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_destinations' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Return destination list.
     *
     * Uses WordPress transient cache.
     *
     * @return \WP_REST_Response
     */
    public function get_destinations(): \WP_REST_Response {

        $destinations = get_transient( self::CACHE_KEY );

        /**
         * Cache exists.
         */
        if ( false !== $destinations ) {

            return rest_ensure_response(
                array(
                    'success' => true,
                    'cached'  => true,
                    'data'    => $destinations,
                )
            );
        }

        /**
         * Cache missing.
         * Query destinations from database.
         */
        $posts = get_posts(
            array(
                'post_type'              => 'destination',
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'orderby'                => 'title',
                'order'                  => 'ASC',

                // Performance improvements.
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );

        $destinations = array();

        foreach ( $posts as $post ) {

            $destinations[] = array(
                'id'    => $post->ID,
                'title' => html_entity_decode(
                    get_the_title( $post->ID ),
                    ENT_QUOTES,
                    get_bloginfo( 'charset' )
                ),
                'url'   => get_permalink( $post->ID ),
            );
        }

        /**
         * Save generated data in cache.
         */
        set_transient(
            self::CACHE_KEY,
            $destinations,
            self::CACHE_EXPIRATION
        );

        return rest_ensure_response(
            array(
                'success' => true,
                'cached'  => false,
                'data'    => $destinations,
            )
        );
    }

    /**
     * Clear cache whenever destination is saved.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an update.
     *
     * @return void
     */
    public function clear_destination_cache(
        int $post_id,
        \WP_Post $post,
        bool $update
    ): void {

        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( wp_is_post_autosave( $post_id ) ) {
            return;
        }

        delete_transient( self::CACHE_KEY );
    }

    /**
     * Clear cache when destination is deleted,
     * trashed, or restored.
     *
     * @param int $post_id Post ID.
     *
     * @return void
     */
    public function maybe_clear_deleted_destination_cache(
        int $post_id
    ): void {

        if ( 'destination' !== get_post_type( $post_id ) ) {
            return;
        }

        delete_transient( self::CACHE_KEY );
    }

    /**
     * Renders the search shell and queues its frontend asset.
     *
     * @param array<string, string> $attributes Shortcode attributes.
     *
     * @return string
     */
    public function render( $attributes = array() ): string {

        $attributes = shortcode_atts(
            array(
                'text' => self::DEFAULT_TEXT,
            ),
            $attributes,
            self::SHORTCODE
        );

        $text = sanitize_text_field(
            (string) $attributes['text']
        );

        $text = '' !== $text
            ? $text
            : self::DEFAULT_TEXT;

        wp_enqueue_style(
            'traveljabs-destination-search',
            TRAVELJABS_URL . 'assets/css/destination-search.css',
            array(),
            TRAVELJABS_VERSION
        );

        wp_enqueue_script(
            'traveljabs-destination-search',
            TRAVELJABS_URL . 'assets/js/destination-search.js',
            array(),
            TRAVELJABS_VERSION,
            true
        );

        wp_localize_script(
            'traveljabs-destination-search',
            'traveljabsDestinationSearch',
            array(

                /**
                 * Use our cached endpoint instead of:
                 *
                 * wp/v2/destination
                 */
                'restUrl' => esc_url_raw(
                    rest_url( 'traveljabs/v1/destinations' )
                ),

                'loadingText' => __(
                    'Loading destination...',
                    'traveljabs'
                ),

                'placeholderText' => __(
                    'Search the destination',
                    'traveljabs'
                ),

                'errorText' => __(
                    'Could not load destinations. Please try again.',
                    'traveljabs'
                ),

                'notFoundText' => __(
                    'No destination found.',
                    'traveljabs'
                ),
            )
        );

        return '<div style="background: white;">'
            . '<div class="traveljabs-destination-text">'
            . esc_html( $text )
            . '</div>'

            . '<div class="traveljabs-destination-search">'

            . '<label class="screen-reader-text">'
            . esc_html__(
                'Search the destination',
                'traveljabs'
            )
            . '</label>'

            . '<input type="search" '
            . 'class="traveljabs-destination-search__input" '
            . 'placeholder="'
            . esc_attr__(
                'Loading destination...',
                'traveljabs'
            )
            . '" autocomplete="off" disabled>'

            . '<ul class="traveljabs-destination-search__results" '
            . 'aria-live="polite"></ul>'

            . '</div>'
            . '</div>';
    }
}