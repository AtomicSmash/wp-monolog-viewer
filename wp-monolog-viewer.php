<?php
/*
Plugin Name: WP Monolog Viewer
Plugin URI: https://github.com/AtomicSmash/wp-monolog-viewer
Description: WP Monolog Viewer adds a custom table viewer for Monolog log entries. It retrieves data from the wp_log table and is available in the Tools menu.
Version: 1.0.0
Author: Atomic Smash
Author URI: https://www.atomicsmash.co.uk/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Monolog_Viewer_Init class will create the page to load the table
 */
if( ! class_exists( 'WP_Monolog_Viewer_Init' ) ) :

	class WP_Monolog_Viewer_Init {

		/**
		* Constructor will create the menu item
		*/
	    public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_monolog_viewer_adnin_to_tools_menu' ) );
	    }

		public function add_monolog_viewer_adnin_to_tools_menu() {
			add_submenu_page( 'tools.php', 'WP Monolog Viewer', 'WP Monolog Viewer', 'manage_options', 'wp-monolog-viewer.php', array( $this, 'display_monolog_table_page' ) );
		}

		/**
		* Display the monolog viewer table page
		*
		* @return Void
		*/
		public function display_monolog_table_page() {

			// Load the plugin admin stylesheet
			$plugin_url = plugin_dir_url( __FILE__ );
			wp_register_style( 'monolog_viewer_admin_style', $plugin_url . 'css/admin.css', false, '1.0.0' );
		    wp_enqueue_style( 'monolog_viewer_admin_style' );

			// Instantiate the class
			$WP_Monolog_Viewer_Table = new WP_Monolog_Viewer_List_Table();
			$WP_Monolog_Viewer_Table->prepare_items();
			?>
			<div class="wrap">
		        <h1>WP Monolog Viewer</h1>

				<form id="wp-monolog-viewer" method="get">
					<?php $WP_Monolog_Viewer_Table->display(); ?>
				</form>

		    </div>
			<?php
		}
	}

	if( is_admin() ) {
	    new WP_Monolog_Viewer_Init();
	}

endif; // class_exists check

// WP_List_Table is not loaded automatically so we need to load it in our application
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Create a new table class that will extend the WP_List_Table
 */
if( ! class_exists( 'WP_Monolog_Viewer_List_Table' ) ) :
	class WP_Monolog_Viewer_List_Table extends WP_List_Table {

		/**
		 * Prepare the items for the table to process
		 *
		 * @return Void
		 */
		public function prepare_items() {

			$columns = $this->get_columns();
			$hidden = $this->get_hidden_columns();
			$sortable = $this->get_sortable_columns();

			// Get the data
			$data = $this->table_data();
			usort( $data, array( &$this, 'sort_data' ) );

			$items_per_page = 100;
			$currentPage = $this->get_pagenum();
			$total_items = count($data);

			$this->set_pagination_args( array(
				'total_items' => $total_items,
				'per_page'    => $items_per_page
			) );

			$data = array_slice( $data, ( ($currentPage - 1 ) * $items_per_page ), $items_per_page );
			$this->_column_headers = array( $columns, $hidden, $sortable );
			$this->items = $data;

		}



		/**
	     * Override the parent columns method. Defines the columns to use in your listing table
	     *
	     * @return Array $columns, the array of columns to use with the table
	     */
		function get_columns() {

			$columns = array(
				'level' => __('Log Level'),
				'time' => __('Date / Time'),
				'message' => __('Message'),
				'channel'=> __('Channel'),
				'app' => __('App')
			);

			return $columns;

		}

		/**
		 * Define which columns are hidden
		 *
		 * @return Array
		 */
	 	public function get_hidden_columns() {
			return array();
	 	}

		/**
	     * Define the sortable columns
	     *
	     * @return Array the array of columns that can be sorted by the user
	     */
	    public function get_sortable_columns() {
	        return array(
				'level' => array( 'level', false ),
				'time' => array( 'time', false ),
				'message' => array( 'message', false ),
				'channel' => array( 'channel', false ),
				'app' => array( 'app', false )
			);
	    }

		/**
		* Get the table data
		*
		* @return Array
		*/
		private function table_data() {

			global $wpdb;

			// Set the table name (default: log)
			$table_name = $wpdb->prefix . 'log';
			if( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
				// Table does not exist
				return array();
			}

			$query = "SELECT * FROM $table_name";
	        $total_rows = $wpdb->query( $query );
			$data = $wpdb->get_results( $query, 'ARRAY_A' );

			return $data;
		}

		/**
		* Define what data to show on each column of the table and call some
		* different methods to format the output
		*
		* @param  Array $item        Data
		* @param  String $column_name - Current column name
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
	     * Add extra markup in the toolbars before or after the list
	     * @param string $which, helps you decide if you add the markup after (bottom) or before (top) the list
	     */
	    function extra_tablenav( $which ) {
	        if ( $which == "top" ) {
	            // Content to appear before table
	        }
	        if ( $which == "bottom" ) {
	        	// Content to appear after table
	        }
	    }

		/**
	     * Add a dropdown to set the posts per page
	     * @param int $value number of posts per page
	     */

	    function set_posts_per_page() {
			?>
			<label class="screen-reader-text" for="items-per-page">Set items per page</label>
			<select name="items-per-page" id="items-per-page" class="postform">
				<option value="0">Items per page</option>
				<option value="entries-25">25</option>
				<option value="entries-50">50</option>
				<option value="entries-100">100</option>
				<option value="entries-150">150</option>
				<option value="entries-200">200</option>
				<option value="entries-250">250</option>
			</select>
			<input type="submit" name="filter_action" id="search-submit" class="button" value="Set">
			<?php
		}

		/**
		 * Allows you to sort the data by the variables set in the $_GET
		 *
		 * @return Mixed
		 */
		private function sort_data( $a, $b ) {

			// Set defaults
			$orderby = 'time';
			$order = 'asc';

			// If orderby is set, use this as the sort column
			if( ! empty( $_GET['orderby'] ) ) {
				$orderby = $_GET['orderby'];
			}

			// If order is set use this as the order
			if( ! empty( $_GET['order'] ) ) {
				$order = $_GET['order'];
			}

			$result = strcmp( $a[$orderby], $b[$orderby] );
			if( $order === 'asc' ) {
				return $result;
			}
			return -$result;
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

endif; // class_exists check
?>