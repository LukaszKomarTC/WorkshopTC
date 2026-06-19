<?php
/**
 * Help / Getting started admin page.
 *
 * A short in-product guide for shop staff: the everyday workflow, what each
 * setting does, the roles, and the steps to perform after installing/updating.
 * Placed under the Repair Jobs menu so every workshop role can reach it.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop\Admin;

use TossaWorkshop\Repair_Job_CPT;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Help / Getting started page.
 */
class Help_Page {

	const PAGE       = 'tcw-help';
	const CAPABILITY = 'tcw_edit_jobs';

	/**
	 * Singleton instance.
	 *
	 * @var Help_Page|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Help_Page
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
		add_action( 'admin_menu', array( $this, 'add_page' ) );
	}

	/**
	 * Register the submenu page under Repair Jobs.
	 *
	 * @return void
	 */
	public function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . Repair_Job_CPT::POST_TYPE,
			__( 'Help & Getting started', 'tossa-workshop' ),
			__( 'Help', 'tossa-workshop' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tossa-workshop' ) );
		}
		?>
		<div class="wrap tcw-help">
			<h1><?php esc_html_e( 'Tossa Workshop - Help & Getting started', 'tossa-workshop' ); ?></h1>

			<h2><?php esc_html_e( 'Everyday workflow', 'tossa-workshop' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Add a bike under "Bikes". On the first save it gets a permanent internal ID (TCB-000001 for customers, TCF- for fleet) and a QR code.', 'tossa-workshop' ); ?></li>
				<li><?php esc_html_e( 'Open the bike and use "Print label" to print the QR and ID, then stick it on the bike.', 'tossa-workshop' ); ?></li>
				<li><?php esc_html_e( 'Create a Repair Job, link the bike with the search box (by internal ID, serial, or owner name/email/phone), and fill in the intake details.', 'tossa-workshop' ); ?></li>
				<li><?php esc_html_e( 'Move the job through the status pipeline using the Status dropdown. Each change is logged and can email the client.', 'tossa-workshop' ); ?></li>
				<li><?php esc_html_e( 'Share the client link (shown in the job Status box) so the client can follow progress and approve or decline an estimate.', 'tossa-workshop' ); ?></li>
				<li><?php esc_html_e( 'Scanning a bike QR code sends staff straight to its record; the public sees only a "contact the shop" page.', 'tossa-workshop' ); ?></li>
			</ol>

			<h2><?php esc_html_e( 'Settings (Workshop, Settings)', 'tossa-workshop' ); ?></h2>
			<ul>
				<li><strong><?php esc_html_e( 'Public base URL', 'tossa-workshop' ); ?></strong> &mdash; <?php esc_html_e( 'the address used to build scan and client links. Leave empty to use the site URL.', 'tossa-workshop' ); ?></li>
				<li><strong><?php esc_html_e( 'Shop phone', 'tossa-workshop' ); ?></strong> &mdash; <?php esc_html_e( 'shown on printed labels and used for the WhatsApp button on the client page (digits only, e.g. 34123456789).', 'tossa-workshop' ); ?></li>
				<li><strong><?php esc_html_e( 'Shop address', 'tossa-workshop' ); ?></strong> &mdash; <?php esc_html_e( 'shown as the pickup address when a job is ready.', 'tossa-workshop' ); ?></li>
				<li><strong><?php esc_html_e( 'Shop contact email', 'tossa-workshop' ); ?></strong> &mdash; <?php esc_html_e( 'used for the "Ask a question" link on the client page.', 'tossa-workshop' ); ?></li>
				<li><strong><?php esc_html_e( 'Staff notification email', 'tossa-workshop' ); ?></strong> &mdash; <?php esc_html_e( 'where client approve/decline alerts are sent (defaults to the site admin email).', 'tossa-workshop' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Under Workshop, Notifications you can turn the client email on or off per status and edit the subject/body for each language (Spanish, English, German).', 'tossa-workshop' ); ?></p>

			<h2><?php esc_html_e( 'Who can do what', 'tossa-workshop' ); ?></h2>
			<ul>
				<li><strong><?php esc_html_e( 'Workshop Manager', 'tossa-workshop' ); ?></strong> &mdash; <?php esc_html_e( 'full access, including prices, closing jobs and settings.', 'tossa-workshop' ); ?></li>
				<li><strong><?php esc_html_e( 'Workshop Mechanic', 'tossa-workshop' ); ?></strong> &mdash; <?php esc_html_e( 'works on jobs and moves status up to Quality check; cannot edit prices or close jobs.', 'tossa-workshop' ); ?></li>
				<li><strong><?php esc_html_e( 'Workshop Front Desk', 'tossa-workshop' ); ?></strong> &mdash; <?php esc_html_e( 'creates bikes and jobs, runs intake, sends notifications and marks bikes collected; no full financials.', 'tossa-workshop' ); ?></li>
			</ul>

			<h2><?php esc_html_e( 'After installing or updating the plugin', 'tossa-workshop' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Deactivate and reactivate the plugin once. This registers the scan/status web addresses and grants the staff roles their permissions.', 'tossa-workshop' ); ?></li>
				<li><?php esc_html_e( 'Make sure pretty permalinks are enabled (Settings, Permalinks) so the scan and client pages work.', 'tossa-workshop' ); ?></li>
				<li><?php esc_html_e( 'Confirm the site can send email. If notifications are not arriving, install or configure an SMTP plugin. The job Status box shows the exact reason when a send fails.', 'tossa-workshop' ); ?></li>
				<li><?php esc_html_e( 'Fill in the Workshop settings above (phone, address, emails).', 'tossa-workshop' ); ?></li>
			</ol>

			<h2><?php esc_html_e( 'Reserved web addresses', 'tossa-workshop' ); ?></h2>
			<ul>
				<li><code>/workshop-scan/</code> &mdash; <?php esc_html_e( 'staff QR scan router.', 'tossa-workshop' ); ?></li>
				<li><code>/workshop-status/</code> &mdash; <?php esc_html_e( 'private client status page (opened via the per-job link).', 'tossa-workshop' ); ?></li>
			</ul>

			<h2><?php esc_html_e( 'Data & privacy', 'tossa-workshop' ); ?></h2>
			<p><?php esc_html_e( 'Client contact details are stored with each bike and copied onto its jobs. A customer workshop data is included in the WordPress Tools, Export/Erase Personal Data screens, so access and erasure requests can be handled there.', 'tossa-workshop' ); ?></p>
		</div>
		<?php
	}
}
