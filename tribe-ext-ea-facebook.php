<?php
/**
 * Plugin Name:       The Events Calendar Extension: Facebook Dev for EA
 * Plugin URI:        https://theeventscalendar.com/extensions/facebook-ea/
 * GitHub Plugin URI: https://github.com/mt-support/tribe-ext-ea-facebook
 * Description:       Provides a new Facebook Dev origin for Event Aggregator.
 * Version:           1.0.2
 * Extension Class:   Tribe\Extensions\EA_FB\Facebook_Dev_Origin
 * Author:            Modern Tribe, Inc.
 * Author URI:        http://m.tri.be/1971
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tribe-ext-ea-facebook
 *
 *     This plugin is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     any later version.
 *
 *     This plugin is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *     GNU General Public License for more details.
 */

namespace Tribe\Extensions\EA_FB;

use Tribe__Events__Aggregator__Cron;
use Tribe__Events__Aggregator__Page;
use Tribe__Events__Aggregator__Record__Abstract;
use Tribe__Events__Main;
use Tribe__Extension;
use Tribe__Settings;

/**
 * Define Constants
 */

if ( ! defined( __NAMESPACE__ . '\NS' ) ) {
	define( __NAMESPACE__ . '\NS', __NAMESPACE__ . '\\' );
}

if ( ! defined( NS . 'PLUGIN_TEXT_DOMAIN' ) ) {
	// `Tribe\Extensions\EA_FB\PLUGIN_TEXT_DOMAIN` is defined
	define( NS . 'PLUGIN_TEXT_DOMAIN', 'tribe-ext-ea-facebook' );
}

// Do not load unless Tribe Common is fully loaded and our class does not yet exist.
if (
	class_exists( 'Tribe__Extension' )
	&& ! class_exists( 'Tribe\Extensions\EA_FB\Facebook_Dev_Origin' )
) {
	/**
	 * Extension main class, class begins loading on init() function.
	 */
	class Facebook_Dev_Origin extends Tribe__Extension {

		/**
		 * Setup the Extension's properties.
		 *
		 * This always executes even if the required plugins are not present.
		 */
		public function construct() {

			// When the `tribe_aggregator_import_setting_origins` filter was added
			$this->add_required_plugin( 'Tribe__Events__Main', '4.6.24.1' );

		}

		/**
		 * Get origin label text.
		 *
		 * @return string
		 */
		public static function get_origin() {

			return 'facebook-dev';

		}

		/**
		 * Get origin label text.
		 *
		 * @return string
		 */
		public static function get_origin_label() {

			return __( 'Facebook Dev', 'tribe-ext-ea-facebook' );

		}

		/**
		 * Get origin URL regex.
		 *
		 * @return string
		 */
		public static function get_origin_url_regex() {

			return '^(https?:\/\/)?(www\.)?facebook\.[a-z]{2,3}(\.[a-z]{2})?\/';

		}

		/**
		 * Get origin example URL.
		 *
		 * @return string
		 */
		public static function get_origin_example_url() {

			return 'facebook.com/event/1234567891012345/';

		}

		/**
		 * Get whether or not this origin requires OAuth to import events.
		 *
		 * @return boolean Whether this origin requires OAuth to import events.
		 */
		public static function get_origin_oauth_requirement() {

			return true;

		}

		/**
		 * Get origin default source.
		 *
		 * @return string
		 */
		public static function get_origin_default_source() {

			return self::get_origin_account_source_url();

		}

		/**
		 * Get origin account source URL.
		 *
		 * @return string
		 */
		public static function get_origin_account_source_url() {

			return 'https://www.facebook.com/me';

		}

		/**
		 * Check if we have a sufficient version of PHP. Admin notice if we don't and user should see it.
		 *
		 * @link https://theeventscalendar.com/knowledgebase/php-version-requirement-changes/
		 *
		 * @return bool
		 */
		private function php_version_check() {
			$php_required_version = '5.6';

			if ( version_compare( PHP_VERSION, $php_required_version, '<' ) ) {
				if (
					is_admin()
					&& current_user_can( 'activate_plugins' )
				) {
					$message = '<p>';

					$message .= sprintf( __( '%s requires PHP version %s or newer to work. Please contact your website host and inquire about updating PHP.', PLUGIN_TEXT_DOMAIN ), $this->get_name(), $php_required_version );

					$message .= sprintf( ' <a href="%1$s">%1$s</a>', 'https://wordpress.org/about/requirements/' );

					$message .= '</p>';

					tribe_notice( $this->get_name(), $message, 'type=error' );
				}

				return false;
			}

			return true;
		}

		/**
		 * Extension initialization and hooks.
		 */
		public function init() {

			// Load plugin textdomain
			load_plugin_textdomain( 'tribe-ext-ea-facebook', false, basename( dirname( __FILE__ ) ) . '/languages/' );

			if ( ! $this->php_version_check() ) {
				return;
			}

			// Register origin.
			$this->add_registered_origin();

			// Enable custom origin.
			add_filter( 'tribe_aggregator_record_by_origin', [ $this, 'init_origin_record' ], 10, 3 );
			add_filter( 'tribe_aggregator_source_origin_regexp', [ $this, 'add_origin_regexp' ] );
			add_filter( 'tribe_aggregator_service_post_import_args', [ $this, 'add_site_url_to_post_args' ] );
			add_filter( 'tribe_aggregator_event_translate_service_data_field_map', [ $this, 'add_field_map' ] );
			add_filter( 'tribe_aggregator_event_translate_service_data_venue_field_map', [ $this, 'add_venue_field_map' ] );
			add_filter( 'tribe_aggregator_event_translate_service_data_organizer_field_map', [ $this, 'add_organizer_field_map' ] );
			add_filter( 'tribe_aggregator_import_validate_meta_by_origin', [ $this, 'validate_import_meta_by_origin' ], 10, 3 );
			add_filter( 'tribe_aggregator_clean_unsupported', [ $this, 'stop_unsupported_clean' ], 10, 3 );

			// Add Global ID origins.
			add_filter( 'tribe_global_id_valid_types', [ $this, 'register_global_id_valid_types' ] );
			add_filter( 'tribe_global_id_type_origins', [ $this, 'register_global_id_type_origins' ] );

			// Add custom origin to UI.
			add_filter( 'tribe_addons_tab_fields', [ $this, 'add_addon_fields' ] );
			add_filter( 'tribe_aggregator_fields', [ $this, 'add_ea_settings_fields' ], 10, 4 );
			add_filter( 'tribe_aggregator_import_setting_origins', [ $this, 'register_import_setting_origin' ] );
			add_action( 'tribe_events_status_third_party', [ $this, 'add_origin_to_status' ] );
			add_action( 'tribe_events_aggregator_import_form', [ $this, 'add_origin_to_import_form' ], 10, 2 );
			add_action( 'tribe_events_aggregator_refine_keyword_exclusions', [ $this, 'add_origin_to_refine_exclusions' ] );
			add_action( 'tribe_events_aggregator_refine_location_exclusions', [ $this, 'add_origin_to_refine_exclusions' ] );

			// Handle disconnecting of token.
			add_action( 'current_screen', [ $this, 'maybe_disconnect_ea_token' ] );

			include_once __DIR__ . '/src/Tribe/Record.php';

			// Filter origin import data to Add Site to URL
			add_filter( 'tribe_aggregator_get_import_data_args', [ 'Tribe\Extensions\EA_FB\Facebook_Dev_Origin__Record', 'filter_add_site_get_import_data' ], 10, 2 );

			// Filter eventbrite events to preserve some fields that aren't supported by origin
			add_filter( 'tribe_aggregator_before_update_event', [ 'Tribe\Extensions\EA_FB\Facebook_Dev_Origin__Record', 'filter_event_to_preserve_fields' ], 10, 2 );

		}

		/**
		 * Initialize record for origin.
		 *
		 * @param Tribe__Events__Aggregator__Record__Abstract|null $record Record object for given origin.
		 * @param string                                           $origin Import origin.
		 * @param \WP_Post|null                                    $post   Record post data.
		 *
		 * @return Tribe__Events__Aggregator__Record__Abstract|null $record Record object for given origin.
		 */
		public function init_origin_record( $record, $origin, $post ) {

			$origins = [
				self::get_origin(),
				'ea/' . self::get_origin(),
			];

			if ( ! in_array( $origin, $origins, true ) ) {
				return $record;
			}

			return new Facebook_Dev_Origin__Record( $post );

		}

		/**
		 * Add origin regexp pattern to list of supported patterns.
		 *
		 * @param array $origins Origin regexp patterns.
		 *
		 * @return array Origin regexp patterns.
		 */
		public function add_origin_regexp( $origins ) {

			$origin = self::get_origin();

			$origins[$origin] = self::get_origin_url_regex();

			return $origins;

		}

		/**
		 * Add origin to list of registered origins.
		 */
		public function add_registered_origin() {

			/** @var \Tribe__Events__Aggregator__API__Origins $origins */
			$origins = tribe( 'events-aggregator.main' )->api( 'origins' );

			$available_origins = $origins->origins;

			$origin = self::get_origin();

			$available_origins[$origin] = (object) [
				'id'       => $origin,
				'name'     => self::get_origin_label(),
				'disabled' => false,
				'upsell'   => false,
			];

			$origins->origins = $available_origins;

			$origin = self::get_origin();
			$source = $this->get_source_id();

			Tribe__Events__Aggregator__Record__Abstract::$unique_id_fields[$origin] = [
				'source' => $source,
				'target' => $this->get_target_id(),
			];

			Tribe__Events__Aggregator__Record__Abstract::$unique_venue_id_fields[$origin] = [
				'source' => $source,
				'target' => $this->get_target_id( 'venue' ),
			];

			Tribe__Events__Aggregator__Record__Abstract::$unique_organizer_id_fields[$origin] = [
				'source' => $source,
				'target' => $this->get_target_id( 'organizer' ),
			];

		}

		/**
		 * Get Source ID for use with fields.
		 *
		 * @return string Source ID.
		 */
		public function get_source_id() {

			$origin = self::get_origin();

			$source = str_replace( '-', '_', $origin );
			$source .= '_id';

			return $source;

		}

		/**
		 * Get Target ID for use with fields.
		 *
		 * @param string $type Type of ID.
		 *
		 * @return string Target ID.
		 */
		public function get_target_id( $type = '' ) {

			$origin = self::get_origin();

			$target = str_replace( [ '_', '-' ], ' ', $origin );
			$target = $type . ' ' . $target;
			$target = ucwords( trim( $target ) );
			$target = str_replace( ' ', '', $target ) . 'ID';

			return $target;

		}

		/**
		 * Register custom origins for Global_ID.
		 *
		 * @param array $valid_types List of origin types.
		 *
		 * @return array List of origin types.
		 */
		public function register_global_id_valid_types( $valid_types ) {
			$valid_types[] = 'facebook-dev';

			return $valid_types;
		}

		/**
		 * Register custom origin URLs for Global_ID.
		 *
		 * @param array $type_origins List of origin URLs.
		 *
		 * @return array List of origin URLs.
		 */
		public function register_global_id_type_origins( $type_origins ) {
			$type_origins['facebook-dev'] = 'facebook.com';

			return $type_origins;
		}

		/**
		 * Add add-on fields for origin.
		 *
		 * @param array $addon_fields Add-on fields.
		 *
		 * @return array Add-on fields.
		 */
		public function add_addon_fields( $addon_fields ) {

			$origin       = sanitize_text_field( self::get_origin() );
			$origin_label = self::get_origin_label();

			$is_token_valid   = $this->is_ea_token_valid();
			$auth_url         = $this->get_auth_url();
			$disconnect_url   = $this->get_disconnect_url();
			$disconnect_label = '';

			if ( ! $is_token_valid ) {
				/* translators: The placeholder used is for the Origin label */
				$button_label = _x( 'Connect to %1$s', 'button text for connection to EA origin', 'tribe-ext-ea-facebook' );
			} else {
				/* translators: The placeholder used is for the Origin label */
				$button_label = _x( 'Refresh your connection to %1$s', 'button text for refreshing connection with EA origin', 'tribe-ext-ea-facebook' );

				$disconnect_label = __( 'Disconnect', 'tribe-ext-ea-facebook' );
			}

			$button_label = sprintf( $button_label, $origin_label );

			/* translators: The placeholder used is for the Origin label */
			$info_text = _x( 'You need to connect Event Aggregator to %1$s to import your events.', 'description of connection for EA origin', 'tribe-ext-ea-facebook' );
			$info_text = sprintf( $info_text, $origin_label );

			/* translators: The placeholder used is for the Origin label */
			$invalid_text = _x( 'You need to connect to %1$s for Event Aggregator to work properly', 'notice for needing connection for EA origin', 'tribe-ext-ea-facebook' );
			$invalid_text = sprintf( $invalid_text, $origin_label );

			ob_start();
			include __DIR__ . '/src/admin-views/addon-fields.php';
			$token_html = ob_get_clean();

			$custom_addon_fields = [
				$origin . '-start'        => [
					'type' => 'html',
					'html' => '<h3>' . esc_html( $origin_label ) . '</h3>',
				],
				$origin . '-info-box'     => [
					'type' => 'html',
					'html' => '<p>' . esc_html( $info_text ) . '</p>',
				],
				$origin . '_token_button' => [
					'type' => 'html',
					'html' => $token_html,
				],
			];

			$addon_fields = array_merge( $addon_fields, $custom_addon_fields );

			return $addon_fields;

		}

		/**
		 * Add fields to EA settings page.
		 *
		 * @param array $fields                  List of aggregator fields.
		 * @param array $origin_post_statuses    List of post statuses.
		 * @param array $origin_categories       List of event categories.
		 * @param array $origin_show_map_options List of show map options.
		 *
		 * @return array List of aggregator fields.
		 */
		public function add_ea_settings_fields( $fields, $origin_post_statuses, $origin_categories, $origin_show_map_options = [] ) {

			$origin       = sanitize_text_field( self::get_origin() );
			$origin_label = self::get_origin_label();

			/* translators: The placeholder used is for the Origin label */
			$heading_text = _x( '%1$s Import Settings', 'heading text for origin import settings', 'tribe-ext-ea-facebook' );
			$heading_text = sprintf( $heading_text, $origin_label );

			$heading = '<h3 id="tribe-import-%1$s-settings">%2$s</h3>';
			$heading = sprintf( $heading, esc_attr( $origin ), esc_html( $heading_text ) );

			/* translators: The placeholder used is for the Origin label */
			$status_tooltip = _x( 'The default post status for events imported for %1$s', 'tooltip text for default post status EA origin setting', 'tribe-ext-ea-facebook' );
			$status_tooltip = sprintf( $status_tooltip, $origin_label );

			/* translators: The placeholder used is for the Origin label */
			$category_tooltip = _x( 'The default event category for events imported for %1$s', 'tooltip text for default event category EA origin setting', 'tribe-ext-ea-facebook' );
			$category_tooltip = sprintf( $category_tooltip, $origin_label );

			/* translators: The placeholder used is for the Origin label */
			$show_map_tooltip = _x( 'Show Google Map by default on imported event and venues for %1$s', 'tooltip text for default event map status EA origin setting', 'tribe-ext-ea-facebook' );
			$show_map_tooltip = sprintf( $show_map_tooltip, $origin_label );

			$custom_fields = [
				$origin . '-defaults'                                  => [
					'type'     => 'html',
					'html'     => $heading,
					'priority' => 18.1,
				],
				'tribe_aggregator_default_' . $origin . '_post_status' => [
					'type'            => 'dropdown',
					'label'           => esc_html__( 'Default Status', 'tribe-ext-ea-facebook' ),
					'tooltip'         => esc_html( $status_tooltip ),
					'size'            => 'medium',
					'validation_type' => 'options',
					'default'         => '',
					'can_be_empty'    => true,
					'parent_option'   => Tribe__Events__Main::OPTIONNAME,
					'options'         => $origin_post_statuses,
					'priority'        => 18.2,
				],
				'tribe_aggregator_default_' . $origin . '_category'    => [
					'type'            => 'dropdown',
					'label'           => esc_html__( 'Default Event Category', 'tribe-ext-ea-facebook' ),
					'tooltip'         => esc_html( $category_tooltip ),
					'size'            => 'medium',
					'validation_type' => 'options',
					'default'         => '',
					'can_be_empty'    => true,
					'parent_option'   => Tribe__Events__Main::OPTIONNAME,
					'options'         => $origin_categories,
					'priority'        => 18.3,
				],
			];

			if ( ! empty( $origin_show_map_options ) ) {
				$custom_fields['tribe_aggregator_default_' . $origin . '_show_map'] = [
					'type'            => 'dropdown',
					'label'           => esc_html__( 'Show Google Map', 'tribe-ext-ea-facebook' ),
					'tooltip'         => esc_html( $show_map_tooltip ),
					'size'            => 'medium',
					'validation_type' => 'options',
					'default'         => 'no',
					'can_be_empty'    => true,
					'parent_option'   => Tribe__Events__Main::OPTIONNAME,
					'options'         => $origin_show_map_options,
					'priority'        => 18.4,
				];
			}

			$fields = array_merge( $fields, $custom_fields );

			return $fields;

		}

		/**
		 * Register import setting origin for default import setting handling.
		 *
		 * @param array $origins List of origins that support import settings.
		 *
		 * @return array List of origins that support import settings.
		 */
		public function register_import_setting_origin( $origins ) {
			$origins[] = 'facebook-dev';

			return $origins;
		}

		/**
		 * Add Site URL to args to send to post import.
		 *
		 * @param array $args Args to send to post import.
		 *
		 * @return array Args to send to post import.
		 */
		public function add_site_url_to_post_args( $args ) {

			$args['site'] = site_url();

			return $args;

		}

		/**
		 * Add field mapping for origin.
		 *
		 * @param array $field_map Field mapping.
		 *
		 * @return array Field mapping.
		 */
		public function add_field_map( $field_map ) {

			$source = $this->get_source_id();
			$target = $this->get_target_id();

			$field_map[$source] = $target;

			return $field_map;

		}

		/**
		 * Add venue field mapping for origin.
		 *
		 * @param array $field_map Field mapping.
		 *
		 * @return array Field mapping.
		 */
		public function add_venue_field_map( $field_map ) {

			$source = $this->get_source_id();
			$target = $this->get_target_id( 'venue' );

			$field_map[$source] = $target;

			return $field_map;

		}

		/**
		 * Add organizer field mapping for origin.
		 *
		 * @param array $field_map Field mapping.
		 *
		 * @return array Field mapping.
		 */
		public function add_organizer_field_map( $field_map ) {

			$source = $this->get_source_id();
			$target = $this->get_target_id( 'organizer' );

			$field_map[$source] = $target;

			return $field_map;

		}

		/**
		 * Validate the import meta for the origin.
		 *
		 * @param array|\WP_Error $result The updated/validated meta array or a `WP_Error` if the validation failed.
		 * @param string          $origin Origin name.
		 * @param array           $meta   Import meta.
		 *
		 * @return array|\WP_Error $result The updated/validated meta array or a `WP_Error` if the validation failed.
		 */
		public function validate_import_meta_by_origin( $result, $origin, $meta ) {

			if ( self::get_origin() !== $origin ) {
				return $result;
			}

			$origin_regex = self::get_origin_url_regex();

			if ( empty( $meta['source'] ) || ! preg_match( '/' . $origin_regex . '/', $meta['source'] ) ) {
				return new \WP_Error( 'not-eventbrite-url', __( 'Please provide a Facebook URL when importing from Facebook.', 'tribe-ext-ea-facebook' ) );
			}

			return $result;

		}

		/**
		 * Stop unsupported post cleaning for our origin.
		 *
		 * @param bool                                            $should_delete Whether the unsupported post should be
		 *                                                                       deleted or not; defaults to true.
		 * @param \Tribe__Events__Aggregator__Record__Unsupported $record        Record object.
		 * @param \WP_Post                                        $post          Record post object.
		 *
		 * @return bool Whether the unsupported post should be deleted or not; defaults to true.
		 */
		public function stop_unsupported_clean( $should_delete, $record, $post ) {

			if ( ! $should_delete ) {
				return $should_delete;
			}

			$origins = [
				self::get_origin(),
				'ea/' . self::get_origin(),
			];

			if ( ! in_array( $record->origin, $origins, true ) ) {
				return false;
			}

			return $should_delete;

		}

		/**
		 * Add origin to status table.
		 *
		 * @param array $indicator_icons List of indicator icons.
		 */
		public function add_origin_to_status( $indicator_icons ) {

			$origin       = sanitize_text_field( self::get_origin() );
			$origin_label = self::get_origin_label();

			$is_token_valid = $this->is_ea_token_valid();
			$auth_url       = $this->get_auth_url();

			$indicator = 'good';
			$notes     = '&nbsp;';
			$text      = 'Connected';

			$needs_connection = false;

			if ( ! $is_token_valid && self::get_origin_oauth_requirement() ) {
				$needs_connection = true;
			}

			if ( $needs_connection ) {
				$indicator = 'warning';

				/* translators: The placeholder used is for the Origin label */
				$text = _x( 'You have not connected Event Aggregator to %1$s', 'no connection notice for EA origin', 'tribe-ext-ea-facebook' );
				$text = sprintf( $text, $origin_label );

				/* translators: The placeholder used is for the Origin label */
				$link_text = _x( 'Connect to %1$s', 'link for connecting EA origin', 'tribe-ext-ea-facebook' );
				$link_text = sprintf( $link_text, $origin_label );

				$notes = '<a href="' . esc_url( $auth_url ) . '">' . esc_html( $link_text ) . '</a>';
			} else {
				$indicator = 'warning';

				/* translators: The placeholder used is for the Origin label */
				$text = _x( 'Limited connectivity with %1$s', 'notice for unavailable oauth for EA origin', 'tribe-ext-ea-facebook' );
				$text = sprintf( $text, $origin_label );

				$notes = esc_html__( 'The service has disabled oAuth. Some types of events may not import.', 'tribe-ext-ea-facebook' );
			}

			$origin_logo = plugins_url( 'src/resources/images/' . $origin . '.png', __FILE__ );

			include __DIR__ . '/src/admin-views/status.php';

		}

		/**
		 * Add origin to import form.
		 *
		 * @param string $aggregator_action Aggregator action (new or edit).
		 * @param array  $form_args         Form arguments.
		 */
		public function add_origin_to_import_form( $aggregator_action, $form_args ) {

			$origin_regex       = self::get_origin_url_regex();
			$origin_example_url = self::get_origin_example_url();
			$default_eb_source  = self::get_origin_default_source();

			$origin       = sanitize_text_field( self::get_origin() );
			$origin_label = self::get_origin_label();

			$is_token_valid = $this->is_ea_token_valid();
			$auth_url       = $this->get_auth_url();

			$form_args['origin_slug'] = $origin;

			$field = (object) [
				'label'       => __( 'Import Type:', 'tribe-ext-ea-facebook' ),
				'placeholder' => __( 'Select Import Type', 'tribe-ext-ea-facebook' ),
				'help'        => __( 'One-time imports include all currently listed events, while scheduled imports automatically grab new events and updates on a set schedule. Single events can be added via a one-time import.', 'tribe-ext-ea-facebook' ),
				'source'      => $origin . '_import_type',
			];

			$frequency = (object) [
				'placeholder' => __( 'Scheduled import frequency', 'tribe-ext-ea-facebook' ),
				'help'        => __( 'Select how often you would like events to be automatically imported.', 'tribe-ext-ea-facebook' ),
				'source'      => $origin . '_import_frequency',
			];

			$frequencies = Tribe__Events__Aggregator__Cron::instance()->get_frequency();

			$data_depends   = '#tribe-ea-field-origin';
			$data_condition = $origin;

			if ( ! $is_token_valid ) {
				$data_depends   = '#tribe-has-' . $origin . '-credentials';
				$data_condition = '1';

				/* translators: The placeholder used is for the Origin label */
				$credentials_text = _x( 'Please connect to %1$s to enable event imports.', 'notice to connect the EA origin', 'tribe-ext-ea-facebook' );
				$credentials_text = sprintf( $credentials_text, $origin_label );

				/* translators: The placeholder used is for the Origin label */
				$credentials_button = _x( 'Connect to %1$s', 'button text to connect the EA origin', 'tribe-ext-ea-facebook' );
				$credentials_button = sprintf( $credentials_button, $origin_label );

				include __DIR__ . '/src/admin-views/import-form/enter-credentials.php';
			}

			include __DIR__ . '/src/admin-views/import-form/frequency.php';

			$ea_page = Tribe__Events__Aggregator__Page::instance();

			if ( 'edit' === $aggregator_action ) {
				$ea_page->template( 'fields/schedule', $form_args );
			}

			$field              = (object) [];
			$field->label       = __( 'Import Source', 'tribe-ext-ea-facebook' );
			$field->placeholder = __( 'Select Source', 'tribe-ext-ea-facebook' );
			$field->help        = __( 'Import events directly from your connected account or from a public URL.', 'tribe-ext-ea-facebook' );

			$field->options = [
				[
					'id'   => self::get_origin_account_source_url(),
					'text' => __( 'Import from your account', 'tribe-ext-ea-facebook' ),
				],
				[
					'id'   => 'source_type_url',
					'text' => __( 'Import from URL', 'tribe-ext-ea-facebook' ),
				],
			];

			include __DIR__ . '/src/admin-views/import-form/source.php';

			$field              = (object) [];
			$field->label       = __( 'URL:', 'tribe-ext-ea-facebook' );
			$field->placeholder = $origin_example_url;

			/* translators: The placeholder used is for the example event URL */
			$field->help = _x( 'Enter an event URL, e.g. %1$s', 'help text for origin event source url', 'tribe-ext-ea-facebook' );
			$field->help = sprintf( $field->help, 'https://www.' . $origin_example_url );

			include __DIR__ . '/src/admin-views/import-form/source-url.php';

			$ea_page->template( 'origins/refine', $form_args );

			include __DIR__ . '/src/admin-views/import-form/preview.php';

		}

		/**
		 * Add origin to refine exclusions list.
		 *
		 * @param array $exclusions List of origins excluded.
		 *
		 * @return array List of origins excluded.
		 */
		public function add_origin_to_refine_exclusions( $exclusions ) {

			$exclusions[] = self::get_origin();

			return $exclusions;

		}

		/**
		 * Authorize token with EA and setup security key if it's not set yet.
		 *
		 * @return bool|object|\WP_Error Authorization result or WP_Error if there was a problem.
		 */
		public function authorize_ea_token() {

			// if the service hasn't enabled oauth for origin, always assume it is valid
			if ( ! tribe( 'events-aggregator.main' )->api( 'origins' )->is_oauth_enabled( self::get_origin() ) ) {
				return true;
			}

			$args = $this->get_ea_args();

			/** @var \Tribe__Events__Aggregator__Service $ea_service */
			$ea_service = tribe( 'events-aggregator.service' );

			$response = $ea_service->get( sanitize_text_field( self::get_origin() ) . '/validate', $args );

			// Handle errors.
			if ( is_wp_error( $response ) || empty( $response->status ) || 'success' !== $response->status ) {
				// @todo How to register/add error messages?
				return tribe_error( 'core:aggregator:invalid-token', [], [ 'response' => $response ] );
			}

			// The security key is sent on initial authorization, we need to save it if we have it.
			if ( ! empty( $response->data->secret_key ) ) {
				$this->set_ea_security_key( $response->data->secret_key );
			}

			return $response;

		}

		/**
		 * Get the authentication URL.
		 *
		 * @param array $args URL arguments.
		 *
		 * @return string Authentication URL.
		 */
		public function get_auth_url( $args = [] ) {

			/** @var \stdClass|\WP_Error $ea_service_api */
			$ea_service_api = tribe( 'events-aggregator.service' )->api();

			if ( is_wp_error( $ea_service_api ) ) {
				return '';
			}

			$url = $ea_service_api->domain . sanitize_text_field( self::get_origin() ) . '/' . sanitize_text_field( $ea_service_api->key );

			$defaults = [
				'back'      => 'settings',
				'referral'  => rawurlencode( home_url() ),
				'admin_url' => rawurlencode( get_admin_url() ),
				'type'      => 'new',
				'lang'      => get_bloginfo( 'language' ),
			];

			$args = wp_parse_args( $args, $defaults );

			return add_query_arg( $args, $url );

		}

		/**
		 * Get the disconnect URL.
		 *
		 * @return string Disconnect URL.
		 */
		public function get_disconnect_url() {

			$current_url = Tribe__Settings::instance()->get_url( [ 'tab' => 'addons' ] );

			$action = 'disconnect-' . sanitize_text_field( self::get_origin() );

			return wp_nonce_url(
				add_query_arg(
					'action',
					$action,
					$current_url
				),
				$action
			);

		}

		/**
		 * Get origin security key for EA.
		 *
		 * @return string Origin security key for EA.
		 */
		public function get_ea_security_key() {

			$origin = sanitize_text_field( self::get_origin() );

			return tribe_get_option( $origin . '_security_key' );

		}

		/**
		 * Set origin security key for EA.
		 *
		 * @param string|null $security_key Origin security key for EA.
		 */
		public function set_ea_security_key( $security_key ) {

			$origin = sanitize_text_field( self::get_origin() );

			tribe_update_option( $origin . '_security_key', $security_key );

		}

		/**
		 * Get arguments for EA.
		 *
		 * @return array EA arguments.
		 */
		public function get_ea_args() {

			$args = [
				'referral'   => rawurlencode( home_url() ),
				'url'        => rawurlencode( site_url() ),
				'secret_key' => $this->get_ea_security_key(),
			];

			/**
			 * Allow filtering for which params we are sending to EA for Token callback.
			 *
			 *
			 * @param array $args Which arguments are sent to Token Callback.
			 */
			return apply_filters( 'tribe_aggregator_' . self::get_origin() . '_token_callback_args', $args );

		}

		/**
		 * Check if we have a valid token with EA.
		 *
		 * @return boolean Whether the EA token is valid.
		 */
		public function is_ea_token_valid() {

			$validate = $this->authorize_ea_token();

			return $validate && ! is_wp_error( $validate );

		}

		/**
		 * Disconnect token on EA.
		 *
		 * @return \stdClass|\WP_Error Response or WP_Error if there was a problem.
		 */
		public function disconnect_ea_token() {

			$args = $this->get_ea_args();

			/** @var \Tribe__Events__Aggregator__Service $ea_service */
			$ea_service = tribe( 'events-aggregator.service' );

			$response = $ea_service->get( sanitize_text_field( self::get_origin() ) . '/disconnect', $args );

			// If we have an WP_Error we return only CSV
			if ( is_wp_error( $response ) ) {
				// @todo How to register/add error messages?
				return tribe_error( 'core:aggregator:invalid-token', [], [ 'response' => $response ] );
			}

			// Clear the EA security key for origin.
			$this->set_ea_security_key( null );

			return $response;

		}

		/**
		 * Determine if we need to disconnect the origin.
		 *
		 * @param \WP_Screen $screen Screen object.
		 */
		public function maybe_disconnect_ea_token( $screen ) {

			if ( 'tribe_events_page_tribe-common' !== $screen->base ) {
				return;
			}

			if ( ! isset( $_GET['tab'] ) || 'addons' !== $_GET['tab'] ) {
				return;
			}

			$origin = self::get_origin();

			$action = 'disconnect-' . $origin;

			if ( ! isset( $_GET['action'] ) || ! isset( $_GET['_wpnonce'] ) ) {
				return;
			}

			if ( $action !== $_GET['action'] || ! wp_verify_nonce( $_GET['_wpnonce'], $action ) ) {
				return;
			}

			$this->disconnect_ea_token();

			$redirect_url = Tribe__Settings::instance()->get_url( [ 'tab' => 'addons' ] );

			wp_redirect( $redirect_url );
			die();

		}

	} // end class
} // end if class_exists check
