<?php
/**
 * Render main plugin settings page
 *
 * @package WordPress_Online_Widget_JivoChat
 */

?>

<?php if ( ! get_option( 'jivosite_widget_id' ) ) { ?>
	<?php require 'signup.php'; ?>
	<?php require 'login.php'; ?>
<?php } elseif ( get_option( 'jivosite_widget_id' ) && ! empty( $post_install ) ) { ?>
	<?php require 'post-install.php'; ?>
<?php } elseif ( get_option( 'jivosite_widget_id' ) && empty( $post_install ) ) { ?>
	<?php require 'reset.php'; ?>
<?php } ?>

<script type="text/javascript">
	(function($) {
		$(function () {
			$('[data-toggle="tooltip"]').tooltip();

			$('#signup_form').submit(function () {
				$('#signup_button').attr('disabled', true);
			});
			$('#login_form').submit(function () {
				$('#login_button').attr('disabled', true);
			});
			$('#reset_form').submit(function () {
				$('#reset_button').attr('disabled', true);
			});

			$('#login_link').click(
				function () {
					$('#login_block').css('display', 'block');
					$('#signup_block').css('display', 'none');
				}
			);
			$('#signup_link').click(
				function () {
					$('#login_block').css('display', 'none');
					$('#signup_block').css('display', 'block');
					$(window).scrollTop(0);
				}
			);

			const languageList = <?php echo wp_json_encode( $language_list ); ?>;
			const suggestedLanguageName = languageList[0].name;
			languageList.forEach(function (language) {
				let lang = language.code;
				if (language.name == 'Spanish (General)') {
					lang = lang.split(',');
					lang = lang[0];
				}
				const $option = $('<option>').attr('value', lang).attr('class', 'dropdown-item').text(language.name);
				$('#languageList').append($option);
			});

			$('#help_language_signup').click(function () {
				$('#suggested_language').text(suggestedLanguageName);
			});
			$('#help_language_signup').hover(function () {
				$('#suggested_language').text(suggestedLanguageName);
			});
		});
	})(jQuery);
</script>
