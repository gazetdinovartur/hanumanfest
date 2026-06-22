<?php
/**
 * Render login page to enter to exist account in jivochat system
 *
 * @package WordPress_Online_Widget_JivoChat
 */

?>

<div id="login_block" class="container jivosite-small"
	<?php
	if ( isset($login) && ! empty( esc_html( $login ) ) ) {
		?>
		style="display: block"
		<?php
	} else {
		?>
		style="display: none" <?php } ?>
>
	<div class="row">
		<div class="col align-self-start">
		</div>
		<div class="col-8 align-self-center">
			<form id="login_form" method="POST" class="jivosite-content">
				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-form-first-col">
						<h1 class="jivosite-center">
							<?php require 'jivo-logo.php'; ?>
						</h1>
					</div>
					<div class="col-2 jivosite-form-second-col"></div>
				</div>
				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-form-first-col">
						<h5 class="jivosite-form-caption"><?php esc_html_e( 'Login to your JivoChat account', 'jivochat' ); ?></h5>
						<h5 class="jivosite-form-caption"><?php esc_html_e( 'to connect it to your store', 'jivochat' ); ?></h5>
					</div>
					<div class="col-2 jivosite-form-second-col"></div>
				</div>
				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-form-first-col">
						<label for="email_login"><?php esc_html_e( 'Your Jivo E-mail Address', 'jivochat' ); ?></label>
						<input type="email" class="form-control" id="email_login" name="email" required>
					</div>
					<div class="col-2 jivosite-form-second-col">
						<div class="jivosite-help-container">
							<span id="help_email_signup" class="jivosite-help">
								<img src="<?php echo esc_html( JIVOSITE_PLUGIN_URL ); ?>img/question.svg"
									class="jivosite-question-svg"
									data-toggle="tooltip"
									data-bs-placement="right"
									data-bs-custom-class="jivosite-tooltip"
									title="<?php esc_html_e( 'Please enter the e-mail address you use for login.', 'jivochat' ); ?>"
									data-trigger="hover focus click"
									data-template='<div class="tooltip" role="tooltip"><div class="tooltip-inner"></div></div>'
								/>
							</span>
						</div>
					</div>
				</div>
				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-form-first-col">
						<label for="password_login"><?php esc_html_e( 'Password', 'jivochat' ); ?></label>
						<input type="password" class="form-control" id="password_login" name="userPassword" required pattern="(?=.*\d)(?=.*[A-Z]).{6,}">
					</div>
					<div class="col-2 jivosite-form-second-col">
						<div class="jivosite-help-container">
							<span id="help_password_signup" class="jivosite-help">
								<img src="<?php echo esc_html( JIVOSITE_PLUGIN_URL ); ?>img/question.svg"
									class="jivosite-question-svg"
									data-toggle="tooltip"
									data-bs-placement="right"
									data-bs-custom-class="jivosite-tooltip"
									title="<?php esc_html_e( 'Please enter the password for your JivoChat account.', 'jivochat' ); ?>"
									data-trigger="hover focus click"
									data-template='<div class="tooltip" role="tooltip"><div class="tooltip-inner"></div></div>'
								/>
							</span>
						</div>
					</div>
				</div>

				<?php wp_nonce_field( 'jivosite_login_form', 'jivosite-login-nonce' ); ?>

				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-form-first-col">
						<button id="login_button" type="submit" class="form-control btn jivosite-base-button"><?php esc_html_e( 'Login', 'jivochat' ); ?></button>
					</div>
					<div class="col-2 jivosite-form-second-col"></div>
				</div>
				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-center jivosite-form-first-col">
						<b style="color:red;"><?php echo esc_html( isset($error) ? $error : 'no_error' ); ?></b>
					</div>
					<div class="col-2 jivosite-form-second-col"></div>
				</div>
				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-center jivosite-form-first-col">
						<?php
						wp_kses(
							_e( 'Forgot your password? You can reset it <a class="jivosite-redirect" href="https://app.jivosite.com/password/reset?utm_source=wordpress" target="_blank">here</a>', 'jivochat' ), // phpcs:ignore
							array(
								'a' => array(
									'class'  => array(),
									'href'   => array(),
									'target' => array(),
								),
							)
						);
						?>
					</div>
					<div class="col-2 jivosite-form-second-col"></div>
				</div>
				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-form-first-col">
						<div  class="jivosite-center jivosite-form-bottom-link">
							<?php
							wp_kses(
								_e( 'You don\'t have a Jivo account? <span id="signup_link" class="jivosite-redirect">Sign up</span>', 'jivochat' ), // phpcs:ignore
								array(
									'span' => array(
										'id'    => array(),
										'class' => array(),
									),
								)
							);
							?>
						</div>
					</div>
					<div class="col-2 jivosite-form-second-col"></div>
				</div>
			</form>
		</div>
	</div>
</div>
