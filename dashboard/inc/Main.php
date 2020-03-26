<?php
/**
 * Main class of the Neve Dashboard
 */

namespace Neve_Dash;

/**
 * Class Main
 *
 * @package Neve_Dash
 */
class Main {
	/**
	 * Rest instance.
	 *
	 * @var Rest
	 */
	private $server = null;

	/**
	 * Rest instance.
	 *
	 * @var Changelog_Handler
	 */
	private $cl_handler = null;
	/**
	 * Plugin Helper instance.
	 *
	 * @var Plugin_Helper
	 */
	private $plugin_helper = null;
	/**
	 * Current theme args.
	 *
	 * @var array
	 */
	private $theme_args = [];

	/**
	 * Useful plugins array.
	 *
	 * @var array
	 */
	private $useful_plugins = [
		'optimole-wp',
		'themeisle-companion',
		'feedzy-rss-feeds',
		'otter-blocks',
		'elementor',
		'wp-product-review',
		'weglot',
		'visualizer',
		'wpforms-lite',
		'translatepress-multilingual',
		'amp',
	];

	private $plugins_cache_key      = 'neve_dash_useful_plugins';
	private $plugins_cache_hash_key = 'neve_dash_useful_plugins_hash';

	/**
	 * Main constructor.
	 */
	public function __construct() {
		define( 'NEVE_NEW_DASHBOARD', true );
		$this->server = new Rest();
		$this->server->init();
		$this->plugin_helper = new Plugin_Helper();
		$this->cl_handler    = new Changelog_Handler();
		$this->setup_config();
		$this->run_actions();
	}

	/**
	 * Run WordPress attached to actions.
	 */
	private function run_actions() {
		add_action( 'admin_menu', [ $this, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Setup the class props based on current theme.
	 */
	private function setup_config() {
		$theme = wp_get_theme();

		$this->theme_args['name']        = apply_filters( 'ti_wl_theme_name', $theme->__get( 'Name' ) );
		$this->theme_args['template']    = $theme->get( 'Template' );
		$this->theme_args['version']     = $theme->__get( 'Version' );
		$this->theme_args['description'] = apply_filters( 'ti_wl_theme_description', $theme->__get( 'Description' ) );
		$this->theme_args['slug']        = $theme->__get( 'stylesheet' );
	}

	/**
	 *
	 */
	public function register() {
		$theme = $this->theme_args;

		if ( empty( $theme['name'] ) || empty( $theme['slug'] ) ) {
			return;
		}

		$page_title = $theme['name'] . ' ' . __( 'Options', 'neve' ) . ' ';
		$menu_name  = $theme['name'] . ' ' . __( 'Options', 'neve' ) . ' ';

		$theme_page = ! empty( $theme['template'] ) ? $theme['template'] . '-welcome' : $theme['slug'] . '-welcome';
		add_theme_page( $page_title, $menu_name, 'activate_plugins', $theme_page, [ $this, 'render' ] );
	}

	/**
	 * Render the application stub.
	 *
	 * @return mixed
	 */
	public function render() {
		echo '<div id="neve-dashboard"></div>';
	}

	/**
	 * Load css and scripts for the about page
	 */
	public function enqueue() {
		$screen = get_current_screen();
		if ( ! isset( $screen->id ) ) {
			return;
		}

		$theme      = $this->theme_args;
		$theme_page = ! empty( $theme['template'] ) ? $theme['template'] . '-welcome' : $theme['slug'] . '-welcome';

		if ( $screen->id !== 'appearance_page_' . $theme_page ) {
			return;
		}

		$dependencies = [ 'react', 'react-dom', 'wp-i18n', 'wp-api', 'wp-components', 'wp-element', 'updates' ];

		wp_enqueue_style( 'neve-dash-style', get_template_directory_uri() . '/dashboard/build/build.css', [ 'wp-components' ], NEVE_VERSION );
		wp_register_script( 'neve-dash-script', get_template_directory_uri() . '/dashboard/build/build.js', $dependencies, NEVE_VERSION, true );
		wp_localize_script( 'neve-dash-script', 'neveDash', apply_filters( 'neve_dashboard_page_data', $this->get_localization() ) );
		wp_enqueue_script( 'neve-dash-script' );
	}

	/**
	 * Get localization data for the dashboard script.
	 *
	 * @return array
	 */
	private function get_localization() {
		return [
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'api'                 => rest_url( REST::API_ROOT ),
			'version'             => 'v' . NEVE_VERSION,
			'assets'              => get_template_directory_uri() . '/dashboard/assets/',
			'notifications'       => $this->get_notifications(),
			'customizerShortcuts' => $this->get_customizer_shortcuts(),
			'plugins'             => $this->get_useful_plugins(),
			'featureData'         => $this->get_free_pro_features(),
			'upgradeURL'          => esc_url( apply_filters( 'neve_upgrade_link_from_child_theme_filter', 'https://themeisle.com/themes/neve/upgrade/?utm_medium=aboutneve&utm_source=freevspro&utm_campaign=neve' ) ),
			'strings'             => [
				'header' => $this->theme_args['name'] . ' ' . __( 'Options', 'neve' ),
			],
			'options'             => [
				'logger' => get_option( 'neve_logger_flag', 'no' ) === 'yes',
			],
			'changelog'           => $this->cl_handler->get_changelog(),
		];
	}

	private function get_notifications() {
		delete_site_transient( 'update_themes' );
		delete_site_transient( 'update_plugins' );
		_maybe_update_themes();
		_maybe_update_plugins();

		$notifications = [];
		$slug          = 'go';
		$themes_update = get_site_transient( 'update_themes' );
		if ( isset( $themes_update->response[ $slug ] ) ) {
			$update                = $themes_update->response[ $slug ];
			$notifications['neve'] = [
				'text'   => sprintf( __( 'New theme update for %1$s! Please update to %2$s.' ), $this->theme_args['name'], $update['new_version'] ),
				'update' => [
					'type' => 'theme',
					'slug' => $slug,
				],
				'cta'    => __( 'Update Now', 'neve' ),
			];
		}

		$plugins_update = get_site_transient( 'update_plugins' );
		$plugin_path    = 'neve-pro-addon/neve-pro-addon.php';
		$plugin_path    = 'akismet/akismet.php';
		if ( isset( $plugins_update->response[ $plugin_path ] ) ) {
			$update                          = $plugins_update->response[ $plugin_path ];
			$notifications['neve-pro-addon'] = [
				'text'   => sprintf( __( 'New plugin update for %1$s! Please update to %2$s.' ), 'Neve Pro', $update->new_version ),
				'update' => [
					'type' => 'plugin',
					// 'slug' => 'neve-pro-addon',
					'slug' => 'akismet',
					// 'path' => 'neve-pro-addon/neve-pro-addon.php'
					'path' => $plugin_path,
				],
				'cta'    => __( 'Update Now', 'neve' ),
			];
		}

		return $notifications;
	}

	/**
	 * Get the Customizer Shortcut Links.
	 *
	 * @return array
	 */
	private function get_customizer_shortcuts() {
		return [
			[
				'text' => __( 'Upload Logo', 'neve' ),
				'link' => add_query_arg( [ 'autofocus[control]' => 'custom_logo' ], admin_url( 'customize.php' ) ),
			],
			[
				'text' => __( 'Set Colors', 'neve' ),
				'link' => add_query_arg( [ 'autofocus[section]' => 'neve_colors_background_section' ], admin_url( 'customize.php' ) ),
			],
			[
				'text' => __( 'Customize Fonts', 'neve' ),
				'link' => add_query_arg( [ 'autofocus[control]' => 'neve_headings_font_family' ], admin_url( 'customize.php' ) ),
			],
			[
				'text' => __( 'Layout Options', 'neve' ),
				'link' => add_query_arg( [ 'autofocus[panel]' => 'neve_layout' ], admin_url( 'customize.php' ) ),
			],
			[
				'text' => __( 'Header Options', 'neve' ),
				'link' => add_query_arg( [ 'autofocus[panel]' => 'neve_header' ], admin_url( 'customize.php' ) ),
			],
			[
				'text' => __( 'Blog Layouts', 'neve' ),
				'link' => add_query_arg( [ 'autofocus[section]' => 'neve_blog_archive_layout' ], admin_url( 'customize.php' ) ),
			],
			[
				'text' => __( 'Footer Options', 'neve' ),
				'link' => add_query_arg( [ 'autofocus[panel]' => 'neve_footer' ], admin_url( 'customize.php' ) ),
			],
			[
				'text' => __( 'Content / Sidebar', 'neve' ),
				'link' => add_query_arg( [ 'autofocus[section]' => 'neve_sidebar' ], admin_url( 'customize.php' ) ),
			],
		];
	}

	private function get_free_pro_features() {
		return [
			[
				'title'       => __( 'Header/Footer builder', 'neve' ),
				'description' => __( 'Easily build your header and footer by dragging and dropping all the important elements in the real-time WordPress Customizer. More advanced options are available in PRO.', 'neve' ),
				'inLite'      => true,
			],
			[
				'title'       => __( 'Page Builder Compatibility', 'neve' ),
				'description' => __( 'Neve is fully compatible with Gutenberg, the new WordPress editor and for all of you page builder fans, Neve has full compatibility with Elementor, Beaver Builder, and all the other popular page builders.', 'neve' ),
				'inLite'      => true,
			],
			[
				'title'       => __( 'Header Booster', 'neve' ),
				'description' => __( 'Take the header builder to a new level with new awesome components: socials, contact, breadcrumbs, language switcher, multiple HTML, sticky and transparent menu, page header builder and many more.', 'neve' ),
				'inLite'      => false,
			],
			[
				'title'       => __( 'Page Header Builder', 'neve' ),
				'description' => __( 'The Page Header is the horizontal area that sits directly below the header and contains the page/post title. Easily design an attractive Page Header area using our dedicated builder.', 'neve' ),
				'inLite'      => false,
			],
			[
				'title'       => __( 'Custom Layouts', 'neve' ),
				'description' => __( 'Powerful Custom Layouts builder which allows you to easily create your own header, footer or custom content on any of the hook locations available in the theme.', 'neve' ),
				'inLite'      => false,
			],
			[
				'title'       => __( 'Blog Booster', 'neve' ),
				'description' => __( 'Give a huge boost to your entire blogging experience with features specially designed for increased user experience.', 'neve' ) . ' ' . __( 'Sharing, custom article sorting, comments integrations, number of minutes needed to read an article and many more.', 'neve' ),
				'inLite'      => false,
			],
			[
				'title'       => __( 'Elementor Booster', 'neve' ),
				'description' => __( 'Leverage the true flexibility of Elementor with powerful addons and templates that you can import with just one click.', 'neve' ),
				'inLite'      => false,
			],
			[
				'title'       => __( 'WooCommerce Booster', 'neve' ),
				'description' => __( 'Empower your online store with awesome new features, specially designed for a smooth WooCommerce integration.', 'neve' ) . ' ' . __( 'Wishlist, quick view, video products, advanced reviews, multiple dedicated layouts and many more.', 'neve' ),
				'inLite'      => false,
			],
			[
				'title'       => __( 'LifterLMS Booster', 'neve' ),
				'description' => __( 'Make your LifterLMS pages look stunning with our PRO design options. Specially created to help you set up your online courses with minimum customizations.', 'neve' ),
				'inLite'      => false,
			],
			[
				'title'       => __( 'Typekit(Adobe) Fonts', 'neve' ),
				'description' => __( "The module allows for an easy way of enabling new awesome Adobe (previous Typekit) Fonts in Neve's Typography options.", 'neve' ),
				'inLite'      => false,
			],
			[
				'title'       => __( 'White Label', 'neve' ),
				'description' => __( "For any developer or agency out there building websites for their own clients, we've made it easy to present the theme as your own.", 'neve' ),
				'inLite'      => false,
			],
			[
				'title'       => __( 'Scroll To Top', 'neve' ),
				'description' => __( 'Simple but effective module to help you navigate back to the top of the really long pages.', 'neve' ),
				'inLite'      => false,
			],
		];
	}

	/**
	 * Get the useful plugin data.
	 *
	 * @return array
	 */
	private function get_useful_plugins() {
		$available    = get_transient( $this->plugins_cache_key );
		$hash         = get_transient( $this->plugins_cache_hash_key );
		$current_hash = substr( md5( wp_json_encode( $this->useful_plugins ) ), 0, 5 );


		if ( $available !== false && $hash === $current_hash ) {
			$available = json_decode( $available, true );

			foreach ( $available as $slug => $args ) {
				$available[ $slug ]['cta']        = $this->plugin_helper->get_plugin_state( $slug );
				$available[ $slug ]['path']       = $this->plugin_helper->get_plugin_path( $slug );
				$available[ $slug ]['activate']   = $this->plugin_helper->get_plugin_action_link( $slug );
				$available[ $slug ]['deactivate'] = $this->plugin_helper->get_plugin_action_link( $slug, 'deactivate' );

			}

			return $available;
		}

		$data = [];
		foreach ( $this->useful_plugins as $slug ) {
			$current_plugin = $this->plugin_helper->get_plugin_details( $slug );
			if ( $current_plugin instanceof \WP_Error ) {
				continue;
			}
			$data[ $slug ] = [
				'banner'      => $current_plugin->banners['low'],
				'name'        => html_entity_decode( $current_plugin->name ),
				'description' => html_entity_decode( $current_plugin->short_description ),
				'version'     => $current_plugin->version,
				'author'      => html_entity_decode( strip_tags( $current_plugin->author ) ),
				'cta'         => $this->plugin_helper->get_plugin_state( $slug ),
				'path'        => $this->plugin_helper->get_plugin_path( $slug ),
				'activate'    => $this->plugin_helper->get_plugin_action_link( $slug ),
				'deactivate'  => $this->plugin_helper->get_plugin_action_link( $slug, 'deactivate' ),
			];
		}

		set_transient( $this->plugins_cache_hash_key, $current_hash );
		set_transient( $this->plugins_cache_key, wp_json_encode( $data ) );

		return $data;
	}
}
