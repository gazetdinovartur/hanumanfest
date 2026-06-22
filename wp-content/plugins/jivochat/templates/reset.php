<?php
/**
 * Render reset button to disable integration and allow use another jivochat account
 *
 * @package WordPress_Online_Widget_JivoChat
 */

?>

<div class="container jivosite-center jivosite-small">
	<div class="row justify-content-center">
		<div class="col-6 jivosite-content">
			<h1>
				<?php require 'jivo-logo.php'; ?>
			</h1>
			<div class="jivosite-margin">
				<div><?php esc_html_e( 'Have questions? Don\'t worry, we offer 24/7 live support!', 'jivochat' ); ?></div>
				<div><?php esc_html_e( 'Ask us anything and we will help you onboarding.', 'jivochat' ); ?></div>
			</div>
			<div class="jivosite-margin">
				<a class="btn jivosite-base-button" href="https://<?php esc_html_e( 'www.jivochat.com', 'jivochat' ); ?>?utm_source=WordPress" target="_blank"><?php esc_html_e( 'Live Chat with Us', 'jivochat' ); ?></a>
				<a class="btn jivosite-base-button" href="https://<?php esc_html_e( 'www.jivochat.com', 'jivochat' ); ?>/help?utm_source=WordPress" target="_blank"><?php esc_html_e( 'Knowledge Base', 'jivochat' ); ?></a>
			</div>
			<div class="jivosite-margin">
				<div><?php esc_html_e( 'If you would like to use another JivoChat account, you can use this log out button to login with another account.', 'jivochat' ); ?></div>
			</div>
			<div class="jivosite-margin">
				<form id="reset_form" method="POST">
					<input type="hidden" name="reset" value="reset">
					<input id="reset_button" class="btn jivosite-base-button" type="submit" value="<?php esc_html_e( 'Log Out', 'jivochat' ); ?>">
				</form>
			</div>
		</div>
	</div>
</div>
