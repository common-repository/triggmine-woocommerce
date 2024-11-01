<?php
/*
    Plugin Name: TriggMine Woocommerce
    Description: Turn abandoned baskets in the extra 30% of sales. Get in touch with the customer before he will buy somewhere else! <a href=\"http://triggmine.com\">...more info...</a>
    Version: 1.0
    Author: TriggMine
    Author URI: http://triggmine.com
	Text Domain: triggmine
	Domain Path: /languages
 */


//connecting needed files
define( "TRIGGMINE_PATH", dirname( __FILE__ ) );
require_once( TRIGGMINE_PATH . '/Integrator.php' );


$integrator = new TriggMine_Integrator_Wordpress_Woocoommerce();
$integrator->setLogInFile(true);

//install-uninstall
register_activation_hook( __FILE__, array( $integrator, 'install' ) );
register_deactivation_hook( __FILE__, array( $integrator, 'uninstall' ) );

// wp events
add_action( 'wp', array( $integrator, 'onPageLoaded' ), 20 );
add_action( 'wp_login', array( $integrator, 'onBuyerLoggedIn' ), 10, 2 );
add_action( 'wp_logout', array( $integrator, 'onBuyerLoggedOut' ) );

// Woocommerce events
add_action('woocommerce_add_to_cart', array($integrator, 'onUpdateCartFull'), 10);
add_action('woocommerce_cart_updated', array($integrator, 'onUpdateCartFull'), 10);
add_action('woocommerce_cart_item_removed', array($integrator, 'onUpdateCartFull'), 10);
add_action('woocommerce_before_cart_item_quantity_zero', array($integrator, 'onUpdateCartFull'), 10);
add_action('woocommerce_after_cart_item_quantity_update', array($integrator, 'onUpdateCartFull'), 10);
add_action('woocommerce_thankyou', array($integrator, 'onCartPurchased'), 10, 1);



class Settings_API_Tabs_Triggmine_Plugin {

	private $general_settings_key = 'triggmine';
	private $advanced_settings_key = 'triggmine_export_settings';
	private $plugin_options_key = 'triggmine_plugin_options';
	private $plugin_settings_tabs = array();
	private $general_settings = array();
	private $advanced_settings = array();
	private $_integrator;

	function __construct() {
		$this->_integrator = new TriggMine_Integrator_Wordpress_Woocoommerce( false );
		add_action( 'init', array( &$this, 'load_settings' ) );
		add_action( 'admin_init', array( &$this, 'register_general_settings' ) );
		add_action( 'admin_init', array( &$this, 'register_advanced_settings' ) );
		add_action( 'admin_menu', array( &$this, 'add_admin_menus' ) );
		load_plugin_textdomain( 'triggmine', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	function load_settings() {
		$this->general_settings  = (array) get_option( $this->general_settings_key );
		$this->advanced_settings = (array) get_option( $this->advanced_settings_key );
		// Merge with defaults
		$this->general_settings = array_merge( array(
			'is_on'    => 0,
			'rest_api' => 'http://api.triggmine.com',
			'token'    => ''
		), $this->general_settings );

		$this->advanced_settings = array_merge( array(
			'send_export_option' => 1,
			'time_export_option' => ''
		), $this->advanced_settings );
	}

	function register_general_settings() {
		$this->plugin_settings_tabs[ $this->general_settings_key ] = 'General';

		register_setting( $this->general_settings_key, $this->general_settings_key );
		add_settings_section( 'section_general', __( 'General Plugin Settings', 'triggmine' ), array(
			&$this,
			'section_general_desc'
		), $this->general_settings_key );
		add_settings_field( 'is_on',  __( 'Plugin on', 'triggmine' ), array(
			&$this,
			'checkbox_is_on'
		), $this->general_settings_key, 'section_general' );
		add_settings_field( 'rest_api', __( 'Rest Api', 'triggmine' ), array(
			&$this,
			'field_rest_api'
		), $this->general_settings_key, 'section_general' );
		add_settings_field( 'token',  __( 'Token', 'triggmine' ), array(
			&$this,
			'field_token'
		), $this->general_settings_key, 'section_general' );
	}

	function section_general_desc() {
		echo  __( 'General section description', 'triggmine' );
	}

	function checkbox_is_on() {
		?>
		<input type="checkbox"
		       name="<?php echo $this->general_settings_key; ?>[is_on]" <?php if ( $this->general_settings['is_on'] ) {
			echo "checked";
		} ?> >
	<?php
	}

	function field_rest_api() {
		?>
		<input type="text" name="<?php echo $this->general_settings_key; ?>[rest_api]"
		       value="<?php echo esc_attr( $this->general_settings['rest_api'] ); ?>"/>
	<?php
	}

	function field_token() {
		?>
		<input type="text" name="<?php echo $this->general_settings_key; ?>[token]"
		       value="<?php echo esc_attr( $this->general_settings['token'] ); ?>"/>
	<?php
	}

	function register_advanced_settings() {
		$this->plugin_settings_tabs[ $this->advanced_settings_key ] = 'Export';

		register_setting( $this->advanced_settings_key, $this->advanced_settings_key, array(
			&$this->_integrator,
			'onSendExport'
		) );
		add_settings_section( 'section_advanced', __( 'Order export', 'triggmine' ), array(
			&$this,
			'section_advanced_desc'
		), $this->advanced_settings_key );
		add_settings_field( 'send_export_option', '', array(
			&$this,
			'field_send_export_option'
		), $this->advanced_settings_key, 'section_advanced' );
		add_settings_field( 'time_export_option', __( 'Select the time period', 'triggmine' ) , array(
			&$this,
			'field_time_export_option'
		), $this->advanced_settings_key, 'section_advanced' );
	}

	function section_advanced_desc() {

		echo __( 'Export description goes here.', 'triggmine' ) ;
	}

	function field_time_export_option() {
		?>
		<select name="<?php echo $this->advanced_settings_key; ?>[time_export_option]">
			<?php
			$options = array(
				'all' => __( 'All', 'triggmine' ),
				'7'   => __( 'Week', 'triggmine' ),
				'31'  => __( 'Month', 'triggmine' ),
				'93'  => __( '3 month', 'triggmine' ),
				'186' => __( '6 month', 'triggmine' ),
				'365' => __( 'Year', 'triggmine' )
			);

			foreach ( $options as $key => $value ) {
				$selected = '';
				if ( $this->advanced_settings['time_export_option'] == $key ) {
					$selected = 'selected';
				}

				$output =
					'<option value="'
					. $key
					. '" '
					. $selected
					. '>'
					. $value
					. '</option>';

				echo $output;
			}
			?>
		</select>
	<?php
	}

	function field_send_export_option() {
		?>
		<input type="hidden" name="<?php echo $this->advanced_settings_key; ?>[send_export_option]"
		       value="<?php echo esc_attr( $this->advanced_settings['send_export_option'] ); ?>"/>
	<?php
	}

	function add_admin_menus() {
		add_options_page( __( 'Triggmine Settings', 'triggmine' ), __( 'Triggmine Settings', 'triggmine' ), 'manage_options', $this->plugin_options_key, array(
			&$this,
			'plugin_options_page'
		) );
	}

	function plugin_options_page() {
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->general_settings_key;
		?>
		<div class="wrap">
			<?php $this->plugin_options_tabs(); ?>
			<form method="post" action="options.php">
				<?php wp_nonce_field( 'update-options' ); ?>
				<?php settings_fields( $tab ); ?>
				<?php do_settings_sections( $tab ); ?>
				<?php ( $tab == $this->advanced_settings_key ) ? submit_button( __( 'Export Orders', 'triggmine' ) ) : submit_button(); ?>
			</form>
		</div>
	<?php
	}

	function plugin_options_tabs() {
		$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->general_settings_key;

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->plugin_settings_tabs as $tab_key => $tab_caption ) {
			$active = $current_tab == $tab_key ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->plugin_options_key . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';
		}
		echo '</h2>';
	}
}

add_action( 'plugins_loaded', create_function( '', '$SETTINGS_API_TABS_TRIGGMINE_PLUGIN = new Settings_API_Tabs_Triggmine_Plugin;' ) );