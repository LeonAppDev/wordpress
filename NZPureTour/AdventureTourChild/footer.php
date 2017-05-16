<?php
/**
 * Footer template part.
 *
 * @author    Themedelight
 * @package   Themedelight/AdventureTours
 * @version   1.0.0
 */

$isShowFooterManu = has_nav_menu( 'footer-menu' );
$classCol = ( $isShowFooterManu ) ? 'col-md-6' : 'col-md-12';
?>
</div><!-- .container -->
<footer class="footer">
	<?php get_template_part( 'templates/footer/widget-areas' ); ?>
	<div class="footer__bottom">
		<div class="footer__arrow-top"><a href="#"><i class="fa fa-chevron-up"></i></a></div>
		<div class="container">
			<div class="row">
				<div class="<?php echo esc_attr( $classCol ); ?>">
					<div class="footer__copyright"><?php echo adventure_tours_esc_text( adventure_tours_get_option( 'footer_text_note' ), 'option_input', true ); ?></div>
				</div>
				<?php if ( $isShowFooterManu ) : ?>
					<div class="col-md-6">
						<div class="footer-nav">
							<?php wp_nav_menu(array(
								'theme_location' => 'footer-menu',
								'container' => 'ul',
								'depth' => 1,
							)); ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</footer>

<?php get_template_part( 'footer','clean' ); ?>
<?php do_action( 'nz_footer_custom' ); ?>