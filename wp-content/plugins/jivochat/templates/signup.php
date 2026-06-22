<?php
/**
 * Render signup page to enter to create new account in jivochat system
 *
 * @package WordPress_Online_Widget_JivoChat
 */

?>

<div id="signup_block" class="container jivosite-big" 
<?php
if ( isset($login) &&! empty( esc_html( $login ) ) ) {
	?>
	style="display: none" <?php } ?>>
	<div class="row">
		<div class="col align-self-start">
		</div>
		<div class="col-8 align-self-center">
			<form id="signup_form" method="POST" class="jivosite-content">
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
						<h5 class="jivosite-form-caption"><?php esc_html_e( 'Create a JivoChat account', 'jivochat' ); ?></h5>
						<h5 class="jivosite-form-caption"><?php esc_html_e( 'to connect with your website', 'jivochat' ); ?></h5>
					</div>
					<div class="col-2 jivosite-form-second-col"></div>
				</div>
				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-form-first-col">
						<label for="email_signup"><?php esc_html_e( 'Your Business E-mail Address', 'jivochat' ); ?></label>
						<input type="email" class="form-control" id="email_signup" name="email" required>
					</div>
					<div class="col-2 jivosite-form-second-col">
						<div class="jivosite-help-container">
							<span id="help_email_signup" class="jivosite-help">
								<img src="<?php echo esc_html( JIVOSITE_PLUGIN_URL ); ?>img/question.svg"
									class="jivosite-question-svg"
									data-toggle="tooltip"
									data-bs-placement="right"
									data-bs-custom-class="jivosite-tooltip"
									title="<?php esc_html_e( 'Please specify the email you will use to login to the agent’s app and admin panel.', 'jivochat' ); ?>"
									data-trigger="hover focus click"
									data-template='<div class="tooltip" role="tooltip"><div class="tooltip-inner"></div></div>'
								/>
							</span>
						</div>
					</div>
				</div>
				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-form-first-col">
						<label for="password_signup"><?php esc_html_e( 'Password', 'jivochat' ); ?></label>
						<input type="password" class="form-control" id="password_signup" name="userPassword" required pattern="(?=.*\d)(?=.*[A-Z]).{6,}">
					</div>
					<div class="col-2 jivosite-form-second-col">
						<div class="jivosite-help-container">
							<span id="help_password_signup" class="jivosite-help">
								<img src="<?php echo esc_html( JIVOSITE_PLUGIN_URL ); ?>img/question.svg"
									class="jivosite-question-svg"
									data-toggle="tooltip"
									data-bs-placement="right"
									data-bs-custom-class="jivosite-tooltip"
									title="<?php esc_html_e( 'Please create a new JivoChat account password. Password is least 6 characters long, contains at least one digit and uppercase letter.', 'jivochat' ); ?>"
									data-trigger="hover focus click"
									data-template='<div class="tooltip" role="tooltip"><div class="tooltip-inner"></div></div>'
								/>
							</span>
						</div>
					</div>
				</div>
				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-form-first-col">
						<label for="name_signup"><?php esc_html_e( 'Agent Name', 'jivochat' ); ?></label>
						<input type="text" class="form-control" id="name_signup" name="userDisplayName" required>
					</div>
					<div class="col-2 jivosite-form-second-col">
						<div class="jivosite-help-container">
							<span id="help_name_signup" class="jivosite-help">
								<img src="<?php echo esc_html( JIVOSITE_PLUGIN_URL ); ?>img/question.svg"
									class="jivosite-question-svg"
									data-toggle="tooltip"
									data-bs-placement="right"
									data-bs-custom-class="jivosite-tooltip"
									title="<?php esc_html_e( 'The agent name that will be displayed to website visitors in the JivoChat chat window.', 'jivochat' ); ?>"
									data-trigger="hover focus click"
									data-template='<div class="tooltip" role="tooltip"><div class="tooltip-inner"></div></div>'
								/>
							</span>
						</div>
					</div>
				</div>
				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-form-first-col">
						<label for="language_list"><?php esc_html_e( 'Widget Language', 'jivochat' ); ?></label>
						<select id="languageList" class="form-control" name="languageList" required></select>
					</div>
					<div class="col-2 jivosite-form-second-col">
						<div class="jivosite-help-container">
							<span class="jivosite-help">
								<img id="help_language_signup" src="<?php echo esc_html( JIVOSITE_PLUGIN_URL ); ?>img/question.svg"
									class="jivosite-question-svg"
									data-toggle="tooltip"
									data-bs-placement="right"
									data-bs-html="true"
									data-bs-custom-class="jivosite-tooltip"
									title='<div>
									<?php
									wp_kses(
										_e( 'You can choose a language to have it in your JivoChat widget on your website. Suggested language for your JivoChat widget is: <b id="suggested_language"></b><div>If you want to change it, please select an alternative at dropdown list.</div>', 'jivochat' ), // phpcs:ignore
										array(
											'b'   => array(
												'id' => array(),
											),
											'div' => array(),
										)
									);
									?>
									</div>'
									data-trigger="hover focus click"
									data-template='<div class="tooltip" role="tooltip"><div class="tooltip-inner"></div></div>'
								/>
							</span>
						</div>
					</div>
				</div>

				<?php wp_nonce_field( 'jivosite_signup_form', 'jivosite-signup-nonce' ); ?>

				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-form-first-col">
						<button id="signup_button" type="submit" class="form-control btn jivosite-base-button"><?php esc_html_e( 'Sign Up', 'jivochat' ); ?></button>
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
					<div class="col-10 jivosite-form-first-col">
						<div class="jivosite-caption">
						<?php
						wp_kses(
							_e( 'By creating an account you agree to <a class="jivosite-caption" href="https://www.jivochat.com/terms?utm_source=wordpress" target="_blank">Terms and Conditions</a> and <a class="jivosite-caption" href="https://www.jivochat.com/files/privacy_policy.pdf?utm_source=wordpress" target="_blank">Privacy Policy</a>', 'jivochat' ), // phpcs:ignore
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
					</div>
					<div class="col-2 jivosite-form-second-col"></div>
				</div>
				<div class="row jivosite-form-margin">
					<div class="col-10 jivosite-form-first-col">
						<div class="jivosite-center jivosite-form-bottom-link">
							<?php
							wp_kses(
								_e( 'Already have an account? <span id="login_link" class="jivosite-redirect">Log in</span>', 'jivochat' ), // phpcs:ignore
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
