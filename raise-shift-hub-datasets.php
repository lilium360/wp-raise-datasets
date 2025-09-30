<?php
/**
 * Plugin Name: Raise Datasets Marketplace
 * Plugin URI: https://github.com/lilium360
 * Description: Provides the [raise_datasets] shortcode to show datasets from the RAISE marketplace.
 * Version: 1.0.2
 * Author: Ilyo D'Urso
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: raise-datasets
 */

defined( 'ABSPATH' ) || exit;

define( 'RAISE_DATASETS_VERSION', '1.0.2' );
define( 'RAISE_DATASETS_PLUGIN_FILE', __FILE__ );
define( 'RAISE_DATASETS_PLUGIN_PATH', plugin_dir_path( RAISE_DATASETS_PLUGIN_FILE ) );
define( 'RAISE_DATASETS_PLUGIN_URL', plugin_dir_url( RAISE_DATASETS_PLUGIN_FILE ) );

require_once RAISE_DATASETS_PLUGIN_PATH . 'includes/class-raise-datasets-rest.php';

final class Raise_Datasets_Plugin {
	/**
	 * Returns the singleton instance.
	 */
	public static function instance(): self {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'register_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'rest_api_init', [ 'Raise_Datasets_REST', 'register_routes' ] );
	}

	/**
	 * Registers the shortcode that renders the dataset list container.
	 */
	public function register_shortcode(): void {
		add_shortcode( 'raise_datasets', [ $this, 'render_shortcode' ] );
	}

	/**
	 * Loads the plugin text domain for translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'raise-datasets', false, dirname( plugin_basename( RAISE_DATASETS_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Registers the frontend scripts and styles.
	 */
	public function register_assets(): void {
		wp_register_style(
			'raise-datasets-frontend',
			RAISE_DATASETS_PLUGIN_URL . 'assets/css/raise-datasets-frontend.css',
			[],
			RAISE_DATASETS_VERSION
		);

		wp_register_script(
			'raise-datasets-frontend',
			RAISE_DATASETS_PLUGIN_URL . 'assets/js/raise-datasets-frontend.js',
			[],
			RAISE_DATASETS_VERSION,
			true
		);

		wp_localize_script(
			'raise-datasets-frontend',
			'raiseDatasetsSettings',
			[
				'apiRoot'   => esc_url_raw( rest_url( 'raise/v1/datasets' ) ),
				'perPage'   => 50,
				'i18n'      => [
					'loading'        => __( 'Loading datasets…', 'raise-datasets' ),
					'noResults'      => __( 'No datasets found for the current filters.', 'raise-datasets' ),
					'error'          => __( 'We could not load the datasets right now. Please try again later.', 'raise-datasets' ),
					'viewDetails'    => __( 'View details', 'raise-datasets' ),
					'untitled'       => __( 'Untitled dataset', 'raise-datasets' ),
					'organization'   => __( 'Organization', 'raise-datasets' ),
					'previousPage'   => __( 'Previous', 'raise-datasets' ),
					'nextPage'       => __( 'Next', 'raise-datasets' ),
				],
			]
		);
	}

	/**
	 * Renders the markup for the shortcode.
	 */
	public function render_shortcode( $atts = [] ): string {
		static $instance = 0;
		$instance ++;
		$search_id = 'raise-datasets-search-' . $instance;

		$atts = shortcode_atts(
			[
				'per_page' => 50,
			],
			$atts,
			'raise_datasets'
		);

		$per_page = (int) $atts['per_page'];
		if ( $per_page < 1 ) {
			$per_page = 10;
		}

		if ( $per_page > 50 ) {
			$per_page = 50;
		}

		wp_enqueue_style( 'raise-datasets-frontend' );
		wp_enqueue_script( 'raise-datasets-frontend' );

		ob_start();
		?>
		<div class="raise-datasets" data-per-page="<?php echo esc_attr( $per_page ); ?>">
			<form class="raise-datasets__controls" role="search">
				<label class="screen-reader-text" for="<?php echo esc_attr( $search_id ); ?>">
					<?php esc_html_e( 'Search datasets', 'raise-datasets' ); ?>
				</label>
				<input
					type="search"
					id="<?php echo esc_attr( $search_id ); ?>"
					class="raise-datasets__search-input"
					placeholder="<?php esc_attr_e( 'Search datasets…', 'raise-datasets' ); ?>"
				>
				<button type="submit" class="raise-datasets__search-button">
					<?php esc_html_e( 'Search', 'raise-datasets' ); ?>
				</button>
			</form>
			<div class="raise-datasets__status" aria-live="polite"></div>
			<div class="raise-datasets__results" aria-live="polite"></div>
			<div class="raise-datasets__pagination">
				<button type="button" class="raise-datasets__button" data-action="prev" disabled>
					<?php esc_html_e( 'Previous', 'raise-datasets' ); ?>
				</button>
				<button type="button" class="raise-datasets__button" data-action="next" disabled>
					<?php esc_html_e( 'Next', 'raise-datasets' ); ?>
				</button>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

Raise_Datasets_Plugin::instance();
