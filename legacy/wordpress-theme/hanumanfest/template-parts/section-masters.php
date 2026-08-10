<div class="masters-grid">
  <?php
  $masters = new WP_Query([
    'post_type' => 'master',
    'posts_per_page' => -1
  ]);

  if ($masters->have_posts()):
    while ($masters->have_posts()): $masters->the_post();
      $photo_full = get_field('photo_full'); // кастомное поле для модалки
  ?>
      <div class="master-card" data-master-id="<?php the_ID(); ?>">
        <div class="master-thumb">
          <?php if (has_post_thumbnail()): ?>
            <img src="<?php the_post_thumbnail_url('medium'); ?>" alt="<?php the_title(); ?>">
          <?php endif; ?>
        </div>
        <h3 class="master-name text-brand-accent"><?php the_title(); ?></h3>

        <!-- скрытые данные для модалки -->
        <div class="master-modal-data" style="display:none;">
          <div class="master-content"><?php the_content(); ?></div>
        </div>
      </div>
  <?php
    endwhile;
    wp_reset_postdata();
  endif;
  ?>
</div>