<?php

/**
 * Includes 'style.css'.
 * Disable this filter if you don't use child style.css file.
 *
 * @param  assoc $default_set set of styles that will be loaded to the page
 * @return assoc
 */
function filter_adventure_tours_get_theme_styles( $default_set ) {
	$default_set['child-style'] = get_stylesheet_uri();
	return $default_set;
}
add_filter( 'get-theme-styles', 'filter_adventure_tours_get_theme_styles' );

define( 'CHILD_DIR', get_stylesheet_directory() );
define( 'CHILD_URL', get_stylesheet_directory_uri() );
//Add 20170504 for test leon
define('CHILD_CSS_REL_DIR',str_replace(site_url().'/','',  get_stylesheet_directory_uri()));
//Add 20170504 for test leon
require CHILD_DIR . '/includes/loader.php';

if ( ! function_exists( 'adventure_tours_filter_after_theme_setup_child' ) ) {
	/**
	 * Init theme function.
	 *
	 * @return void
	 */
	function adventure_tours_filter_after_theme_setup_child() {
		//load_theme_textdomain( 'adventure-tours', CHILD_DIR  );
		load_theme_textdomain( 'adventure-tours', CHILD_DIR . '/languages' );
	}

	add_action( 'after_setup_theme', 'adventure_tours_filter_after_theme_setup_child' );
}

if( !function_exists('nztour_nzd_to_cny_rate') ){
	function nztour_nzd_to_cny_rate( ) {
		$last_update_time = get_option('nzd_to_cny_rate_update_time');
		$now_rate = get_option('nzd_to_cny_rate');
		if($last_update_time && $now_rate){
			$next_update_time = $last_update_time + ( 60 * 30 );
			if( $next_update_time < time() ){
				$url = 'http://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.xchange%20where%20pair%20in%20(%22NZDCNY%22)&env=store://datatables.org/alltableswithkeys';
				$sxml = simplexml_load_file($url);
				$now_rate = floatval($sxml->results->rate->Rate);
				update_option( 'nzd_to_cny_rate', $now_rate );
				update_option( 'nzd_to_cny_rate_update_time', time() );
			}
		}
		else{
			$url = 'http://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.xchange%20where%20pair%20in%20(%22NZDCNY%22)&env=store://datatables.org/alltableswithkeys';
			$sxml = simplexml_load_file($url);
			$now_rate = floatval($sxml->results->rate->Rate);
			update_option( 'nzd_to_cny_rate', $now_rate );
			update_option( 'nzd_to_cny_rate_update_time', time() );
		}
		
		return $now_rate;
	}
}
if( !function_exists('nztour_get_image_id') ){
	function nztour_get_image_id($image_url) {
		global $wpdb;
		$attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $image_url ));
		if($attachment && isset($attachment[0])){
			return $attachment[0]; 
		}
		else{
			return null;
		}
	}
}
//rule for cart

add_action( 'woocommerce_after_cart_item_quantity_update', 'nztour_woocommerce_update_cart_validation', 50, 3 );
function nztour_woocommerce_update_cart_validation( $cart_item_key, $quantity, $old_quantity ) {
	if ( $quantity < $old_quantity ) {
		$cart = WC()->cart;
		$item = $cart->get_cart_item( $cart_item_key );
		$product = $cart_product = isset($item['data']) ? $item['data'] : null;
		$booking_date = ! empty( $item['date'] ) ? $item['date'] : null;
	
		if ( $cart_product && $cart_product->is_type( 'variation' ) ) {
			$product = $cart_product->parent;
		}

		if ( $booking_date && $product && $product->is_type( 'tour' ) ) {
			$other_items_quantity = 0;
			$current_id = $product->id;
			$period_info_product = adventure_tours_di( 'tour_booking_service' )->get_periods( $current_id );;
			$period_info_product = $period_info_product[0];
			$min_quantity = $period_info_product['limit_min'];
			$cart_items = $cart->get_cart();
			foreach ($cart_items as $_ik => $_item ) {
				if ( $_item['product_id'] == $current_id && isset( $_item['date'] ) ) {
					if ( $_ik == $cart_item_key ) {
						continue;
					}
					if ( $booking_date != $_item['date'] ) {
						continue;
					}
					$other_items_quantity += isset( $_item['quantity'] ) ? $_item['quantity'] : 0;
				}
			}
		 
			$min_quantity = intval($min_quantity);
			$booking_form = adventure_tours_di( 'booking_form' );
			if ( $booking_form ) {
				if ( $quantity + $other_items_quantity < $min_quantity ) {
					wc_add_notice(
						sprintf( esc_html__( 'At least %s tickets for %s within one order.', 'adventure-tours' ),
							$min_quantity, // $delta,
							$product->get_title()
						),
						'error'
					);
					$cart->set_quantity( $cart_item_key, $old_quantity, false ); // $cart->set_quantity( $cart_item_key, $max_quantity, false );
				}
			}
		}
	}
}

//customization for checkout process
add_action( 'woocommerce_checkout_update_order_meta', 'nztour_woocommerce_checkout_update_order_meta', 999, 2 );

function nztour_woocommerce_checkout_update_order_meta($order_id, $posted){
	$order = wc_get_order( $order_id );
	if($order){
		$total_pay = WC()->cart->total;
		$need_pay = $total_pay * 0.3;
		$order->set_total( $need_pay );
	}
}

add_filter( 'woocommerce_get_order_item_totals', 'nztour_woocommerce_get_order_item_totals', 999, 2 );

function nztour_woocommerce_get_order_item_totals($total_rows, $order){
	$order_items = $order->get_items();
	$order_total = 0;
	foreach ( $order_items as $item_id => $item ){
		$_product     = apply_filters( 'woocommerce_order_item_product', $order->get_product_from_item( $item ), $item );
		$regular_price = $_product->get_regular_price();
		$quantity     = (int) $item['qty'];
		$order_total += ($regular_price * $quantity);
	}
	
	$need_pay = $order_total * 0.3;
	$need_pay = round($need_pay, 2);
	$rest_pay = $order_total - $need_pay;
	$total_rows['order_paid'] = array(
		'label' => __( '30% to be paid now:', 'adventure-tours' ),
		'value_number' => $need_pay,
		'value'	=> wc_price( $need_pay, array( 'currency' => $order->get_order_currency() ) )
	);

	$total_rows['order_total'] = array(
		'label' => __( 'Total:', 'adventure-tours' ),
		'value_number' => $order_total,
		'value'	=> wc_price( $order_total, array( 'currency' => $order->get_order_currency() ) )
	);
	
	$total_rows['order_total_rest'] = array(
		'label' => __( 'Balance to be paid on arrival at accommodation:', 'adventure-tours' ),
		'value_number' => $rest_pay,
		'value'	=> wc_price( $rest_pay, array( 'currency' => $order->get_order_currency() ) )
	);

	return $total_rows;
}

add_action( 'woocommerce_order_status_completed', 'nztour_woocommerce_order_status_completed' );
add_action( 'woocommerce_order_status_processing', 'nztour_woocommerce_order_status_completed' );
function nztour_woocommerce_order_status_completed( $order_id ){
	$order = wc_get_order( $order_id );
	
	if ( $order && ( $order->has_status( 'processing' ) || $order->has_status( 'completed' ) ) ){
		foreach( $order->get_items() as $item_id => $item ) {
			$product = apply_filters( 'woocommerce_order_item_product', $order->get_product_from_item( $item ), $item );
			if($product->is_type( 'tour' )){
				$product_id = $product->id;
				$date_field_key = 'tour_date';
				$booking_date = '';
				foreach ( $item['item_meta_array'] as $meta_id => $meta ) { 
					if ( $date_field_key == $meta->key && ! empty( $meta->value ) ){
						$bookin_form_service = adventure_tours_di( 'booking_form' );
						$booking_date = $bookin_form_service->convert_date_for_human( $meta->value );
					}
				}
				if(!empty($booking_date)){
					//correct strtotime issue
					$booking_date = str_replace('/', '-', $booking_date);
					$booking_date = strtotime($booking_date);
					$booking_date_read = date('Y-m-d',$booking_date);
					$sku = get_post_meta( $product_id, 'sku', true );
					global $wpdb;
					$booking_id = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT ID FROM wprh_booking_record
							WHERE product_id = %s AND booking_date = %s", $sku, $booking_date ) );

					if(is_null($booking_id)){
						$data = array(
							'product_id'      	=> $sku,
							'booking_date'    	=> $booking_date,
							'booking_date_read'	=> $booking_date_read,
						);
						$wpdb->insert(
							'wprh_booking_record',
							$data
						);
						
						// create a new cURL resource
						$ch = curl_init();
						
						// set URL and other appropriate options
						curl_setopt($ch, CURLOPT_URL, "http://en.nzpuretour.com/nztour-order/?sender=ixr2qebjscsc2e12evi0ecm3cwquuiofo8evmeliiwbmghmjujvnvc3yyfuh5m&date=".$booking_date."&id=".$sku);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						
						// grab URL and pass it to the browser
						curl_exec($ch);
						
						// close cURL resource, and free up system resources
						curl_close($ch);
					}
				}
			}				
		}
	}
}

//when order cancelled or failed or fully_refunded
add_action( 'woocommerce_order_status_cancelled', 'nztour_woocommerce_order_status_cancelled' );
add_action( 'woocommerce_order_status_failed', 'nztour_woocommerce_order_status_cancelled' );
add_action( 'woocommerce_order_fully_refunded', 'nztour_woocommerce_order_status_cancelled' );
function nztour_woocommerce_order_status_cancelled( $order_id ){
	$order = wc_get_order( $order_id );
	
	if ( $order && $order->has_status( array( 'failed', 'refunded', 'cancelled' ) ) ){
		foreach( $order->get_items() as $item_id => $item ) {
			$product = apply_filters( 'woocommerce_order_item_product', $order->get_product_from_item( $item ), $item );
			if($product->is_type( 'tour' )){
				$product_id = $product->id;
				$date_field_key = 'tour_date';
				$booking_date = '';
				foreach ( $item['item_meta_array'] as $meta_id => $meta ) { 
					if ( $date_field_key == $meta->key && ! empty( $meta->value ) ){
						$bookin_form_service = adventure_tours_di( 'booking_form' );
						$booking_date = $bookin_form_service->convert_date_for_human( $meta->value );
					}
				}
				if(!empty($booking_date)){
					//correct strtotime issue
					$booking_date = str_replace('/', '-', $booking_date);
					$booking_date = strtotime($booking_date);
					$booking_date_read = date('Y-m-d',$booking_date);
					$sku = get_post_meta( $product_id, 'sku', true );
					global $wpdb;
					$booking_id = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT ID FROM wprh_booking_record
							WHERE product_id = %s AND booking_date = %s", $sku, $booking_date ) );
					if(!is_null($booking_id)){
						$wpdb->delete( 'wprh_booking_record', array( 'ID' => $booking_id ) );
						
						// create a new cURL resource
						$ch = curl_init();
						
						// set URL and other appropriate options
						curl_setopt($ch, CURLOPT_URL, "http://en.nzpuretour.com/nztour-order/?delete=ixr2qebssa2e12sevi0ecm3cwquuiofo8evmeliiwbmghmjujvnvc3yyfuh5m&date=".$booking_date."&id=".$sku);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						
						// grab URL and pass it to the browser
						curl_exec($ch);
						
						// close cURL resource, and free up system resources
						curl_close($ch);
					}
				}
			}				
		}
	}
}

add_action( 'woocommerce_after_checkout_validation', 'nztour_woocommerce_after_checkout_validation' );
function nztour_woocommerce_after_checkout_validation( $posted ){
	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		$_product     = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
		if($_product->is_type( 'tour' )){
			$product_id   = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
			if(isset($cart_item['date'])){
				$booking_date_start = $cart_item['date'];
				//correct strtotime issue
				$booking_date = str_replace('/', '-', $booking_date_start);
				$booking_date = strtotime($booking_date);
				$booking_date_start = date('YÂπ¥mÊúàdÔø??', $booking_date);
				$sku = get_post_meta( $product_id, 'sku', true );
				global $wpdb;
				$booking_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT ID FROM wprh_booking_record
						WHERE product_id = %s AND booking_date = %s", $sku, $booking_date ) );
				if(!is_null($booking_id)){
					$booking_date_end = date('YÂπ¥mÊúàdÔø??', strtotime("+6 day", $booking_date));
					$notice = sprintf ( esc_html__( 'The booking date %1$s - %2$s for %3$s is not available anymore', 'adventure-tours'), $booking_date_start, $booking_date_end, $_product->post->post_title);
					
					wc_add_notice( '<strong>'.$notice.'</strong>', 'error' );
				}
			}
			else{
				wc_add_notice( '<strong>'.__( 'The booking date is empty', 'woocommerce' ).'</strong>', 'error' );
			}
			
		}
	}
}

//customization for order page

//add_filter( 'woocommerce_order_items_meta_get_formatted', 'nztour_woocommerce_order_items_meta_get_formatted' , 99, 2 );
function nztour_woocommerce_order_items_meta_get_formatted( $formatted_meta_set, $order_item_meta ) {
	if ( $formatted_meta_set ) {
		$date_field_key = 'tour_date';
		foreach ( $formatted_meta_set as $_index => $meta ) {
			if ( $date_field_key == $meta['key'] && ! empty( $meta['value'] ) ) {
				$booking_date = $meta['value']; 
				$booking_date_correct = str_replace('/', '-', $booking_date);
				$booking_date_str = strtotime($booking_date_correct); 
				$booking_date_end = date('d/m/Y', strtotime("+6 day", $booking_date_str));
				$formatted_meta_set[$_index]["value"] = $booking_date .' - '. $booking_date_end;
			}
		}
	}

	return $formatted_meta_set;
}


//customization for admin order page
add_filter( 'woocommerce_get_formatted_order_total', 'nztour_woocommerce_get_formatted_order_total', 999, 2 );
function nztour_woocommerce_get_formatted_order_total( $formatted_total, $order ){
	if( current_user_can( 'edit_shop_orders' ) && $order && ( $order->has_status( 'processing' ) || $order->has_status( 'completed' ) ) ){
		$order_items = $order->get_items();
		$order_total_indeed = 0;
		foreach ( $order_items as $item_id => $item ){
			$_product     = apply_filters( 'woocommerce_order_item_product', $order->get_product_from_item( $item ), $item );
			$regular_price = $_product->get_regular_price();
			$quantity     = (int) $item['qty'];
			$order_total_indeed += ($regular_price * $quantity);
		}

		$formatted_total = wc_price( $order_total_indeed, array( 'currency' => $order->get_order_currency() ) );
	}
	return $formatted_total;
}

add_action( 'woocommerce_admin_order_totals_after_total', 'nztour_woocommerce_admin_order_totals_after_total' , 99 );
function nztour_woocommerce_admin_order_totals_after_total( $order_id ){
	$order = wc_get_order($order_id);
	$formatted_total = wc_price( $order->get_total(), array( 'currency' => $order->get_order_currency() ) );
	$order_total    = $order->get_total();
	$total_refunded = $order->get_total_refunded();
	$tax_string     = '';

	// Tax for inclusive prices
	if ( wc_tax_enabled() && 'incl' == $tax_display ) {
		$tax_string_array = array();

		if ( 'itemized' == get_option( 'woocommerce_tax_total_display' ) ) {
			foreach ( $order->get_tax_totals() as $code => $tax ) {
				$tax_amount         = ( $total_refunded && $display_refunded ) ? wc_price( WC_Tax::round( $tax->amount - $order->get_total_tax_refunded_by_rate_id( $tax->rate_id ) ), array( 'currency' => $order->get_order_currency() ) ) : $tax->formatted_amount;
				$tax_string_array[] = sprintf( '%s %s', $tax_amount, $tax->label );
			}
		} else {
			$tax_amount         = ( $total_refunded && $display_refunded ) ? $order->get_total_tax() - $order->get_total_tax_refunded() : $order->get_total_tax();
			$tax_string_array[] = sprintf( '%s %s', wc_price( $tax_amount, array( 'currency' => $order->get_order_currency() ) ), WC()->countries->tax_or_vat() );
		}
		if ( ! empty( $tax_string_array ) ) {
			$tax_string = ' ' . sprintf( __( '(includes %s)', 'woocommerce' ), implode( ', ', $tax_string_array ) );
		}
	}

	if ( $total_refunded && $display_refunded ) {
		$formatted_total = '<del>' . strip_tags( $formatted_total ) . '</del> <ins>' . wc_price( $order_total - $total_refunded, array( 'currency' => $order->get_order_currency() ) ) . $tax_string . '</ins>';
	} else {
		$formatted_total .= $tax_string;
	}
?>
<tr>
	<td class="label"><?php _e( 'Paid', 'woocommerce' ); ?>:</td>
	<td>
		<?php if ( $order->is_editable() ) : ?>
			<div class="wc-order-edit-line-item-actions">
				<a class="edit-order-item" href="#"></a>
			</div>
		<?php endif; ?>
	</td>
	<td class="total">
		<div class="view"><?php echo $formatted_total; ?></div>
		<div class="edit" style="display: none;">
			<input type="text" class="wc_input_price" id="_order_total" name="_order_total" placeholder="<?php echo wc_format_localized_price( 0 ); ?>" value="<?php echo ( isset( $data['_order_total'][0] ) ) ? esc_attr( wc_format_localized_price( $data['_order_total'][0] ) ) : ''; ?>" />
			<div class="clear"></div>
		</div>
	</td>
</tr>
<?php
}

//add wechat to woocommerce

add_filter( 'woocommerce_billing_fields', 'nztour_custom_checkout_billing_field' );
function nztour_custom_checkout_billing_field($fields){
	$new_fields = array();
	foreach($fields as $k=>$v){
		$new_fields[$k] = $v;
		if($k == 'billing_phone'){
			$new_fields['billing_wechat'] = array(
				'type'      => 'text',
				'label'     => __('Wechat', 'woocommerce'),
				'placeholder'   => _x('Wechat', 'placeholder', 'woocommerce'),
				'required'  => false,
				'class'     => array('wechat-billing-field-class form-row'),
				'clear'     => true
			);	
		}
	}
	return $new_fields;
}
add_action( 'woocommerce_checkout_update_user_meta', 'nztour_custom_woocommerce_checkout_update_user_meta', 20,2 );
function nztour_custom_woocommerce_checkout_update_user_meta( $customer_id, $posted ) {
	if ( ! empty( $posted['billing_wechat'] ) ) {
		$billing_wechat = $posted['billing_wechat'];
		update_user_meta( $customer_id, 'billing_wechat' , $billing_wechat ); 
	}	 
}

add_action( 'woocommerce_checkout_update_order_meta', 'nztour_custom_checkout_field_update_order_meta' );
function nztour_custom_checkout_field_update_order_meta( $order_id ) {
	if(!empty( $_POST['billing_wechat'] )){
		$billing_wechat = sanitize_text_field( $_POST['billing_wechat'] );
	}
	else{
		$billing_wechat = '';
	}
	update_post_meta( $order_id, '_billing_wechat', $billing_wechat );    
}

/**
 * Display field value on the order edit page
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'nztour_custom_checkout_field_display_admin_order_meta', 10, 1 );
 
function nztour_custom_checkout_field_display_admin_order_meta($order){
   echo '<p><strong>'.__('Wechat').':</strong> ' . get_post_meta( $order->id, '_billing_wechat', true ) . '</p>';
}


//tour gallery
add_action( 'after_setup_theme', 'nztour_theme_setup_add_image_size' );

function nztour_theme_setup_add_image_size() {
   if ( function_exists( 'add_theme_support' ) ) {
      add_image_size( 'activity_thumb', 360, 240, true ); 
   }
}

add_filter( 'post_gallery', 'nztour_post_gallery_filter', 1, 2 );
function nztour_post_gallery_filter( $empty, $attr ) {
	global $post;
	global $wp_filter;
	
	if(isset($attr['tour'])){
		if(isset($wp_filter['post_gallery'])){
			$hook = $wp_filter['post_gallery'];
			foreach($hook->callbacks as $pr=>$fuc){
				foreach($fuc as $name=>$incon){
					if( strpos( $name, 'adventure_tours_post_gallery_filter' ) !== false ){
						remove_filter( 'post_gallery', $name, $pr );
					}
				}
			}
		}
	}
	else{
		return $empty;
	}
	extract( shortcode_atts( array(
		'order' => 'ASC',
		'orderby' => 'menu_order ID',
		'id' => $post ? $post->ID : 0,
		'include' => '',
		'exclude' => '',
		// custom attributes
		'layout' => 'default',
		'pagination' => '',
		'filter' => '',
		'single_page' => '',
	), $attr, 'gallery' ) );
	
	
	// get attachments set
	$queryArgs = array(
		'post_status' => 'publish',
		'post_type' => 'activitytemplate',
		'order' => $order,
		'orderby' => $orderby,
		'posts_per_page'   => 9999,
	);

	$attachments = get_posts( $queryArgs );

	$defaultThumbSize = 'activity_thumb';
	$defaultFullSize = 'large';

	$galleryLayouts = array(
		'default' => array(
			'showCategories' => true,
			'allowPagination' => true,
			'thumbSize' => $defaultThumbSize,
		),
	);

	if ( ! $layout || ! isset( $galleryLayouts[$layout] ) ) {
		$layout = 'default';
	}

	$layoutConfig = $galleryLayouts[$layout];
	$thumbSize = isset( $layoutConfig['thumbSize'] ) ? $layoutConfig['thumbSize'] : $defaultThumbSize;
	$fullSize = isset( $layoutConfig['fullSize'] ) ? $layoutConfig['fullSize'] : $defaultFullSize;
 
	$showCategories = ! empty( $layoutConfig['showCategories'] ) && adventure_tours_check( 'media_category_taxonomy_exists' );
	$is_filter = adventure_tours_di( 'shortcodes_helper' )->attribute_is_true( $filter );
	if ( $is_filter && ! $showCategories ) {
		$is_filter = false;
	}

	$is_pagination = adventure_tours_di( 'shortcodes_helper' )->attribute_is_true( $pagination );
	if ( empty( $layoutConfig['allowPagination'] ) && $is_pagination ) {
		$is_pagination = false;
	}

	$gallery_images = array();
	$full_categories_list = array();
	if ( $attachments ) {
		foreach ( $attachments as $attachment ) {
			$idAttch = $attachment->ID;
			$idAttch = get_post_thumbnail_id( $idAttch ); 
			// Get image link to a specific sizes
			// Image attribute [0] => url [1] => width [2] => height
			
			$image_attributes_full = wp_get_attachment_image_src( $idAttch, $fullSize );
			$image_attributes_custom_size = wp_get_attachment_image_src( $idAttch, $thumbSize );
			$link_full = ! empty( $image_attributes_full[0] ) ? $image_attributes_full[0] : '';
			$link_custom_size = ! empty( $image_attributes_custom_size[0] ) ? $image_attributes_custom_size[0] : '';

			// categories
			$image_categories = array();
			if ( $showCategories ) {
				$taxonomies = get_the_terms( $idAttch, 'media_category' ); // 'category'
				if ( $taxonomies ) {
					foreach ( $taxonomies as $taxonomy ) {
						$full_categories_list[$taxonomy->slug] = $taxonomy->name;
						$image_categories[$taxonomy->slug] = $taxonomy->name;
					}
				}
			}

			$alt = get_post_meta( $idAttch, '_wp_attachment_image_alt', true );

			$link_full = get_post_permalink($attachment->ID);
			$gallery_images[] = array(
				'link_full' => $link_full,
				'link_custom_size' => $link_custom_size,
				'title' => $attachment->post_title,
				'categories' => $image_categories,
				'alt' => $alt ? $alt : $attachment->post_title,
				'act_id' => $attachment->ID
			);
		}
	}
	elseif ( $include ) {
		$imageManager = adventure_tours_di( 'image_manager' );
		$fullSizeDetails = $imageManager->getImageSizeDetails( $fullSize == 'full' ? 'large' : $fullSize );
		$thumbSizeDetails = $imageManager->getImageSizeDetails( $thumbSize );

		$includeIds = explode( ',', trim( $include,', ' ) );
		foreach ( $includeIds as $attachemntId ) {
			$dummyTitle = '#' . $attachemntId;
			$placeholdText = urlencode( $dummyTitle ); // find why additional encode is required

			$placeholdThumbUrl = $imageManager->getPlaceholdImage( $thumbSizeDetails['width'], $thumbSizeDetails['height'], $placeholdText );

			$fullImageUrl = $fullSizeDetails
				? $imageManager->getPlaceholdImage( $fullSizeDetails['width'], $fullSizeDetails['height'], $placeholdText )
				: $placeholdThumbUrl;

			$gallery_images[] = array(
				'link_full' => $fullImageUrl,
				'link_custom_size' => $placeholdThumbUrl,
				'title' => $dummyTitle,
				'categories' => array(),
				'alt' => $dummyTitle,
			);
		}
	}

	if ( ! $gallery_images ) {
		return '';
	}

	$output = '';

	// get gallery id
	static $galleryCounter;
	if ( null == $galleryCounter ) {
		$galleryCounter = 1;
	} else {
		$galleryCounter++;
	}
	$galleryId = 'gallery_' . $galleryCounter;

	$classWithBanner = adventure_tours_di( 'register' )->getVar( 'is_banner' ) ? ' gallery--withbanner' : '';
	$classSinglePageMode = adventure_tours_di( 'shortcodes_helper' )->attribute_is_true( $single_page ) && $is_filter ? ' gallery--page' : '';
	$output .= '<div id="' . esc_attr( $galleryId ) . '" class="gallery' . esc_attr( $classSinglePageMode ) . esc_attr( $classWithBanner ) . '">';

	if ( $is_filter && $full_categories_list ) {
		$filterHtml = '<div class="gallery__navigation margin-bottom">' .
			'<ul>' .
				'<li class="gallery__navigation__item-current"><a href="#" data-filterid="all">' . esc_html__( 'all', 'adventure-tours' ) . '</a></li>';

		foreach ( $full_categories_list as $category_slug => $category_name ) {
			$filterHtml .= '<li><a href="#" data-filterid="' . esc_attr( $category_slug ) . '">' . esc_html( $category_name ) . '</a></li>';
		}

		$filterHtml .= '</ul></div>';

		$output .= $filterHtml;
	}

	ob_start();
	include locate_template( 'templates/gallery/' . $layout . '.php' );
	$output .= ob_get_clean();

	if ( $is_pagination ) {
		wp_enqueue_script( 'jPages' );
		$output .= '<div class="pagination margin-top"></div>';
	}

	$output .= '</div>';

	return $output;
}


//activities form
add_action( 'wpcf7_init', 'nztour_contact_form_7_activity_fields' );
function nztour_contact_form_7_activity_fields() {
	if( class_exists('WPCF7_Shortcode') ) {
		wpcf7_add_shortcode( array( 'activity', 'activity*' ), 'wpcf7_activity_shortcode_handler', true );
		wpcf7_add_shortcode( array( 'activity_gallery', 'activity_gallery*' ), 'wpcf7_activity_gallery_shortcode_handler', true );
		//add_filter( 'wpcf7_validate_activity', 'nztour_wpcf7_validation_filter', 20, 2 );		
		add_filter( 'wpcf7_validate', 'nztour_wpcf7_validation_filter', 20, 2 );		
		add_filter( 'wpcf7_mail_components', 'nztour_wpcf7_mail_components', 20, 3 );
	}
}

function nztour_wpcf7_validation_filter( $result, $tags ) {
	$email_activity = true;
	foreach ( $tags as $context ) {
		if ( $context instanceof WPCF7_FormTag ) {
			$tag = $context; 
		} elseif ( is_array( $context ) ) {
			$tag = new WPCF7_FormTag( $context );
		} elseif ( is_string( $context ) ) {
			$tags = wpcf7_scan_form_tags( array( 'name' => trim( $context ) ) );
			$tag = $tags ? new WPCF7_FormTag( $tags[0] ) : null;
		}

		$name = ! empty( $tag ) ? $tag->name : null;

		if ( empty( $name ) || ! wpcf7_is_name( $name ) ) {
			continue;
		}
 
		if ( 'activity' == $tag->type ) {
			if( isset( $_POST['your-email'] ) && is_email( $_POST['your-email'] ) ){
				$email_user = wc_clean($_POST['your-email']);
				global $wpdb;
				$sql_txt = $wpdb->prepare( 
					"SELECT post_id FROM $wpdb->postmeta
					WHERE `meta_key` = '_billing_email' AND `meta_value` = %s",
					$email_user
				);
				$orders_id = $wpdb->get_results( $sql_txt );
				$flag = false;
				if($orders_id){
					foreach($orders_id as $order_id){
						$order = wc_get_order( $order_id->post_id );  
						if( $order && ( $order->has_status( 'processing' ) || $order->has_status( 'completed' ) ) ){
							$flag = true;
							break;
						}
					}
				} 
				if($flag){
					$activity_selection = isset( $_POST['activities_form_container_submit'] ) ? trim( $_POST['activities_form_container_submit'] ) : '';
					if(empty($activity_selection)){
						$result->invalidate( $tag, esc_html__( 'You have to select at least one activity before submit', 'adventure-tours' ) );
					}
					else{
						$activity_group = isset( $_POST['activities_group'] ) ? $_POST['activities_group'] : '';
						if(empty($activity_group)){
							$result->invalidate( $tag, esc_html__( 'You have to select at least one activity before submit', 'adventure-tours' ) );
						}
						else{
							$activity_selection = explode(',',$activity_selection);
							$activity_selection = array_flip($activity_selection);
							foreach($activity_group as $act_id => $act_value){
								if(array_key_exists($act_id,$activity_selection)){
									
									if( !isset($act_value['priority']) || empty($act_value['priority']) ){
										$result->invalid_fields['activity-container-s'.$act_id] = array(
												'reason' => esc_html__( 'Please select priority for your selected activity', 'adventure-tours' ),
												'idref' => '' );

									}
									if(
										isset($act_value['adult']) && floatval($act_value['adult']) == 0
										)
									{
										$result->invalid_fields['activity-container-a'.$act_id] = array(
												'reason' => esc_html__( 'Please tell us the number of adults for your selected activity', 'adventure-tours' ),
												'idref' => '' );
									}
									if(
										isset($act_value['child']) && $act_value['child'] == ''
										)
									{
										$result->invalid_fields['activity-container-c'.$act_id] = array(
												'reason' => esc_html__( 'Please tell us the number of infants for your selected activity', 'adventure-tours' ),
												'idref' => '' );
									}
								}
							}
						}
					}
				}
				else{
					//$result->invalidate( $tag, esc_html__( 'Your have to order one accommodation at least', 'adventure-tours' ) );
					$email_activity = false;
				}
			}
		}
		
	} 
	foreach ( $tags as $context ) {
		if ( $context instanceof WPCF7_FormTag ) {
			$tag = $context; 
		} elseif ( is_array( $context ) ) {
			$tag = new WPCF7_FormTag( $context );
		} elseif ( is_string( $context ) ) {
			$tags = wpcf7_scan_form_tags( array( 'name' => trim( $context ) ) );
			$tag = $tags ? new WPCF7_FormTag( $tags[0] ) : null;
		}

		$name = ! empty( $tag ) ? $tag->name : null;

		if ( empty( $name ) || ! wpcf7_is_name( $name ) ) {
			continue;
		}
 
		if ( ( 'email' == $tag->type || 'email*' == $tag->type ) && !$email_activity ) { 
			$result->invalidate( $tag, esc_html__( 'Your have to order one accommodation at least', 'adventure-tours' ) );
		}
	}
	//$tag = new WPCF7_FormTag( $tag );

	return $result;
}


function wpcf7_activity_gallery_shortcode_handler( $tag ) {
	$tag = new WPCF7_Shortcode( $tag );

	if ( empty( $tag->name ) ) {
		return '';
	}

	$validation_error = wpcf7_get_validation_error( $tag->name );

	$class = wpcf7_form_controls_class( $tag->type, 'wpcf7-activity-gallery' );

	if ( $validation_error ) {
		$class .= ' wpcf7-not-valid';
	}

	$class .= ' wpcf7-activity-gallery';
	
	if ( 'activity-gallery*' === $tag->type ) {
		$class .= ' wpcf7-validates-gallery-as-required';
	}
	
	$activity_id = array();
	$queryArgs = array(
		'post_status' => 'publish',
		'post_type' => 'activitytemplate',
		'order' => 'DESC',
		'orderby' => 'menu_order ID',
		'posts_per_page'   => 9999,
	);
	$query2 = new WP_Query( $queryArgs );
	if ( $query2->have_posts() ) {
		// The 2nd Loop
		while ( $query2->have_posts() ) {
			$query2->the_post();
			$idAttch =  $query2->post->ID;
			$activity_id[$idAttch] = 1;
		}
	}
	wp_reset_postdata();
	$activity_id_str = '';
	foreach($activity_id as $k=>$v){
		if(empty($activity_id_str)){
			$activity_id_str = $k;
		}
		else{
			$activity_id_str = ','.$k;
		}
	}
	
	ob_start();
	
	?>
	<div class="form-contact__fields-short">
		<span class="wpcf7-form-control-wrap">
			<h3><?php echo esc_html__( 'Pictures of activities', 'adventure-tours' ); ?></h3>
		</span>
   </div>
	<?php
	echo do_shortcode( '[gallery pagination="off" tour="1" filter="on" ids="'.$activity_id_str.'"]' ); 
	
	$html_gallery = ob_get_clean();
	
	$queryArgs = array(
		'post_status' => 'publish',
		'post_type' => 'activitytemplate',
		'order' => 'ASC',
		'orderby' => 'menu_order ID',
		'posts_per_page'   => 9999,
	);

	$avtivities = get_posts( $queryArgs );
	
	ob_start();
		
	if ( $avtivities ) {
		foreach ( $avtivities as $avtivity ) {
			$avtivity_id = $avtivity->ID;
			$activity_cost_adult = get_post_meta($avtivity_id,'wpcf-activity-cost-per-adult',true);
			$activity_cost_child = get_post_meta($avtivity_id,'wpcf-activity-cost-per-child',true);
?>
<!-- form-contact__fields-short -->
<blockquote class="blockquote_activity" id="blockquote_activity_<?php echo $avtivity_id; ?>">
	
<div class="form-contact__fields-short activity_form_container_sigle" data-act-id="<?php echo $avtivity_id; ?>" id="activity_<?php echo $avtivity_id; ?>_inner" style="display:none;">
	<div style="text-align:center;">
		<?php echo $avtivity->post_title; ?>
		<a href="#" class="back_actimg" data-act-id="<?php echo $avtivity_id; ?>" style="float: right;"><?php echo esc_html__( 'Return to Pictures of activities', 'adventure-tours' ); ?></a>
	</div>
	<div class="form-block__item form-block__field-width-icon">
		<span class="wpcf7-form-control-wrap activity-container-s<?php echo $avtivity_id; ?> activity-container-inner">
			<span class="wpcf7-form-control form-validation-item"></span>
		</span>
		<select id="input_593_<?php echo $avtivity_id; ?>" name="activities_group[<?php echo $avtivity_id; ?>][priority]" class="selectpicker activity_single_priority" >
			<option value="high"><?php echo esc_html__( 'High', 'adventure-tours' ); ?> </option>
			<option value="medium" selected="selected"><?php echo esc_html__( 'Medium', 'adventure-tours' ); ?></option>
			<option value="low"><?php echo esc_html__( 'Low', 'adventure-tours' ); ?></option>
		</select>
		<i class="td-heart-3"></i>
		
	</div>
	<?php
	if(!empty($activity_cost_adult) && floatval($activity_cost_adult) > 0){
	?>
	<div class="form-contact__fields-short">
		<div class="form-contact__item-short" style="width: 30% !important;">
			<span class="wpcf7-form-control-wrap">
				<label style="line-height: 40px;"><?php echo wc_price( floatval($activity_cost_adult) ); ?> <?php echo esc_html__( 'per person', 'adventure-tours' ); ?> </label>
			</span>
		</div>
		<div class="form-contact__item-short" style="width: 70% !important;">
			<span class="wpcf7-form-control-wrap activity-container-a<?php echo $avtivity_id; ?> activity-container-inner">
				<span class="wpcf7-form-control form-validation-item"></span>
			</span>
			<span class="wpcf7-form-control-wrap">
				<input type="number" data-filled="0" data-act-id="<?php echo $avtivity_id; ?>" data-per-cost="<?php echo floatval($activity_cost_adult); ?>" placeholder="<?php echo esc_html__( 'Number of Adult', 'adventure-tours' ); ?>" id="activity_<?php echo $avtivity_id; ?>_adult_number" name="activities_group[<?php echo $avtivity_id; ?>][adult]"  value="" class="wpcf7-form-control wpcf7-number wpcf7-validates-as-number wpcf7-number-adult" min="0" max="9999" aria-invalid="false">
			</span>
		</div>
	</div>
	<?php
	}
	else{
	?>
	<div class="form-contact__fields-short">
		<div class="form-contact__item-short" style="width: 30% !important;">
			<span class="wpcf7-form-control-wrap">
				<label style="line-height: 40px;">&nbsp;</label>
			</span>
		</div>
		<div class="form-contact__item-short" style="width: 70% !important;">
			<span class="wpcf7-form-control-wrap activity-container-a<?php echo $avtivity_id; ?> activity-container-inner">
				<span class="wpcf7-form-control form-validation-item"></span>
			</span>
			<span class="wpcf7-form-control-wrap">
				<input type="number" data-filled="0" data-act-id="<?php echo $avtivity_id; ?>" data-per-cost="0" placeholder="<?php echo esc_html__( 'Number of Adult', 'adventure-tours' ); ?>" id="activity_<?php echo $avtivity_id; ?>_adult_number" name="activities_group[<?php echo $avtivity_id; ?>][adult]"  value="" class="wpcf7-form-control wpcf7-number wpcf7-validates-as-number wpcf7-number-adult" min="0" max="9999" aria-invalid="false">
			</span>
		</div>
	</div>
	<?php
	}
	?>
	<?php
	if(!empty($activity_cost_child) && floatval($activity_cost_child) > 0){
	?>
	<div class="form-contact__fields-short">
		<div class="form-contact__item-short" style="width: 30% !important;">
			<span class="wpcf7-form-control-wrap">
				<label style="line-height: 40px;"><?php echo wc_price( floatval($activity_cost_child) ); ?> <?php echo esc_html__( 'per child', 'adventure-tours' ); ?></label>
			</span>
		</div>
		<div class="form-contact__item-short" style="width: 70% !important;">
			<span class="wpcf7-form-control-wrap activity-container-c<?php echo $avtivity_id; ?> activity-container-inner">
				<span class="wpcf7-form-control form-validation-item"></span>
			</span>
			<span class="wpcf7-form-control-wrap">
				<input type="number" data-filled="0" data-act-id="<?php echo $avtivity_id; ?>" data-per-cost="<?php echo floatval($activity_cost_child); ?>" placeholder="<?php echo esc_html__( 'Number of Child', 'adventure-tours' ); ?>" id="activity_<?php echo $avtivity_id; ?>_child_number" name="activities_group[<?php echo $avtivity_id; ?>][child]"  value="" class="wpcf7-form-control wpcf7-number wpcf7-validates-as-number wpcf7-number-child" min="0" max="10" aria-invalid="false">
			</span>
		</div>
	</div>
	<?php
	}
	else{
	?>
	<div class="form-contact__fields-short">
		<div class="form-contact__item-short" style="width: 30% !important;">
			<span class="wpcf7-form-control-wrap">
				<label style="line-height: 40px;">&nbsp;</label>
			</span>
		</div>
		<div class="form-contact__item-short" style="width: 70% !important;">
			<span class="wpcf7-form-control-wrap activity-container-c<?php echo $avtivity_id; ?> activity-container-inner">
				<span class="wpcf7-form-control form-validation-item"></span>
			</span>
			<span class="wpcf7-form-control-wrap">
				<input type="number" data-filled="0" data-act-id="<?php echo $avtivity_id; ?>" data-per-cost="0" placeholder="<?php echo esc_html__( 'Number of Child', 'adventure-tours' ); ?>" id="activity_<?php echo $avtivity_id; ?>_child_number" name="activities_group[<?php echo $avtivity_id; ?>][child]"  value="" class="wpcf7-form-control wpcf7-number wpcf7-validates-as-number wpcf7-number-child" min="0" max="10" aria-invalid="false">
			</span>
		</div>
	</div>
	<?php
	}
	?>
	<?php
	if(!empty($activity_cost_adult) && floatval($activity_cost_adult) > 0){
	?>
	<div class="form-contact__fields-short">
		<div class="form-contact__item-short" style="width: 60% !important;">
			&nbsp;
		</div>
		<div class="form-contact__item-short" style="width: 40% !important;">
			<span class="wpcf7-form-control-wrap">
				<label style="line-height: 40px;"><?php echo esc_html__( 'Total of ', 'adventure-tours').$avtivity->post_title; ?> : </label>
				<span id="total_act_each_<?php echo $avtivity_id; ?>"></span>
				<input type="hidden" id="total_act_each_<?php echo $avtivity_id; ?>_input" name="activities_group[<?php echo $avtivity_id; ?>][total]" value="0">				
			</span>
		</div>
	</div>
	<?php
	}
	else{
	?>
	<div class="form-contact__fields-short" style="display: none;">
		<div class="form-contact__item-short" style="width: 60% !important;">
			&nbsp;
		</div>
		<div class="form-contact__item-short" style="width: 40% !important;">
			<span class="wpcf7-form-control-wrap">
				<label style="line-height: 40px;"><?php echo esc_html__( 'Total of ', 'adventure-tours').$avtivity->post_title; ?> : </label>
				<span id="total_act_each_<?php echo $avtivity_id; ?>"></span>
				<input type="hidden" name="activities_group[<?php echo $avtivity_id; ?>][total]" value="0">
			</span>
		</div>
	</div>
	<?php
	}
	?>
</div><!-- form-contact__fields-short -->
</blockquote>
<?php
		}
	}
	$html = ob_get_clean();
	
	$html_rev = '
	<div class="row">
		<div class="col-md-12">
		'.$html_gallery.'
		</div>
	</div>
	<div class="form-contact__fields-short" id="activities_gallery_container">
		<span class="wpcf7-form-control-wrap">
			<h3>'.esc_html__( 'Activities selected, priorities and numbers', 'adventure-tours' ).'</h3>
		</span>
   </div>
	<div class="row" class="">
		<div class="col-md-12">
		'.$html.'
		</div>
	</div>';
	
	return $html_rev;
	
}

function wpcf7_activity_shortcode_handler( $tag ) {

	$tag = new WPCF7_Shortcode( $tag );

	if ( empty( $tag->name ) ) {
		return '';
	}

	$validation_error = wpcf7_get_validation_error( $tag->name );

	$class = wpcf7_form_controls_class( $tag->type, 'wpcf7-activity' );

	if ( $validation_error ) {
		$class .= ' wpcf7-not-valid';
	}

	$class .= ' wpcf7-activity';

	if ( 'activity*' === $tag->type ) {
		$class .= ' wpcf7-validates-as-required';
	}
	
	ob_start();
	
	$queryArgs = array(
		'post_status' => 'publish',
		'post_type' => 'itinerary',
		'order' => 'ASC',
		'orderby' => 'menu_order ID',
		'posts_per_page'   => 9999,
	);
	$query2 = new WP_Query( $queryArgs );
	$activity_group = array();
	if ( $query2->have_posts() ) {
		while ( $query2->have_posts() ) {
			$query2->the_post();
			$itinerary_id_key =  $query2->post->ID;
			$itinerary_link = get_post_meta($itinerary_id_key, 'page_link' , true);
			$itinerary_link = get_permalink($itinerary_link);
			$itinerary_pdf_link = get_post_meta($itinerary_id_key, 'pdf_link' , true);
			if(!empty($itinerary_pdf_link)){
				$itinerary_pdf_link = wp_get_attachment_url( $itinerary_pdf_link );
			}
			else{
				$itinerary_pdf_link = '';
			}
			$activities_include = array();
			$field_activity_group = get_field_object('activities');
			if(isset($field_activity_group['value']) && !empty($field_activity_group['value'])){
				$field_activity_group_choices = $field_activity_group['value'];
				foreach($field_activity_group_choices as $k=>$v){
					$itinerary_id = $v->ID;
					$activities_include[] = $itinerary_id;
				}
			}
			$activity_group[$itinerary_id_key] = array(
				'label' => $query2->post->post_title,
				'link' => $itinerary_link,
				'pdf' => $itinerary_pdf_link,
				'value' => $activities_include,
			);
		}
	}
	wp_reset_postdata();
	
	
	/*$queryArgs = array(
		'post_status' => 'publish',
		'post_type' => 'activitytemplate',
		'order' => 'DESC',
		'orderby' => 'menu_order ID',
		'posts_per_page'   => 9999,
	);
	$query2 = new WP_Query( $queryArgs );
	if ( $query2->have_posts() ) {
		// The 2nd Loop
		while ( $query2->have_posts() ) {
			$query2->the_post();
			$idAttch =  $query2->post->ID;
			$field_activity_group = get_field_object('activity_group');
			if(isset($field_activity_group['value']) && !empty($field_activity_group['value'])){
				$field_activity_group_choices = $field_activity_group['value'];
				foreach($field_activity_group_choices as $k=>$v){
					$itinerary_id = $v->ID;
					if(array_key_exists($itinerary_id,$activity_group)){
						$activity_group[$itinerary_id]['value'][] = $idAttch;
					}
				}
			}
		}
	}
	wp_reset_postdata();*/


	?>
	<div class="form-contact__fields-short">
		<div class="form-contact__item-short">
			<span class="wpcf7-form-control-wrap">
				<h3>(A) <?php echo esc_html__( 'Enter people numbers', 'adventure-tours'); ?></h3>
			</span>
		</div>
   </div>
	<div class="form-contact__fields-short">
		<div class="form-contact__item-short">
			<span class="wpcf7-form-control-wrap defined_child">
				<input disabled="disabled" tabindex="2" min="0" type="number" name="defined_child" id="defined_child" value="" size="40" class="wpcf7-form-control wpcf7-text " aria-invalid="false" placeholder="<?php echo esc_html__( 'Number of Child', 'adventure-tours' ); ?>" data-original-title="" title="">
			</span>
		</div>
		<div class="form-contact__item-short">
			<span class="wpcf7-form-control-wrap defined_adult">
				<input disabled="disabled" tabindex="1" min="1" type="number" name="defined_adult" id="defined_adult" value="" size="40" class="wpcf7-form-control wpcf7-text form-validation-item" aria-invalid="false" placeholder="<?php echo esc_html__( 'Number of Adult', 'adventure-tours' ); ?>" data-original-title="" title="">
			</span>
		</div>
	</div>
	<div class="form-contact__fields-short">
		<div class="form-contact__item-short">
			<span class="wpcf7-form-control-wrap">
				<h3>(B) <?php echo esc_html__( 'Select an itinerary', 'adventure-tours'); ?></h3>
			</span>
		</div>
   </div>
	<div class="form-contact__fields-short">
		<div class="">
			<select tabindex="3" style="display: none;" disabled="disabled" id="activity_group_select" name="activity_group_select" class="selectpicker activity_single_priority">
				<option value=""><?php echo esc_html__( 'Select group...', 'adventure-tours'); ?></option>
				<?php
						foreach($activity_group as $k=>$v){
				?>
						<option value="<?php echo $k; ?>" data-link="<?php echo $v['link']; ?>"><?php echo $v['label']; ?></option>
				<?php
						}
				?>
			</select>
			<span><?php echo esc_html__( 'you need to fill in the number of adult and child before select a itinerary', 'adventure-tours'); ?></span>
			<div id="custom_itinerary_info" style="display:none;"><?php echo esc_html__( 'With Custom, select all of your activities, which you can do lower down this page.', 'adventure-tours'); ?></div>
		</div>
		
		<script type="text/javascript" charset="utf-8">
		var all_activity_group = {
		<?php
			foreach($activity_group as $key=>$val){
				echo '"'.$key.'" : {';
				foreach($val['value'] as $k=>$v){
					echo '"'.$v.'" : "'.$v.'",';
				}			
				echo '},';
			}
		?>
		};
		var all_activity_group_link = {
		<?php
			foreach($activity_group as $key=>$val){
				echo '"'.$key.'" : "'.$val['link'].'",';
			}
		?>	
		};
		var all_activity_group_pdf_link = {
		<?php
			foreach($activity_group as $key=>$val){
				echo '"'.$key.'" : "'.$val['pdf'].'",';
			}
		?>	
		};
		</script>
	</div>
	<div class="form-contact__fields-short">
		<div class="form-contact__item-short"style="">
			<a href="" id="itinerary_link" target="_blank" class="btn btn-primary" style="background: #000;display: none;"><?php echo esc_html__( 'view details', 'adventure-tours'); ?></a>
		</div>
		<div class="form-contact__item-short"style="">
			<a href="" id="itinerary_link_pdf" target="_blank" class="btn btn-primary" style="background: #000;display: none;"><?php echo esc_html__( 'view pdf', 'adventure-tours'); ?></a>
		</div>
	</div>
	<div class="form-contact__fields-short">		
		<span class="wpcf7-form-control-wrap">
			<h3>(C) <?php echo esc_html__( 'Add or remove activity', 'adventure-tours'); ?></h3>
		</span>
		<ol>
			<li><?php echo esc_html__( 'Look at the pictures below to find an activity.', 'adventure-tours'); ?></li>
			<li><?php echo esc_html__( 'Click on a picture to find out details and costs. A separate page opens.', 'adventure-tours'); ?></li>
			<li><?php echo esc_html__( 'Return to this page', 'adventure-tours'); ?></li>
		</ol>
		<ol style="list-style-type: none;">
			<li><?php echo esc_html__( 'To add, tick the I am interested in this below the activity.', 'adventure-tours'); ?></li>
			<li><?php echo esc_html__( 'It takes you lower down the page.', 'adventure-tours'); ?></li>
			<li><?php echo esc_html__( 'Enter the priority and the number of people.', 'adventure-tours'); ?></li>
			<li><?php echo esc_html__( 'To remove, untick the I am interested in this below the activity.', 'adventure-tours'); ?></li>
		</ol>
   </div>
	<div class="form-contact__fields-short">
		<span class="wpcf7-form-control-wrap">
			<h3>(D) <?php echo esc_html__( 'Submit itinerary', 'adventure-tours'); ?></h3>
		</span>
		<h4><?php echo esc_html__( 'Activities included', 'adventure-tours'); ?></h4>
		<p id="show_select_activities_list">
			
		</p>
   </div>
	<?php
	$html_group = ob_get_clean();
	
	$html = '<input type="hidden" name="activities_form_container_submit" id="activities_form_container_submit" value="" />'.$html_group;

	return $html;
}

/* Tag generator */

if ( is_admin() ) {
	add_action( 'admin_init', 'wpcf7_add_tag_generator_activity', 30 );
}

function wpcf7_add_tag_generator_activity() {

	if( class_exists('WPCF7_TagGenerator') ) {

		$tag_generator = WPCF7_TagGenerator::get_instance();
		$tag_generator->add( 'activity', __( 'activity', 'cf7_modules' ), 'wpcf7_tg_pane_activity' );
		$tag_generator->add( 'activity_gallery', __( 'activity_gallery', 'cf7_modules' ), 'wpcf7_tg_pane_activity_gallery' );

	}
}

function wpcf7_tg_pane_activity_gallery( $contact_form, $args = '' ) {

	$args = wp_parse_args( $args, array() );

	$description = __( "Generate a form tag for a activities gallery.", 'contact-form-7' );
?>
<div class="control-box">
	<fieldset>
		<legend><?php printf( esc_html( $description ) ); ?></legend>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>">
						<?php echo esc_html( __( 'Name', 'contact-form-7' ) ); ?>
						</label>
					</th>
					<td>
						<input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" />
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( $args['content'] . '-id' ); ?>">
						<?php echo esc_html( __( 'ID attribute', 'contact-form-7' ) ); ?>
						</label>
					</th>
					<td>
						<input type="text" name="id" class="idvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-id' ); ?>" />
					</td>
				</tr>			
			</tbody>
		</table>
	</fieldset>
</div>
	<div class="insert-box">
		<input type="text" name="activity_gallery" class="tag code" readonly="readonly" onfocus="this.select()" />

		<div class="submitbox">
			<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'contact-form-7' ) ); ?>" />
		</div>
	</div>
<?php
}

function wpcf7_tg_pane_activity( $contact_form, $args = '' ) {

	$args = wp_parse_args( $args, array() );

	$description = __( "Generate a form tag for a activities.", 'contact-form-7' );
?>
<div class="control-box">
	<fieldset>
		<legend><?php printf( esc_html( $description ) ); ?></legend>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>">
						<?php echo esc_html( __( 'Name', 'contact-form-7' ) ); ?>
						</label>
					</th>
					<td>
						<input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" />
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( $args['content'] . '-id' ); ?>">
						<?php echo esc_html( __( 'ID attribute', 'contact-form-7' ) ); ?>
						</label>
					</th>
					<td>
						<input type="text" name="id" class="idvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-id' ); ?>" />
					</td>
				</tr>			
			</tbody>
		</table>
	</fieldset>
</div>
	<div class="insert-box">
		<input type="text" name="activity" class="tag code" readonly="readonly" onfocus="this.select()" />

		<div class="submitbox">
			<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'contact-form-7' ) ); ?>" />
		</div>
	</div>
<?php
}

//hook on send contact form email
function nztour_wpcf7_mail_components($components, $current_form, $mail){
	$submission = WPCF7_Submission::get_instance();
	if ($submission && $current_form) {
$append_body = esc_html__( 'Activities', 'adventure-tours' ).':';
		$posted_data = $submission->get_posted_data();
		$activity_selection = isset( $posted_data['activities_form_container_submit'] ) ? trim( $posted_data['activities_form_container_submit'] ) : '';
		if(!empty($activity_selection)){
			$activity_group = isset( $posted_data['activities_group'] ) ? $posted_data['activities_group'] : '';
			if(!empty($activity_group)){
				$activity_selection = explode(',',$activity_selection);
				$activity_selection = array_flip($activity_selection);
				$all_total = 0;
				foreach($activity_group as $act_id => $act_value){
					if(array_key_exists($act_id,$activity_selection)){
						$activity_post = get_post($act_id);
						$activity_post_title = $activity_post->post_title;
						$activity_post_priority = $act_value['priority'];
						$activity_post_priority = strtoupper($activity_post_priority);
						switch($activity_post_priority){
							case 'HIGH':
								$activity_post_priority = 'Ôø??';
								break;
							case 'MEDIUM':
								$activity_post_priority = 'Ôø??';
								break;
							case 'LOW':
								$activity_post_priority = 'Ôø??';
								break;
							default:
								break;
						}
$append_body .=
'
---------------------------------------------
'.$activity_post_title .' - '.$activity_post_priority;


$show_total_flag = false;

						if( isset($act_value['adult']) ){
							$show_total_flag = true;
							$activity_post_adult = intval($act_value['adult']);

							if( isset($act_value['child']) ){
$append_body .=
'

'.esc_html__( 'Adult', 'adventure-tours' ).' : '.$activity_post_adult;
							}
							else{
$append_body .=
'

'.esc_html__( 'Person', 'adventure-tours' ).' : '.$activity_post_adult;
							}
							//$activity_cost_adult = get_post_meta($act_id,'wpcf-activity-cost-per-adult',true);
							//$activity_cost_adult = floatval($activity_cost_adult);
							//$activity_post_adult_cost = $activity_post_adult * $activity_cost_adult;
							//$activity_post_adult_cost = wc_price($activity_post_adult_cost);
							
						}
						if( isset($act_value['child']) ){
							$show_total_flag = true;
							$activity_post_child = intval($act_value['child']);
$append_body .=
'

'.esc_html__( 'Child', 'adventure-tours' ).' : '.$activity_post_child;

						}

if($show_total_flag && floatval($act_value['total']) > 0){
	$this_activity_total = floatval($act_value['total']);
	$all_total = $all_total + $this_activity_total;
$append_body .=
'

'.esc_html__( 'Subtotal', 'adventure-tours' ).' : NZ$'.$this_activity_total;
}
$append_body .=
'
---------------------------------------------
';						
					}
				}
				
				if($all_total > 0){
$append_body .=
'

'.esc_html__( 'Total', 'adventure-tours' ).' : NZ$'.$all_total;					
				}
			}
		}
		$components['body'] = str_replace("[activity]", $append_body, $components['body']);
	}

	return $components;
}

//All addresses info except country is optional on checkout page
add_filter( 'woocommerce_checkout_fields', 'nztour_woocommerce_checkout_fields', 999 );
function nztour_woocommerce_checkout_fields($checkout_fields){
	//remove company field
	
	unset($checkout_fields["billing"]['billing_company']);
	$optional_fields = array(
		'billing_address_1' => 1,
		'billing_city' => 1,
		'billing_state' => 1,
		'billing_postcode' => 1,		
	);
	foreach($optional_fields as $key=>$v){
		if(array_key_exists($key,$checkout_fields["billing"])){
			$checkout_fields["billing"][$key]['required'] = false;
		}
	}
	if(isset($checkout_fields['order']['order_comments'])){
		$checkout_fields['order']['order_comments'] = array(
				'type' => 'textarea',
				'class' => array('notes'),
				'label' => __( 'Special requests', 'adventure-tours' ),
				'placeholder' => _x('Enter any special request or information.', 'placeholder', 'adventure-tours')
			);
	}
	return $checkout_fields;
}

add_filter( 'woocommerce_country_locale_field_selectors', 'nztour_woocommerce_country_locale_field_selectors', 999 );
function nztour_woocommerce_country_locale_field_selectors($locale_fields){
	/*$locale_fields = array (
			'address_1' => '#billing_address_1_field, #shipping_address_1_field',
			'address_2' => '#billing_address_2_field, #shipping_address_2_field',
			'state'     => '#billing_state_field, #shipping_state_field, #calc_shipping_state_field',
			'postcode'  => '#billing_postcode_field, #shipping_postcode_field, #calc_shipping_postcode_field',
			'city'      => '#billing_city_field, #shipping_city_field, #calc_shipping_city_field',
		);*/
	unset($locale_fields["address_1"]);
	unset($locale_fields["address_2"]);
	unset($locale_fields["state"]);
	unset($locale_fields["postcode"]);
	unset($locale_fields["city"]);
	return $locale_fields;
}

//change currency symbol
add_filter( 'woocommerce_currency_symbols', 'nztour_woocommerce_currency_symbols', 999 );
function nztour_woocommerce_currency_symbols($symbols){
	$symbols['NZD'] = 'NZ$ ';
	return $symbols;
}

// admin other plugins that the items have been saved
add_action( 'woocommerce_saved_order_items', 'nztour_woocommerce_saved_order_items', 30 , 2 );
function nztour_woocommerce_saved_order_items($order_id, $items){
	$total        = 0;
	if ( isset( $items['order_item_id'] ) ) {
		$line_total = $line_subtotal = $line_tax = $line_subtotal_tax = array();

		foreach ( $items['order_item_id'] as $item_id ) {

			$item_id = absint( $item_id );

			// Get values. Subtotals might not exist, in which case copy value from total field
			$line_total[ $item_id ]        = isset( $items['line_total'][ $item_id ] ) ? $items['line_total'][ $item_id ] : 0;
			$line_subtotal[ $item_id ]     = isset( $items['line_subtotal'][ $item_id ] ) ? $items['line_subtotal'][ $item_id ] : $line_total[ $item_id ];
			$line_tax[ $item_id ]          = isset( $items['line_tax'][ $item_id ] ) ? $items['line_tax'][ $item_id ] : array();
			$line_subtotal_tax[ $item_id ] = isset( $items['line_subtotal_tax'][ $item_id ] ) ? $items['line_subtotal_tax'][ $item_id ] : $line_tax[ $item_id ];

			// Format taxes
			$line_taxes          = array_map( 'wc_format_decimal', $line_tax[ $item_id ] );
			$line_subtotal_taxes = array_map( 'wc_format_decimal', $line_subtotal_tax[ $item_id ] );

			// Save line tax data - Since 2.2
			$taxes['items'][] = $line_taxes;

			// Total up
			$subtotal     += wc_format_decimal( $line_subtotal[ $item_id ] );
			$total        += wc_format_decimal( $line_total[ $item_id ] );
			$subtotal_tax += array_sum( $line_subtotal_taxes );
			$total_tax    += array_sum( $line_taxes );

		}
	}
	$total = $total * 0.3;
	update_post_meta( $order_id, '_order_total', wc_format_decimal( $total ) );
}


add_filter( 'wpo_wcpdf_listing_actions', 'nztour_wpo_wcpdf_meta_box_actions', 999, 2 );
add_filter( 'wpo_wcpdf_meta_box_actions', 'nztour_wpo_wcpdf_meta_box_actions', 999, 2 );
function nztour_wpo_wcpdf_meta_box_actions($meta_actions, $post_id){
	unset($meta_actions['packing-slip']);
	return $meta_actions;
}


if ( ! function_exists( 'adventure_tours_render_tab_description' ) ) {
	/**
	 * Tour details page, tab description rendeing function.
	 *
	 * @return void
	 */
	function adventure_tours_render_tab_description() {
		global $product;

		/*if ( adventure_tours_check( 'tour_category_taxonomy_exists' ) ) {
			$taxonomy = 'tour_category';
			$terms = get_the_terms( $product->ID, $taxonomy );
			if ( $terms ) {
				echo '<ul class="list-block list-block--tour-tabs">';
				foreach ( $terms as $term ) {
					echo '<li><a href="' . get_term_link( $term->slug, $taxonomy ) . '">' . $term->name . '</a></li>';
				}
				echo '</ul>';
			}
		}*/

		the_content();

		if ( ! empty( $GLOBALS['_tour_additional_attributes'] ) ) {
			adventure_tours_render_template_part( 'templates/tour/additional-attributes', '', array(
				'title' => esc_html__( 'Additional information', 'adventure-tours' ),
				'attributes' => $GLOBALS['_tour_additional_attributes'],
			) );
		}
	}
}

// -----------------------------------------------------------------#
// Renderind: tour page details, tabs rendering
// -----------------------------------------------------------------#
if ( ! function_exists( 'adventure_tours_filter_tour_tabs' ) ) {
	/**
	 * Tour details page, tabs filter function.
	 * Defines what tabs should be rendered.
	 *
	 * @return void
	 */
	function adventure_tours_filter_tour_tabs( $tabs ) {
		global $product;
		if ( empty( $product ) ) {
			return $tabs;
		}

		$tabs['description'] = array(
			'title' => esc_html__( 'Details', 'adventure-tours' ),
			'priority' => 10,
			'top_section_callback' => 'adventure_tours_render_tab_description_top_section',
			'callback' => 'adventure_tours_render_tab_description',
		);

		// Photos tab rendering.
		/*ob_start();
		adventure_tours_render_tab_photos();
		$photosTabContent = ob_get_clean();
		if ( $photosTabContent ) {
			$tabs['photos'] = array(
				'title' => esc_html__( 'Photos', 'adventure-tours' ),
				'priority' => 25,
				'content' => $photosTabContent,
			);
		}*/

		/*
		if ( comments_open() ) {
			$tabs['reviews'] = array(
				'title'    => sprintf( esc_html__( 'Reviews (%d)', 'adventure-tours' ), $product->get_review_count() ),
				'priority' => 30,
				'callback' => 'comments_template',
			);
		}*/

		$booking_dates = adventure_touts_get_tour_booking_dates( $product->id );
		if ( $booking_dates ) {
			$tabs['booking_form'] = array(
				'title' => apply_filters( 'adventure_tours_booking_form_title', esc_html__( 'Book the tour', 'adventure-tours'), $product ),
				'tab_css_class' => 'visible-xs booking-form-scroller',
				'priority' => 35,
				'content' => ''
			);
		}

		return $tabs;
	}
}

//cart validation

add_action( 'wp_ajax_adventure_touts_child_cart_valid', 'adventure_touts_child_cart_valid_fuc'  );
add_action( 'wp_ajax_nopriv_adventure_touts_child_cart_valid', 'adventure_touts_child_cart_valid_fuc'  );
if( !function_exists('adventure_touts_child_cart_valid_fuc') ){
function adventure_touts_child_cart_valid_fuc(){
	$flag = false;
	$error = '';
	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		$_product     = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
		if($_product->is_type( 'tour' )){
			$product_id   = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
			if(isset($cart_item['date'])){
				$booking_date_start = $cart_item['date'];
				//correct strtotime issue
				$booking_date = str_replace('/', '-', $booking_date_start);
				$booking_date = strtotime($booking_date);
				$sku = get_post_meta( $product_id, 'sku', true );
				global $wpdb;
				$booking_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT ID FROM wprh_booking_record
						WHERE product_id = %s AND booking_date = %s", $sku, $booking_date ) );
				if(!is_null($booking_id)){
					$flag = true;
					$booking_date_end = date('YÂπ¥nÊúàjÔø??', $booking_date);
					$booking_date_end = date('YÂπ¥nÊúàjÔø??', strtotime("+6 day", $booking_date));
					$notice = $booking_date_start . ' - '. $booking_date_end .'  '. $_product->post->post_title . 'Â∑≤ÁªèË¢´ËÆ¢Ë¥≠‰∫Ü';
					$error .= '<div class="cart_notice_nzd" style="color:red;">
								'.$notice.'
								</div>';
				}
			}			
		}
	}
	if($flag){
		$rev_data = array(
			'value' => "no",
			'error' => $error
		); 
	}
	else{
		$rev_data = array(
			'value' => "yes"
		); 
	}
	wp_send_json( $rev_data );
}
}
//Modify on 03/05/2017 to do refactor on code add script and css to footer Leon
//add_action( 'nz_footer_custom', 'nz_footer_custom_fuc' );
function nz_custom_script_func(  ) {

wp_enqueue_style('jquery_style','https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.1.0/jquery-confirm.min.css');
wp_enqueue_script('jquery_confirm','https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.1.0/jquery-confirm.min.js');
wp_enqueue_style('print_style','/'.CHILD_CSS_REL_DIR.'/style-print.css',null,null,'print');
//wp_enqueue_style('print_style',get_stylesheet_directory_uri().'/style-print',null,null,'print');
//wp_enqueue_script('jquery_datepicker',get_stylesheet_directory_uri().'/js/datepicker-zh-CN.js');
wp_enqueue_script('jquery_datepicker','/'.CHILD_CSS_REL_DIR.'/js/datepicker-zh-CN.js');
}
add_action('wp_enqueue_scripts','nz_custom_script_func');
function nz_footer_custom_func(){
    echo get_stylesheet_directory_uri();
    echo CHILD_CSS_REL_DIR;
    
?>
<!--<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.1.0/jquery-confirm.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.1.0/jquery-confirm.min.js"></script>
<link rel="stylesheet" id="child-style-print-css" href="<?php echo site_url();?>/wp-content/themes/adventure-tours-child/style-print.css" type="text/css" media="print">
<script src="<?php echo site_url();?>/wp-content/themes/adventure-tours-child/js/datepicker-zh-CN.js"></script>-->
<script type="text/javascript" charset="utf-8">
jQuery(document).ready(function(){
	jQuery.datepicker.setDefaults(
	  jQuery.extend(
		 jQuery.datepicker.regional['zh-CN']
	  )
	);
});
</script>
<?php
}
add_action( 'nz_footer_custom', 'nz_footer_custom_func' );
//Modify on 03/05/2017 to do refactor on code add script and css to footer Leon
function nz_get_cn_week($week){
	switch($week){
		case 'Monday':
			return '–«∆⁄“ª';
			break;
		case 'Tuesday':
			return '–«∆⁄∂˛';
			break;
		case 'Wednesday':
			return '–«∆⁄»˝';
			break;
		case 'Thursday':
			return '–«∆⁄Àƒ';
			break;
		case 'Friday':
			return '–«∆⁄ŒÂ';
			break;
		case 'Saturday':
			return '–«∆⁄¡˘';
			break;
		case 'Sunday':
			return '–«∆⁄»’';
			break;
		default:
			return '';
			break;
	}
	return '';
}

//Chinese website always go to Chinese PayPal
add_filter( 'woocommerce_paypal_args', 'nz_woocommerce_paypal_args', 20, 2 );
function nz_woocommerce_paypal_args($args, $order){
	$args['lc'] = 'zh_CN';
	return $args;
}

if ( ! function_exists( 'adventure_tours_get_tour_booking_range' ) ) {
	/**
	 * Returns range during that booking for specefied tour can be done.
	 *
	 * @param  int $tour_id
	 * @return assoc        contains 'start' and 'end' keys with dates during that booking is active
	 */
	function adventure_tours_get_tour_booking_range( $tour_id ) {
		static $start_days_in, $length;
		$tour_meta = get_post_meta($tour_id,'tour_booking_periods',true);
		/*if ( null == $start_days_in ) {
			$start_days_in = (int) adventure_tours_get_option( 'tours_booking_start' );
			$length = (int) adventure_tours_get_option( 'tours_booking_length' ); 
			if ( $start_days_in < 0 ) {
				$start_days_in = 0;
			}
			if ( $length < 1 ) {
				$length = 1;
			}
		}*/

		$min_time = time();//strtotime( '+' . $start_days_in . ' day' );
		$max_time = strtotime($tour_meta[0]['to']);// strtotime( '+' . $length . ' day', $min_time );

		return array(
			'start' => date( 'Y-m-d', $min_time ),
			'end' => date( 'Y-m-d', $max_time ),
		);
	}
}

if ( ! function_exists( 'nz_getUserIP' ) ) {
function nz_getUserIP(){
   $client  = @$_SERVER['HTTP_CLIENT_IP'];
   $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
   $remote  = $_SERVER['REMOTE_ADDR'];

   if(filter_var($client, FILTER_VALIDATE_IP)){
      $ip = $client;
   }
   elseif(filter_var($forward, FILTER_VALIDATE_IP)){
      $ip = $forward;
   }
   else{
      $ip = $remote;
   }
   return $ip;
}
}

if ( ! function_exists( 'nz_childadd_query_vars_filter' ) ) {
function nz_childadd_query_vars_filter( $vars ){
  $vars[] = "url";
  $vars[] = "keyword";
  $vars[] = "matchtype";
  $vars[] = "device";
  $vars[] = "target";
  $vars[] = "placement";
  $vars[] = "subcode";
  return $vars;
}
}
add_filter( 'query_vars', 'nz_childadd_query_vars_filter' );


add_action('template_redirect', 'nz_child_template_redirect_d90sj');
 
 
function nz_child_template_redirect_d90sj(){
   global $wp_query;
	if(isset($wp_query->query['post_type']) && $wp_query->query['post_type'] == 'product'){
		if(isset($wp_query->posts) && isset($wp_query->posts[0])){
			$post = $wp_query->posts[0];
			$post_id = $post->ID;
			
			date_default_timezone_set('Pacific/Auckland');
	
			$full_url = get_the_permalink($post_id);	
			$url_nz_oasi23 = get_query_var( 'url', '' );
			$keyword_nz_oasi23 = get_query_var( 'keyword', '' );
			$matchtype_nz_oasi23 = get_query_var( 'matchtype', '' );
			$device_nz_oasi23 = get_query_var( 'device', '' );
			$target_nz_oasi23 = get_query_var( 'target', '' );
			$placement_nz_oasi23 = get_query_var( 'placement', '' );
			$subcode_nz_oasi23 = get_query_var( 'subcode', '' );
		
			//if(isset($subcode_nz_oasi23) && !empty($subcode_nz_oasi23)){
			global $wpdb;
			$user_ip = nz_getUserIP();
			$now_date = time();//date('Y-m-d H:i:s');
			
			$url_nz_oasi23 = trim( $url_nz_oasi23 );
			$keyword_nz_oasi23 = trim( $keyword_nz_oasi23 );
			$matchtype_nz_oasi23 = trim( $matchtype_nz_oasi23 );
			$device_nz_oasi23 = trim( $device_nz_oasi23 );
			$target_nz_oasi23 = trim( $target_nz_oasi23 );
			$placement_nz_oasi23 = trim( $placement_nz_oasi23 );
			$subcode_nz_oasi23 = trim( $subcode_nz_oasi23 );
			/*if( (isset($url_nz_oasi23) && !empty($url_nz_oasi23)) ||
				(isset($keyword_nz_oasi23) && !empty($keyword_nz_oasi23)) ||
				(isset($matchtype_nz_oasi23) && !empty($matchtype_nz_oasi23)) ||
				(isset($device_nz_oasi23) && !empty($device_nz_oasi23)) ||
				(isset($target_nz_oasi23) && !empty($target_nz_oasi23)) ||
				(isset($placement_nz_oasi23) && !empty($placement_nz_oasi23)) ||
				(isset($subcode_nz_oasi23) && !empty($subcode_nz_oasi23)) ){*/
				
			session_start();    
			if(!isset($_SESSION['track_subcode_id'])){
				$_SESSION['track_subcode_id'] = md5($user_ip.$now_date); //default language
			}
		
			$seconds_12_ago = strtotime("-10 seconds");
			//$seconds_12_ago_date = date( 'Y-m-d H:i:s', $seconds_12_ago );
			$booking_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM wprh_subcode_record
				WHERE date_record >= %s AND ip = %s AND site_page_url = %s",
				$seconds_12_ago, $user_ip, $full_url ) );
			if(is_null($booking_id)){
				$data = array(
					'url'   => $url_nz_oasi23,
					'keyword'   => $keyword_nz_oasi23,
					'matchtype'   => $matchtype_nz_oasi23,
					'device'   => $device_nz_oasi23,
					'target'   => $target_nz_oasi23,
					'placement'   => $placement_nz_oasi23,
					'subcode'   => $subcode_nz_oasi23,
					'date_record'    	=> $now_date,
					'ip'	      => $user_ip,
					'session_id' => $_SESSION['track_subcode_id'],
					'site_page_url' => $full_url
				);
				$wpdb->insert(
					'wprh_subcode_record',
					$data
				);
				if ( is_home() ) {
					wp_redirect(home_url());
					exit();
				}
			}
		}
	}
	
}

if ( ! function_exists( 'nz_child_pre_get_posts_d90sj' ) ) {
function nz_child_pre_get_posts_d90sj( $query ) {
	// check if the user is requesting an admin page 
	// or current query is not the main query
	if ( ! $query->is_main_query() ){
		return;
	}
	
	if(is_admin()){
		return;
	}
	if( isset($query->queried_object) && !empty($query->queried_object) && isset($query->queried_object->ID) ){
		$post_id = $query->queried_object->ID;
	}
	elseif( isset($query->query_vars) && isset($query->query_vars['page_id']) && $query->query_vars['page_id'] != 0 ){
		$post_id = $query->query_vars['page_id'];
	}
	elseif(is_home()){
		$post_id = get_option( 'page_on_front' );
	}
	else{
		return;
	}
	
	
	date_default_timezone_set('Pacific/Auckland');
	
	$full_url = get_the_permalink($post_id);	
	$url_nz_oasi23 = get_query_var( 'url', '' );
	$keyword_nz_oasi23 = get_query_var( 'keyword', '' );
	$matchtype_nz_oasi23 = get_query_var( 'matchtype', '' );
	$device_nz_oasi23 = get_query_var( 'device', '' );
	$target_nz_oasi23 = get_query_var( 'target', '' );
	$placement_nz_oasi23 = get_query_var( 'placement', '' );
	$subcode_nz_oasi23 = get_query_var( 'subcode', '' );

	//if(isset($subcode_nz_oasi23) && !empty($subcode_nz_oasi23)){
	global $wpdb;
	$user_ip = nz_getUserIP();
	$now_date = time();//date('Y-m-d H:i:s');
	
	$url_nz_oasi23 = trim( $url_nz_oasi23 );
	$keyword_nz_oasi23 = trim( $keyword_nz_oasi23 );
	$matchtype_nz_oasi23 = trim( $matchtype_nz_oasi23 );
	$device_nz_oasi23 = trim( $device_nz_oasi23 );
	$target_nz_oasi23 = trim( $target_nz_oasi23 );
	$placement_nz_oasi23 = trim( $placement_nz_oasi23 );
	$subcode_nz_oasi23 = trim( $subcode_nz_oasi23 );
/*	if( (isset($url_nz_oasi23) && !empty($url_nz_oasi23)) ||
		(isset($keyword_nz_oasi23) && !empty($keyword_nz_oasi23)) ||
		(isset($matchtype_nz_oasi23) && !empty($matchtype_nz_oasi23)) ||
		(isset($device_nz_oasi23) && !empty($device_nz_oasi23)) ||
		(isset($target_nz_oasi23) && !empty($target_nz_oasi23)) ||
		(isset($placement_nz_oasi23) && !empty($placement_nz_oasi23)) ||
		(isset($subcode_nz_oasi23) && !empty($subcode_nz_oasi23)) ){*/
		
	session_start();    
	if(!isset($_SESSION['track_subcode_id'])){
		$_SESSION['track_subcode_id'] = md5($user_ip.$now_date); //default language
	}

	$seconds_12_ago = strtotime("-10 seconds");
	//$seconds_12_ago_date = date( 'Y-m-d H:i:s', $seconds_12_ago );
	$booking_id = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT ID FROM wprh_subcode_record
		WHERE date_record >= %s AND ip = %s AND site_page_url = %s",
		$seconds_12_ago, $user_ip, $full_url ) );
	if(is_null($booking_id)){
		$data = array(
			'url'   => $url_nz_oasi23,
			'keyword'   => $keyword_nz_oasi23,
			'matchtype'   => $matchtype_nz_oasi23,
			'device'   => $device_nz_oasi23,
			'target'   => $target_nz_oasi23,
			'placement'   => $placement_nz_oasi23,
			'subcode'   => $subcode_nz_oasi23,
			'date_record'    	=> $now_date,
			'ip'	      => $user_ip,
			'session_id' => $_SESSION['track_subcode_id'],
			'site_page_url' => $full_url
		);
		$wpdb->insert(
			'wprh_subcode_record',
			$data
		);
           
		if ( is_home() ) {
			wp_redirect(home_url());
			exit();
		}
	}
        
        
	

 if( (isset($url_nz_oasi23) && !empty($url_nz_oasi23)) ||
		(isset($keyword_nz_oasi23) && !empty($keyword_nz_oasi23)) ||
		(isset($matchtype_nz_oasi23) && !empty($matchtype_nz_oasi23)) ||
		(isset($device_nz_oasi23) && !empty($device_nz_oasi23)) ||
		(isset($target_nz_oasi23) && !empty($target_nz_oasi23)) ||
		(isset($placement_nz_oasi23) && !empty($placement_nz_oasi23)) ||
		(isset($subcode_nz_oasi23) && !empty($subcode_nz_oasi23)) ){
		if ( is_home() ) {
			wp_redirect(home_url());
			exit();
		}
	}
	//}
        
	/*$booking_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM wprh_subcode_record
			WHERE subcode = %s AND date = %s AND ip = %s
			AND ip = %s AND ip = %s AND ip = %s AND ip = %s
			AND ip = %s AND ip = %s",
			$subcode_nz_oasi23, $now_date, $user_ip ) );*/
	/*if(is_null($booking_id)){
				
	}*/
	
	//}

	return true;
}
}

add_action( 'pre_get_posts', 'nz_child_pre_get_posts_d90sj', 1 );