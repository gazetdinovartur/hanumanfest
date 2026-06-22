<?php
$gallery = get_field('hf_gallery');
if ($gallery):
  $gallery = preg_replace(['#<p[^>]*>#', '#</p>#', '#<br\s*/?>#', '/\s+/'], '', $gallery);
  preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $gallery, $matches);

?>
<div class="hf-gallery-carousel">
  <button class="hf-gallery-arrow hf-gallery-prev" aria-label="Предыдущее">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="15 18 9 12 15 6"></polyline>
    </svg>
  </button>

  <div class="hf-gallery-track">
    <?php foreach ($matches[1] as $src):
      $src = trim($src);
      if (!$src) continue;

      $full_src = preg_replace('/-\d+x\d+(?=\.\w+$)/', '', $src)
    ?>
      <div class="hf-gallery-item">
        <img src="<?php echo esc_url($full_src); ?>" alt="">
      </div>
    <?php endforeach; ?>
  </div>

  <button class="hf-gallery-arrow hf-gallery-next" aria-label="Следующее">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="9 18 15 12 9 6"></polyline>
    </svg>
  </button>
</div>
<?php endif; ?>