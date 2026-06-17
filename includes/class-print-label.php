<?php
/**
 * Printable bike label + QR regeneration.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

use TossaWorkshop\Admin\Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the print-label view and manual QR regeneration.
 */
class Print_Label {

	/**
	 * Singleton instance.
	 *
	 * @var Print_Label|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Print_Label
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
		add_action( 'admin_action_tcw_print_label', array( $this, 'print_label' ) );
		add_action( 'admin_action_tcw_regenerate_qr', array( $this, 'regenerate_qr' ) );
	}

	/**
	 * Resolve and authorize the requested bike from the admin action.
	 *
	 * @param string $nonce_prefix Nonce action prefix.
	 * @return int Bike post ID.
	 */
	private function authorized_bike( $nonce_prefix ) {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $post_id || ! wp_verify_nonce( $nonce, $nonce_prefix . $post_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'tossa-workshop' ) );
		}
		if ( Bike_CPT::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tossa-workshop' ) );
		}
		return $post_id;
	}

	/**
	 * Output a printable A4/label-friendly page for a bike.
	 *
	 * @return void
	 */
	public function print_label() {
		$post_id     = $this->authorized_bike( 'tcw_print_label_' );
		$internal_id = get_post_meta( $post_id, 'internal_id', true );
		$qr_id       = (int) get_post_meta( $post_id, 'qr_attachment_id', true );
		$qr_src      = $qr_id ? wp_get_attachment_image_url( $qr_id, 'full' ) : '';

		$settings = Settings_Page::get_settings();
		$shop     = get_bloginfo( 'name' );
		$website  = QR_Generator::base_url();
		$phone    = isset( $settings['shop_phone'] ) ? $settings['shop_phone'] : '';

		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( $internal_id ); ?></title>
	<style>
		*{box-sizing:border-box;}
		body{font-family:Arial,Helvetica,sans-serif;color:#000;margin:0;padding:16mm;}
		.label{border:2px solid #000;border-radius:8px;padding:8mm;max-width:120mm;margin:0 auto;text-align:center;}
		.shop{font-size:18pt;font-weight:bold;margin:0 0 4mm;}
		.qr img{width:55mm;height:55mm;}
		.id{font-size:22pt;font-weight:bold;letter-spacing:1px;margin:4mm 0;}
		.contact{font-size:11pt;margin-top:4mm;}
		.print-btn{margin:8mm auto 0;display:block;padding:8px 16px;font-size:12pt;cursor:pointer;}
		@media print{.print-btn{display:none;}body{padding:0;}}
	</style>
</head>
<body onload="window.focus()">
	<div class="label">
		<p class="shop"><?php echo esc_html( $shop ); ?></p>
		<?php if ( $qr_src ) : ?>
			<div class="qr"><img src="<?php echo esc_url( $qr_src ); ?>" alt="<?php echo esc_attr( $internal_id ); ?>" /></div>
		<?php endif; ?>
		<p class="id"><?php echo esc_html( $internal_id ); ?></p>
		<p class="contact">
			<?php if ( $phone ) : ?>
				<?php echo esc_html( $phone ); ?><br />
			<?php endif; ?>
			<?php echo esc_html( preg_replace( '#^https?://#', '', $website ) ); ?>
		</p>
	</div>
	<button class="print-btn" onclick="window.print()"><?php esc_html_e( 'Print', 'tossa-workshop' ); ?></button>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Regenerate a bike's QR image, then return to the edit screen.
	 *
	 * @return void
	 */
	public function regenerate_qr() {
		$post_id     = $this->authorized_bike( 'tcw_regenerate_qr_' );
		$internal_id = get_post_meta( $post_id, 'internal_id', true );

		if ( $internal_id ) {
			$old = (int) get_post_meta( $post_id, 'qr_attachment_id', true );
			$new = QR_Generator::generate_for_bike( $post_id, $internal_id );
			if ( ! is_wp_error( $new ) ) {
				update_post_meta( $post_id, 'qr_attachment_id', $new );
				if ( $old && $old !== $new ) {
					wp_delete_attachment( $old, true );
				}
			}
		}

		wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
		exit;
	}
}
