<?php
/*
Plugin Name: Nate's Super Latte Savings Calculator
Description: Calculates the savings of making lattes at home.
Author: Nathan Shumate
Author URI: http://nathan-shumate.com
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

function coffee_calc_install() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'coffee_calc_prices';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		milk float NULL,
		coffee float NULL,
		sugar float NULL,
		syrup float NULL,
		generic_coffee_price float NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

function coffee_calc_install_data() {
	global $wpdb;
	$milk_request = 'https://api.bls.gov/publicAPI/v2/timeseries/data/APU0000709112?startyear=2017&endyear=2017&annualaverage=true';
	$milk_response = wp_remote_get( $milk_request );
	$milk = json_decode( wp_remote_retrieve_body( $milk_response ), true );
	$current_milk_price = floatval($milk['Results']['series'][0]['data'][0]['value']) / 128;

	$coffee_request = 'https://api.bls.gov/publicAPI/v2/timeseries/data/APU0000717311?startyear=2017&endyear=2017&annualaverage=true';
	$coffee_response = wp_remote_get( $coffee_request );
	$coffee = json_decode( wp_remote_retrieve_body( $coffee_response ), true );
	$current_coffee_price = floatval($coffee['Results']['series'][0]['data'][0]['value']) / 16;

	$sugar_request = 'https://api.bls.gov/publicAPI/v2/timeseries/data/APU0000715212?startyear=2017&endyear=2017&annualaverage=true';
	$sugar_response = wp_remote_get( $sugar_request );
	$sugar = json_decode( wp_remote_retrieve_body( $sugar_response ), true );
	$current_sugar_price = floatval($sugar['Results']['series'][0]['data'][0]['value']) / 16;

	$syrup_price = 0.500;
	$generic_coffee_price = 4.950;
	
	$table_name = $wpdb->prefix . 'coffee_calc_prices';	
	$wpdb->insert( 
		$table_name, 
		array( 
			'milk' => $current_milk_price, 
			'coffee' => $current_coffee_price, 
			'sugar' => $current_sugar_price, 
			'syrup' => $syrup_price, 
			'generic_coffee_price' => $generic_coffee_price 
		) 
	);

}
register_activation_hook( __FILE__, 'coffee_calc_install' );
register_activation_hook( __FILE__, 'coffee_calc_install_data' );

function coffee_calc_load_widget() {
    register_widget( 'COFFEE_CALC' );   
}
add_action( 'widgets_init', 'coffee_calc_load_widget' );

class COFFEE_CALC extends WP_Widget {

function __construct() {
	parent::__construct(
 
	'COFFEE_CALC', 
 
	__('Coffee Savings Widget', 'coffee_calc_widget_domain'), 
 
	array( 'description' => 
		__( 'Shows how much you can save by making coffee at home', 
			'coffee_calc_widget_domain' ),
	));
	add_action( 'wp_enqueue_scripts', array($this, 'add_app_scripts' ));
	global $wpdb;
    $this->db = $wpdb;
}

public function add_app_scripts() {
	wp_register_script( 'vue-js',  'https://unpkg.com/vue' ) ;
	wp_enqueue_script( 'vue-js' );
}
  
public function widget( $args, $instance ) {
	$title = apply_filters( 'widget_title', $instance['title'] );
	echo $args['before_widget'];
	if ( ! empty( $title ) )
		echo $args['before_title'] . $title . $args['after_title'];
		$table_name = $this->db->prefix . 'coffee_calc_prices'; 
		$price_data = $this->db->get_results("SELECT milk, sugar, coffee, syrup, generic_coffee_price FROM $table_name");
		$prices = array();
	    foreach ($price_data[0] as $key => $value) {
	    	$prices[$key] = $value;
	    }
		?>
		<div id="coffee_app">
			<form method="post" >
				<p>Placeholder values show standard quantites of each ingredient for a flavored latte with a double shot of espresso. Adjust the values as needed to get accurate savings based on your consumption.</p>
			     <label>Enter the days you made coffee at home.</label>
			     <input type="number" name="days_no_coffee" value="" placeholder="How many days?" v-model.number="days">
			     <label>Enter how many ounces of coffee used.</label>
			     <input type="number" name="coffee" value="" placeholder="How much coffee?" v-model.number="coffee">
			     <label>Enter how many ounces of milk used.</label>
			     <input type="number" name="milk" value="" placeholder="How much milk?" v-model.number="milk">
			     <label>Enter how many ounces of sugar used.</label>
			     <input type="number" name="sugar" value="" placeholder="How much sugar?" v-model.number="sugar">
			     <label>Enter how many ounces of syrup used.</label>
			     <input type="number" name="syrup" value="" placeholder="How much syrup?" v-model.number="syrup">
			     <br>
			   <h3>You have saved: {{ savings | currency }}</h3>
			</form>
		</div>

		<script>
			Vue.filter('currency', function (value) {
			    return '$' + parseFloat(value).toFixed(2);
			});
			new Vue({
			  el: '#coffee_app',
			  data: {
			    days: 1,
			    coffee: 0.564, 
			    milk: 9,
			    syrup: 0.5,
			    sugar: 1
			  },
			  computed: {
			    savings: function() {
			    	var bought = <?php echo $prices['generic_coffee_price'] ?> * this.days;
			    	var home = this.sugar * <?php echo $prices['sugar'] ?> + this.milk * <?php echo $prices['milk'] ?> + this.coffee * <?php echo $prices['coffee'] ?> + this.syrup * <?php echo $prices['syrup'] ?> * this.days;
			        return bought - home;
			    }
			  }
			})
		</script>
		<?php

		echo $args['after_widget'];
	}
         
	public function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'New title', 'coffee_calc_widget_domain' );
		}
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php 
}
     
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		return $instance;
	}

	public function coffee_calculations() {}
} // Class COFFEE_CALC ends here

?>
