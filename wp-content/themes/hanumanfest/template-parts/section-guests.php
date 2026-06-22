<div class="guests-grid">
  <?php
  $guests = new WP_Query([
      'post_type' => 'special_guest',
      'posts_per_page' => -1
  ]);

  if ($guests->have_posts()):
    while ($guests->have_posts()): $guests->the_post(); ?>
      <div class="guest-card">
        <h3 class="guest-title text-brand-accent"><?php the_title(); ?></h3>
        <div class="guest-image">
          <?php if (has_post_thumbnail()): ?>
            <img src="<?php the_post_thumbnail_url('large'); ?>" alt="<?php the_title(); ?>">
          <?php endif; ?>
          <div class="guest-overlay">
            <p><?php the_excerpt(); ?></p>
            <br />
              <button class="hf-guest-readmore btn btn-outline-light btn-sm">
                Подробнее
              </button>
          </div>
        </div>
                <!-- скрытые данные для модалки -->
        <div class="guest-modal-data" style="display:none;">
          <div class="guest-content"><?php the_content(); ?></div>
        </div>
      </div>
    <?php endwhile;
    wp_reset_postdata();
  else: ?>
    <p class="text-center text-muted">Гости пока не добавлены.</p>
  <?php endif; ?>
</div>