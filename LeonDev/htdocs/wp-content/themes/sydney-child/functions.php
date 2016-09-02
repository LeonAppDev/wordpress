<?php
function sydney_child_enqueue_styles() {

    $parent_style = 'sydney-style'; // This is 'sydney-style' for the sydney theme.
    /*wp_enqueue_script('jquery.poptrox.min.js',
    	get_stylesheet_directory_uri() . '/assets/js/jquery.poptrox.min.js',
    	array('jquery'),
    	wp_get_theme()->get('Version'),
    	true);
    
        wp_enqueue_script('skel.min.js',
    	get_stylesheet_directory_uri() . '/assets/js/skel.min.js',
    	array('jquery'),
    	wp_get_theme()->get('Version'),
    	true);
    	
    	wp_enqueue_script('util.js',
    	get_stylesheet_directory_uri() . '/assets/js/util.js',
    	array('jquery'),
    	wp_get_theme()->get('Version'),
    	true);
    		
       wp_enqueue_script('main.js',
    	get_stylesheet_directory_uri() . '/assets/js/main.js',
    	array('jquery'),
    	wp_get_theme()->get('Version'),
    	true);
       
      
    wp_enqueue_script('jquery.min.js',
    	get_stylesheet_directory_uri() . '/assets/js/jquery.min.js',
    	array('jquery'),
    	wp_get_theme()->get('Version'),
    	true);
     wp_enqueue_style('main',
    	get_stylesheet_directory_uri() . '/assets/css/main.css',
    	false,
    	wp_get_theme()->get('Version'),
    	'all');
    	*/
    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'sydney-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( $parent_style ),
        wp_get_theme()->get('Version')
    );
    
    
    
}
add_action( 'wp_enqueue_scripts', 'sydney_child_enqueue_styles' );
?>