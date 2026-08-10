<?php
/* Template Name: Gutenberg Page */

get_header(); ?>

<main class="page-content container">
  <?php
  if (have_posts()):
    while (have_posts()): the_post();?>
      <h1 class="text-brand-main mb-5"> <?php the_title();?> </h1>
      <? the_content();
    endwhile;
  endif;
  ?>
</main>

<?php get_footer(); ?>