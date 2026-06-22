<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="color-scheme" content="light only">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $logo_id = get_theme_mod('custom_logo');
    if ($logo_id) {
        $logo_url = wp_get_attachment_image_src($logo_id, 'full')[0];
        echo '<meta property="og:image" content="' . esc_url($logo_url) . '">';
    }
    ?>
    <?php wp_head(); ?>
	<script src="//code.jivo.ru/widget/qNhdVN9jC1" async></script>
</head>
<body>

<header id="header">
    <nav class="container d-flex align-items-center justify-content-between py-2">
        <!-- Логотип -->
        <div class="nav-logo">
            <a href="/">      <?php
            if (function_exists('the_custom_logo') && has_custom_logo()) {
                the_custom_logo(); // логотип из админки
            } else {
                echo '<span class="site-title">' . get_bloginfo('name') . '</span>';
            }
            ?></a>
        </div>
        <?php
$menu = wp_get_nav_menu_object(7);
$menu_items = wp_get_nav_menu_items($menu->term_id);

?>
    <?php foreach ($menu_items as $item): ?>
        <a href="<?php echo esc_url($item->url); ?>" class="nav-link d-none d-md-block"><?php echo esc_html($item->title); ?></a>
    <?php endforeach; ?>
<!-- Гамбургер -->
<button class="navbar-toggler d-md-none ms-auto" type="button"
        data-bs-toggle="collapse" data-bs-target="#mobileMenu"
        aria-controls="mobileMenu" aria-expanded="false" aria-label="Переключить меню">
    <span class="hamburger">
        <span></span>
        <span></span>
        <span></span>
    </span>
</button>

</nav>

<!-- Подложка -->
<div class="mobile-menu-overlay"></div>

<!-- Мобильное меню -->
<div class="collapse mobile-menu p-4 d-md-none" id="mobileMenu">
    <?php
    wp_nav_menu([
        'theme_location' => 'mobile_menu',
        'container'      => false,
        'menu_class'     => 'nav flex-column gap-3 mobile-menu-list',
        'fallback_cb'    => false,
        'link_before'    => '<span>',
        'link_after'     => '</span>',
    ]);
    ?>
</div>
</header>