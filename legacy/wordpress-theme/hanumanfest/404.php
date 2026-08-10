<?php get_header(); ?>

<main class="error-404 not-found" style="text-align:center; padding:80px 20px;">
  <h1 class="py-5">Страница не найдена</h1>
  <p>Похоже, здесь раньше что-то было, или вы попали не туда.</p>
  <a href="<?php echo home_url(); ?>" class="button" style="display:inline-block; margin-top:20px; padding:10px 20px; background:#222; color:#fff; border-radius:4px; text-decoration:none;">На главную</a>
</main>

<?php get_footer(); ?>