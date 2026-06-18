<?php
/**
 * Token-gated client status page at /workshop-status/?job=&token= (spec §9).
 *
 * No data is shown without a valid job_id + client_token match. When approval
 * is required and pending, the client can approve (moves status to approved,
 * notifies staff) or decline (records comments, notifies staff). Client input
 * is never trusted beyond the token match.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

use TossaWorkshop\Admin\Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles the public client status page.
 */
class Client_Status_Page {

	const QUERY_VAR = 'tcw_status_page';

	/**
	 * Singleton instance.
	 *
	 * @var Client_Status_Page|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Client_Status_Page
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Build the client status URL for a job.
	 *
	 * @param string $job_number Job ID (TCW-…).
	 * @param string $token      Client token.
	 * @return string
	 */
	public static function url( $job_number, $token ) {
		return QR_Generator::base_url() . '/workshop-status/?job=' . rawurlencode( $job_number ) . '&token=' . rawurlencode( $token );
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
	}

	/**
	 * Register the rewrite rule. Also called from the activator before flush.
	 *
	 * @return void
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( '^workshop-status/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
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
	 * Handle a client status request (GET render or POST action).
	 *
	 * @return void
	 */
	public function maybe_handle() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		nocache_headers();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended — token is the auth; POST action verifies its own nonce.
		$job_number = isset( $_REQUEST['job'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['job'] ) ) : '';
		$token      = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';
		$done       = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$job_id = $job_number ? $this->resolve_job( $job_number ) : 0;
		$valid  = $job_id && $this->token_matches( $job_id, $token );

		if ( ! $valid ) {
			status_header( 404 );
			$this->render_invalid();
			exit;
		}

		$lang = get_post_meta( $job_id, 'client_language', true );
		$lang = Client_Status_Copy::lang( $lang ? $lang : 'es' );

		// Handle approve/decline submissions (POST).
		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '' ) ) {
			$this->handle_action( $job_id, $job_number, $token, $lang );
			exit;
		}

		status_header( 200 );
		$this->render_page( $job_id, $job_number, $token, $lang, $done );
		exit;
	}

	/**
	 * Process an approve/decline submission, then redirect (PRG).
	 *
	 * @param int    $job_id     Job ID.
	 * @param string $job_number Job ID string.
	 * @param string $token      Client token.
	 * @param string $lang       Client language.
	 * @return void
	 */
	private function handle_action( $job_id, $job_number, $token, $lang ) {
		// Rate limit per token FIRST, so failed/forged attempts are throttled
		// too (not just successfully-nonced ones).
		$rl_key = 'tcw_cs_' . md5( $token );
		$count  = (int) get_transient( $rl_key );
		set_transient( $rl_key, $count + 1, 10 * MINUTE_IN_SECONDS );
		if ( $count >= 10 ) {
			status_header( 429 );
			$this->render_message( $lang, Client_Status_Copy::t( 'too_many', $lang ) );
			return;
		}

		// Nonce tied to the token (defence in depth; the token is the real auth).
		$nonce = isset( $_POST['tcw_cs_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['tcw_cs_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'tcw_client_' . $token ) ) {
			status_header( 403 );
			$this->render_invalid();
			return;
		}

		$action = isset( $_POST['tcw_client_action'] ) ? sanitize_key( wp_unslash( $_POST['tcw_client_action'] ) ) : '';

		$required = (bool) get_post_meta( $job_id, 'approval_required', true );
		$status   = get_post_meta( $job_id, 'approval_status', true );

		$done = '';
		if ( $required && 'pending' === $status ) {
			if ( 'approve' === $action ) {
				update_post_meta( $job_id, 'approval_status', 'approved' );
				update_post_meta( $job_id, 'approval_timestamp', current_time( 'mysql' ) );
				// Client action: move job status to approved (fires the action).
				Job_Status::set( $job_id, 'approved', 0 );
				Notifier::instance()->notify_staff_decision( $job_id, 'approved' );
				$done = 'approved';
			} elseif ( 'decline' === $action ) {
				$comments = isset( $_POST['client_comments'] ) ? sanitize_textarea_field( wp_unslash( $_POST['client_comments'] ) ) : '';
				update_post_meta( $job_id, 'approval_status', 'declined' );
				update_post_meta( $job_id, 'approval_timestamp', current_time( 'mysql' ) );
				update_post_meta( $job_id, 'client_comments', $comments );
				// Move the job to the declined side-exit so the client sees a
				// consistent status and the decline is recorded in history.
				Job_Status::set( $job_id, 'declined', 0 );
				Notifier::instance()->notify_staff_decision( $job_id, 'declined', $comments );
				$done = 'declined';
			}
		}

		wp_safe_redirect( self::url( $job_number, $token ) . ( $done ? '&done=' . $done : '' ) );
		exit;
	}

	/**
	 * Resolve a job post ID from its job_id meta.
	 *
	 * @param string $job_number Job ID string.
	 * @return int
	 */
	private function resolve_job( $job_number ) {
		$query = new \WP_Query(
			array(
				'post_type'              => Repair_Job_CPT::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'job_id',
						'value' => $job_number,
					),
				),
			)
		);
		return $query->have_posts() ? (int) $query->posts[0] : 0;
	}

	/**
	 * Constant-time token comparison.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $token  Supplied token.
	 * @return bool
	 */
	private function token_matches( $job_id, $token ) {
		$stored = (string) get_post_meta( $job_id, 'client_token', true );
		return '' !== $stored && '' !== $token && hash_equals( $stored, $token );
	}

	/**
	 * Format a stored decimal as a localized price, or empty.
	 *
	 * @param mixed  $value Stored value.
	 * @param string $lang  Language code.
	 * @return string
	 */
	private function money( $value, $lang ) {
		if ( ! is_numeric( $value ) ) {
			return '';
		}
		return 'en' === $lang
			? number_format( (float) $value, 2, '.', ',' ) . ' €'
			: number_format( (float) $value, 2, ',', '.' ) . ' €';
	}

	/**
	 * Render the full status page for a valid token.
	 *
	 * @param int    $job_id     Job ID.
	 * @param string $job_number Job ID string.
	 * @param string $token      Client token.
	 * @param string $lang       Client language.
	 * @param string $done       'approved'|'declined'|'' flash.
	 * @return void
	 */
	private function render_page( $job_id, $job_number, $token, $lang, $done ) {
		$settings = Settings_Page::get_settings();
		$status   = Job_Status::get( $job_id );
		$copy     = Client_Status_Copy::status( $status, $lang );

		$bike_id = (int) get_post_meta( $job_id, 'bike_id', true );
		$bike    = '';
		if ( $bike_id ) {
			$bike = trim( get_post_meta( $bike_id, 'brand', true ) . ' ' . get_post_meta( $bike_id, 'model', true ) );
		}

		$this->head( Client_Status_Copy::t( 'page_title', $lang ), $lang );
		?>
		<div class="tcw-card">
			<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>

			<?php if ( 'approved' === $done ) : ?>
				<div class="tcw-flash tcw-ok"><?php echo esc_html( Client_Status_Copy::t( 'approved_thanks', $lang ) ); ?></div>
			<?php elseif ( 'declined' === $done ) : ?>
				<div class="tcw-flash tcw-ok"><?php echo esc_html( Client_Status_Copy::t( 'declined_thanks', $lang ) ); ?></div>
			<?php endif; ?>

			<div class="tcw-status">
				<span class="tcw-badge"><?php echo esc_html( $copy['label'] ); ?></span>
				<p class="tcw-desc"><?php echo esc_html( $copy['desc'] ); ?></p>
			</div>

			<dl class="tcw-meta">
				<dt><?php echo esc_html( Client_Status_Copy::t( 'job', $lang ) ); ?></dt>
				<dd><?php echo esc_html( $job_number ); ?></dd>
				<?php if ( $bike ) : ?>
					<dt><?php echo esc_html( Client_Status_Copy::t( 'bike', $lang ) ); ?></dt>
					<dd><?php echo esc_html( $bike ); ?></dd>
				<?php endif; ?>
				<?php
				$promised = get_post_meta( $job_id, 'promised_completion', true );
				if ( $promised ) :
					?>
					<dt><?php echo esc_html( Client_Status_Copy::t( 'promised', $lang ) ); ?></dt>
					<dd><?php echo esc_html( $promised ); ?></dd>
				<?php endif; ?>
				<?php
				$estimate = $this->money( get_post_meta( $job_id, 'estimate_amount', true ), $lang );
				if ( '' !== $estimate ) :
					?>
					<dt><?php echo esc_html( Client_Status_Copy::t( 'estimate', $lang ) ); ?></dt>
					<dd><?php echo esc_html( $estimate ); ?></dd>
				<?php endif; ?>
				<?php
				if ( 'ready' === $status ) :
					$final = $this->money( get_post_meta( $job_id, 'final_price', true ), $lang );
					if ( '' !== $final ) :
						?>
						<dt><?php echo esc_html( Client_Status_Copy::t( 'final_price', $lang ) ); ?></dt>
						<dd><?php echo esc_html( $final ); ?></dd>
					<?php endif; ?>
					<?php if ( ! empty( $settings['shop_address'] ) ) : ?>
						<dt><?php echo esc_html( Client_Status_Copy::t( 'pickup', $lang ) ); ?></dt>
						<dd><?php echo esc_html( $settings['shop_address'] ); ?></dd>
					<?php endif; ?>
				<?php endif; ?>
			</dl>

			<?php
			$this->work_block( $job_id, 'recommended_work', Client_Status_Copy::t( 'recommended', $lang ) );
			$this->work_block( $job_id, 'mandatory_work', Client_Status_Copy::t( 'mandatory', $lang ) );
			$this->work_block( $job_id, 'optional_work', Client_Status_Copy::t( 'optional', $lang ) );
			?>

			<?php
			$required = (bool) get_post_meta( $job_id, 'approval_required', true );
			$astatus  = get_post_meta( $job_id, 'approval_status', true );
			if ( $required && 'pending' === $astatus ) :
				?>
				<div class="tcw-approve">
					<p><strong><?php echo esc_html( Client_Status_Copy::t( 'approve_intro', $lang ) ); ?></strong></p>
					<form method="post" action="<?php echo esc_url( self::url( $job_number, $token ) ); ?>">
						<?php wp_nonce_field( 'tcw_client_' . $token, 'tcw_cs_nonce' ); ?>
						<textarea name="client_comments" rows="3" placeholder="<?php echo esc_attr( Client_Status_Copy::t( 'comments', $lang ) ); ?>"></textarea>
						<div class="tcw-buttons">
							<button type="submit" name="tcw_client_action" value="approve" class="tcw-btn tcw-btn-ok"><?php echo esc_html( Client_Status_Copy::t( 'approve', $lang ) ); ?></button>
							<button type="submit" name="tcw_client_action" value="decline" class="tcw-btn tcw-btn-no"><?php echo esc_html( Client_Status_Copy::t( 'decline', $lang ) ); ?></button>
						</div>
					</form>
				</div>
			<?php endif; ?>

			<?php $this->contact_links( $job_number, $lang, $settings ); ?>
		</div>
		<?php
		$this->foot();
	}

	/**
	 * Output a labelled work block if the meta is non-empty.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $key    Meta key.
	 * @param string $label  Localized label.
	 * @return void
	 */
	private function work_block( $job_id, $key, $label ) {
		$value = get_post_meta( $job_id, $key, true );
		if ( '' === trim( (string) $value ) ) {
			return;
		}
		echo '<div class="tcw-work"><h3>' . esc_html( $label ) . '</h3><p>' . nl2br( esc_html( $value ) ) . '</p></div>';
	}

	/**
	 * Output the WhatsApp + contact links.
	 *
	 * @param string $job_number Job ID string.
	 * @param string $lang       Client language.
	 * @param array  $settings   Plugin settings.
	 * @return void
	 */
	private function contact_links( $job_number, $lang, array $settings ) {
		echo '<div class="tcw-contact">';

		$phone = isset( $settings['shop_phone'] ) ? preg_replace( '/\D+/', '', $settings['shop_phone'] ) : '';
		if ( $phone ) {
			$text = rawurlencode( sprintf( '%s — %s', get_bloginfo( 'name' ), $job_number ) );
			echo '<a class="tcw-btn tcw-btn-wa" href="' . esc_url( 'https://wa.me/' . $phone . '?text=' . $text ) . '" target="_blank" rel="noopener">' . esc_html( Client_Status_Copy::t( 'whatsapp', $lang ) ) . '</a>';
		}

		$email = ! empty( $settings['shop_email'] ) ? $settings['shop_email'] : get_option( 'admin_email' );
		if ( $email && is_email( $email ) ) {
			$subject = rawurlencode( sprintf( '%s — %s', Client_Status_Copy::t( 'ask', $lang ), $job_number ) );
			echo '<a class="tcw-btn" href="' . esc_url( 'mailto:' . $email . '?subject=' . $subject ) . '">' . esc_html( Client_Status_Copy::t( 'ask', $lang ) ) . '</a>';
		}

		echo '</div>';
	}

	/**
	 * Render the generic invalid-link page (no data).
	 *
	 * @return void
	 */
	private function render_invalid() {
		$lang = 'es';
		$this->head( Client_Status_Copy::t( 'invalid_title', $lang ), $lang );
		echo '<div class="tcw-card"><h1>' . esc_html( get_bloginfo( 'name' ) ) . '</h1>';
		echo '<p>' . esc_html( Client_Status_Copy::t( 'invalid_msg', $lang ) ) . '</p></div>';
		$this->foot();
	}

	/**
	 * Render a simple one-message page.
	 *
	 * @param string $lang    Language.
	 * @param string $message Message text.
	 * @return void
	 */
	private function render_message( $lang, $message ) {
		$this->head( get_bloginfo( 'name' ), $lang );
		echo '<div class="tcw-card"><h1>' . esc_html( get_bloginfo( 'name' ) ) . '</h1>';
		echo '<p>' . esc_html( $message ) . '</p></div>';
		$this->foot();
	}

	/**
	 * Output the page head + styles.
	 *
	 * @param string $title Page title.
	 * @param string $lang  Language code.
	 * @return void
	 */
	private function head( $title, $lang ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		:root{--tcw:#1f6f3c;}
		*{box-sizing:border-box;}
		body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f4f5f4;color:#1d2327;margin:0;padding:16px;}
		.tcw-card{background:#fff;border:1px solid #e2e4e2;border-radius:12px;max-width:520px;margin:16px auto;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
		h1{font-size:1.3rem;margin:0 0 16px;color:var(--tcw);}
		.tcw-status{margin:8px 0 16px;}
		.tcw-badge{display:inline-block;background:var(--tcw);color:#fff;border-radius:999px;padding:4px 14px;font-weight:600;font-size:.95rem;}
		.tcw-desc{margin:10px 0 0;line-height:1.5;}
		.tcw-meta{display:grid;grid-template-columns:auto 1fr;gap:4px 16px;margin:16px 0;padding:16px 0;border-top:1px solid #eee;border-bottom:1px solid #eee;}
		.tcw-meta dt{font-weight:600;color:#50575e;}
		.tcw-meta dd{margin:0;}
		.tcw-work{margin:12px 0;}
		.tcw-work h3{margin:0 0 4px;font-size:1rem;}
		.tcw-work p{margin:0;line-height:1.5;}
		.tcw-approve{margin:18px 0;padding:16px;background:#f6faf7;border:1px solid #d8ebde;border-radius:10px;}
		.tcw-approve textarea{width:100%;border:1px solid #ccc;border-radius:8px;padding:8px;font-size:1rem;}
		.tcw-buttons{display:flex;gap:10px;margin-top:10px;}
		.tcw-btn{display:inline-block;padding:12px 18px;border-radius:8px;border:0;font-size:1rem;font-weight:600;cursor:pointer;text-decoration:none;text-align:center;}
		.tcw-btn-ok{background:var(--tcw);color:#fff;flex:1;}
		.tcw-btn-no{background:#eee;color:#1d2327;flex:1;}
		.tcw-btn-wa{background:#25d366;color:#fff;}
		.tcw-contact{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px;}
		.tcw-contact .tcw-btn{background:#f0f0f1;color:#1d2327;}
		.tcw-contact .tcw-btn-wa{background:#25d366;color:#fff;}
		.tcw-flash{padding:12px;border-radius:8px;margin-bottom:14px;}
		.tcw-ok{background:#e6f4ea;border:1px solid #b7e0c2;}
	</style>
</head>
<body>
		<?php
	}

	/**
	 * Output the page foot.
	 *
	 * @return void
	 */
	private function foot() {
		echo '</body></html>';
	}
}
