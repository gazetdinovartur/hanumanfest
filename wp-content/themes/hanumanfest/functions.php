<?php
// === Подключаем стили и скрипты ===
function hanumanfest_cleanup_wp_styles() {
    // Отключаем лишние системные стили WordPress
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('global-styles');
    wp_dequeue_style('classic-theme-styles');
    wp_dequeue_style('dashicons');
}
add_action('wp_enqueue_scripts', 'hanumanfest_cleanup_wp_styles', 5);


function hanumanfest_enqueue_assets() {
    $theme_dir = get_template_directory_uri();
    $theme_path = get_template_directory();

    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';

    // Bootstrap CSS
    wp_enqueue_style(
        'bootstrap-css',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        [],
        '5.3.3'
    );

    // Шрифт Montserrat
    wp_enqueue_style(
        'hanumanfest-fonts',
        'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap',
        [],
        null
    );

    wp_enqueue_style(
        'lightbox-style',
        get_stylesheet_directory_uri() . '/assets/hf-lightbox.css',
        [],
        filemtime(get_stylesheet_directory() . '/assets/hf-lightbox.css'),
        'all'
    );

    // Основной стиль темы — грузим последним, чтобы всё переопределял
    wp_enqueue_style(
        'hanumanfest-style',
        get_stylesheet_uri(),
        ['bootstrap-css', 'hanumanfest-fonts'],
        filemtime(get_stylesheet_directory() . '/style.css')
    );
    // Bootstrap JS (в футере)
    wp_enqueue_script(
        'bootstrap-js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        ['jquery'],
        '5.3.3',
        true
    );

    wp_enqueue_script(
        'hf-gallery',
        $theme_dir . '/assets/js/hf-gallery.js',
        [],
        filemtime($theme_path . '/assets/js/hf-gallery.js'),
        true
    );

    wp_enqueue_script(
        'hf-lightbox',
        $theme_dir . '/assets/js/hf-lightbox.js',
        [],
        filemtime($theme_path . '/assets/js/hf-lightbox.js'),
        true
    );

    wp_enqueue_script(
        'script',
        $theme_dir . '/assets/js/script.js',
        ['jquery'],
        filemtime($theme_path . '/assets/js/script.js'),
        true
    );

    wp_enqueue_script(
        'payment',
        $theme_dir . '/assets/js/payment.js',
        ['jquery'],
        filemtime($theme_path . '/assets/js/payment.js'),
        true
    );

    wp_localize_script('payment', 'ykData', [
        'restUrl' => esc_url_raw(rest_url('yk/v1/')),
        'nonce'   => wp_create_nonce('wp_rest')
    ]);
}
add_action('wp_enqueue_scripts', 'hanumanfest_enqueue_assets', 20);


// === Регистрируем меню ===
function hanumanfest_register_menus() {
    register_nav_menus([
        'header_menu' => __('Меню в шапке', 'hanumanfest'),
        'footer_menu' => __('Меню в подвале', 'hanumanfest'),
        'mobile_menu' => __('Мобильное меню', 'hanumanfest'),
    ]);
}
add_action('after_setup_theme', 'hanumanfest_register_menus');


// === Поддержка базовых возможностей темы ===
function hanumanfest_theme_support() {
    add_theme_support('title-tag');        // Автоматический <title>
    add_theme_support('post-thumbnails');  // Миниатюры постов
    add_theme_support('custom-logo');      // Кастомный логотип
}
add_action('after_setup_theme', 'hanumanfest_theme_support');

// Регистрируем CPT'ы: masters, program, info_block, review, faq, special guests
function hanumanfest_register_post_types() {

    // --- Masters / Practitioners ---
    $labels = [
        'name' => 'Мастера и практики',
        'singular_name' => 'Мастер',
        'add_new_item' => 'Добавить мастера',
        'edit_item' => 'Редактировать мастера',
        'menu_name' => 'Мастера и практики'
    ];
    register_post_type('master', [
        'labels' => $labels,
        'public' => true,
        'has_archive' => false,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-groups',
        'supports' => ['title','editor','excerpt','thumbnail','revisions'],
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'masters'],
    ]);

    // --- Program (карточки программы) ---
    $labels = [
        'name' => 'Программа фестиваля',
        'singular_name' => 'Программа',
        'add_new_item' => 'Добавить новый день',
        'menu_name' => 'Программа'
    ];
    register_post_type('program', [
        'labels' => $labels,
        'public' => true,
        'has_archive' => false,
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => ['title','editor','excerpt','thumbnail','revisions'],
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'program'],
    ]);

    // --- Info Blocks (проживание, питание, трансфер) ---
    $labels = [
        'name' => 'Инфоблоки',
        'singular_name' => 'Инфоблок',
        'add_new_item' => 'Добавить инфоблок',
        'menu_name' => 'Инфоблоки'
    ];
    register_post_type('info_block', [
        'labels' => $labels,
        'public' => false, // можно выводить только вручную через шаблон, или оставить public=true если нужно архивирование
        'show_ui' => true,
        'menu_icon' => 'dashicons-info',
        'supports' => ['title','editor','thumbnail','revisions'],
        'show_in_rest' => true,
    ]);

    // --- Reviews ---
    $labels = [
        'name' => 'Отзывы',
        'singular_name' => 'Отзыв',
        'add_new_item' => 'Добавить отзыв',
        'menu_name' => 'Отзывы'
    ];
    register_post_type('review', [
        'labels' => $labels,
        'public' => true,
        'has_archive' => false,
        'menu_icon' => 'dashicons-testimonial',
        'supports' => ['title','editor','thumbnail','custom-fields'],
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'reviews'],
    ]);

    // --- FAQ ---
    $labels = [
        'name' => 'FAQ',
        'singular_name' => 'Вопрос',
        'add_new_item' => 'Добавить вопрос',
        'menu_name' => 'FAQ'
    ];
    register_post_type('faq', [
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-editor-help',
        'supports' => ['title','editor'],
        'show_in_rest' => true,
    ]);

    $labels = [
        'name'               => 'Специальные гости',
        'singular_name'      => 'Гость',
        'add_new_item'       => 'Добавить нового гостя',
        'menu_name'          => 'Специальные гости',
    ];

    register_post_type('special_guest', [
        'labels'             => $labels,
        'public'             => true,
        'menu_icon'          => 'dashicons-star-filled',
        'has_archive'        => false,
        'supports'           => ['title', 'editor', 'thumbnail'],
        'rewrite'            => ['slug' => 'guests'],
        'show_in_rest'       => true, // Gutenberg
    ]);

    // --- Приглашённые музыканты ---
    $labels = [
        'name' => 'musicians',
        'singular_name' => 'Музыкант',
        'add_new_item' => 'Добавить музыканта',
        'menu_name' => 'Приглашённые музыканты'
    ];
    register_post_type('musicians', [
        'labels' => $labels,
        'public' => true,
        'show_ui' => true,
        'menu_icon' => 'dashicons-microphone',
        'supports' => ['title','editor','thumbnail'],
        'show_in_rest' => true,
    ]);
}

add_action('init', 'hanumanfest_register_post_types');

add_filter('use_block_editor_for_post', function($use_block_editor, $post) {
    // Получаем ID страницы, установленной как главная
    $front_page_id = (int) get_option('page_on_front');

    // Если текущий пост — главная страница, отключаем Gutenberg
    if ($post->ID === $front_page_id) {
        return false;
    }

    return $use_block_editor;
}, 10, 2);

function theme_customize_register($wp_customize) {
  $wp_customize->add_section('footer_settings', [
    'title'    => 'Футер',
    'priority' => 130,
  ]);

  // Настройка фонового изображения футера
  $wp_customize->add_setting('footer_background', [
    'default'           => '',
    'sanitize_callback' => 'esc_url_raw',
  ]);

  $wp_customize->add_control(
    new WP_Customize_Image_Control(
      $wp_customize,
      'footer_background',
      [
        'label'    => 'Фоновое изображение футера',
        'section'  => 'footer_settings',
        'settings' => 'footer_background',
      ]
    )
  );

  // Настройка информации об ИП
  $wp_customize->add_setting('footer_company_info', [
    'default'           => '',
    'sanitize_callback' => 'wp_kses_post', // допускает переносы и базовые теги
  ]);

  $wp_customize->add_control('footer_company_info', [
    'label'   => 'Информация об ИП',
    'section' => 'footer_settings',
    'type'    => 'textarea', // многострочное поле
  ]);

    $wp_customize->add_setting('footer_contacts', [
    'default'           => '',
    'sanitize_callback' => 'wp_kses_post', // допускает переносы и базовые теги
  ]);

  $wp_customize->add_control('footer_contacts', [
    'label'   => 'Контакты',
    'section' => 'footer_settings',
    'type'    => 'textarea', // многострочное поле
  ]);
}
add_action('customize_register', 'theme_customize_register');