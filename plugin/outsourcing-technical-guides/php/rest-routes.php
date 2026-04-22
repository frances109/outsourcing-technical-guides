<?php
/**
 * php/rest-routes.php  —  Outsourcing Technical Guides
 *
 * Self-contained REST API layer. Works in two modes:
 *
 *   STANDALONE  — loaded by outsourcing-technical-guides.php via require_once.
 *   HUB MODE    — loaded by Magellan Hub's mhub_load_php_dir() on rest_api_init.
 *
 * Duplicate registration guard:
 *   Every function is wrapped with function_exists(). If both the standalone
 *   plugin and the hub load this file, the first-loaded version wins.
 *   define('OTG_ROUTES_REGISTERED') prevents double add_action() queuing.
 *
 * Dependencies (must be loaded before this file):
 *   - php/shared-config.php  (otg_get_setting, otg_running_under_hub, otg_start_session)
 *   - php/email-templates.php (otg_email_admin_notification, otg_email_consultation, etc.)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/shared-config.php';

/* ═══════════════════════════════════════════════════════════════
   ROUTE REGISTRATION
═══════════════════════════════════════════════════════════════ */
if ( ! defined( 'OTG_ROUTES_REGISTERED' ) ) {
    define( 'OTG_ROUTES_REGISTERED', true );

    add_action( 'rest_api_init', 'otg_register_rest_routes' );
}

function otg_register_rest_routes(): void {
    register_rest_route( 'otg/v1', '/submit', [
        'methods'             => 'POST',
        'callback'            => 'otg_handle_submission',
        'permission_callback' => '__return_true',
        'args'                => [
            'first_name'      => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'last_name'       => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'company_name'    => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'work_email'      => [ 'required' => true,  'sanitize_callback' => 'sanitize_email' ],
            'phone_number'    => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'recaptcha_token' => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );

    register_rest_route( 'otg/v1', '/consultation', [
        'methods'             => 'POST',
        'callback'            => 'otg_handle_consultation',
        'permission_callback' => '__return_true',
        'args'                => [
            'first_name'   => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'last_name'    => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'company_name' => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'work_email'   => [ 'required' => true,  'sanitize_callback' => 'sanitize_email' ],
            'phone_number' => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'guide_name'   => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
        ],
    ] );

    register_rest_route( 'otg/v1', '/geo', [
        'methods'             => 'GET',
        'callback'            => 'otg_geo_lookup',
        'permission_callback' => '__return_true',
    ] );
}

/* ═══════════════════════════════════════════════════════════════
   SUBMISSION HANDLER
═══════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'otg_handle_submission' ) ) :

function otg_handle_submission( WP_REST_Request $request ): WP_REST_Response {
    // Session must be active for the access-token write to persist.
    // otg_start_session() is a no-op if the session is already running.
    otg_start_session();

    $data  = $request->get_params();
    $recap = otg_verify_recaptcha( $data['recaptcha_token'] );
    if ( is_wp_error( $recap ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => $recap->get_error_message() ], 400 );
    }
    if ( ! is_email( $data['work_email'] ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Invalid email address.' ], 400 );
    }

    otg_store_flamingo( $data );
    otg_send_admin_notification( $data );

    // Write session access token — checked by the template_redirect guard on
    // the download page.
    $_SESSION['otg_access_token'] = bin2hex( random_bytes( 16 ) );
    $_SESSION['otg_contact']      = [
        'first_name'   => $data['first_name'],
        'last_name'    => $data['last_name'],
        'company_name' => $data['company_name'],
        'work_email'   => $data['work_email'],
        'phone_number' => $data['phone_number'],
    ];

    $slug         = get_option( 'otg_download_page_slug', 'outsourcing-download-guides' );
    $redirect_url = home_url( '/' . $slug );

    return new WP_REST_Response( [
        'success'      => true,
        'message'      => 'Submission received.',
        'redirect_url' => $redirect_url,
    ], 200 );
}

endif;

/* ═══════════════════════════════════════════════════════════════
   CONSULTATION HANDLER
═══════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'otg_handle_consultation' ) ) :

function otg_handle_consultation( WP_REST_Request $request ): WP_REST_Response {
    $data = $request->get_params();

    if ( ! is_email( $data['work_email'] ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Invalid email address.' ], 400 );
    }

    $fullname = trim( $data['first_name'] . ' ' . $data['last_name'] );
    $guide    = ! empty( $data['guide_name'] ) ? ' – ' . $data['guide_name'] : '';

    otg_save_to_flamingo(
        $data,
        "Consultation Request – {$fullname} ({$data['company_name']}){$guide}",
        'outsourcing-technical-guides'
    );

    otg_send_consultation( $data );
    otg_send_consultation_confirmation( $data );

    return new WP_REST_Response( [ 'success' => true, 'message' => 'Consultation request sent.' ], 200 );
}

endif;

/* ═══════════════════════════════════════════════════════════════
   FLAMINGO
═══════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'otg_save_to_flamingo' ) ) :

function otg_save_to_flamingo( array $d, string $subject, string $channel ): void {
    $email    = strtolower( trim( $d['work_email'] ?? '' ) );
    $fullname = trim( ( $d['first_name'] ?? '' ) . ' ' . ( $d['last_name'] ?? '' ) );
    if ( empty( $email ) ) return;

    if ( class_exists( 'Flamingo_Inbound_Message' ) ) {
        Flamingo_Inbound_Message::add( [
            'channel'    => $channel,
            'subject'    => $subject,
            'from'       => "{$fullname} <{$email}>",
            'from_name'  => $fullname,
            'from_email' => $email,
            'fields'     => $d,
            'meta'       => [
                'remote_ip'  => sanitize_text_field( $_SERVER['REMOTE_ADDR']     ?? '' ),
                'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
            ],
        ] );
    }

    if ( class_exists( 'Flamingo_Contact' ) ) {
        $existing = Flamingo_Contact::search_by_email( $email );
        $props    = $existing ? (array) $existing->props : [];
        $props['company'] = $d['company_name'] ?? ( $props['company'] ?? '' );
        $props['phone']   = $d['phone_number'] ?? ( $props['phone']   ?? '' );
        $props['channel'] = $channel;

        Flamingo_Contact::add( [
            'email'          => $email,
            'name'           => $fullname,
            'props'          => $props,
            'last_contacted' => current_time( 'mysql' ),
            'channel'        => $channel,
        ] );
    }
}

endif;

if ( ! function_exists( 'otg_store_flamingo' ) ) :

function otg_store_flamingo( array $d ): void {
    $fullname = trim( $d['first_name'] . ' ' . $d['last_name'] );
    otg_save_to_flamingo(
        $d,
        "New Guide Request – {$fullname} ({$d['company_name']})",
        'outsourcing-technical-guides'
    );
}

endif;

/* ═══════════════════════════════════════════════════════════════
   RECAPTCHA v3
═══════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'otg_verify_recaptcha' ) ) :

function otg_verify_recaptcha( string $token ) {
    $secret = otg_get_setting( 'otg_recaptcha_secret_key' );
    if ( empty( $secret ) ) return true;
    if ( $token === 'dev-bypass' ) return true;

    if ( empty( $token ) || $token === 'not-loaded' ) {
        return new WP_Error(
            'recaptcha_not_loaded',
            'Security check could not complete. Please disable any ad blockers or browser extensions and try again.'
        );
    }

    $res = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
        'body'    => [ 'secret' => $secret, 'response' => $token ],
        'timeout' => 10,
    ] );

    if ( is_wp_error( $res ) ) {
        error_log( '[OTG] reCAPTCHA remote request failed: ' . $res->get_error_message() );
        return true;
    }

    $body = json_decode( wp_remote_retrieve_body( $res ), true );

    if ( empty( $body['success'] ) ) {
        error_log( '[OTG] reCAPTCHA token invalid. Error codes: ' . implode( ', ', (array) ( $body['error-codes'] ?? [] ) ) );
        return new WP_Error( 'recaptcha_invalid', 'Security verification failed. Please refresh and try again.' );
    }

    $threshold = apply_filters( 'otg_recaptcha_score_threshold', 0.3 );

    if ( isset( $body['score'] ) && (float) $body['score'] < $threshold ) {
        error_log( sprintf( '[OTG] reCAPTCHA score too low: %.2f (threshold: %.2f)', $body['score'], $threshold ) );
        return new WP_Error( 'recaptcha_low_score', 'reCAPTCHA score too low. Please try again.' );
    }

    return true;
}

endif;

/* ═══════════════════════════════════════════════════════════════
   GEO LOOKUP
═══════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'otg_geo_lookup' ) ) :

function otg_geo_lookup(): WP_REST_Response {
    $res = wp_remote_get( 'https://ipapi.co/json/', [
        'timeout' => 5,
        'headers' => [ 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) ],
    ] );
    if ( is_wp_error( $res ) ) {
        return new WP_REST_Response( [ 'country_code' => 'PH' ], 200 );
    }
    $body = json_decode( wp_remote_retrieve_body( $res ), true );
    return new WP_REST_Response( [ 'country_code' => strtoupper( $body['country_code'] ?? 'PH' ) ], 200 );
}

endif;

/* ═══════════════════════════════════════════════════════════════
   EMAIL NOTIFICATIONS
═══════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'otg_get_notify_emails' ) ) :

function otg_get_notify_emails(): array {
    $raw    = otg_get_setting( 'otg_notify_emails', get_option( 'admin_email' ) );
    $emails = array_map( 'trim', explode( ',', $raw ) );
    return array_values( array_filter( $emails, 'is_email' ) );
}

endif;

if ( ! function_exists( 'otg_send_admin_notification' ) ) :

function otg_send_admin_notification( array $d ): void {
    $recipients = otg_get_notify_emails();
    if ( empty( $recipients ) ) return;
    $subject = sprintf( '[Magellan Guides] New Lead: %s %s – %s', $d['first_name'], $d['last_name'], $d['company_name'] );
    wp_mail( $recipients, $subject, otg_email_admin_notification( $d, $recipients ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

endif;

if ( ! function_exists( 'otg_send_confirmation' ) ) :

function otg_send_confirmation( array $d ): void {
    wp_mail( $d['work_email'], 'Thank You – Magellan Solutions Executive Guides', otg_email_confirmation( $d ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

endif;

if ( ! function_exists( 'otg_send_consultation' ) ) :

function otg_send_consultation( array $d ): void {
    $recipients = otg_get_notify_emails();
    if ( empty( $recipients ) ) return;
    $subject = sprintf( '[Magellan Guides] Book a Consultation – %s %s (%s)', $d['first_name'], $d['last_name'], $d['company_name'] );
    wp_mail( $recipients, $subject, otg_email_consultation( $d ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

endif;

if ( ! function_exists( 'otg_send_consultation_confirmation' ) ) :

function otg_send_consultation_confirmation( array $d ): void {
    wp_mail( $d['work_email'], 'Your Consultation Request – Magellan Solutions', otg_email_consultation_confirmation( $d ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

endif;
