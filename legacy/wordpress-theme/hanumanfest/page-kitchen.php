<?php
/* Template Name: Страница кухни */

get_header(); ?>

<main class="page-content container">
  <?php
  if (have_posts()):
    while (have_posts()): the_post();?>
      <h1 class="text-brand-main mb-3"> <?php the_title();?> </h1>
      <? the_content();
    endwhile;
  endif;
  ?>
</main>
<section class="promo-grid">

  <div class="promo-card">
    <video src="/wp-content/uploads/2026/03/IMG_8652.mp4" autoplay muted loop controls playsinline preload="metadata"></video>
  </div>

  <div class="promo-card">
    <video src="/wp-content/uploads/2026/03/IMG_8223.mp4" muted loop controls playsinline preload="metadata"></video>
  </div>

  <div class="promo-card">
    <video src="/wp-content/uploads/2026/03/IMG_8224.mp4" muted loop controls playsinline preload="metadata"></video>
  </div>

  <div class="promo-card">
    <video src="/wp-content/uploads/2026/03/IMG_8222.mp4" muted loop controls playsinline preload="metadata"></video>
  </div>

</section>
<?php get_footer(); ?>