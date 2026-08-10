<?php get_header(); ?>

<main class="site-main">
  <?php
  if (have_posts()) :
    while (have_posts()) : the_post();
      ?>
      <article <?php post_class(); ?>>
        <h1 class="entry-title"><?php the_title(); ?></h1>
        <div class="entry-content">
          <?php the_content(); ?>
        </div>
      </article>
      <?php
    endwhile;
  endif;
  ?>
</main>

<?php get_footer(); ?>