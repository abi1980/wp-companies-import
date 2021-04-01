<?php

/**
 * Plugin Name: Import Vehicles
 * Plugin URI: 
 * Description: 
 * Version: 1.0
 * Author: Khawaja Abid
 * Author URI:
 */

if ( !class_exists('SMKImportVehicles')){
	class SMKImportVehicles{
		function __construct(){
			register_activation_hook( __FILE__, array(&$this, 'install') );
			register_deactivation_hook(__FILE__,  array(&$this,'my_deactivation'));
			add_action('admin_menu', array(&$this,'adminMenu'));
			add_action('wp_enqueue_scripts', array(&$this, 'wpEnqueueScripts') );
			add_action('admin_enqueue_scripts', array(&$this, 'AdminEnqueueScripts') );
			add_action('init', array(&$this, 'fe_init') );
			add_action('my_task_hook',  array(&$this, 'my_task_function' ));
			add_filter('cron_schedules', array(&$this,'addCronMinutes'));
		}

		function addCronMinutes($array) {
			$schedules['every_hour'] = array(
			'interval' => 3600, 
			'display' => __( 'Every Hour' , 'texthomain' )
			);
			
			#Once a day: 86,400
			#Twice a day: 43,200
			#Thrice a day: 28,800
			#Four Time a day: 21,600
			#Custom Minutes: 1 * 60

			return $schedules;
		}

		function my_task_function(){
			$this->importVehicles();
		}
		
		function my_deactivation() {
			wp_clear_scheduled_hook('my_task_hook');
			// Function call to delete old posts
			$this->delete_old_CSV_posts();
		}

		function wp_set_content_type(){
			return "text/html";
		}
		
		function wpEnqueueScripts(){
			wp_enqueue_script('jquery');
			wp_enqueue_style( 'import-css-bootstrap', plugins_url('css/bootstrap.min.css', __FILE__) );
		}
		
		function AdminEnqueueScripts(){
			wp_enqueue_script('jquery');
			wp_enqueue_style( 'import-css-bootstrap', plugins_url('css/bootstrap.min.css', __FILE__) );
		}
		
		function fe_init(){
			ob_start();
			
			$labels = array(
				'name'                  => _x( 'Vehicles', 'Post Type General Name', 'text_domain' ),
				'singular_name'         => _x( 'Vehicle', 'Post Type Singular Name', 'text_domain' ),
				'menu_name'             => __( 'Vehicles', 'text_domain' ),
				'name_admin_bar'        => __( 'Vehicles', 'text_domain' ),
				'archives'              => __( 'Vehicle Archives', 'text_domain' ),
				'attributes'            => __( 'Vehicle Attributes', 'text_domain' ),
				'parent_item_colon'     => __( 'Parent Vehicle:', 'text_domain' ),
				'all_items'             => __( 'All Vehciles', 'text_domain' ),
				'add_new_item'          => __( 'Add New Vehicle', 'text_domain' ),
				'add_new'               => __( 'Add New', 'text_domain' ),
				'new_item'              => __( 'New Vehicle', 'text_domain' ),
				'edit_item'             => __( 'Edit Vehicle', 'text_domain' ),
				'update_item'           => __( 'Update Vehicle', 'text_domain' ),
				'view_item'             => __( 'View Vehicle', 'text_domain' ),
				'view_items'            => __( 'View Vehicle', 'text_domain' ),
				'search_items'          => __( 'Search Vehicle', 'text_domain' ),
				'not_found'             => __( 'Not found', 'text_domain' ),
				'not_found_in_trash'    => __( 'Not found in Trash', 'text_domain' ),
				'featured_image'        => __( 'Featured Image', 'text_domain' ),
				'set_featured_image'    => __( 'Set featured image', 'text_domain' ),
				'remove_featured_image' => __( 'Remove featured image', 'text_domain' ),
				'use_featured_image'    => __( 'Use as featured image', 'text_domain' ),
				'insert_into_item'      => __( 'Insert into item', 'text_domain' ),
				'uploaded_to_this_item' => __( 'Uploaded to this item', 'text_domain' ),
				'items_list'            => __( 'Items list', 'text_domain' ),
				'items_list_navigation' => __( 'Items list navigation', 'text_domain' ),
				'filter_items_list'     => __( 'Filter items list', 'text_domain' ),
			);
			$args = array(

				'label'                 => __( 'Vehciles', 'text_domain' ),
				'description'           => __( 'Post Type Description', 'text_domain' ),
				'labels'                => $labels,
				'supports'              => array( 'title', 'editor','excerpt', 'custom-fields', 'page-attributes' ),
				'hierarchical'          => false,
				'rewrite'               => true,
				'public'                => true,
				'show_ui'               => true,
				'show_in_menu'          => true,
				'menu_position'         => 5,
				'show_in_admin_bar'     => true,
				'show_in_nav_menus'     => true,
				'can_export'            => true,
				'has_archive'           => true,
				'exclude_from_search'   => false,
				'publicly_queryable'    => true,
				'capability_type'       => 'post',

			);

			register_post_type( 'vehicles', $args );
			  
		    register_taxonomy(
				'vehicle-category',
				'vehicles',
				array(
				'label' => __( 'Vehicle Category' ),
				'rewrite' => array('slug' => 'vehicle-category'),
				'hierarchical' => true,
				)
			);
		}
		
		function install () {
			if ( ! wp_next_scheduled( 'my_task_hook' ) ) {
				wp_schedule_event( time(), 'every_hour', 'my_task_hook');
			}
		}
		
		function adminMenu(){

			add_menu_page('Manually Import Vehicles', 'Manually Import Vehicles', 'manage_options', 'vehicles_import', array(&$this, 'importVehicles') );

			// Submenu Page for testing with function => importVehiclesTabular
			//add_submenu_page( 'vehicles_import', 'Vehicles Test Page', 'Vehicles Test Page', 'manage_options', 'vehicles_test_page', array(&$this, 'importVehiclesTabular') );
		}
		

		//Function for Admin Side Testing/Debugging values
		function importVehiclesTabular(){

			$files  =   glob(ABSPATH . "/car_content_data.csv");
			if($files[0]){
				$file_path = $files[0];
				if (($handle = fopen($file_path, "r")) !== FALSE ) {
					$headerColumn = fgetcsv($handle, 100000, ",");

					$x = 0;
					$row = 0;
					$rc = 0;
					?>
					<table style="height: 840px;" class="table table-striped table-responsive table-bordered">
						<thead>
							<tr>
								<th>No.</th>
								<th>category</th>
								<th>ext_color</th>
								<th>int_color</th>
								<th>cylinders</th>
								<th>description</th>
								<th>doors</th>
								<th>driveline</th>
								<th>engine</th>
								<th>fuel</th>
								<th>horsepower</th>
								<th>internetprice</th>
								<th>location</th>
								<th>modelcode</th>
								<th>odometer</th>
								<th>retailprice</th>
								<th>stock_num</th>
								<th>style</th>
								<th>transmission</th>
								<th>is_new</th>
								<th>make</th>
								<th>model</th>
								<th>submodel</th>
								<th>vin</th>
								<th>year</th>
								<th>pendingdeal</th>
								<!-- <th>image_urls</th> -->
								<th>title</th>
							</tr>
						</thead>
						<tbody>
					<?php
					while (($data = fgetcsv($handle, 100000, ",")) !== FALSE) {
						// if($x == 1) //delete_old_data_complete(); 
						// $x++;
						$row++;
						
						$category =  $data[2];
						$ext_color =  $data[7];
						$int_color =  $data[9];
						$cylinders =  $data[20];
						$description =  htmlentities($data[21]);
						$doors =  $data[23];
						$driveline =  $data[25];
						$engine = $data[26];
						$fuel =  $data[27];
						$horsepower = $data[32];
						$internetprice =  $data[33]; //Finance price
						$location = $data[37];
						$modelcode =  $data[39];
						$odometer =  $data[49];
						$retailprice =  $data[60]; //Sale price
						$stock_num =  $data[64];
						$style = $data[65];
						$transmission =  $data[70];
						$is_new =  $data[72];
						$make = $data[73];
						$model = $data[74];
						$submodel = $data[75];
						$vin =  $data[77];
						$year = $data[79];
						$pendingdeal = $data[84];
						$image_urls =  $data[85];

						// Title field using Year Make Model and Submodel
						$title = $year.' '.$make.' '.$model.' '.$submodel;
						if($category == ''){
							continue;
						}
						$rc++;
						if(trim($title) == ''){
							$cstyle = 'style="background: #964545; color: #fff;"';
						}else{
							$cstyle = '';
						}
						?>
						<tr <?php echo $cstyle; ?>>
							<td><?php echo $rc; ?> / <?php echo $row; ?></td>
							<td><?php echo $category; ?></td>
							<td><?php echo $ext_color; ?></td>
							<td><?php echo $int_color; ?></td>
							<td><?php echo $cylinders; ?></td>
							<td><?php echo $description; ?></td>
							<td><?php echo $doors; ?></td>
							<td><?php echo $driveline; ?></td>
							<td><?php echo $engine; ?></td>
							<td><?php echo $fuel; ?></td>
							<td><?php echo $horsepower; ?></td>
							<td><?php echo $internetprice; ?></td>
							<td><?php echo $location; ?></td>
							<td><?php echo $modelcode; ?></td>
							<td><?php echo $odometer; ?></td>
							<td><?php echo $retailprice; ?></td>
							<td><?php echo $stock_num; ?></td>
							<td><?php echo $style; ?></td>
							<td><?php echo $transmission; ?></td>
							<td><?php echo $is_new; ?></td>
							<td><?php echo $make; ?></td>
							<td><?php echo $model; ?></td>
							<td><?php echo $submodel; ?></td>
							<td><?php echo $vin; ?></td>
							<td><?php echo $year; ?></td>
							<td><?php echo $pendingdeal; ?></td>
							<!-- <td><?php echo $image_urls; ?></td> -->
							<td><?php echo $title; ?></td>
						</tr>
						<?php
						}
						?>
						</tbody>
						</table>
						<?php
					
					echo "<h2>File Imported Successfully</h2>";
					fclose( $handle );
				}else{
					echo "<h2>Incorrect file format </h2>";
				}
			}else{
				echo "No file found";
			}
		}
		
		function importVehicles(){
			
				$files  =   glob(ABSPATH . "/car_content_data.csv");
				if($files[0]){

					// Function call to delete old posts
					$this->delete_old_CSV_posts();

					// Function call to import Vehicles info
					$this->importVehiclesDetail($files[0]);
					FWP()->indexer->index();
				
				}else{
					echo "No file found";
				}

		}
		
		function importVehiclesDetail($file_path){

			if (($handle = fopen($file_path, "r")) !== FALSE ) {
				$headerColumn = fgetcsv($handle, 100000, ",");

				$x = 1;
				$row = 1;

				while (($data = fgetcsv($handle, 100000, ",")) !== FALSE) {
					if($x == 1) //delete_old_data_complete(); 
					$x++;
					$row++;
					
					$category =  $data[2];
					$ext_color =  $data[7];
					$int_color =  $data[9];
					$cylinders =  $data[20];
					$description =  $data[21];
					$doors =  $data[23];
					$driveline =  $data[25];
					$engine = $data[26];
					$fuel =  $data[27];
					$horsepower = $data[32];
					$internetprice =  $data[33]; //Finance price
					$location = $data[37];
					$modelcode =  $data[39];
					$odometer =  $data[49];
					$retailprice =  $data[60]; //Sale price
					$stock_num =  $data[64];
					$style = $data[65];
					$transmission =  $data[70];
					$is_new =  $data[72];
					$make = $data[73];
					$model = $data[74];
					$submodel = $data[75];
					$vin =  $data[77];
					$year = $data[79];
					$pendingdeal = $data[84];
					$image_urls =  $data[85];

					// Title field using Year Make Model and Submodel
					$title = $year.' '.$make.' '.$model.' '.$submodel;
					
					//Insert Posts for Vehicles CPT
					$my_post = array(
						'post_title'    => $title,
						'post_content'  => $description,
						'post_status'   => 'publish',
						'post_type'     => 'vehicles' );

					$post_id = wp_insert_post( $my_post );
					
					if(!empty($post_id)){
						if(!empty($year)){
							update_post_meta($post_id , 'year', $year);
						}
						if(!empty($make)){
							update_post_meta($post_id , 'make', $make);
						}
						if(!empty($model)){
							update_post_meta($post_id , 'model', $model);
						}
						if(!empty($submodel)){
							update_post_meta($post_id , 'submodel', $submodel);
						}
						if(!empty($category)){
							update_post_meta($post_id , 'category', $category);
						}
						if(!empty($stock_num)){
							update_post_meta($post_id , 'stock_num', $stock_num);
						}
						if(!empty($vin)){
							update_post_meta($post_id , 'vin', $vin);
						}
						if(!empty($is_new)){
							update_post_meta($post_id , 'is_new', $is_new);
						}
						if(!empty($odometer)){
							update_post_meta($post_id , 'odometer', $odometer);
						}
						if(!empty($internetprice)){
							update_post_meta($post_id , 'price', $internetprice);
						}
						if(!empty($retailprice)){
							update_post_meta($post_id , 'sale_price', $retailprice);
						}
						if(!empty($transmission)){
							update_post_meta($post_id , 'transmission', $transmission);
						}
						if(!empty($ext_color)){
							update_post_meta($post_id , 'ext_color', $ext_color);
						}
						if(!empty($int_color)){
							update_post_meta($post_id , 'int_color', $int_color);
						}
						if(!empty($cylinders)){
							update_post_meta($post_id , 'cylinders', $cylinders);
						}
						if(!empty($driveline)){
							update_post_meta($post_id , 'drive', $driveline);
						}
						if(!empty($doors)){
							update_post_meta($post_id , 'doors', $doors);
						}
						if(!empty($fuel)){
							update_post_meta($post_id , 'fuel', $fuel);
						}
						if(!empty($engine)){
							update_post_meta($post_id , 'engine', $engine);
						}
						if(!empty($location)){
							update_post_meta($post_id , 'location', $location);
						}
						if(!empty($horsepower)){
							update_post_meta($post_id , 'horsepower', $horsepower);
						}
						if(!empty($pendingdeal)){
							update_post_meta($post_id , 'pendingdeal', $pendingdeal);
						}else{
							update_post_meta($post_id , 'pendingdeal', 0);
						}
						// Image URLs
						if(!empty($image_urls)){
							update_post_meta($post_id , 'car_images', $image_urls);
						}
						if(!empty($modelcode)){
							update_post_meta($post_id , 'modelcode', $modelcode);
						}

						// Function call for API
						$this->manageVINCurl($vin, $post_id);

						// Function call to update slug using VIN
						$this->change_default_slug($post_id);
					
					}
				}
				
				echo "<h2>File Imported Successfully</h2>";
				
				fclose( $handle );
			
			}else{
			
				echo "<h2>Incorrect file format </h2>";
			
			}
		}

		function manageVINCurl($vin, $post_id){
			// Get VIN and set it to api request url below to get full specs for Vehicle
			// API request URL
			$api_call = "https://api.carpages.ca/v1/vinexplosion/".$vin.".json";

			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => $api_call,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);

			curl_close($curl);

			if ($err) {
				echo "cURL Error #:" . $err;
			} else {

				$array = json_decode($response);

				$main_array = (array)$array;

				$vehicle_array = (array)$main_array['vehicle'];
				// Array of Features
				$features = $vehicle_array['features'];
				
				// Dynamically populate Repeater field with one subfield
				// Main Field key
				$classs_field_key = 'field_6008610d3f6a0';
				// Sub field key
				$classs_subfield_key = 'field_600861233f6a1';
	
				$classs_items = $features;
				foreach ($classs_items as $classs_items_value) {
					$classs_value[] = array($classs_subfield_key => $classs_items_value->name);
					update_field($classs_field_key, $classs_value, $post_id);
				}

			}
		}

		// Function to generate the featured images from the Image URLs
		function Generate_Featured_Image( $image_url, $post_id  ){
		    $upload_dir = wp_upload_dir();
		    $image_data = file_get_contents($image_url);
		    $filename = basename($image_url);
		    if(wp_mkdir_p($upload_dir['path']))
		      $file = $upload_dir['path'] . '/' . $filename;
		    else
		      $file = $upload_dir['basedir'] . '/' . $filename;
		    file_put_contents($file, $image_data);

		    $wp_filetype = wp_check_filetype($filename, null );
		    $attachment = array(
		        'post_mime_type' => $wp_filetype['type'],
		        'post_title' => sanitize_file_name($filename),
		        'post_content' => '',
		        'post_status' => 'inherit'
		    );
		    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
		    require_once(ABSPATH . 'wp-admin/includes/image.php');
		    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		    $res1= wp_update_attachment_metadata( $attach_id, $attach_data );
		    $res2= set_post_thumbnail( $post_id, $attach_id );
		}

		function change_default_slug($post_id) {

		    // get vin number
			$yearr = get_post_meta($post_id, 'year', true);
			$makee = get_post_meta($post_id, 'make', true);
			$modell = get_post_meta($post_id, 'model', true);
			$stock_no = get_post_meta($post_id, 'stock_num', true);
			$vin = $yearr.'_'.$makee.'_'.$modell.'_'.$stock_no;
			
			$post_to_update = get_post( $post_id );

		    // prevent empty slug, running at every post_type and infinite loop
		    if ( $vin == '' || $post_to_update->post_type != 'vehicles' || $post_to_update->post_name == $vin ){
		        return;
			}
		    $updated_post = array();
		    $updated_post['ID'] = $post_id;
		    $updated_post['post_name'] = $vin;
			wp_update_post( $updated_post ); // update newly created post
			return true;
		}

		function delete_old_CSV_posts($post_type = 'vehicles'){
		    global $wpdb;
		    $result = $wpdb->query( 
		        $wpdb->prepare("
		            DELETE posts,pt,pm
		            FROM {$wpdb->prefix}posts posts
		            LEFT JOIN {$wpdb->prefix}term_relationships pt ON pt.object_id = posts.ID
		            LEFT JOIN {$wpdb->prefix}postmeta pm ON pm.post_id = posts.ID
		            WHERE posts.post_type = %s
		            AND posts.post_status = 'publish'
		            ", 
		            $post_type
		        ) 
		    );
		    return $result !== false;
		}
		

	}//end class
}//end main class

$SMKImport = new SMKImportVehicles();


