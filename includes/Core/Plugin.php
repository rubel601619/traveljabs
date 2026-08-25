<?php
/**
 * Main plugin bootstrap class.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Core;

use Traveljabs\Admin\AdminMenu;
use Traveljabs\Admin\Settings;
use Traveljabs\Meta\ClinicDetails;
use Traveljabs\PostTypes\Clinic\ClinicSingle;
use Traveljabs\PostTypes\Destinations;
use Traveljabs\PostTypes\OurClinics;
use Traveljabs\PostTypes\Vaccinations;
use Traveljabs\Redirects\RedirectAdmin;
use Traveljabs\Redirects\RedirectManager;

defined( 'ABSPATH' ) || exit;

/**
 * Initializes all plugin modules.
 */
final class Plugin {

	/**
	 * Singleton plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Admin menu module.
	 *
	 * @var AdminMenu
	 */
	private AdminMenu $admin_menu;

	/**
	 * Settings module.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Destinations post type module.
	 *
	 * @var Destinations
	 */
	private Destinations $destinations;

	/**
	 * Our Clinics post type module.
	 *
	 * @var OurClinics
	 */
	private OurClinics $our_clinics;

	/**
	 * Vaccinations post type module.
	 *
	 * @var Vaccinations
	 */
	private Vaccinations $vaccinations;

	/**
	 * Clinic Details field group module.
	 *
	 * @var ClinicDetails
	 */
	private ClinicDetails $clinic_details;

	/**
	 * Clinic single-post module.
	 *
	 * @var ClinicSingle
	 */
	private ClinicSingle $clinic_single;

	/**
	 * Redirect business logic module.
	 *
	 * @var RedirectManager
	 */
	private RedirectManager $redirect_manager;

	/**
	 * Redirect admin UI module.
	 *
	 * @var RedirectAdmin
	 */
	private RedirectAdmin $redirect_admin;


	/**
	 * Private constructor. Use instance().
	 */
	private function __construct() {}

	/**
	 * Returns the singleton plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Instantiates every module; each module registers its own hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->admin_menu       = new AdminMenu();
		$this->settings         = new Settings();
		$this->destinations     = new Destinations();
		$this->our_clinics      = new OurClinics();
		$this->vaccinations     = new Vaccinations();
		$this->clinic_details   = new ClinicDetails();
		$this->clinic_single    = new ClinicSingle();
		$this->redirect_manager = new RedirectManager();
		$this->redirect_admin   = new RedirectAdmin( $this->redirect_manager );

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Loads the plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'traveljabs',
			false,
			dirname( plugin_basename( TRAVELJABS_FILE ) ) . '/languages'
		);
	}
}
