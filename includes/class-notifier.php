<?php
/**
 * Client notifications on status change (spec §8).
 *
 * Listens to tcw_job_status_changed and emails the job's stored client in
 * their stored language when the target status is enabled. Also exposes a
 * manual "resend last notification" admin action.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and sends client emails.
 */
class Notifier {

	const LAST_META = 'last_notification';

	/**
	 * Singleton instance.
	 *
	 * @var Notifier|null
	 */
	private static $instance = null;

	/**
	 * Last wp_mail error message captured during a send attempt.
	 *
	 * @var string
	 */
	private $mail_error = '';

	/**
	 * Get the singleton instance.
	 *
	 * @return Notifier
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
		add_action( 'tcw_job_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
		add_action( 'admin_action_tcw_resend_notification', array( $this, 'handle_resend' ) );
		add_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );
	}

	/**
	 * Capture the mailer error message so failures can be explained.
	 *
	 * @param \WP_Error $error Error from wp_mail.
	 * @return void
	 */
	public function capture_mail_error( $error ) {
		if ( is_wp_error( $error ) ) {
			$this->mail_error = $error->get_error_message();
		}
	}

	/**
	 * React to a status change: send the client email if that status is enabled.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $from   Previous status.
	 * @param string $to     New status.
	 * @return void
	 */
	public function on_status_changed( $job_id, $from, $to ) {
		if ( ! Notification_Templates::is_enabled( $to ) ) {
			return;
		}

		/**
		 * Allow a caller to suppress just the client email for one status change
		 * while keeping the status, history and the status-changed action. Used
		 * by the intake "send received email?" toggle.
		 *
		 * @param bool   $send   Whether to send.
		 * @param int    $job_id Job ID.
		 * @param string $to     New status slug.
		 */
		if ( false === apply_filters( 'tcw_send_client_notification', true, $job_id, $to ) ) {
			return;
		}

		$this->send( $job_id, $to );
	}

	/**
	 * Send the client email for a given status.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $status Status slug.
	 * @return bool True if wp_mail reported success.
	 */
	public function send( $job_id, $status ) {
		$email = get_post_meta( $job_id, 'client_email', true );
		if ( ! $email || ! is_email( $email ) ) {
			return false;
		}

		$lang = get_post_meta( $job_id, 'client_language', true );
		$lang = $lang ? $lang : 'es';

		$tpl  = Notification_Templates::get( $status, $lang );
		$vars = $this->build_vars( $job_id, $status, $lang );

		$subject = Notification_Templates::render( $tpl['subject'], $vars );
		$body    = Notification_Templates::render( $tpl['body'], $vars );

		$this->mail_error = '';
		$sent             = (bool) wp_mail( $email, $subject, $body, $this->mail_headers() );

		update_post_meta(
			$job_id,
			self::LAST_META,
			array(
				'status'    => $status,
				'lang'      => $lang,
				'to'        => $email,
				'timestamp' => current_time( 'mysql' ),
				'sent'      => $sent,
				'error'     => $sent ? '' : $this->mail_error,
			)
		);

		return $sent;
	}

	/**
	 * Build the From header so notifications route through the same mail/SMTP
	 * sender as the rest of the site (many SMTP plugins route by From address).
	 * The From email defaults to the shop contact email, then the site admin
	 * email; overridable via filter.
	 *
	 * @return string[]
	 */
	private function mail_headers() {
		$settings = Admin\Settings_Page::get_settings();
		$from     = ! empty( $settings['shop_email'] ) && is_email( $settings['shop_email'] )
			? $settings['shop_email']
			: get_option( 'admin_email' );
		$name     = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		/**
		 * Filter the From address used for workshop notifications.
		 *
		 * @param string $from From email address.
		 */
		$from = apply_filters( 'tcw_mail_from', $from );

		$headers = array();
		if ( $from && is_email( $from ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $name, $from );
		}
		return $headers;
	}

	/**
	 * Build the placeholder map for a job.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $status Status slug.
	 * @param string $lang   Language code (for number formatting).
	 * @return array<string,string>
	 */
	private function build_vars( $job_id, $status, $lang = 'es' ) {
		$settings = Admin\Settings_Page::get_settings();
		$labels   = Job_Status_Taxonomy::status_labels();

		$bike_id = (int) get_post_meta( $job_id, 'bike_id', true );
		$bike    = '';
		if ( $bike_id ) {
			$brand    = get_post_meta( $bike_id, 'brand', true );
			$model    = get_post_meta( $bike_id, 'model', true );
			$internal = get_post_meta( $bike_id, 'internal_id', true );
			$bike     = trim( trim( $brand . ' ' . $model ) . ( $internal ? ' (' . $internal . ')' : '' ) );
		}

		$job_number = get_post_meta( $job_id, 'job_id', true );
		$token      = get_post_meta( $job_id, 'client_token', true );
		$status_url = ( $job_number && $token ) ? Client_Status_Page::url( $job_number, $token ) : '';

		return array(
			'client_name'  => (string) get_post_meta( $job_id, 'client_name', true ),
			'bike'         => $bike,
			'job_id'       => (string) $job_number,
			'status_url'   => $status_url,
			'estimate'     => $this->money( get_post_meta( $job_id, 'estimate_amount', true ), $lang ),
			'final_price'  => $this->money( get_post_meta( $job_id, 'final_price', true ), $lang ),
			'shop_address' => (string) ( isset( $settings['shop_address'] ) ? $settings['shop_address'] : '' ),
			'status'       => isset( $labels[ $status ] ) ? $labels[ $status ] : $status,
		);
	}

	/**
	 * Format a stored decimal as a price, or empty string. Uses
	 * comma-decimal / dot-grouping for es/de, dot-decimal for en.
	 *
	 * @param mixed  $value Stored value.
	 * @param string $lang  Language code.
	 * @return string
	 */
	private function money( $value, $lang = 'es' ) {
		if ( ! is_numeric( $value ) ) {
			return '';
		}
		if ( 'en' === $lang ) {
			return number_format( (float) $value, 2, '.', ',' ) . ' €';
		}
		return number_format( (float) $value, 2, ',', '.' ) . ' €';
	}

	/**
	 * Email staff about a client approval decision (spec §9). Staff-facing, so
	 * it uses gettext (site/admin language).
	 *
	 * @param int    $job_id   Job ID.
	 * @param string $decision 'approved' or 'declined'.
	 * @param string $comments Optional client comments.
	 * @return bool
	 */
	public function notify_staff_decision( $job_id, $decision, $comments = '' ) {
		$settings = Admin\Settings_Page::get_settings();
		$to       = ! empty( $settings['staff_notify_email'] ) ? $settings['staff_notify_email'] : get_option( 'admin_email' );

		/**
		 * Filter the staff notification recipient.
		 *
		 * @param string $to     Email address.
		 * @param int    $job_id Job ID.
		 */
		$to = apply_filters( 'tcw_staff_notify_email', $to, $job_id );
		if ( ! $to || ! is_email( $to ) ) {
			return false;
		}

		// sanitize_text_field strips CR/LF, hardening the subject against header
		// injection even though job_id is plugin-generated.
		$job_number = sanitize_text_field( get_post_meta( $job_id, 'job_id', true ) );
		$client     = get_post_meta( $job_id, 'client_name', true );
		$edit_url   = get_edit_post_link( $job_id, 'raw' );

		if ( 'approved' === $decision ) {
			/* translators: %s: job ID */
			$subject = sprintf( __( 'Client approved estimate — %s', 'tossa-workshop' ), $job_number );
		} else {
			/* translators: %s: job ID */
			$subject = sprintf( __( 'Client declined estimate — %s', 'tossa-workshop' ), $job_number );
		}

		$lines   = array();
		$lines[] = $subject;
		$lines[] = '';
		/* translators: %s: client name */
		$lines[] = sprintf( __( 'Client: %s', 'tossa-workshop' ), $client ? $client : '—' );
		if ( '' !== trim( (string) $comments ) ) {
			/* translators: %s: client comments */
			$lines[] = sprintf( __( 'Comments: %s', 'tossa-workshop' ), $comments );
		}
		$lines[] = '';
		$lines[] = $edit_url;

		return (bool) wp_mail( $to, $subject, implode( "\n", $lines ), $this->mail_headers() );
	}

	/**
	 * Handle the "resend last notification" admin action.
	 *
	 * @return void
	 */
	public function handle_resend() {
		$job_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $job_id || ! wp_verify_nonce( $nonce, 'tcw_resend_' . $job_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'tossa-workshop' ) );
		}
		if ( Repair_Job_CPT::POST_TYPE !== get_post_type( $job_id ) || ! current_user_can( 'edit_post', $job_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tossa-workshop' ) );
		}

		// Resend the notification for the current status (manual override of the
		// per-status toggle).
		$last   = get_post_meta( $job_id, self::LAST_META, true );
		$status = ( is_array( $last ) && ! empty( $last['status'] ) ) ? $last['status'] : Job_Status::get( $job_id );

		$ok = $status ? $this->send( $job_id, $status ) : false;

		// 'raw' context returns an unescaped & so add_query_arg / the redirect
		// header don't end up with a double-encoded &amp;.
		wp_safe_redirect( add_query_arg( 'tcw_resent', $ok ? '1' : '0', get_edit_post_link( $job_id, 'raw' ) ) );
		exit;
	}
}
