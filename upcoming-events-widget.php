<?php
/**
 * Plugin Name: Upcoming Events Widget
 * Description: Custom Post Type for Events with categories. [upcoming_events] shows next N upcoming events as cards. [all_events] shows a one-month calendar with AJAX prev/next navigation, clickable event chips that open a detail modal, and full mobile responsiveness.
 * Version: 4.3
 * Author: Omiebi Eki-Allen
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================
   1. REGISTER CUSTOM POST TYPE: events
   ========================================================= */
function uew_register_events_cpt() {
    register_post_type( 'events', array(
        'labels' => array(
            'name'               => 'Events',
            'singular_name'      => 'Event',
            'add_new'            => 'Add New Event',
            'add_new_item'       => 'Add New Event',
            'edit_item'          => 'Edit Event',
            'new_item'           => 'New Event',
            'view_item'          => 'View Event',
            'search_items'       => 'Search Events',
            'not_found'          => 'No events found',
            'not_found_in_trash' => 'No events found in Trash',
            'menu_name'          => 'Events',
        ),
        'public'             => true,
        'publicly_queryable' => false,
        'has_archive'        => true,
        'rewrite'            => array( 'slug' => 'events' ),
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'menu_icon'          => 'dashicons-calendar-alt',
        'show_in_rest'       => true,
    ) );
}
add_action( 'init', 'uew_register_events_cpt' );


/* =========================================================
   2. REGISTER EVENT CATEGORY TAXONOMY
   ========================================================= */
function uew_register_event_category_taxonomy() {
    register_taxonomy( 'event_category', 'events', array(
        'labels' => array(
            'name'          => 'Event Categories',
            'singular_name' => 'Event Category',
            'search_items'  => 'Search Categories',
            'all_items'     => 'All Categories',
            'edit_item'     => 'Edit Category',
            'update_item'   => 'Update Category',
            'add_new_item'  => 'Add New Category',
            'new_item_name' => 'New Category Name',
            'menu_name'     => 'Categories',
        ),
        'hierarchical'      => true,
        'public'            => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => array( 'slug' => 'event-category' ),
    ) );
}
add_action( 'init', 'uew_register_event_category_taxonomy' );


/* =========================================================
   2b. SEED DEFAULT CATEGORIES ON ACTIVATION
   ========================================================= */
function uew_seed_default_categories() {
    $defaults = array(
        array( 'name' => 'External Ministry',  'slug' => 'external-ministry' ),
        array( 'name' => 'External Corporate', 'slug' => 'external-corporate' ),
        array( 'name' => 'Internal Corporate', 'slug' => 'internal-corporate' ),
    );
    foreach ( $defaults as $cat ) {
        if ( ! term_exists( $cat['slug'], 'event_category' ) ) {
            wp_insert_term( $cat['name'], 'event_category', array( 'slug' => $cat['slug'] ) );
        }
    }
}
register_activation_hook( __FILE__, 'uew_seed_default_categories' );


/* =========================================================
   3. REGISTER CUSTOM META FIELDS
   ========================================================= */
function uew_register_event_meta() {
    $shared = array(
        'show_in_rest'  => true,
        'single'        => true,
        'type'          => 'string',
        'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
    );
    register_post_meta( 'events', 'event_date',     $shared );
    register_post_meta( 'events', 'event_end_date', $shared );
    register_post_meta( 'events', 'event_location', $shared );
    register_post_meta( 'events', 'event_link',     $shared );
}
add_action( 'init', 'uew_register_event_meta' );


/* =========================================================
   4. META BOX (classic editor / Elementor)
   ========================================================= */
function uew_add_event_meta_box() {
    add_meta_box( 'uew_event_details', 'Event Details', 'uew_render_event_meta_box', 'events', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'uew_add_event_meta_box' );

function uew_render_event_meta_box( $post ) {
    wp_nonce_field( 'uew_save_event_meta', 'uew_event_nonce' );
    $event_date     = get_post_meta( $post->ID, 'event_date', true );
    $event_end_date = get_post_meta( $post->ID, 'event_end_date', true );
    $event_location = get_post_meta( $post->ID, 'event_location', true );
    $event_link     = get_post_meta( $post->ID, 'event_link', true );

    $terms = wp_get_post_terms( $post->ID, 'event_category' );
    $current_cat_slug = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : '';

    $categories = array(
        ''                   => '— Select Category —',
        'external-ministry'  => 'External Ministry',
        'external-corporate' => 'External Corporate',
        'internal-corporate' => 'Internal Corporate',
    );
    ?>
    <style>
        .uew-meta-table { width:100%; border-collapse:collapse; }
        .uew-meta-table th { text-align:left; padding:8px 10px 4px; font-weight:600; font-size:13px; color:#1d2327; }
        .uew-meta-table td { padding:4px 10px 12px; }
        .uew-meta-table input[type="date"],
        .uew-meta-table input[type="text"],
        .uew-meta-table input[type="url"],
        .uew-meta-table select { width:100%; max-width:340px; }
        .uew-meta-note { font-size:12px; color:#666; margin-top:3px; }
    </style>
    <table class="uew-meta-table">
        <tr>
            <th><label for="uew_event_category">Category</label></th>
            <td>
                <select id="uew_event_category" name="uew_event_category">
                    <?php foreach ( $categories as $slug => $label ) : ?>
                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_cat_slug, $slug ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="event_date">Event Start Date <span style="color:red">*</span></label></th>
            <td>
                <input type="date" id="event_date" name="event_date" value="<?php echo esc_attr( $event_date ); ?>" required>
                <p class="uew-meta-note">The event hides from [upcoming_events] after this date passes.</p>
            </td>
        </tr>
        <tr>
            <th><label for="event_end_date">Event End Date <em>(optional)</em></label></th>
            <td>
                <input type="date" id="event_end_date" name="event_end_date" value="<?php echo esc_attr( $event_end_date ); ?>">
                <p class="uew-meta-note">For multi-day events. Leave blank for single-day events.</p>
            </td>
        </tr>
        <tr>
            <th><label for="event_location">Location</label></th>
            <td>
                <input type="text" id="event_location" name="event_location" value="<?php echo esc_attr( $event_location ); ?>" placeholder="e.g. Victoria Island, Lagos">
            </td>
        </tr>
        <tr>
            <th><label for="event_link">Registration / Event Link <em>(optional)</em></label></th>
            <td>
                <input type="url" id="event_link" name="event_link" value="<?php echo esc_attr( $event_link ); ?>" placeholder="https://...">
                <p class="uew-meta-note">Used for the "Attend" button on the calendar modal.</p>
            </td>
        </tr>
    </table>
    <?php
}

function uew_save_event_meta( $post_id ) {
    if ( ! isset( $_POST['uew_event_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['uew_event_nonce'], 'uew_save_event_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['event_date'] ) )
        update_post_meta( $post_id, 'event_date',     sanitize_text_field( $_POST['event_date'] ) );
    if ( isset( $_POST['event_end_date'] ) )
        update_post_meta( $post_id, 'event_end_date', sanitize_text_field( $_POST['event_end_date'] ) );
    if ( isset( $_POST['event_location'] ) )
        update_post_meta( $post_id, 'event_location', sanitize_text_field( $_POST['event_location'] ) );
    if ( isset( $_POST['event_link'] ) )
        update_post_meta( $post_id, 'event_link', esc_url_raw( $_POST['event_link'] ) );

    if ( isset( $_POST['uew_event_category'] ) && $_POST['uew_event_category'] !== '' ) {
        $term = get_term_by( 'slug', sanitize_text_field( $_POST['uew_event_category'] ), 'event_category' );
        if ( $term ) wp_set_post_terms( $post_id, array( $term->term_id ), 'event_category' );
    } else {
        wp_set_post_terms( $post_id, array(), 'event_category' );
    }
}
add_action( 'save_post', 'uew_save_event_meta' );


/* =========================================================
   5. SHARED HELPERS
   ========================================================= */

function uew_get_category_badge_data( $post_id ) {
    $terms = wp_get_post_terms( $post_id, 'event_category' );
    if ( empty( $terms ) || is_wp_error( $terms ) ) return null;
    $slug  = $terms[0]->slug;
    $name  = $terms[0]->name;
    $colors = array(
        'external-ministry'  => array( 'bg' => '#2563EB', 'label' => 'MINISTRY' ),
        'external-corporate' => array( 'bg' => '#16A34A', 'label' => 'CORPORATE' ),
        'internal-corporate' => array( 'bg' => '#EA580C', 'label' => 'CORPORATE' ),
    );
    $color = isset( $colors[$slug] ) ? $colors[$slug] : array( 'bg' => '#555', 'label' => strtoupper( $name ) );
    return array_merge( $color, array( 'slug' => $slug, 'name' => $name ) );
}

function uew_cat_chip_color( $slug ) {
    $map = array(
        'external-ministry'  => '#2563EB',
        'external-corporate' => '#16A34A',
        'internal-corporate' => '#EA580C',
    );
    return isset( $map[$slug] ) ? $map[$slug] : '#888';
}

function uew_format_date( $event_date, $event_end_date = '' ) {
    if ( ! $event_date ) return '';
    $start_ts = strtotime( $event_date );
    if ( $event_end_date && $event_end_date !== $event_date ) {
        $end_ts = strtotime( $event_end_date );
        if ( date( 'MY', $start_ts ) === date( 'MY', $end_ts ) ) {
            return date( 'l, jS', $start_ts ) . ' – ' . date( 'l, jS F Y', $end_ts );
        }
        return date( 'l, jS F', $start_ts ) . ' – ' . date( 'l, jS F Y', $end_ts );
    }
    return date( 'l, jS F Y', $start_ts );
}


/* =========================================================
   6. ENQUEUE FONTS
   ========================================================= */
function uew_enqueue_assets() {
    wp_enqueue_style( 'uew-font-outfit',
        'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap', array(), null );
    wp_enqueue_style( 'uew-font-satoshi',
        'https://api.fontshare.com/v2/css?f[]=satoshi@400,500&display=swap', array(), null );
}
add_action( 'wp_enqueue_scripts', 'uew_enqueue_assets' );


/* =========================================================
   7. SHARED CSS  (printed once per page)
   ========================================================= */
function uew_print_shared_css() {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;
    ?>
    <style id="uew-shared-styles">
    /* ==========================================================
       ELEMENTOR ISOLATION
       ========================================================== */
    .elementor-widget-container:has(.uew-wrap) { overflow: visible !important; padding: 0 !important; margin: 0 !important; }
    .uew-wrap { overflow: visible !important; width: 100% !important; max-width: none !important; }

    /* ==========================================================
       BRAND PALETTE
       ========================================================== */
    .uew-wrap {
        --font-heading:  'Outfit', sans-serif;
        --font-body:     'Satoshi', sans-serif;

        --uew-pink:      #C33EC3;
        --uew-amber:     #FCC201;
        --uew-black:     #020202;
        --uew-white:     #fefefe;

        --uew-page-bg:   #fefefe;
        --uew-cell-bg:   #fefefe;
        --uew-cell-alt:  #f5f5f5;
        --uew-outside:   #e8e8e8;
        --uew-border:    #e0e0e0;

        --uew-header-bg: #C33EC3;
        --uew-nav-bg:    #9a1e9a;
        --uew-today-bg:  #FCC201;
        --uew-divider:   #C33EC3;

        --uew-text:      #020202;
        --uew-text-dim:  #555;
        --uew-text-out:  #bbb;

        --uew-dark-bg:   #020202;
        --uew-dark-div:  #C33EC3;
        --uew-dark-rule: #1e1e1e;
        --uew-card-text: #fefefe;
        --uew-card-meta: #FCC201;
    }

    .uew-wrap, .uew-wrap *, .uew-wrap *::before, .uew-wrap *::after { box-sizing: border-box !important; }

    .uew-events-grid > *, .uew-cal-grid > *, .uew-cal-dow-row > *, .uew-cal-nav > *, .uew-modal-body > *, .uew-modal-header > *, .uew-cal-legend > * {
        margin: 0 !important; padding: revert;
    }

    /* ======================================================
       WIDGET — [upcoming_events] dark 3-up card grid
       ====================================================== */
    .uew-events-grid {
        display: grid !important; grid-template-columns: repeat(3, 1fr) !important; background: var(--uew-dark-bg) !important; align-items: stretch !important; gap: 0 !important; width: 100% !important; max-width: none !important;
    }
    .uew-event-card {
        background: var(--uew-dark-bg) !important; padding: 28px 28px 32px !important; border: 1px solid #2a2a2a !important; border-left: none !important; border-top: none !important; display: flex !important; flex-direction: column !important; position: relative !important; min-width: 0 !important; width: auto !important;
    }
    .uew-event-card:last-child { border-right: none !important; }

    .uew-card-top { display: flex !important; align-items: flex-start !important; justify-content: space-between !important; gap: 12px !important; margin-bottom: 14px !important; padding: 0 !important; }
    .uew-card-month { font-family: var(--font-heading) !important; font-size: 1.5rem !important; font-weight: 700 !important; color: var(--uew-amber) !important; letter-spacing: 0.01em !important; line-height: 1 !important; text-transform: uppercase !important; margin: 0 !important; padding: 0 !important; }
    .uew-card-badge { display: inline-block !important; font-family: var(--font-body) !important; font-size: 0.6rem !important; letter-spacing: 0.12em !important; text-transform: uppercase !important; color: #aaa !important; padding: 4px 9px !important; border-radius: 2px !important; border: 1px solid #444 !important; background: transparent !important; font-weight: 500 !important; white-space: nowrap !important; flex-shrink: 0 !important; margin-top: 4px !important; margin-bottom: 0 !important; }
    .uew-card-divider { height: 1px !important; background: #2a2a2a !important; margin-bottom: 20px !important; padding: 0 !important; border: none !important; }
    .uew-card-title-area { flex: 1 !important; margin-bottom: 28px !important; padding: 0 !important; }
    .uew-event-title { font-family: var(--font-heading) !important; font-size: clamp(1.15rem, 1.7vw, 1.55rem) !important; font-weight: 600 !important; color: var(--uew-card-text) !important; line-height: 1.3 !important; margin: 0 !important; padding: 0 !important; }
    .uew-event-meta { display: flex !important; flex-direction: column !important; gap: 9px !important; margin-top: auto !important; padding: 0 !important; }
    .uew-event-meta-item { display: flex !important; align-items: center !important; gap: 9px !important; color: #888 !important; font-family: var(--font-body) !important; font-size: 0.82rem !important; letter-spacing: 0.01em !important; line-height: 1.4 !important; margin: 0 !important; padding: 0 !important; }
    .uew-event-meta-item svg { flex-shrink: 0 !important; width: 15px !important; height: 15px !important; fill: none !important; stroke: #666 !important; stroke-width: 1.7 !important; stroke-linecap: round !important; stroke-linejoin: round !important; display: block !important; min-width: 15px !important; }
    .uew-no-events { font-family: var(--font-body) !important; color: #666 !important; padding: 40px 32px !important; text-align: center !important; background: var(--uew-dark-bg) !important; font-size: 0.9rem !important; }

    /* ======================================================
       CALENDAR — [all_events]
       ====================================================== */
    .uew-cal-wrap { background: var(--uew-page-bg) !important; font-family: var(--font-body) !important; position: relative !important; width: 100% !important; max-width: none !important; overflow: visible !important; }
    .uew-cal-sticky-head { position: sticky !important; top: 0 !important; z-index: 100 !important; isolation: isolate !important; width: 100% !important; }

    .uew-cal-nav { display: flex !important; align-items: center !important; justify-content: space-between !important; background: var(--uew-nav-bg) !important; padding: 14px 20px !important; user-select: none !important; box-shadow: 0 2px 10px rgba(0,0,0,0.22) !important; width: 100% !important; gap: 0 !important; }
    .uew-cal-nav-title { font-family: var(--font-heading) !important; font-size: 1.15rem !important; font-weight: 600 !important; color: #fff !important; letter-spacing: 0.04em !important; text-transform: uppercase !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; margin: 0 12px !important; padding: 0 !important; flex: 1 !important; text-align: center !important; }
    .uew-cal-nav-btn { background: rgba(255,255,255,0.15) !important; border: 1px solid rgba(255,255,255,0.3) !important; color: #fff !important; border-radius: 4px !important; width: 40px !important; height: 40px !important; display: flex !important; align-items: center !important; justify-content: center !important; cursor: pointer !important; font-size: 1.1rem !important; transition: background 0.15s !important; flex-shrink: 0 !important; touch-action: manipulation !important; padding: 0 !important; margin: 0 !important; line-height: 1 !important; text-decoration: none !important; }
    .uew-cal-nav-btn:hover  { background: rgba(255,255,255,0.28) !important; }
    .uew-cal-nav-btn:active { background: rgba(255,255,255,0.42) !important; }
    .uew-cal-nav-btn:disabled { opacity: 0.35 !important; cursor: default !important; }

    .uew-cal-dow-row { display: grid !important; grid-template-columns: repeat(7, 1fr) !important; background: var(--uew-header-bg) !important; width: 100% !important; gap: 0 !important; }
    .uew-cal-dow-cell { padding: 9px 0 !important; text-align: center !important; font-size: 0.65rem !important; font-weight: 700 !important; letter-spacing: 0.12em !important; text-transform: uppercase !important; color: #fff !important; margin: 0 !important; display: block !important; }

    .uew-cal-grid { display: grid !important; grid-template-columns: repeat(7, 1fr) !important; background: var(--uew-border) !important; gap: 1px !important; width: 100% !important; max-width: none !important; }
    .uew-cal-day { background: var(--uew-cell-bg) !important; padding: 8px 10px 12px !important; min-height: 120px !important; position: relative !important; overflow: hidden !important; display: flex !important; flex-direction: column !important; align-items: flex-start !important; min-width: 0 !important; width: auto !important; }
    .uew-cal-day--alt     { background: var(--uew-cell-alt) !important; }
    .uew-cal-day--outside { background: var(--uew-outside) !important; opacity: 0.5 !important; }
    .uew-cal-day--has-event { cursor: pointer !important; transition: background 0.2s ease !important; }
    .uew-cal-day--has-event:hover { background: #fcf4fc !important; }

    .uew-cal-daynum { font-family: var(--font-heading) !important; font-size: 0.82rem !important; font-weight: 500 !important; color: var(--uew-text-dim) !important; line-height: 1 !important; margin-bottom: 6px !important; margin-top: 0 !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; min-width: 26px !important; width: 26px !important; height: 26px !important; flex-shrink: 0 !important; }
    .uew-cal-day--today .uew-cal-daynum { background: var(--uew-today-bg) !important; color: var(--uew-black) !important; border-radius: 50% !important; font-weight: 700 !important; }

    /* ---- Event chip ---- */
    .uew-cal-event-chip {
        display: flex !important; align-items: flex-start !important; gap: 5px !important; margin-top: 4px !important; margin-bottom: 0 !important; padding: 4px 7px !important; border-radius: 3px !important; font-size: 0.7rem !important; font-weight: 500 !important; line-height: 1.35 !important; color: #fff !important; cursor: pointer !important; border: none !important; width: 100% !important; max-width: 100% !important; text-align: left !important; transition: opacity 0.12s, transform 0.1s !important; word-break: break-word !important; white-space: normal !important; font-family: var(--font-body) !important; box-shadow: none !important; text-decoration: none !important; letter-spacing: normal !important; text-transform: none !important; min-width: 0 !important; pointer-events: none !important; /* Force click on day cell */
    }
    
    .uew-chip-dot { flex-shrink: 0 !important; width: 6px !important; height: 6px !important; border-radius: 50% !important; background: rgba(255,255,255,0.7) !important; margin-top: 4px !important; margin-bottom: 0 !important; margin-left: 0 !important; margin-right: 0 !important; padding: 0 !important; display: block !important; min-width: 6px !important; flex-shrink: 0 !important; }
    .uew-chip-title { flex: 1 !important; min-width: 0 !important; display: block !important; margin: 0 !important; padding: 0 !important; line-height: inherit !important; }

    .uew-cal-region { position: relative !important; transition: opacity 0.2s !important; }
    .uew-cal-region.uew-loading { opacity: 0.45 !important; pointer-events: none !important; }
    .uew-cal-spinner { display: none; position: absolute !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; width: 32px !important; height: 32px !important; border: 3px solid #ddd !important; border-top-color: var(--uew-pink) !important; border-radius: 50% !important; animation: uew-spin 0.7s linear infinite !important; z-index: 10 !important; margin: 0 !important; padding: 0 !important; }
    .uew-cal-region.uew-loading .uew-cal-spinner { display: block !important; }
    @keyframes uew-spin { to { transform: translate(-50%,-50%) rotate(360deg); } }

    .uew-cal-legend { display: flex !important; flex-wrap: wrap !important; gap: 16px !important; padding: 18px 20px !important; border-top: 1px solid var(--uew-border) !important; background: var(--uew-page-bg) !important; margin: 0 !important; }
    .uew-cal-legend-item { display: flex !important; align-items: center !important; gap: 7px !important; font-size: 0.72rem !important; color: var(--uew-text-dim) !important; letter-spacing: 0.06em !important; text-transform: uppercase !important; font-family: var(--font-body) !important; margin: 0 !important; padding: 0 !important; }
    .uew-cal-legend-dot { width: 10px !important; height: 10px !important; border-radius: 2px !important; flex-shrink: 0 !important; display: inline-block !important; }

    /* ======================================================
       ELEMENTOR-PROOF MODAL (Overrides global button styles & layout)
       ====================================================== */
    body .uew-modal p, body .uew-modal h1, body .uew-modal h2, body .uew-modal h3, body .uew-modal h4, body .uew-modal span, body .uew-modal a, body .uew-modal div { margin: 0 !important; padding: 0 !important; }
    
    body .uew-modal-header { display: flex !important; align-items: flex-start !important; justify-content: space-between !important; gap: 16px !important; padding: 32px 32px 20px !important; border-bottom: 1px solid #eee !important; }
    body .uew-modal-title-wrap { flex: 1 1 auto !important; min-width: 0 !important; }
    
    /* Force Elementor to leave the close button alone */
    body .elementor-widget-container .uew-modal-close,
    body .uew-modal-overlay .uew-modal-close,
    body .uew-modal-close {
        background: transparent !important;
        border: none !important;
        color: #888 !important; 
        font-size: 28px !important; 
        line-height: 1 !important;
        padding: 0 4px !important;
        margin: 0 !important;
        height: auto !important;
        width: auto !important;
        min-width: 0 !important;
        min-height: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        align-self: flex-start !important;
    }
    body .uew-modal-close:hover { color: #111 !important; background: transparent !important; }

    body .uew-modal-body, body .uew-modal-footer { padding: 0 32px 32px !important; }
    body .uew-modal-topbar  { height: 5px !important; padding: 0 !important; }
    body .uew-modal-badge   { padding: 2px 9px !important; margin-bottom: 8px !important; display: inline-block !important; font-size: 0.6rem !important; letter-spacing: 0.1em !important; text-transform: uppercase !important; font-weight: 700 !important; font-family: var(--font-body) !important; border-radius: 2px !important;}
    body .uew-modal-title   { margin: 0 !important; font-family: var(--font-heading) !important; font-size: 1.1rem !important; font-weight: 500 !important; color: var(--uew-black) !important; line-height: 1.5 !important; margin-top: 8px !important; }
    body .uew-modal-row     { display: flex !important; padding: 0 !important; gap: 11px !important; align-items: flex-start !important; font-family: var(--font-body) !important; font-size: 0.9rem !important; color: #333 !important; line-height: 1.5 !important;}
    body .uew-modal-row svg { flex-shrink: 0 !important; width: 18px !important; height: 18px !important; margin-top: 1px !important; }
    body .uew-modal-attend  { padding: 10px 24px !important; display: inline-block !important; background: var(--uew-pink) !important; color: #fff !important; font-family: var(--font-body) !important; font-size: 0.82rem !important; letter-spacing: 0.08em !important; text-transform: uppercase !important; text-decoration: none !important; border-radius: 4px !important; transition: background 0.15s !important;}
    body .uew-modal-attend:hover { background: #a02ba0 !important; }
    
    body .uew-modal-single  { display: flex; flex-direction: column; gap: 16px !important; }
    
    /* List View Elements */
    body .uew-modal-list { display: flex; flex-direction: column; margin: 0 -32px !important; border-top: 1px solid #eee; }
    body .uew-modal-list-item { padding: 20px 32px !important; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s; }
    body .uew-modal-list-item:hover { background: #f9f9f9; }
    body .uew-modal-list-title { font-family: var(--font-heading); font-size: 1.1rem; font-weight: 500; color: var(--uew-black); margin-top: 6px !important; line-height: 1.5 !important;}
    body .uew-modal-back { background: none; border: none; font-family: var(--font-body); font-size: 0.9rem; color: var(--uew-pink); cursor: pointer; padding: 0; margin-bottom: 12px; display: inline-flex; align-items: center; gap: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
    body .uew-modal-back:hover { text-decoration: underline; }

    body .uew-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(2,2,2,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 24px !important; }
    body .uew-modal-overlay.uew-modal-open { display: flex; }
    body .uew-modal { background: #fff; border-radius: 8px; max-width: 420px; width: 100%; box-shadow: 0 24px 64px rgba(0,0,0,0.28); overflow: hidden; animation: uew-modal-in 0.18s ease; }
    @keyframes uew-modal-in { from { opacity:0; transform: scale(0.95) translateY(10px); } to { opacity:1; transform: scale(1) translateY(0); } }
    body .uew-modal-topbar { height: 5px; background: var(--uew-pink); }

    /* ======================================================
       RESPONSIVE
       ====================================================== */

    @media (max-width: 900px) {
        .uew-events-grid { grid-template-columns: 1fr 1fr !important; }
        .uew-event-card:nth-child(2n) { border-right: none !important; }
        .uew-event-card:nth-child(n+3) { border-top: 1px solid #2a2a2a !important; }
        
        /* Mobile scroll override */
        .elementor-widget-container:has(.uew-cal-scroll-wrap) { overflow: visible !important; }
        body .uew-cal-scroll-wrap { overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; padding-bottom: 12px !important; scrollbar-width: thin; scrollbar-color: #C33EC3 #f0f0f0; }
        body .uew-cal-scroll-wrap::-webkit-scrollbar { height: 6px; }
        body .uew-cal-scroll-wrap::-webkit-scrollbar-track { background: #f0f0f0; border-radius: 4px; }
        body .uew-cal-scroll-wrap::-webkit-scrollbar-thumb { background-color: #C33EC3; border-radius: 4px; }
        body .uew-cal-grid, body .uew-cal-dow-row { min-width: 750px !important; }
    }

    @media (max-width: 640px) {
        .uew-events-grid { grid-template-columns: 1fr !important; }
        .uew-event-card { border-right: none !important; border-left: none !important; border-top: 1px solid #2a2a2a !important; padding: 24px 20px 28px !important; }
        .uew-event-card:first-child { border-top: none !important; }
        .uew-card-month { font-size: 1.25rem !important; }

        .uew-cal-nav { padding: 10px 12px !important; }
        .uew-cal-nav-title { font-size: 0.9rem !important; letter-spacing: 0.04em !important; }
        .uew-cal-nav-btn { width: 38px !important; height: 38px !important; font-size: 1rem !important; }

        .uew-cal-day { min-height: 80px !important; padding: 6px 4px 6px !important; }
        .uew-cal-daynum { font-size: 0.72rem !important; min-width: 22px !important; width: 22px !important; height: 22px !important; margin-bottom: 4px !important; }
        .uew-cal-day--today .uew-cal-daynum { width: 22px !important; height: 22px !important; }

        .uew-cal-legend { gap: 10px; padding: 14px 12px; }
        .uew-cal-legend-item { font-size: 0.65rem; }

        body .uew-modal { border-radius: 6px; }
        body .uew-modal { animation: uew-modal-up 0.2s ease; }
        @keyframes uew-modal-up { from { opacity:0; transform: translateY(30px); } to { opacity:1; transform: translateY(0); } }
    }
    </style>
    <?php
}


/* =========================================================
   8. RENDER: SINGLE WIDGET CARD
   ========================================================= */
function uew_render_card( $post_id ) {
    $event_date     = get_post_meta( $post_id, 'event_date', true );
    $event_end_date = get_post_meta( $post_id, 'event_end_date', true );
    $event_location = get_post_meta( $post_id, 'event_location', true );
    $title          = get_the_title( $post_id );
    $badge          = uew_get_category_badge_data( $post_id );

    $month_label = $event_date ? strtoupper( date( 'F', strtotime( $event_date ) ) ) : '';

    $date_display = '';
    if ( $event_date ) {
        $start_ts = strtotime( $event_date );
        if ( $event_end_date && $event_end_date !== $event_date ) {
            $end_ts = strtotime( $event_end_date );
            if ( date( 'MY', $start_ts ) === date( 'MY', $end_ts ) ) {
                $date_display = date( 'D j', $start_ts ) . ' – ' . date( 'D j M', $end_ts );
            } else {
                $date_display = date( 'D j M', $start_ts ) . ' – ' . date( 'D j M', $end_ts );
            }
        } else {
            $date_display = date( 'D j M', $start_ts );
        }
    }

    $badge_label = '';
    if ( $badge ) {
        $slug_map = array(
            'external-ministry'  => 'EXTERNAL',
            'external-corporate' => 'EXTERNAL',
            'internal-corporate' => 'INTERNAL',
        );
        $badge_label = isset( $slug_map[ $badge['slug'] ] ) ? $slug_map[ $badge['slug'] ] : strtoupper( $badge['name'] );
    }

    $icon_cal = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
    $icon_pin = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>';

    ob_start();
    ?>
    <div class="uew-event-card">
        <div class="uew-card-top">
            <span class="uew-card-month"><?php echo esc_html( $month_label ); ?></span>
            <?php if ( $badge_label ) : ?>
            <span class="uew-card-badge"><?php echo esc_html( $badge_label ); ?></span>
            <?php endif; ?>
        </div>
        <div class="uew-card-divider"></div>
        <div class="uew-card-title-area">
            <h3 class="uew-event-title"><?php echo esc_html( $title ); ?></h3>
        </div>
        <div class="uew-event-meta">
            <?php if ( $date_display ) : ?>
            <div class="uew-event-meta-item">
                <?php echo $icon_cal; ?>
                <span><?php echo esc_html( $date_display ); ?></span>
            </div>
            <?php endif; ?>
            <?php if ( $event_location ) : ?>
            <div class="uew-event-meta-item">
                <?php echo $icon_pin; ?>
                <span><?php echo esc_html( $event_location ); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


/* =========================================================
   9. SHORTCODE: [upcoming_events]
   ========================================================= */
function uew_upcoming_events_shortcode( $atts ) {
    $atts  = shortcode_atts( array( 'count' => 3 ), $atts, 'upcoming_events' );
    $today = date( 'Y-m-d' );

    $query = new WP_Query( array(
        'post_type'      => 'events',
        'posts_per_page' => intval( $atts['count'] ),
        'post_status'    => 'publish',
        'meta_key'       => 'event_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => array( array(
            'key'     => 'event_date',
            'value'   => $today,
            'compare' => '>=',
            'type'    => 'DATE',
        ) ),
    ) );

    ob_start();
    uew_print_shared_css();
    ?>
    <div class="uew-wrap">
        <?php if ( ! $query->have_posts() ) : ?>
            <p class="uew-no-events">No upcoming events right now. Check back soon.</p>
        <?php else : ?>
            <div class="uew-events-grid">
            <?php
            while ( $query->have_posts() ) :
                $query->the_post();
                echo uew_render_card( get_the_ID() );
            endwhile;
            wp_reset_postdata();
            ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'upcoming_events', 'uew_upcoming_events_shortcode' );


/* =========================================================
   10. BUILD events_by_day INDEX
   ========================================================= */
function uew_build_events_by_day( $year, $month ) {
    $month_start = sprintf( '%04d-%02d-01', $year, $month );
    $month_end   = date( 'Y-m-t', strtotime( $month_start ) );

    $query = new WP_Query( array(
        'post_type'      => 'events',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_key'       => 'event_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => array( array(
            'key'     => 'event_date',
            'value'   => $month_end,
            'compare' => '<=',
            'type'    => 'DATE',
        ) ),
    ) );

    $events_by_day = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $pid       = get_the_ID();
            $start_str = get_post_meta( $pid, 'event_date', true );
            $end_str   = get_post_meta( $pid, 'event_end_date', true );
            $link      = get_post_meta( $pid, 'event_link', true );
            $location  = get_post_meta( $pid, 'event_location', true );
            $title     = get_the_title();
            $badge     = uew_get_category_badge_data( $pid );
            $cat_slug  = $badge ? $badge['slug'] : '';
            $cat_label = $badge ? $badge['label'] : '';
            $cat_bg    = $badge ? $badge['bg'] : '#888';

            if ( ! $start_str ) continue;

            $start_ts = strtotime( $start_str );
            $end_ts   = ( $end_str && $end_str !== $start_str ) ? strtotime( $end_str ) : $start_ts;

            $clamp_start = max( $start_ts, strtotime( $month_start ) );
            $clamp_end   = min( $end_ts,   strtotime( $month_end ) );

            if ( $clamp_start > $clamp_end ) continue;

            $date_display = uew_format_date( $start_str, ( $end_str ?: '' ) );

            $ev = array(
                'title'     => $title,
                'link'      => $link,
                'location'  => $location,
                'cat_slug'  => $cat_slug,
                'cat_label' => $cat_label,
                'cat_bg'    => $cat_bg,
                'date_str'  => $date_display,
            );

            $cur = $clamp_start;
            while ( $cur <= $clamp_end ) {
                $d = date( 'Y-m-d', $cur );
                $events_by_day[$d][] = $ev;
                $cur = strtotime( '+1 day', $cur );
            }
        }
        wp_reset_postdata();
    }

    return $events_by_day;
}


/* =========================================================
   11. RENDER ONE MONTH GRID (HTML string, used by shortcode + AJAX)
   ========================================================= */
function uew_render_cal_month_html( $year, $month ) {
    $today_str     = date( 'Y-m-d' );
    $month_ts      = mktime( 0, 0, 0, $month, 1, $year );
    $days_in_month = (int) date( 't', $month_ts );
    $first_dow     = (int) date( 'w', $month_ts );

    $events_by_day = uew_build_events_by_day( $year, $month );

    ob_start();

    echo '<div class="uew-cal-grid">';

    $cell = 0;

    $prev_month_ts = mktime( 0, 0, 0, $month - 1, 1, $year );
    $prev_days     = (int) date( 't', $prev_month_ts );
    for ( $b = 0; $b < $first_dow; $b++ ) {
        $pd = $prev_days - ( $first_dow - 1 - $b );
        echo '<div class="uew-cal-day uew-cal-day--outside"><span class="uew-cal-daynum">' . $pd . '</span></div>';
        $cell++;
    }

    for ( $day = 1; $day <= $days_in_month; $day++ ) {
        $date_str   = sprintf( '%04d-%02d-%02d', $year, $month, $day );
        $is_today   = ( $date_str === $today_str );
        $is_alt     = ( (int) floor( $cell / 7 ) % 2 === 1 );
        $day_events = isset( $events_by_day[$date_str] ) ? $events_by_day[$date_str] : array();

        $classes = array( 'uew-cal-day' );
        if ( $is_today ) $classes[] = 'uew-cal-day--today';
        if ( $is_alt   ) $classes[] = 'uew-cal-day--alt';
        if ( ! empty( $day_events ) ) $classes[] = 'uew-cal-day--has-event';

        echo '<div class="' . implode( ' ', $classes ) . '">';
        echo '<span class="uew-cal-daynum">' . $day . '</span>';

        foreach ( $day_events as $ev ) {
            $chip_color = uew_cat_chip_color( $ev['cat_slug'] );

            $data  = ' data-title="'    . esc_attr( $ev['title'] ) . '"';
            $data .= ' data-date="'     . esc_attr( $ev['date_str'] ) . '"';
            $data .= ' data-location="' . esc_attr( $ev['location'] ) . '"';
            $data .= ' data-link="'     . esc_attr( $ev['link'] ) . '"';
            $data .= ' data-cat="'      . esc_attr( $ev['cat_label'] ) . '"';
            $data .= ' data-catbg="'    . esc_attr( $ev['cat_bg'] ) . '"';

            echo '<button class="uew-cal-event-chip" style="background:' . esc_attr( $chip_color ) . '"'
                . $data . ' aria-label="' . esc_attr( $ev['title'] ) . '">';
            echo '<span class="uew-chip-dot"></span>';
            echo '<span class="uew-chip-title">' . esc_html( $ev['title'] ) . '</span>';
            echo '</button>';
        }

        echo '</div>';
        $cell++;
    }

    $trailing = ( 7 - ( $cell % 7 ) ) % 7;
    for ( $t = 1; $t <= $trailing; $t++ ) {
        echo '<div class="uew-cal-day uew-cal-day--outside"><span class="uew-cal-daynum">' . $t . '</span></div>';
        $cell++;
    }

    echo '</div>';

    return ob_get_clean();
}


/* =========================================================
   12. AJAX HANDLER: uew_cal_navigate
   ========================================================= */
function uew_ajax_cal_navigate() {
    check_ajax_referer( 'uew_cal_nav', 'nonce' );

    $year  = intval( $_POST['year']  ?? date('Y') );
    $month = intval( $_POST['month'] ?? date('m') );

    if ( $month < 1  ) { $month = 12; $year--; }
    if ( $month > 12 ) { $month = 1;  $year++; }

    $html        = uew_render_cal_month_html( $year, $month );
    $month_label = date( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );

    wp_send_json_success( array(
        'html'        => $html,
        'month_label' => $month_label,
        'year'        => $year,
        'month'       => $month,
    ) );
}
add_action( 'wp_ajax_uew_cal_navigate',        'uew_ajax_cal_navigate' );
add_action( 'wp_ajax_nopriv_uew_cal_navigate', 'uew_ajax_cal_navigate' );


/* =========================================================
   13. SHORTCODE: [all_events]
   ========================================================= */
function uew_all_events_shortcode( $atts ) {
    $atts = shortcode_atts( array(), $atts, 'all_events' );

    $year  = (int) date( 'Y' );
    $month = (int) date( 'm' );
    $month_label = date( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );

    ob_start();
    uew_print_shared_css();
    ?>
    <div class="uew-wrap uew-cal-wrap" id="uew-all-events">

        <div class="uew-cal-sticky-head">
            <div class="uew-cal-nav">
                <button class="uew-cal-nav-btn" id="uew-cal-prev" aria-label="Previous month">&#8592;</button>
                <span class="uew-cal-nav-title" id="uew-cal-title"><?php echo esc_html( strtoupper( $month_label ) ); ?></span>
                <button class="uew-cal-nav-btn" id="uew-cal-next" aria-label="Next month">&#8594;</button>
            </div>
        </div>

        <div class="uew-cal-scroll-wrap">
            <div class="uew-cal-dow-row">
                <div class="uew-cal-dow-cell">Sun</div>
                <div class="uew-cal-dow-cell">Mon</div>
                <div class="uew-cal-dow-cell">Tue</div>
                <div class="uew-cal-dow-cell">Wed</div>
                <div class="uew-cal-dow-cell">Thu</div>
                <div class="uew-cal-dow-cell">Fri</div>
                <div class="uew-cal-dow-cell">Sat</div>
            </div>

            <div class="uew-cal-region" id="uew-cal-region">
                <div class="uew-cal-spinner"></div>
                <?php echo uew_render_cal_month_html( $year, $month ); ?>
            </div>
        </div>

        <div class="uew-cal-legend">
            <div class="uew-cal-legend-item">
                <span class="uew-cal-legend-dot" style="background:#2563EB;"></span> External Ministry
            </div>
            <div class="uew-cal-legend-item">
                <span class="uew-cal-legend-dot" style="background:#16A34A;"></span> External Corporate
            </div>
            <div class="uew-cal-legend-item">
                <span class="uew-cal-legend-dot" style="background:#EA580C;"></span> Internal Corporate
            </div>
            <div class="uew-cal-legend-item">
                <span class="uew-cal-legend-dot" style="background:#FCC201; border-radius:50%;"></span> Today
            </div>
        </div>

    </div>

    <div class="uew-modal-overlay" id="uew-modal" role="dialog" aria-modal="true" aria-labelledby="uew-modal-title-text">
        <div class="uew-modal">
            <div class="uew-modal-topbar" id="uew-modal-topbar"></div>
            <div class="uew-modal-header">
                <div class="uew-modal-title-wrap">
                    <button id="uew-modal-back" class="uew-modal-back" style="display:none;">← Back</button>
                    <span class="uew-modal-badge" id="uew-modal-badge"></span>
                    <h2 class="uew-modal-title" id="uew-modal-title-text"></h2>
                </div>
                <button class="uew-modal-close" id="uew-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="uew-modal-body">
                <div id="uew-modal-list" class="uew-modal-list" style="display:none;"></div>
                
                <div id="uew-modal-single" class="uew-modal-single">
                    <div class="uew-modal-row" id="uew-modal-date-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#a03070" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <span id="uew-modal-date"></span>
                    </div>
                    <div class="uew-modal-row" id="uew-modal-loc-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#a03070" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                            <circle cx="12" cy="9" r="2.5"/>
                        </svg>
                        <span id="uew-modal-loc"></span>
                    </div>
                </div>
            </div>
            <div class="uew-modal-footer" id="uew-modal-footer"></div>
        </div>
    </div>

    <script>
    (function() {
        var ajaxUrl   = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
        var nonce     = <?php echo json_encode( wp_create_nonce( 'uew_cal_nav' ) ); ?>;
        var curYear   = <?php echo $year; ?>;
        var curMonth  = <?php echo $month; ?>;

        var region    = document.getElementById('uew-cal-region');
        var titleEl   = document.getElementById('uew-cal-title');
        var prevBtn   = document.getElementById('uew-cal-prev');
        var nextBtn   = document.getElementById('uew-cal-next');

        function navigate(year, month) {
            region.classList.add('uew-loading');
            prevBtn.disabled = true;
            nextBtn.disabled = true;

            var data = new FormData();
            data.append('action', 'uew_cal_navigate');
            data.append('nonce',  nonce);
            data.append('year',   year);
            data.append('month',  month);

            fetch(ajaxUrl, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        var spinner = region.querySelector('.uew-cal-spinner');
                        region.innerHTML = '';
                        region.appendChild(spinner);
                        region.insertAdjacentHTML('beforeend', res.data.html);

                        titleEl.textContent = res.data.month_label.toUpperCase();
                        curYear  = res.data.year;
                        curMonth = res.data.month;
                    }
                })
                .catch(function(err) { console.error('UEW nav error', err); })
                .finally(function() {
                    region.classList.remove('uew-loading');
                    prevBtn.disabled = false;
                    nextBtn.disabled = false;
                });
        }

        prevBtn.addEventListener('click', function() { navigate(curYear, curMonth - 1); });
        nextBtn.addEventListener('click', function() { navigate(curYear, curMonth + 1); });

        // ---- Modal Elements & Logic ----
        var overlay       = document.getElementById('uew-modal');
        
        // TELEPORT THE MODAL: Escape Elementor widget container
        if (overlay && overlay.parentNode !== document.body) {
            document.body.appendChild(overlay);
        }

        var closeBtn      = document.getElementById('uew-modal-close');
        var modalTopbar   = document.getElementById('uew-modal-topbar');
        var modalBadge    = document.getElementById('uew-modal-badge');
        var modalTitle    = document.getElementById('uew-modal-title-text');
        var modalDate     = document.getElementById('uew-modal-date');
        var modalLoc      = document.getElementById('uew-modal-loc');
        var modalLocRow   = document.getElementById('uew-modal-loc-row');
        var modalFooter   = document.getElementById('uew-modal-footer');
        
        var modalList     = document.getElementById('uew-modal-list');
        var modalSingle   = document.getElementById('uew-modal-single');
        var modalBack     = document.getElementById('uew-modal-back');
        var currentDayChips = []; // Keep track to build back button

        function openListModal(dayCell) {
            var chips = dayCell.querySelectorAll('.uew-cal-event-chip');
            if (!chips.length) return;
            
            currentDayChips = chips; // Save context
            
            if (chips.length === 1) {
                openSingleModal(chips[0], false);
                return;
            }
            
            // Build and show the list view
            modalSingle.style.display = 'none';
            modalFooter.style.display = 'none';
            modalList.style.display = 'flex';
            modalBack.style.display = 'none';

            if (modalTopbar) modalTopbar.style.background = '#FCC201'; // Default yellow
            modalBadge.style.display = 'none';
            modalTitle.textContent = chips[0].dataset.date; // E.g., "Friday, 1st May 2026"
            
            modalList.innerHTML = '';
            chips.forEach(function(chip) {
                var item = document.createElement('div');
                item.className = 'uew-modal-list-item';
                var safeBg = chip.dataset.catbg || '#2563EB';
                item.innerHTML = '<span class="uew-modal-badge" style="background:'+safeBg+'; color:#fff; display:inline-block; margin-bottom:6px;">'+chip.dataset.cat+'</span>' +
                                 '<div class="uew-modal-list-title">'+chip.dataset.title+'</div>';
                item.addEventListener('click', function() {
                    openSingleModal(chip, true);
                });
                modalList.appendChild(item);
            });

            overlay.classList.add('uew-modal-open');
            document.body.style.overflow = 'hidden';
            closeBtn.focus();
        }

        function openSingleModal(chip, fromList) {
            var title    = chip.dataset.title    || '';
            var date     = chip.dataset.date     || '';
            var location = chip.dataset.location || '';
            var link     = chip.dataset.link     || '';
            var cat      = chip.dataset.cat      || '';
            var catBg    = chip.dataset.catbg    || '#2563EB';

            // Show single view
            modalList.style.display = 'none';
            modalSingle.style.display = 'flex';
            modalFooter.style.display = 'block';

            if (fromList) {
                modalBack.style.display = 'inline-flex';
            } else {
                modalBack.style.display = 'none';
            }

            if (modalTopbar) modalTopbar.style.background = catBg;

            modalBadge.textContent       = cat;
            modalBadge.style.background  = catBg;
            modalBadge.style.color       = '#fff';
            modalBadge.style.display     = cat ? 'inline-block' : 'none';

            modalTitle.textContent = title;
            modalDate.textContent  = date;

            modalLocRow.style.display = location ? 'flex' : 'none';
            if (location) modalLoc.textContent = location;

            modalFooter.innerHTML = '';
            if (link) {
                var a = document.createElement('a');
                a.href = link;
                a.target = '_blank';
                a.rel = 'noopener';
                a.className = 'uew-modal-attend';
                a.textContent = 'Attend Event →';
                modalFooter.appendChild(a);
            }

            overlay.classList.add('uew-modal-open');
            document.body.style.overflow = 'hidden';
        }
        
        // Handle Back button
        modalBack.addEventListener('click', function() {
            if (currentDayChips.length > 1) {
                openListModal(currentDayChips[0].closest('.uew-cal-day'));
            }
        });

        function closeModal() {
            overlay.classList.remove('uew-modal-open');
            document.body.style.overflow = '';
        }

        // Delegate clicks
        document.addEventListener('click', function(e) {
            // Because event chips have pointer-events: none, clicks hit the wrapper day cell natively.
            var day = e.target.closest('.uew-cal-day--has-event');
            if (day) {
                e.preventDefault();
                openListModal(day);
                return;
            }

            if (e.target === overlay) closeModal();
        });

        closeBtn.addEventListener('click', closeModal);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'all_events', 'uew_all_events_shortcode' );


/* =========================================================
   14. CSV IMPORT — ADMIN SUBMENU PAGE
   ========================================================= */
function uew_add_import_page() {
    add_submenu_page(
        'edit.php?post_type=events',
        'Import Events via CSV',
        'Import CSV',
        'manage_options',
        'uew-import-csv',
        'uew_render_import_page'
    );
}
add_action( 'admin_menu', 'uew_add_import_page' );

function uew_maybe_send_sample_csv() {
    if (
        ! is_admin() ||
        ! isset( $_GET['page'] ) || $_GET['page'] !== 'uew-import-csv' ||
        ! isset( $_GET['uew_sample'] ) ||
        ! current_user_can( 'manage_options' )
    ) return;

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="events-sample.csv"' );
    header( 'Pragma: no-cache' );

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'Event Name', 'Start Date', 'End Date', 'Location', 'Category', 'Link' ) );
    fputcsv( $out, array( 'GLT Daniel and Daniella Career Summit', '2026-05-01', '',           'Lekki, Lagos',           'external-ministry',  'https://example.com/register' ) );
    fputcsv( $out, array( 'W2W Conference HOTR Abuja',             '2026-05-09', '2026-05-10', 'Abuja',                  'external-ministry',  '' ) );
    fputcsv( $out, array( 'ACT Podcast Recording',                 '2026-05-14', '',           'Victoria Island, Lagos', 'internal-corporate', '' ) );
    fclose( $out );
    exit;
}
add_action( 'admin_init', 'uew_maybe_send_sample_csv' );

function uew_process_csv_upload() {
    if (
        ! isset( $_POST['uew_import_nonce'] ) ||
        ! wp_verify_nonce( $_POST['uew_import_nonce'], 'uew_import_csv' ) ||
        ! current_user_can( 'manage_options' )
    ) return null;

    if ( empty( $_FILES['uew_csv_file']['tmp_name'] ) || $_FILES['uew_csv_file']['error'] !== UPLOAD_ERR_OK ) {
        return array( 'error' => 'No file uploaded or upload error. Please try again.' );
    }

    $file = $_FILES['uew_csv_file']['tmp_name'];
    $ext  = strtolower( pathinfo( sanitize_file_name( $_FILES['uew_csv_file']['name'] ), PATHINFO_EXTENSION ) );
    if ( $ext !== 'csv' ) return array( 'error' => 'Invalid file type. Please upload a .csv file.' );

    $handle = fopen( $file, 'r' );
    if ( ! $handle ) return array( 'error' => 'Could not read the uploaded file.' );

    $imported   = 0;
    $skipped    = array();
    $row_num    = 0;
    $valid_cats = array( 'external-ministry', 'external-corporate', 'internal-corporate' );

    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        $row_num++;
        if ( $row_num === 1 && strtolower( trim( $row[0] ) ) === 'event name' ) continue;

        $row = array_map( 'trim', $row );
        if ( empty( $row[0] ) ) { $skipped[] = "Row $row_num: missing event name — skipped."; continue; }

        $event_name     = sanitize_text_field( $row[0] );
        $raw_start      = $row[1] ?? '';
        $raw_end        = $row[2] ?? '';
        $event_location = sanitize_text_field( $row[3] ?? '' );
        $cat_slug       = strtolower( sanitize_text_field( $row[4] ?? '' ) );
        $event_link     = esc_url_raw( $row[5] ?? '' );

        $start_ts = strtotime( $raw_start );
        if ( ! $raw_start || ! $start_ts ) {
            $skipped[] = "Row $row_num: \"$event_name\" — invalid or missing start date ($raw_start) — skipped.";
            continue;
        }
        $event_date = date( 'Y-m-d', $start_ts );

        $event_end_date = '';
        if ( $raw_end ) {
            $end_ts = strtotime( $raw_end );
            if ( ! $end_ts ) {
                $skipped[] = "Row $row_num: \"$event_name\" — invalid end date ($raw_end) — imported without end date.";
            } else {
                $event_end_date = date( 'Y-m-d', $end_ts );
            }
        }

        $post_id = wp_insert_post( array(
            'post_type'   => 'events',
            'post_title'  => $event_name,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $post_id ) ) {
            $skipped[] = "Row $row_num: \"$event_name\" — WP error: " . $post_id->get_error_message();
            continue;
        }

        update_post_meta( $post_id, 'event_date',     $event_date );
        update_post_meta( $post_id, 'event_end_date', $event_end_date );
        update_post_meta( $post_id, 'event_location', $event_location );
        update_post_meta( $post_id, 'event_link',     $event_link );

        if ( $cat_slug && in_array( $cat_slug, $valid_cats, true ) ) {
            $term = get_term_by( 'slug', $cat_slug, 'event_category' );
            if ( $term ) wp_set_post_terms( $post_id, array( $term->term_id ), 'event_category' );
        }

        $imported++;
    }

    fclose( $handle );
    return array( 'imported' => $imported, 'skipped' => $skipped, 'rows' => $row_num );
}

function uew_render_import_page() {
    $result     = uew_process_csv_upload();
    $sample_url = add_query_arg( array( 'post_type' => 'events', 'page' => 'uew-import-csv', 'uew_sample' => '1' ), admin_url( 'edit.php' ) );
    $manage_url = admin_url( 'edit.php?post_type=events' );
    ?>
    <div class="wrap" id="uew-import-wrap">
        <style>
            #uew-import-wrap h1 { margin-bottom: 8px; }
            .uew-import-box { background:#fff; border:1px solid #ddd; border-radius:4px; padding:28px 32px; max-width:720px; margin-top:20px; }
            .uew-import-box h2 { font-size:15px; margin:0 0 16px; color:#1d2327; }
            .uew-drop-zone { border:2px dashed #b0b0b0; border-radius:4px; padding:40px 24px; text-align:center; cursor:pointer; transition:border-color .2s,background .2s; background:#fafafa; position:relative; }
            .uew-drop-zone.uew-dragover { border-color:#c9a84c; background:#fffdf4; }
            .uew-drop-zone input[type="file"] { position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer; }
            .uew-drop-icon { font-size:36px; line-height:1; margin-bottom:10px; }
            .uew-drop-label { font-size:14px; color:#555; }
            .uew-drop-label strong { color:#1d2327; }
            .uew-drop-name { margin-top:12px; font-size:13px; color:#2271b1; font-weight:500; min-height:18px; }
            .uew-import-actions { display:flex; align-items:center; gap:14px; margin-top:20px; flex-wrap:wrap; }
            .uew-btn-primary { background:#0d0d0d; color:#fff; border:none; padding:10px 24px; border-radius:3px; font-size:13px; cursor:pointer; font-weight:500; transition:background .2s; }
            .uew-btn-primary:hover { background:#333; }
            .uew-btn-primary:disabled { background:#aaa; cursor:not-allowed; }
            .uew-sample-link { font-size:13px; color:#2271b1; text-decoration:none; }
            .uew-sample-link:hover { text-decoration:underline; }
            .uew-format-guide { margin-top:24px; border-top:1px solid #eee; padding-top:20px; }
            .uew-format-guide h3 { font-size:13px; margin:0 0 10px; color:#1d2327; }
            .uew-format-table { width:100%; border-collapse:collapse; font-size:13px; }
            .uew-format-table th { background:#f6f7f7; border:1px solid #e0e0e0; padding:7px 12px; text-align:left; font-weight:600; color:#1d2327; }
            .uew-format-table td { border:1px solid #e0e0e0; padding:7px 12px; color:#444; vertical-align:top; }
            .uew-format-table tr:nth-child(even) td { background:#fafafa; }
            .uew-col-req { color:#c0392b; font-weight:600; font-size:11px; }
            .uew-col-opt { color:#777; font-size:11px; }
            .uew-code { font-family:monospace; background:#f1f1f1; padding:1px 5px; border-radius:2px; font-size:12px; }
            .uew-notice { border-left:4px solid; padding:12px 16px; margin:20px 0 0; border-radius:2px; font-size:13px; }
            .uew-notice-success { border-color:#00a32a; background:#f0fff4; color:#1d2327; }
            .uew-notice-warning { border-color:#dba617; background:#fffbe6; color:#1d2327; }
            .uew-notice-error   { border-color:#d63638; background:#fff5f5; color:#1d2327; }
            .uew-notice strong  { display:block; margin-bottom:4px; }
            .uew-skip-list { margin:8px 0 0 16px; padding:0; }
            .uew-skip-list li { margin-bottom:4px; color:#555; }
            .uew-manage-link { display:inline-block; margin-top:12px; font-size:13px; }
        </style>

        <h1>📥 Import Events via CSV</h1>
        <p style="color:#555;font-size:13px;margin:0;">Upload a CSV file to bulk-import events. Each row becomes one event post.</p>

        <?php if ( $result !== null ) :
            if ( isset( $result['error'] ) ) : ?>
                <div class="uew-notice uew-notice-error"><strong>Import failed</strong><?php echo esc_html( $result['error'] ); ?></div>
            <?php else :
                $type = ( $result['imported'] > 0 && empty( $result['skipped'] ) ) ? 'success' : 'warning'; ?>
                <div class="uew-notice uew-notice-<?php echo $type; ?>">
                    <strong><?php echo $result['imported']; ?> event<?php echo $result['imported'] !== 1 ? 's' : ''; ?> imported.<?php if ( ! empty( $result['skipped'] ) ) echo ' ' . count( $result['skipped'] ) . ' row' . ( count( $result['skipped'] ) !== 1 ? 's' : '' ) . ' skipped.'; ?></strong>
                    <?php if ( ! empty( $result['skipped'] ) ) : ?>
                    <ul class="uew-skip-list"><?php foreach ( $result['skipped'] as $msg ) echo '<li>' . esc_html( $msg ) . '</li>'; ?></ul>
                    <?php endif; ?>
                </div>
                <?php if ( $result['imported'] > 0 ) : ?>
                <a href="<?php echo esc_url( $manage_url ); ?>" class="uew-manage-link button">View imported events →</a>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <div class="uew-import-box">
            <h2>Upload CSV File</h2>
            <form method="post" enctype="multipart/form-data" id="uew-csv-form">
                <?php wp_nonce_field( 'uew_import_csv', 'uew_import_nonce' ); ?>
                <div class="uew-drop-zone" id="uew-drop-zone">
                    <input type="file" name="uew_csv_file" id="uew-csv-file" accept=".csv,text/csv" required>
                    <div class="uew-drop-icon">📄</div>
                    <div class="uew-drop-label"><strong>Click to choose a file</strong> or drag and drop here</div>
                    <div class="uew-drop-name" id="uew-file-name">No file chosen</div>
                </div>
                <div class="uew-import-actions">
                    <button type="submit" class="uew-btn-primary" id="uew-submit-btn" disabled>Import Events</button>
                    <a href="<?php echo esc_url( $sample_url ); ?>" class="uew-sample-link" download>⬇ Download sample CSV</a>
                </div>
            </form>
            <div class="uew-format-guide">
                <h3>CSV column order (6 columns)</h3>
                <table class="uew-format-table">
                    <thead><tr><th>#</th><th>Column</th><th>Example</th><th>Notes</th></tr></thead>
                    <tbody>
                        <tr><td>1</td><td>Event Name <span class="uew-col-req">required</span></td><td>GLT Career Summit</td><td>The event title.</td></tr>
                        <tr><td>2</td><td>Start Date <span class="uew-col-req">required</span></td><td><span class="uew-code">2026-05-01</span></td><td><span class="uew-code">YYYY-MM-DD</span> is safest.</td></tr>
                        <tr><td>3</td><td>End Date <span class="uew-col-opt">optional</span></td><td><span class="uew-code">2026-05-10</span></td><td>Multi-day events only.</td></tr>
                        <tr><td>4</td><td>Location <span class="uew-col-opt">optional</span></td><td>Victoria Island, Lagos</td><td>Leave blank if none.</td></tr>
                        <tr><td>5</td><td>Category <span class="uew-col-opt">optional</span></td><td><span class="uew-code">external-ministry</span></td><td><span class="uew-code">external-ministry</span>, <span class="uew-code">external-corporate</span>, or <span class="uew-code">internal-corporate</span></td></tr>
                        <tr><td>6</td><td>Link <span class="uew-col-opt">optional</span></td><td><span class="uew-code">https://…</span></td><td>Registration / Attend URL shown in modal.</td></tr>
                    </tbody>
                </table>
                <p style="margin-top:10px;font-size:12px;color:#777;">A header row is optional — it is auto-detected and skipped.</p>
            </div>
        </div>
    </div>
    <script>
    (function() {
        var input = document.getElementById('uew-csv-file');
        var nameEl = document.getElementById('uew-file-name');
        var submitBtn = document.getElementById('uew-submit-btn');
        var dropZone = document.getElementById('uew-drop-zone');
        function onFileChosen(files) {
            nameEl.textContent = (files && files.length) ? files[0].name : 'No file chosen';
            submitBtn.disabled = !(files && files.length);
        }
        input.addEventListener('change', function() { onFileChosen(this.files); });
        dropZone.addEventListener('dragover',  function(e) { e.preventDefault(); this.classList.add('uew-dragover'); });
        dropZone.addEventListener('dragleave', function()  { this.classList.remove('uew-dragover'); });
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault(); this.classList.remove('uew-dragover');
            var files = e.dataTransfer.files;
            if (files.length) {
                try { var dt = new DataTransfer(); dt.items.add(files[0]); input.files = dt.files; } catch(ex) {}
                onFileChosen(files);
            }
        });
    })();
    </script>
    <?php
}


/* =========================================================
   15. FLUSH REWRITE RULES
   ========================================================= */
function uew_activate() {
    uew_register_events_cpt();
    uew_register_event_category_taxonomy();
    uew_seed_default_categories();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'uew_activate' );

function uew_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'uew_deactivate' );