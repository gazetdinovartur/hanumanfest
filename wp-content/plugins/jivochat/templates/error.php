<?php
/**
 * Render error, if plugin connection to api.jivosite.com doesn't work or have invalid data
 *
 * @package WordPress_Online_Widget_JivoChat
 */

?>

<div class="wrap">
	<h1>
		<?php require 'jivo-logo.php'; ?>
	</h1>
	<b style="color:red;"><?php echo esc_html( $error ); ?></b>
		<div class="gray_form">
			<?php
				list($code) = explode( '-', get_bloginfo( 'language' ) );
				printf(
					/* translators: %s: jivo domain */
					esc_html__( 'Unfortunately, your server configuration does not allow the plugin to connect to JivoChat servers to create account. Please, go to <a target="_blank" href="https://app.jivosite.com/?lang=%s">https://app.jivosite.com</a> and sign up. During the signup process you will be offered to download another WordPress module that does not require to communicate over the network', 'jivosite' ),
					esc_html( $code )
				);
				?>
		</div>
</div>
