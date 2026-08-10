<?php
$footer_bg = get_theme_mod('footer_background');
$contacts_raw = get_theme_mod('footer_contacts'); // строка со всеми контактами

// массив соцсетей и их иконки
$socials_icons = [
    'vk.com' => 'vk',
    'facebook.com' => 'fb',
    'instagram.com' => 'inst',
    't.me' => 'tg',
    'wa.me' => 'wa'
];

// разбиваем контакты по переносу строки (учитываем \r\n и \n)
$contacts_items = preg_split('/\r\n|\r|\n/', trim($contacts_raw));
?>

<footer class="site-footer" style="background-image: url('<?php echo esc_url($footer_bg); ?>');">
  <div class="footer-overlay"></div>
  <div class="footer-container">

    <div class="footer-right">
      <nav class="footer-menu">
        <?php
        wp_nav_menu([
            'theme_location' => 'footer_menu',
            'container' => false,
            'menu_class' => 'footer-menu-items',
            'depth' => 1
        ]);
        ?>
      </nav>

      <div class="footer-contacts" id="contacts">
        <?php foreach ($contacts_items as $item):
          $item = trim($item);
          if (!$item) continue;

          if (filter_var($item, FILTER_VALIDATE_EMAIL)) {
              echo '<a href="mailto:' . esc_attr($item) . '">' . esc_html($item) . '</a>';
          }
          elseif(preg_match('/\+?\d[\d\(\)\-\s]+$/', $item)) {
              $tel = preg_replace('/\D+/', '', $item);
              echo '<a href="tel:'.$tel.'">'.esc_html($item).'</a>';
          }
        endforeach; ?>
      </div>

      <div class="footer-socials">
        <?php foreach ($contacts_items as $item):
          foreach($socials_icons as $domain => $name){
            if(strpos($item, $domain)!==false){
              echo '<a href="'.esc_url($item).'" class="footer-social footer-'.$name.'" target="_blank" rel="noopener">';
              echo '<img src="'.get_template_directory_uri().'/assets/icons/'.$name.'.svg" alt="'.esc_attr(strtoupper($name)).'">';
              echo '</a>';
            }
          }
        endforeach; ?>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <p>© <?php echo date('Y'); ?> <?php bloginfo('name'); ?></p>
    <p><?php echo nl2br(esc_html(get_theme_mod('footer_company_info'))); ?></p>
  </div>
</footer>

<?php wp_footer(); ?>
<script>
    // кнопка вверх
    const scrollUpBtn = document.getElementById("scroll-up");
    const header = document.getElementById("header");

    window.addEventListener("scroll", () => {
        scrollUpBtn.classList.toggle("show", window.scrollY > 300);
        header.classList.toggle("header-background--white", window.scrollY > 100);
        header.classList.toggle("fixed-top", window.scrollY > 500);
    });
</script>
</body>
</html>