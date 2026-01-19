<?php
/**
 * Plugin Name: WP NET Init
 * Description: Initialise the WP NET mu-plugin library which connects WordPress to WP NET client management services, implements various tweaks and creates the WP NET Dashboard Widgets. If you remove this plugin it will be automatically reinstalled during routine maintenance.
 * Author: WP NET
 * Author URI: https://wpnet.nz
 * Version: 1.7.4
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPNET_PLUGIN_BASE', __FILE__ );
$wp_init_data = get_file_data( WPNET_PLUGIN_BASE, array( 'Version' => 'Version' ) );
define( 'WPNET_INIT_PLUGIN_VERSION', $wp_init_data['Version'] );

/*==========================================================
=                      WP NET INIT                         =
==========================================================*/

/**
 * Main initialization class for WP NET functionality
 */
class WPNET_Init {
	
	/**
	 * Maximum allowed comment length in characters
	 */
	private const MAX_COMMENT_LENGTH = 14000;
	
	/**
	 * Constructor - sets up hooks
	 */
	public function __construct() {
		add_filter( 'pre_comment_content', array( $this, 'block_long_comment' ), 9999 );
		add_filter( 'xmlrpc_enabled', '__return_false' );
	}
	
	/**
	 * Block excessively long comments as a security precaution
	 *
	 * @param string $text The comment content
	 * @return string The comment content if valid
	 */
	public function block_long_comment( string $text ): string {
		if ( strlen( $text ) > self::MAX_COMMENT_LENGTH ) {
			wp_die(
				esc_html__( 'This comment is longer than permitted and has been blocked.', 'wpnet' ),
				esc_html__( 'Comment Blocked', 'wpnet' ),
				array( 'response' => 413 )
			);
		}
		return $text;
	}
}

global $WPNET_Init;
$WPNET_Init = new WPNET_Init();

/*========================================================
=                       BRANDING                         =
========================================================*/

/**
 * Handles WP Admin and plugin branding customizations
 */
class WPNET_WP_Admin_Branding {
	
	/**
	 * Constructor - sets up hooks
	 */
	public function __construct() {
		add_filter( 'admin_footer_text', array( $this, 'wpnet_dashboard_footer' ) );
		add_filter( 'all_plugins', array( $this, 'wpnet_plugin_branding' ) );
		
		if ( $this->should_show_announcements_widget() ) {
			$hook = ( is_main_site() && is_multisite() ) ? 'wp_network_dashboard_setup' : 'wp_dashboard_setup';
			add_action( $hook, array( $this, 'setup_announcements_widget' ) );
		}
		
		add_action( 'admin_enqueue_scripts', array( $this, 'iwp_hide_notice_css' ), 1 );
	}
	
	/**
	 * Check if announcements widget should be displayed
	 *
	 * @return bool
	 */
	private function should_show_announcements_widget(): bool {
		if ( defined( 'WPNET_ANNOUNCEMENTS_WIDGET_DISABLE' ) && WPNET_ANNOUNCEMENTS_WIDGET_DISABLE === true ) {
			return false;
		}
		
		if ( is_multisite() && ! is_main_site() ) {
			return ! ( defined( 'WPNET_ANNOUNCEMENTS_WIDGET_WPMU_DISABLE' ) && WPNET_ANNOUNCEMENTS_WIDGET_WPMU_DISABLE === true );
		}
		
		return true;
	}
	
	/**
	 * Register announcements widget
	 */
	public function setup_announcements_widget(): void {
		wp_add_dashboard_widget(
			'wpnet_announcements_rss',
			'WP NET - Announcements',
			array( $this, 'announcements_dashboard_widget' )
		);
		add_action( 'admin_enqueue_scripts', array( $this, 'announcements_widget_css' ) );
	}
	
	/**
	 * Add CSS for announcements widget
	 */
	public function announcements_widget_css(): void {
		$custom_css = '
			#wpnet_announcements_rss ul { margin-bottom: 0; font-size: 1em; }
			#wpnet_announcements_rss ul li { 
				list-style-position: outside; 
				list-style-type: none; 
				margin-left: 1em;
				padding: 0 0 0.2em 0;
				border-bottom: 1px solid #f0f0f1;
			}
			#wpnet_announcements_rss ul li:last-child { border-bottom: none; }
			#wpnet_announcements_rss ul li span.dashicons { margin-left: -1.15em; }
			#wpnet_announcements_rss ul li .date { color: #999; font-size: 0.9em; }
		';
		wp_add_inline_style( 'dashboard', $custom_css );
	}
	
	/**
	 * Customize admin footer text
	 *
	 * @return string
	 */
	public function wpnet_dashboard_footer(): string {
		return sprintf(
			'<span id="footer-thankyou" style="font-style:normal"><a target="_blank" rel="noopener" href="%s" title="%s"><img style="vertical-align:bottom;" src="%s" width="60" height="18" alt="WP NET"></a> &#8211; %s</span>',
			esc_url( 'https://wpnet.nz' ),
			esc_attr__( 'Hosted on WP NET - WordPress Hosting & Support', 'wpnet' ),
			esc_url( '//assets.wpnet.nz/wpnet-logo-med.png' ),
			esc_html__( 'WordPress Hosting & Support', 'wpnet' )
		);
	}
	
	/**
	 * Hide IWP Client notices with CSS
	 */
	public function iwp_hide_notice_css(): void {
		$custom_css = '
			body.wp-admin #wpbody .updated[style*="text-align: center; display:block !important;"] { visibility: hidden; }
			body.wp-admin #wpbody .updated[style*="text-align: center; display:block !important;"] * { display: none !important; }
		';
		wp_add_inline_style( 'common', $custom_css );
	}
	
	/**
	 * Brand specific plugins in the plugins list
	 *
	 * @param array $plugins_list Array of installed plugins
	 * @return array Modified plugins list
	 */
	public function wpnet_plugin_branding( array $plugins_list ): array {
		// Brand IWP Client plugin
		if ( isset( $plugins_list['iwp-client/init.php'] ) ) {
			$plugins_list['iwp-client/init.php'] = array_merge(
				$plugins_list['iwp-client/init.php'],
				array(
					'Name'        => 'WP NET Client',
					'Title'       => 'WP NET Client',
					'Description' => 'WP NET Client plugin. If you deactivate or remove this plugin it will be automatically reinstalled. Status: <strong>DISCONNECTED</strong>',
					'Author'      => 'WP NET',
					'AuthorName'  => 'WP NET',
					'AuthorURI'   => 'https://wpnet.nz',
					'PluginURI'   => 'https://wpnet.nz',
					'hide'        => false,
				)
			);
		}
		
		// Brand SpinupWP plugin
		if ( isset( $plugins_list['spinupwp/spinupwp.php'] ) ) {
			$plugins_list['spinupwp/spinupwp.php'] = array_merge(
				$plugins_list['spinupwp/spinupwp.php'],
				array(
					'Name'        => 'Cache Control',
					'Title'       => 'Cache Control',
					'Author'      => 'WP NET',
					'AuthorName'  => 'WP NET',
					'AuthorURI'   => 'https://wpnet.nz',
					'PluginURI'   => 'https://wpnet.nz',
					'Description' => "Creates a WP Admin Menu item for 'Cache'. Options include: 'Purge Page Cache' (Nginx), 'Purge Object Cache' (Redis). If a cache type is inactive, it will not display in the menu.",
					'slug'        => null,
				)
			);
		}
		
		return $plugins_list;
	}
	
	/**
	 * Output the announcements RSS feed widget
	 */
	public function announcements_dashboard_widget(): void {
		include_once ABSPATH . WPINC . '/feed.php';
		$rss = fetch_feed( 'http://feeds.wpnet.nz/wpnetannouncements' );
		if ( is_wp_error( $rss ) ) {
			echo '<p>';
			if ( current_user_can( 'manage_options' ) ) {
				printf(
					'<strong>%s</strong>: %s',
					esc_html__( 'RSS Error', 'wpnet' ),
					esc_html( $rss->get_error_message() )
				);
			} else {
				esc_html_e( 'An error has occurred. RSS feed could not be created.', 'wpnet' );
			}
			echo '</p>';
			return;
		}
		$maxitems  = $rss->get_item_quantity( 5 );
		$rss_items = $rss->get_items( 0, $maxitems );
		echo '<ul>';
		if ( 0 === $maxitems ) {
			echo '<li>' . esc_html__( 'No announcements to display.', 'wpnet' ) . '</li>';
		} else {
			foreach ( $rss_items as $item ) {
				printf(
					'<li>
						<span style="color:#999" class="dashicons dashicons-arrow-right"></span>
						<a href="%s" title="%s" target="_blank" rel="noopener"><strong>%s</strong></a>
						 - <span class="date">%s</span>
						<span class="desc" style="display:inline-block">%s</span>
					</li>',
					esc_url( $item->get_permalink() ),
					esc_attr( sprintf( __( 'Posted %s', 'wpnet' ), $item->get_date( 'j F Y | g:i a' ) ) ),
					esc_html( $item->get_title() ),
					esc_html( $item->get_date( 'j M y' ) ),
					esc_html( wp_html_excerpt( $item->get_description(), 150, '&nbsp;&hellip;' ) )
				);
			}
		}
		echo '</ul>';
		$rss->__destruct();
		unset( $rss );
	}
}

global $WPNET_WP_Admin_Branding;
$WPNET_WP_Admin_Branding = new WPNET_WP_Admin_Branding();

/*====================================================
=            WP NET Dashboard Info Widget            =
====================================================*/

/**
 * Display site information in a dashboard widget
 * Shows PHP version, disk space, memory usage, etc.
 */
if ( is_admin() || is_network_admin() ) {
	
	class WPNET_Site_Info_Widget {
		
		/**
		 * Disk statistics cache
		 *
		 * @var array
		 */
		private $disk_stats = array();
		
		/**
		 * Constructor - sets up hooks
		 */
		public function __construct() {
			if ( ! $this->should_show_widget() ) {
				return;
			}
			
			$hook = ( is_main_site() && is_multisite() ) ? 'wp_network_dashboard_setup' : 'wp_dashboard_setup';
			add_action( $hook, array( $this, 'setup_site_info_widget' ) );
			add_action( 'wp_ajax_wpnet_get_site_info', array( $this, 'ajax_get_site_info' ) );
		}
		
		/**
		 * Check if widget should be displayed
		 *
		 * @return bool
		 */
		private function should_show_widget(): bool {
			if ( defined( 'WPNET_INFO_WIDGET_DISABLE' ) && WPNET_INFO_WIDGET_DISABLE === true ) {
				return false;
			}
			
			if ( is_multisite() && ! is_main_site() ) {
				return ! ( defined( 'WPNET_INFO_WIDGET_WPMU_DISABLE' ) && WPNET_INFO_WIDGET_WPMU_DISABLE === true );
			}
			
			return true;
		}
		
		/**
		 * Register dashboard widget
		 */
		public function setup_site_info_widget(): void {
			wp_add_dashboard_widget(
				'site_info_widget',
				sprintf( 'WP NET - Site Info <span class="title-version">v%s</span>', esc_html( WPNET_INIT_PLUGIN_VERSION ) ),
				array( $this, 'site_info_dashboard_widget' )
			);
			add_action( 'admin_enqueue_scripts', array( $this, 'site_info_widget_css' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'site_info_widget_js' ) );
		}
		
		/**
	 * Output the dashboard widget content
	 * Always renders fresh since PHP version, WP version, memory, and DB size change frequently.
	 * Disk stats are loaded from .wp-stats JSON file which updates independently.
	 */
	public function site_info_dashboard_widget(): void {
		$this->render_site_info_content();
	}
	
	/**
	 * Add CSS for the widget - Mobile-responsive grid layout
	 */
	public function site_info_widget_css(): void {
			$custom_css = '
				/* Main widget container */
				#site_info_widget .inside { padding: 12px; }
				
				/* Grid layout for info items */
				#site_info_widget .wpnet-info-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
					gap: 6px;
					margin-bottom: 6px;
					padding-bottom: 6px;
				}
				
				#site_info_widget .wpnet-info-item {
					display: flex;
					justify-content: space-between;
					align-items: center;
					padding: 8px 12px;
					background: #f6f7f7;
					border-radius: 4px;
				}

				#site_info_widget .wpnet-info-item a {
					text-decoration: none;
				}
				
				#site_info_widget .wpnet-info-item .label {
					font-weight: 600;
					color: #1d2327;
					margin-right: 8px;
				}
				
				#site_info_widget .wpnet-info-item .data {
					font-size: 1.1em;
					text-align: right;
					white-space: nowrap;
				}
				
				/* Disk usage section */
				#site_info_widget .wpnet-disk-usage {
					margin-bottom: 16px;
					padding-bottom: 16px;
					border-bottom: 1px solid #dcdcde;
				}
				
				#site_info_widget .wpnet-disk-usage .big {
					font-size: 1.8em;
					font-weight: 300;
					line-height: 1;
				}
				
				#site_info_widget .wpnet-disk-details {
					display: grid;
					grid-template-columns: repeat(6, 1fr);
					gap: 8px;
					margin-top: 12px;
					padding: 12px;
					background: #f6f7f7;
					border-radius: 4px;
				}
				
				#site_info_widget .wpnet-disk-details .wpnet-info-item {
					background: #fff;
					padding: 8px 12px;
				}
				
				/* First row: */
				#site_info_widget .wpnet-disk-details .wpnet-info-item:nth-child(1) {
					grid-column: span 6;
				}
				
				/* Second row:*/
				#site_info_widget .wpnet-disk-details .wpnet-info-item:nth-child(2),
				#site_info_widget .wpnet-disk-details .wpnet-info-item:nth-child(3),
				#site_info_widget .wpnet-disk-details .wpnet-info-item:nth-child(4) {
					grid-column: span 2;
				}
				
				#site_info_widget .wpnet-disk-details .note {
					grid-column: 1 / -1;
					font-style: italic;
					color: #646970;
					font-size: 0.9em;
					text-align: center;
					margin: 8px 0 0;
					background: transparent;
				}
				
				/* Notifications */
				#site_info_widget .wpnet-notification {
					background: #fff3cd;
					border-left: 4px solid #ffc107;
					padding: 12px;
					margin: 12px 0;
					border-radius: 4px;
				}
				
				#site_info_widget .wpnet-notification .dashicons {
					color: #ff9800;
					margin-right: 4px;
				}
				
				/* Widget footer */
				#site_info_widget .widget-footer {
					border-top: 1px solid #dcdcde;
					padding-top: 12px;
					text-align: center;
				}
				
				#site_info_widget .widget-footer .button {
					margin: 4px;
				}
				
				#site_info_widget .widget-footer p {
					margin: 8px 0;
				}
				
				/* Version badge in title */
				#site_info_widget h2 .title-version {
					color: #787c82;
					font-weight: 400;
					font-size: 0.9em;
				}
				
				/* Status colors */
				#site_info_widget .green { color: #00a32a; }
				#site_info_widget .red { color: #d63638; }
				#site_info_widget .orange { color: #ff9800; }
				#site_info_widget .blue { color: #2271b1; }
				#site_info_widget .grey { color: #dcdcde; }
				
				/* Clickable disk usage div */
				#site_info_widget .wpnet-disk-usage-toggle[data-has-details="yes"] {
					cursor: pointer;
					transition: background-color 0.2s ease;
				}
				
				#site_info_widget .wpnet-disk-usage-toggle[data-has-details="yes"]:hover {
					background-color: #eaeaea;
				}
				
				#site_info_widget .wpnet-disk-usage-toggle .dashicons {
					transition: transform 0.3s ease;
					margin-left: 4px;
				}
				
				#site_info_widget .wpnet-disk-usage-toggle .dashicons.open {
					transform: rotate(90deg);
				}
				
				#site_info_widget .wpnet-disk-usage-toggle[data-has-details="yes"]:focus {
					box-shadow: none;
					outline: 2px solid #2271b1;
					outline-offset: 2px;
				}
				
				/* Mobile responsive */
				@media screen and (max-width: 782px) {
					#site_info_widget .wpnet-info-grid {
						grid-template-columns: 1fr;
					}
					
					#site_info_widget .wpnet-info-item {
						flex-direction: column;
						align-items: flex-start;
						gap: 4px;
					}
					
					#site_info_widget .wpnet-info-item .data {
						text-align: left;
					}
					
					#site_info_widget .widget-footer .button {
						display: block;
						width: 100%;
						margin: 8px 0;
					}
				}
				
				/* Hint.css tooltips - minified version */
				[class*=hint--]{position:relative;display:inline-block}
				[class*=hint--]:after,[class*=hint--]:before{position:absolute;transform:translate3d(0,0,0);visibility:hidden;opacity:0;z-index:1000000;pointer-events:none;transition:.3s ease;transition-delay:0s}
				[class*=hint--]:hover:after,[class*=hint--]:hover:before{visibility:visible;opacity:1;transition-delay:.1s}
				[class*=hint--]:before{content:"";position:absolute;background:0 0;border:6px solid transparent;z-index:1000001}
				[class*=hint--]:after{background:#383838;color:#fff;padding:8px 10px;font-size:12px;line-height:12px;white-space:nowrap;border-radius:4px;box-shadow:4px 4px 8px rgba(0,0,0,.3)}
				[class*=hint--][aria-label]:after{content:attr(aria-label)}
				.hint--top:before{border-top-color:#383838}
				.hint--top:after,.hint--top:before{bottom:100%;left:50%}
				.hint--top:before{margin-bottom:-11px;left:calc(50% - 6px)}
				.hint--top:after{transform:translateX(-50%)}
				.hint--top:hover:before{transform:translateY(-8px)}
				.hint--top:hover:after{transform:translateX(-50%) translateY(-8px)}
				.hint--top-left:before{border-top-color:#383838}
				.hint--top-left:after,.hint--top-left:before{bottom:100%;left:50%}
				.hint--top-left:before{margin-bottom:-11px;left:calc(50% - 6px)}
				.hint--top-left:after{transform:translateX(-100%);margin-left:12px}
				.hint--top-left:hover:before{transform:translateY(-8px)}
				.hint--top-left:hover:after{transform:translateX(-100%) translateY(-8px)}
				.hint--bottom-left:before{border-bottom-color:#383838}
				.hint--bottom-left:after,.hint--bottom-left:before{top:100%;left:50%}
				.hint--bottom-left:before{margin-top:-11px;left:calc(50% - 6px)}
				.hint--bottom-left:after{transform:translateX(-100%);margin-left:12px}
				.hint--bottom-left:hover:before{transform:translateY(8px)}
				.hint--bottom-left:hover:after{transform:translateX(-100%) translateY(8px)}
				.hint--error:after{background-color:#d63638}
				.hint--error.hint--top-left:before,.hint--error.hint--top:before{border-top-color:#d63638}
				.hint--error.hint--bottom-left:before{border-bottom-color:#d63638}
				.hint--large:after{white-space:normal;line-height:1.4em;word-wrap:break-word;width:300px}
				.hint--rounded:after{border-radius:4px}
				.hint--bounce:after,.hint--bounce:before{transition:opacity .3s ease,visibility .3s ease,transform .3s cubic-bezier(.71,1.7,.77,1.24)}
				
				/* Loading state */
				#site_info_widget .wpnet-loading {
					text-align: center;
					padding: 40px 20px;
					color: #646970;
				}
				
				#site_info_widget .wpnet-loading .spinner {
					visibility: visible;
					float: none;
					margin: 0 auto 10px;
				}
				
				#site_info_widget .wpnet-error {
					background: #fcf0f1;
					border-left: 4px solid #d63638;
					padding: 12px;
					margin: 12px 0;
					border-radius: 4px;
					color: #50575e;
				}
			';
			wp_add_inline_style( 'dashboard', $custom_css );
		}
		
		/**
		 * Enqueue JavaScript for widget
		 */
		public function site_info_widget_js(): void {
			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}
			
			// Check if we're on a dashboard page
			$is_dashboard = ( strpos( $screen->id, 'dashboard' ) !== false );
			if ( ! $is_dashboard ) {
				return;
			}
			
		$inline_js = "
		(function($) {
			'use strict';
			
			$(document).ready(function() {
				// Handle disk usage toggle clicks
				$('#site_info_widget').on('click', '.wpnet-disk-usage-toggle[data-has-details=\"yes\"]', function(e) {
					e.preventDefault();
					$('#wpnet-diskstats').slideToggle('slow');
					$('#open-indicator').toggleClass('open');
				});
			});
		})(jQuery);
		";
		
		wp_add_inline_script( 'dashboard', $inline_js );
	}
	
	/**
	 * Render the actual site info content
	 */
	private function render_site_info_content(): void {
			global $wpdb, $wp_version;
			
			// Load wp-admin/includes/file.php if get_home_path doesn't exist
			if ( ! function_exists( 'get_home_path' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			
			// Get home path
			$homepath = ( get_home_path() === '/' ) ? ABSPATH : get_home_path();
			
			// Get memory info
			$memory = $this->get_memory_info();
			
			// Load disk stats
			$this->load_disk_stats( $homepath );
			
			// Get database size
			$dbsize = $this->get_database_size();
			
			// Get version info
			$wp_version_notice = $this->get_wp_version_notice( $wp_version );
			$php_version_notice = $this->get_php_version_notice();
			$memory_notice = $this->get_memory_notice( $memory );
			$max_upload_notice = $this->get_upload_limit_notice();
			?>
			<div class="wpnet-info-grid">
				<div class="wpnet-info-item">
					<span class="label"><?php esc_html_e( 'WordPress', 'wpnet' ); ?>:</span>
					<span class="data"><?php echo wp_kses_post( $wp_version_notice ); ?></span>
				</div>
				<div class="wpnet-info-item">
					<span class="label"><?php esc_html_e( 'PHP', 'wpnet' ); ?>:</span>
					<span class="data"><?php echo wp_kses_post( $php_version_notice ); ?></span>
				</div>
				<div class="wpnet-info-item">
					<span class="label"><?php esc_html_e( 'Max Upload', 'wpnet' ); ?>:</span>
					<span class="data"><?php echo wp_kses_post( $max_upload_notice ); ?></span>
				</div>
				<div class="wpnet-info-item">
					<span class="label"><?php esc_html_e( 'Memory', 'wpnet' ); ?>:</span>
					<span class="data"><?php echo wp_kses_post( $memory_notice ); ?></span>
				</div>
				<div class="wpnet-info-item">
					<span class="label"><?php esc_html_e( 'Database', 'wpnet' ); ?>:</span>
					<span class="data">
						<span class="hint--top-left hint--rounded hint--bounce" aria-label="<?php esc_attr_e( 'Database size', 'wpnet' ); ?>">
							<?php echo esc_html( size_format( $dbsize ) ); ?>
						</span>
					</span>
				</div>
			<div class="wpnet-info-item wpnet-disk-usage-toggle hint--top hint--rounded hint--bounce" data-has-details="<?php echo esc_attr( $this->get_disk_stat( 'wp-content' ) !== '-' ? 'yes' : 'no' ); ?>" aria-label="<?php echo esc_attr( $this->get_disk_stat( 'wp-content' ) !== '-' ? __( 'Web root total size. Click for more.', 'wpnet' ) : __( 'Disk usage data', 'wpnet' ) ); ?>">
					<span class="label"><?php esc_html_e( 'Disk Usage', 'wpnet' ); ?>:</span>
					<span class="data big">
						<?php echo esc_html( $this->get_disk_stat( 'webroot' ) ); ?>
						<?php if ( $this->get_disk_stat( 'wp-content' ) !== '-' ) : ?>
							<span id="open-indicator" class="dashicons dashicons-arrow-right"></span>
						<?php endif; ?>
					</span>
				</div>
			</div>
			
			<?php $this->output_disk_details(); ?>
			
			<?php $this->output_wp_update_notification(); ?>
			
			<?php $this->output_widget_footer(); ?>
			<?php
		}
		
		/**
		 * Get memory information
		 *
		 * @return array
		 */
		private function get_memory_info(): array {
			return array(
				'memory_limit' => ini_get( 'memory_limit' ),
				'memory_usage' => function_exists( 'memory_get_usage' ) ? round( memory_get_usage(), 2 ) : 0,
			);
		}
		
		/**
		 * Load disk statistics from file
		 *
		 * @param string $homepath
		 */
		private function load_disk_stats( string $homepath ): void {
			$stats_file = $homepath . '.wp-stats';
			
			if ( file_exists( $stats_file ) ) {
				$decoded = json_decode( file_get_contents( $stats_file ), true );
				$this->disk_stats = is_array( $decoded ) ? $decoded : array();
			} else {
				$this->disk_stats = array();
			}
		}
		
		/**
		 * Get database size
		 *
		 * @return int Database size in bytes
		 */
		private function get_database_size(): int {
			global $wpdb;
			
			$result = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
			$dbsize = 0;
			
			if ( ! empty( $result ) ) {
				foreach ( $result as $row ) {
					$dbsize += $row['Data_length'] + $row['Index_length'];
				}
			}
			
			return $dbsize;
		}
		
		/**
		 * Get WordPress version status HTML
		 *
		 * @param string $wp_version Current WP version
		 * @return string HTML output
		 */
		private function get_wp_version_notice( string $wp_version ): string {
			$update_core_data = get_site_transient( 'update_core' );
			$wp_latest_version = ( isset( $update_core_data->updates[0]->version ) ) ? $update_core_data->updates[0]->version : $wp_version;
			$update_available = version_compare( $wp_latest_version, $wp_version, '>' );
			
			if ( $update_available ) {
				return sprintf(
					'<a href="%s" class="pointer orange hint--top-left hint--rounded hint--bounce" aria-label="%s"><span class="orange dashicons dashicons-update"></span> %s</a>',
					esc_url( admin_url( 'update-core.php' ) ),
					esc_attr__( 'A WordPress update is available', 'wpnet' ),
					esc_html( $wp_version )
				);
			}
			
			return sprintf(
				'<span class="green hint--top-left hint--rounded hint--bounce" aria-label="%s"><span class="dashicons dashicons-yes"></span>%s</span>',
				esc_attr__( 'WordPress is up to date', 'wpnet' ),
				esc_html( $wp_version )
			);
		}
		
		/**
		 * Get PHP version status HTML
		 *
		 * @return string HTML output
		 */
		private function get_php_version_notice(): string {
			$php_version = PHP_VERSION;
			
			if ( version_compare( $php_version, '8.4', '>=' ) ) {
				return sprintf(
					'<span class="red hint--top-left hint--error hint--rounded hint--bounce hint--large" aria-label="%s"><span class="red dashicons dashicons-warning"></span>%s</span>',
					esc_attr( sprintf( __( 'PHP %s support is in BETA. Test carefully!', 'wpnet' ), $php_version ) ),
					esc_html( $php_version )
				);
			}
			
			if ( version_compare( $php_version, '8.2', '>=' ) && version_compare( $php_version, '8.4', '<' ) ) {
				return sprintf(
					'<span class="green hint--top-left hint--rounded hint--bounce hint--large" aria-label="%s"><span class="green dashicons dashicons-yes"></span>%s</span>',
					esc_attr( sprintf( __( 'WordPress core, themes & plugins should be compatible with PHP %s.', 'wpnet' ), $php_version ) ),
					esc_html( $php_version )
				);
			}
			
			if ( version_compare( $php_version, '7.4', '>=' ) && version_compare( $php_version, '8.2', '<' ) ) {
				return sprintf(
					'<span class="orange hint--error hint--top-left hint--rounded hint--bounce hint--large" aria-label="%s"><span class="orange dashicons dashicons-warning"></span>%s</span>',
					esc_attr( sprintf( __( 'PHP %s is compatible with WordPress, but has reached end-of-life. Contact WP NET Support for help.', 'wpnet' ), $php_version ) ),
					esc_html( $php_version )
				);
			}
			
			if ( version_compare( $php_version, '7.4', '<' ) ) {
				return sprintf(
					'<span class="red hint--error hint--top-left hint--rounded hint--bounce hint--large" aria-label="%s"><span class="red dashicons dashicons-warning"></span>v%s</span>',
					esc_attr__( 'You are running an outdated and unsupported version of PHP! Contact WP NET Support for help.', 'wpnet' ),
					esc_html( $php_version )
				);
			}
			
			return sprintf(
				'<span class="green hint--top-left hint--rounded hint--bounce" aria-label="%s">%s</span>',
				esc_attr__( 'PHP version is supported', 'wpnet' ),
				esc_html( $php_version )
			);
		}
		
		/**
		 * Get memory usage notice HTML
		 *
		 * @param array $memory Memory info array
		 * @return string HTML output
		 */
		private function get_memory_notice( array $memory ): string {
			if ( strlen( $memory['memory_limit'] ) >= 6 ) {
				return sprintf(
					'<span class="hint--top-left hint--rounded hint--bounce" aria-label="%s"><span class="green">%s</span></span>',
					esc_attr__( 'Memory used to load this page', 'wpnet' ),
					esc_html( size_format( $memory['memory_usage'] ) )
				);
			}
			
			return sprintf(
				'<span class="hint--top-left hint--rounded hint--bounce" aria-label="%s"><span class="green">%s</span><small>/%sB</small></span>',
				esc_attr__( 'Memory used / Memory available', 'wpnet' ),
				esc_html( size_format( $memory['memory_usage'] ) ),
				esc_html( $memory['memory_limit'] )
			);
		}
		
		/**
		 * Get upload limit notice HTML
		 *
		 * @return string HTML output
		 */
		private function get_upload_limit_notice(): string {
			return sprintf(
				'<span class="hint--top-left hint--rounded hint--bounce" aria-label="%s">%sB</span>',
				esc_attr__( 'Maximum file upload size', 'wpnet' ),
				esc_html( ini_get( 'upload_max_filesize' ) )
			);
		}
		
		/**
		 * Output disk usage link/value
		 */
		private function output_disk_usage_link(): void {
			$disk_usage = $this->get_disk_stat( 'webroot' );
			
			if ( $this->get_disk_stat( 'wp-content' ) !== '-' ) {
				printf(
					'<a href="#" class="wpnet-toggle-diskstats hint--top-right hint--rounded hint--bounce" aria-label="%s">%s<span id="open-indicator" class="dashicons dashicons-arrow-right"></span></a>',
					esc_attr__( 'Web root total size.Updated every 2 hours. Click for more.', 'wpnet' ),
					esc_html( $disk_usage )
				);
			} else {
				echo esc_html( $disk_usage );
			}
		}
		
		/**
		 * Output detailed disk usage stats
		 */
		private function output_disk_details(): void {
			if ( $this->get_disk_stat( 'wp-content' ) === '-' ) {
				return;
			}
			
			$diskstats = array(
				'&#10551;&nbsp;wp-content'	=> $this->get_disk_stat( 'wp-content' ),
				'&#10551;&nbsp;plugins'		=> $this->get_disk_stat( 'plugins' ),
				'&#10551;&nbsp;themes' 		=> $this->get_disk_stat( 'themes' ),
				'&#10551;&nbsp;uploads'		=> $this->get_disk_stat( 'uploads' ),
			);
			
			echo '<div id="wpnet-diskstats" class="wpnet-disk-details" style="display:none;">';
			
			foreach ( $diskstats as $name => $value ) {
				printf(
					'<div class="wpnet-info-item"><span class="label">%s:</span> <span class="data">%s</span></div>',
					wp_kses_post( $name ),
					esc_html( $value )
				);
			}
			
			printf(
				'<p class="note">%s</p>',
				esc_html__( 'Disk usage data updated every 2 hours', 'wpnet' )
			);
			
			echo '</div>';
		}
		
		/**
		 * Output WordPress update notification if needed
		 */
		private function output_wp_update_notification(): void {
			global $wp_version;
			
			$update_core_data = get_site_transient( 'update_core' );
			$wp_latest_version = ( isset( $update_core_data->updates[0]->version ) ) ? $update_core_data->updates[0]->version : $wp_version;
			
			if ( version_compare( $wp_latest_version, $wp_version, '>' ) ) {
				printf(
					'<div class="wpnet-notification"><span class="dashicons dashicons-warning"></span> <strong><a class="hint--top hint--rounded hint--bounce" href="%s" aria-label="%s">%s</a>!</strong></div>',
					esc_url( admin_url( 'update-core.php' ) ),
					esc_attr__( 'Click to update WordPress', 'wpnet' ),
					esc_html( sprintf( __( 'WordPress %s is available', 'wpnet' ), $wp_latest_version ) )
				);
			}
		}
		
		/**
		 * Output widget footer with links
		 */
		private function output_widget_footer(): void {
			?>
			<div class="widget-footer">
				<p>
					<a href="https://my.wpnet.nz/submitticket.php" class="button button-primary" target="_blank" rel="noopener">
						<?php esc_html_e( 'Open Support Ticket', 'wpnet' ); ?>
					</a>
					<a href="https://my.wpnet.nz/knowledgebase" class="button button-primary" target="_blank" rel="noopener">
						<?php esc_html_e( 'KnowledgeBase', 'wpnet' ); ?>
					</a>
					<a href="https://my.wpnet.nz" class="button" target="_blank" rel="noopener">
						<?php esc_html_e( 'My WP NET', 'wpnet' ); ?>
					</a>
				</p>
				<p>
					<span class="blue dashicons dashicons-lightbulb"></span>
					<a href="https://wpnet.nz/wordpress-php/" target="_blank" rel="noopener">
						<?php esc_html_e( 'Read about WordPress and PHP compatibility', 'wpnet' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
		
		/**
		 * Get disk usage stat for a specific directory
		 *
		 * @param string $dir Directory key
		 * @return string Formatted disk usage or '-'
		 */
		private function get_disk_stat( string $dir = 'webroot' ): string {
			return isset( $this->disk_stats[ $dir ] ) ? $this->disk_stats[ $dir ] : '-';
		}
	}
	
	// Initialize widget
	if ( ! defined( 'WPNET_INFO_WIDGET_DISABLE' ) || WPNET_INFO_WIDGET_DISABLE === false ) {
		global $WPNET_Site_Info_Widget;
		$WPNET_Site_Info_Widget = new WPNET_Site_Info_Widget();
	}
}
