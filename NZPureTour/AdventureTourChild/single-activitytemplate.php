<?php
/**
 * The template for displaying all single posts.
 *
 * @package Poseidon
 */

get_header(); ?>
<?php while (have_posts()) : the_post();



	$post_id = get_the_ID();
	$activity_name = get_post_meta($post_id,'wpcf-activity-name',true);
	$activity_des = get_post_meta($post_id,'wpcf-activity-des',true);
	$activity_img = get_post_meta($post_id,'wpcf-activity-ime');

	
//$field = get_field_object('activity_group');
//var_dump($field['value']);




	$thumbnail = get_the_post_thumbnail( $post_id, 'large' ); 

endwhile; ?>
<div class="woocommerce">
	<div class="row">
		
		<main class="col-md-12" role="main">	
		
			
			<div itemscope="" itemtype="http://schema.org/Product" id="product-213" class="post-213 product type-product status-publish product_cat-tents-and-shelters first instock shipping-taxable purchasable product-type-variable has-children">

				<div class="product-box product-box--page-single padding-all">
					<div class="row">
						<div class="col-md-6">
							<div class="images avtivity_img_featured">
								<?php
								if(is_array($activity_img) && count($activity_img) > 1){
								?>
								<div id="slider" class="flexslider">
									<ul class="slides">
									<?php
									foreach($activity_img as $img){
										$img_id = nztour_get_image_id($img);
										if($img_id){
											$image_o = wp_get_attachment_image_src( $img_id, 'large' );
											$image_o_url = $image_o[0];
											echo '<li>
													<img src="'.$image_o_url.'" />
												</li>';
										}
										
									}
									?>										
									</ul>
								</div>
								<div id="carousel" class="flexslider">
									<ul class="slides">
									<?php
									foreach($activity_img as $img){
										$img_id = nztour_get_image_id($img);
										if($img_id){
											$image_o = wp_get_attachment_image_src( $img_id, 'thumbnail' );
											$image_o_url = $image_o[0];
											echo '<li>
													<img src="'.$image_o_url.'" />
												</li>';
										}
										
									}
									?>										
									</ul>
								</div>
								<script type="text/javascript" charset="utf-8">
									jQuery(window).load(function() {
										jQuery('#carousel').flexslider({
											 animation: "slide",
											 controlNav: false,
											 animationLoop: false,
											 slideshow: false,
											 itemWidth: 150,
											 itemMargin: 5,
											 asNavFor: '#slider'
										});
										 
										jQuery('#slider').flexslider({
											 animation: "slide",
											 controlNav: false,
											 animationLoop: false,
											 slideshow: false,
											 sync: "#carousel"
										});
									});
								</script>
								<style>
									.flex-direction-nav a:before {
										margin-top: 5px;
									}
									#slider.flexslider .slides > li {
										height: 468px;
										line-height: 468px;
										vertical-align: middle;
									}
									#slider.flexslider .slides > li img{
										vertical-align: middle;
										display: inline;
									}
								</style>
								<?php
								}
								else{
									echo $thumbnail;
								}
								?>
							</div>
						</div>
						<div class="col-md-6">
							<h2 itemprop="name" class="product_title entry-title">
								<?php echo $activity_name; ?>
							</h2>
							<div itemprop="description">
								<p><?php echo $activity_des; ?></p>
							</div>

						</div>
					</div>
				</div>

			</div> 
		</main>
		<?php
		/*$sidebar_content = '';
		 
		ob_start();
		do_action( 'woocommerce_sidebar' );
		$sidebar_content = ob_get_clean();
 
		echo $sidebar_content;*/
		?>

	</div>
</div>
	
	
<?php get_footer(); ?>
<script type="text/javascript" src="<?php echo CHILD_URL; ?>/flexslider/jquery.flexslider-min.js"></script>
<link rel="stylesheet" href="<?php echo CHILD_URL; ?>/flexslider/flexslider.css" type="text/css" media="screen">