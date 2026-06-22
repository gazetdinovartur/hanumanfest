<?php get_header('inner'); ?>

<main class="site-main">
  <?php
  if (have_posts()) :
    while (have_posts()) : the_post();
      ?>
      <article <?php post_class(); ?>>
        <div class="entry-content">
          <h1 class="entry-title mb-5"><?php the_title(); ?></h1>
          <?php the_content(); ?>
        </div>
      </article>
      <?php
    endwhile;
  endif;
  ?>
</main>

<?php get_footer(); ?>