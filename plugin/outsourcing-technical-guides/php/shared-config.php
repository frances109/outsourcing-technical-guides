<?php
/**
 * php/shared-config.php  —  Outsourcing Technical Guides
 *
 * Unified configuration layer. Loaded in both modes:
 *   - Standalone: required by outsourcing-technical-guides.php
 *   - Hub mode:   required by php/rest-routes.php
 *
 * Defines otg_get_setting(), otg_running_under_hub(), and otg_start_session()
 * exactly once, guarded with function_exists().
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'otg_running_under_hub' ) ) :

function otg_running_under_hub(): bool {
    if ( ! function_exists( 'mhub_get_project_by_slug' ) ) return false;
    $slug    = get_option( 'otg_form_page_slug', 'outsourcing-technical-guides' );
    $project = mhub_get_project_by_slug( $slug );
    return ( $project && $project->status === 'active' );
}

endif;

if ( ! function_exists( 'otg_get_setting' ) ) :

/**
 * Retrieve a setting, falling back to Magellan Hub global values when running
 * under the hub and the plugin-level option is blank.
 *
 * @param string $option  The WP option key.
 * @param string $default Fallback value.
 * @return string
 */
function otg_get_setting( string $option, string $default = '' ): string {
    $value = get_option( $option, '' );
    if ( $value !== '' ) return $value;

    if ( otg_running_under_hub() ) {
        switch ( $option ) {
            case 'otg_recaptcha_site_key':
                return get_option( 'mhub_recaptcha_site', $default );
            case 'otg_recaptcha_secret_key':
                return get_option( 'mhub_recaptcha_secret', $default );
            case 'otg_notify_emails':
                return get_option( 'mhub_notify_emails', get_option( 'admin_email', $default ) );
        }
    }

    return $default;
}

endif;

if ( ! function_exists( 'otg_start_session' ) ) :

/**
 * Start a PHP session if not already active.
 * Called on 'init' priority 1 by the standalone plugin.
 * Also called defensively inside the REST submission handler.
 *
 * Session cookie is set with SameSite=Lax + Secure (on HTTPS) so the
 * PHPSESSID survives the JS redirect from the form page to the download page.
 */
function otg_start_session(): void {
    if ( session_status() !== PHP_SESSION_NONE ) return;

    $lifetime = 30 * MINUTE_IN_SECONDS;

    session_set_cookie_params( [
        'lifetime' => $lifetime,
        'path'     => COOKIEPATH  ?: '/',
        'domain'   => COOKIE_DOMAIN ?: '',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ] );

    session_start();
}

endif;
