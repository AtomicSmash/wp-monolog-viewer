<?php

/*
Plugin Name: WP Monolog Viewer
Plugin URI: https://github.com/AtomicSmash/wp-monolog-viewer
Description: WP Monolog Viewer adds a custom table viewer for Monolog log entries. It retrieves data from the wp_log table and is available in the Tools menu.
Version: 1.0.2
Author: Atomic Smash
Author URI: https://www.atomicsmash.co.uk/
Text Domain: atomic
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WP_List_Table is not loaded automatically so we need to load it in our application
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WP_Monolog_Viewer_List_Table extends WP_List_Table {

	/** Class constructor */
	public function __construct() {
		parent::__construct( [
			'singular' => __( 'Log', 'atomic' ), 	// singular name of the listed records
			'plural'   => __( 'Logs', 'atomic' ), 	// plural name of the listed records
			'ajax'     => 	false 					// should this table support ajax?
		] );
	}

	/**
	 * Retrieve log data from the database
	 *
	 * @param int	$per_page  		Number of records to get in DB
	 * @param int 	$page_number	Offset for the DB SQL query
	 * @return Array
	 */
	private static function get_logs( $per_page = 100, $page_number = 1 ) {

		global $wpdb;

		$sql = "SELECT * FROM {$wpdb->prefix}log";		// Specify table

		// Add to the sql query if page settings are sent
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		}

		// Limit the sql query by passed arguments
		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;

		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}

	/**
	* Return the number of logs in the Database
	*
	* @return null|string
	*/
	public static function record_count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}log";

		return $wpdb->get_var( $sql );
	}

	/** Text displayed when no customer data is available */
	public function no_items() {
		_e( 'No logs avaliable.', 'atomic' );
	}

	/**
	* Define what data to show on each column of the table and call some
	* different methods to format the output
	*
	* @param  array $item        	Data
	* @param  string $column_name	Current column name
	*
	* @return Mixed
	*/
	public function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'time':

				// Convert from linux timestamp to date/time
				$timestamp = $item[ $column_name ];

				// Get an html formatted string
				$date_time = $this->format_log_date_time( $timestamp );

				return $date_time;

			case 'message':

				/*
					 * In case the message contains serialized data, display another
					 * message as it shouldn't be appearing in the log
				 */
				$message = $item[ $column_name ];
				$serialized = @unserialize( $message );
				if( $serialized !== false ) {
					return '-- serialized data --';
				}

				/*
					 * With mailchimp the log In case the message contains serialized data, display another
					 * message as it shouldn't be appearing in the log
				 */

				return $message;

			case 'channel':
			case 'app':
				return $item[ $column_name ];

			case 'level':
				$level = intval( $item[$column_name] );
				$this->get_log_level_icon( $level );


		}
	}

	/**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array $columns, the array of columns to use with the table
     */
	function get_columns() {

		$columns = array(
			'level' => __( 'Log Level', 'atomic' ),
			'time' => __( 'Date / Time', 'atomic' ),
			'message' => __( 'Message', 'atomic' ),
			'channel'=> __( 'Channel', 'atomic' ),
			'app' => __( 'App', 'atomic' )
		);

		return $columns;
	}

	/**
     * Define the sortable columns
     *
     * @return Array the array of columns that can be sorted by the user
     */
    public function get_sortable_columns() {
        return array(
			'level' => array( 'level', true ),
			'time' => array( 'time', true ),
			'message' => array( 'message', true ),
			'channel' => array( 'channel', true ),
			'app' => array( 'app', true )
		);
    }


	/**
	 * Prepare the items for the table to process
	 *
	 * @return Void
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		$per_page     = $this->get_items_per_page( 'logs_per_page' );	// Amount and default

		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page
		) );

		$this->items = self::get_logs( $per_page, $current_page );

	}


	/**
	 * Output a dashicon and text based on a severity level (log level)
	 * @param int $level get the level field from monolog wp_log table
	 * @return string|mixed html and text output
	 */
    public function get_log_level_icon( $level ) {

        if( ! is_int( $level ) ) return false;

        if( $level === 100 ) {

            // Debug
            echo '<span class="monolog-debug" title="'.$level.'">🐞</span><span class="monolog-text" title="'.$level.'">Debug</span>';

        } else if( $level === 200 ) {

            // Info
            echo '<span class="monolog-info" title="'.$level.'">ℹ️</span><span class="monolog-text" title="'.$level.'">Info';

        } else if( $level === 250 ) {

            // Notice
            echo '<span class="monolog-notice" title="'.$level.'">🗒</span><span class="monolog-text" title="'.$level.'">Notice';

        } else if( $level === 300 ) {

            // Warning
            echo '<span class="monolog-warning" title="'.$level.'">⚠️</span><span class="monolog-text" title="'.$level.'">Warning';

        } else if( $level === 400 ) {

            // Error
            echo '<span class="monolog-error" title="'.$level.'">❌</span><span class="monolog-text" title="'.$level.'">Error';

        } else if( $level === 500 ) {

            // Critical
            echo '<span class="monolog-critical" title="'.$level.'">🔥</span><span class="monolog-text" title="'.$level.'">Critical';

        } else if( $level === 550 ) {

            // Alert
            echo '<span class="monolog-alert" title="'.$level.'">🛎</span><span class="monolog-text" title="'.$level.'">Alert';

        } else if( $level === 600 ) {

            // Emergency
            echo '<span class="monolog-emergency" title="'.$level.'">🚨</span><span class="monolog-text" title="'.$level.'">Emergency';

        }
    }

	/**
	 * Output a formatted date time from the log timestamp
	 * @param int $datetime get the time field from monolog wp_log table
	 * @return string html the output string for the date and time
	 */
	public function format_log_date_time( $timestamp ) {

		$date = date("d.m.Y", $timestamp);
		$time = date("H:i:s", $timestamp );

		$datetime = '<span class="monolog-date">'. $date .'</span><span class="monolog-date-time-separator">/</span><span class="monolog-time">'. $time .'</span>';
		echo $datetime;

	}
}

class WP_Monolog_Viewer_Init {

	// Class instance
	static $instance;

	// log WP_List_Table object
	public $WP_Monolog_Viewer_Table;

	/**
	* Constructor will create the menu item and add screen options
	*/
	public function __construct() {
		add_filter( 'set-screen-option', [ __CLASS__, 'set_screen' ], 10, 3 );
		add_action( 'admin_menu', array( $this, 'add_monolog_viewer_admin_to_tools_menu' ) );
	}

	public static function set_screen( $status, $option, $value ) {
		return $value;
	}

	public function add_monolog_viewer_admin_to_tools_menu() {

		$hook = add_submenu_page(
			'tools.php',
			'WP Monolog Viewer',
			'WP Monolog Viewer',
			'manage_options',
			'wp-monolog-viewer',
			[ $this, 'display_monolog_table_page' ]
		);

		add_action( "load-$hook", [ $this, 'screen_option' ] );

	}


	/**
	* Display the monolog viewer table page
	*
	* @return Void
	*/
	public function display_monolog_table_page() {
		// Load the plugin admin stylesheet
		wp_register_style( 'monolog_viewer_admin_style', plugin_dir_url( __FILE__ ) . 'css/admin.css', false, '1.0.0' );
		wp_enqueue_style( 'monolog_viewer_admin_style' );
		?>
		<div class="wrap">
	        <h1>WP Monolog Viewer</h1>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="post" id="wp-monolog-viewer">
								<?php
								$this->WP_Monolog_Viewer_Table->prepare_items();
								$this->WP_Monolog_Viewer_Table->display();
								?>
							</form>
						</div>
					</div>
				</div>
				<br class="clear">
			</div>
		</div>
		<?php
	}


	/**
	* Screen options
	*/
	public function screen_option() {

		$option = 'per_page';
		$args   = [
			'label'   => 'Logs per page',
			'default' => 100,
			'option'  => 'logs_per_page'
		];

		add_screen_option( $option, $args );

		$this->WP_Monolog_Viewer_Table = new WP_Monolog_Viewer_List_Table();
	}

	/** Singleton instance to ensure only one object instance exists */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

add_action( 'plugins_loaded', function () {
	WP_Monolog_Viewer_Init::get_instance();
} );


?>