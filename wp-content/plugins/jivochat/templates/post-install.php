<?php
/**
 * Render plugin setting page after installed widget
 *
 * @package WordPress_Online_Widget_JivoChat
 */

?>

<div class="container jivosite-center jivosite-big">
	<div class="row justify-content-center">
		<div class="col-6 jivosite-content">
			<h1>
				<?php require 'jivo-logo.php'; ?>
			</h1>
			<div class="jivosite-margin">
				<h5><?php esc_html_e( 'Congratulations, you have successfully installed JivoChat widget to your website!', 'jivochat' ); ?></h5>
			</div>
			<div class="jivosite-margin">
				<?php esc_html_e( 'You are now able to see live chat widget on your webiste. Now you can customize & configure your JivoChat widget. Click the button below to go to web app and start testing it!', 'jivochat' ); ?>
			</div>
			<div class="jivosite-margin">
				<a class="btn jivosite-base-button" href="<?php echo esc_html( JIVOSITE_INTEGRATION_URL ) . '/login?partnerId=WordPress&token=' . esc_html( get_option( 'jivosite_token' ) ) . '&lang=' . esc_html( get_option( 'jivosite_lang_code' ) ); ?>" target="_blank"><?php esc_html_e( 'Go to Web Application', 'jivochat' ); ?></a>
			</div>
			<div class="jivosite-margin">
				<?php esc_html_e( 'You can either use our web app, our desktop apps for Windows, macOS, or mobile apps for iOS and Android. We recommend using desktop and mobile apps simultaneously.', 'jivochat' ); ?>
			</div>
			<div class="jivosite-margin">
				<a class="btn jivosite-base-button" href="https://<?php esc_html_e( 'www.jivochat.com', 'jivochat' ); ?>/apps?utm_source=WordPress" target="_blank"><?php esc_html_e( 'Install Apps', 'jivochat' ); ?></a>
			</div>
			<div class="jivosite-margin">
				<div><?php esc_html_e( 'Have questions? Don\'t worry, we offer 24/7 live support!', 'jivochat' ); ?></div>
				<div><?php esc_html_e( 'Ask us anything and we will help you onboarding.', 'jivochat' ); ?></div>
			</div>
			<div class="jivosite-margin">
				<a class="btn jivosite-base-button" href="https://<?php esc_html_e( 'www.jivochat.com', 'jivochat' ); ?>?utm_source=WordPress" target="_blank"><?php esc_html_e( 'Live Chat with Us', 'jivochat' ); ?></a>
				<a class="btn jivosite-base-button" href="https://<?php esc_html_e( 'www.jivochat.com', 'jivochat' ); ?>/help?utm_source=WordPress" target="_blank"><?php esc_html_e( 'Knowledge Base', 'jivochat' ); ?></a>
			</div>
		</div>
	</div>
</div>
