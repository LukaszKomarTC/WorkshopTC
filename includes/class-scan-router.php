<?php
/**
 * Front-end scan router for /workshop-scan/?id={internal_id}.
 *
 * Staff (logged in + tcw_view_bikes) are redirected to the bike's edit screen.
 * Everyone else sees a minimal page that reveals no personal data. Unknown IDs
 * get a friendly not-found page. Kept distinct from the client status URL.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves scanned bike IDs.
 */
class Scan_Router {

	const QUERY_VAR = 'tcw_scan';

	/**
	 * Singleton instance.
	 *
	 * @var Scan_Router|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Scan_Router
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle' ) );
		add_action( 'admin_init', array( $this, 'maybe_flush' ) );
	}

	/**
	 * Flush rewrite rules once after a plugin update so the scan rule persists
	 * even when the plugin is upgraded in place (no deactivate/reactivate).
	 *
	 * Runs on admin_init, i.e. after the rule is registered on init.
	 *
	 * @return void
	 */
	public function maybe_flush() {
		if ( get_option( 'tcw_rewrite_version' ) !== TCW_VERSION ) {
			flush_rewrite_rules();
			update_option( 'tcw_rewrite_version', TCW_VERSION, false );
		}
	}

	/**
	 * Register the rewrite rule. Also called from the activator before flush.
	 *
	 * @return void
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( '^workshop-scan/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Register the query var.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Handle a scan request.
	 *
	 * @return void
	 */
	public function maybe_handle() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended — read-only public lookup.
		$internal_id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';

		$bike_id = $internal_id ? $this->resolve_bike( $internal_id ) : 0;

		if ( $bike_id && is_user_logged_in() && current_user_can( 'tcw_view_bikes' ) ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . $bike_id . '&action=edit' ) );
			exit;
		}

		status_header( $bike_id ? 200 : 404 );
		nocache_headers();
		$this->render_public_page( $internal_id, (bool) $bike_id );
		exit;
	}

	/**
	 * Resolve a bike post ID from its internal ID.
	 *
	 * @param string $internal_id Internal ID.
	 * @return int Bike post ID, or 0.
	 */
	private function resolve_bike( $internal_id ) {
		$query = new \WP_Query(
			array(
				'post_type'              => Bike_CPT::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'internal_id',
						'value' => $internal_id,
					),
				),
			)
		);

		return $query->have_posts() ? (int) $query->posts[0] : 0;
	}

	/**
	 * Render the minimal public page (no PII).
	 *
	 * @param string $internal_id Internal ID from the request.
	 * @param bool   $found       Whether the bike exists.
	 * @return void
	 */
	private function render_public_page( $internal_id, $found ) {
		$shop = get_bloginfo( 'name' );

		if ( $found ) {
			$title   = __( 'Tossa Cycling', 'tossa-workshop' );
			$message = sprintf(
				/* translators: %s: bike internal ID */
				__( 'Bike %s. Please contact the shop for details.', 'tossa-workshop' ),
				$internal_id
			);
		} else {
			$title   = __( 'Tossa Cycling', 'tossa-workshop' );
			$message = __( 'Sorry, we could not find that bike. Please contact the shop.', 'tossa-workshop' );
		}

		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f6f7f7;color:#1d2327;margin:0;padding:2rem;display:flex;min-height:90vh;align-items:center;justify-content:center;}
		.card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:2rem;max-width:420px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.06);}
		h1{font-size:1.4rem;margin:0 0 .5rem;}
		p{margin:.5rem 0;line-height:1.5;}
		code{background:#f0f0f1;padding:.1rem .4rem;border-radius:4px;}
	</style>
</head>
<body>
	<div class="card">
		<h1><?php echo esc_html( $shop ? $shop : $title ); ?></h1>
		<p><?php echo esc_html( $message ); ?></p>
	</div>
</body>
</html>
		<?php
	}
}
