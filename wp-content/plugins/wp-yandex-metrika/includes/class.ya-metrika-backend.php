<?php

class YaMetrikaBackend
{
    protected static $instance;

    const BRAND_TYPE_TAXONOMY = 'taxonomy';
    const BRAND_TYPE_CUSTOM_FIELD = 'custom_field';

    private function __construct()
    {
        add_action('admin_menu', [$this, 'createAdminPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'registerAssets']);
        add_action('admin_head', [$this, 'addLibraries']);
        add_action('wp_head', [$this, 'addFrontendMeta']);
    }

    public function registerAssets()
    {
        wp_enqueue_style(YAM_SLUG, plugins_url('/assets/admin.min.css', YAM_FILE), false, YAM_VER);
        wp_enqueue_style(YAM_SLUG, plugins_url('/assets/fonts/fonts.min.css', YAM_FILE), false, YAM_VER);
        wp_enqueue_script(YAM_SLUG, plugins_url('/assets/admin.min.js', YAM_FILE), ['jquery'], YAM_VER);
    }

    public function createAdminPage()
    {
        add_options_page(
            __('Yandex.Metrica settings', 'wp-yandex-metrika'),
            __('Yandex.Metrica', 'wp-yandex-metrika'),
            'edit_theme_options',
            YAM_PAGE_SLUG,
            $this->getPageHtmlFunction('index')
        );
    }

    public function addLibraries()
    {
        echo '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
        echo '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>';
        echo '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/ru.js"></script>';
    }

    public function addFrontendMeta()
    {
        echo '<meta name="verification" content="f612c7d25f5690ad41496fcfdbf8d1" />';
    }

    public function registerSettings()
    {
        add_settings_section('default', 'counters', null, YAM_PAGE_SLUG);
        add_settings_section('ec', 'brand-settings' /*__('Электронная коммерция', 'wp-yandex-metrika')*/, null, YAM_PAGE_SLUG);
        add_settings_section('ymct', 'custom-targets', null, YAM_PAGE_SLUG);

        register_setting(YAM_PAGE_SLUG, YAM_OPTIONS_SLUG, [
            'sanitize_callback' => [$this, 'sanitizeOptions']
        ]);

        add_settings_field(
            'yam_counters',
            __('Tags', 'wp-yandex-metrika'),
            [$this, 'displayRepeaterField'],
            YAM_PAGE_SLUG,
            'default',
            array(
                "name" => 'counters',
                "fields" => [
                    "number" => [
                        'callback' => [$this, 'displayTextField'],
                        'name' => 'number',
                        'default' => '',
                        'attrs' => [
                            'type' => 'text',
                            'data-input-type' => 'number',
                            'placeholder' => __('Tag number', 'wp-yandex-metrika')
                        ]
                    ],
                    "webvisor" => [
                        'callback' => [$this, 'displayCheckboxField'],
                        'name' => 'webvisor',
                        'default' => true,
                        'label' => __('Session Replay', 'wp-yandex-metrika'),
                        'attrs' => [
                            'type' => 'checkbox'
                        ]
                    ]
                ]
            )
        );

        add_settings_field(
            'yam_extra_settings',
            '',
            [$this, 'displayShowButton'],
            YAM_PAGE_SLUG,
            'default',
            array(
                'name' => 'extra_settings',
                'title' => __('Extra settings', 'wp-yandex-metrika'),
                'class' => 'extra-settings',
                'selectors' => array(
                    '.brand-settings-table', '.custom-targets-table'
                )
            )
        );

        add_settings_field(
            'yam_brand_title_slug',
            __('Brands properties', 'wp-yandex-metrika'),
            [$this, 'displaySectionDescription'],
            YAM_PAGE_SLUG,
            'ec',
            array(
                'name' => 'brand_title_slug',
                'description' => __('You can send information about the brands of your online store to Yandex.Metrica. Based on this data, Yandex.Metrica generates a report that helps, for example, to determine the most popular or profitable brand. <a href="https://yandex.com/support/metrica/ecommerce/wordpress.html#wordpress__set" target="_blank">More</a>', 'wp-yandex-metrika'),
//                'description' => __('В Метрику можно передавать информацию о брендах вашего интернет-магазина. На основе этих данных в Метрике формируется отчет, который помогает, например, определить наиболее популярный или прибыльный бренд. <a href="https://yandex.ru/support/metrica/ecommerce/wordpress.html#wordpress__set" target="_blank">Подробнее</a>', 'wp-yandex-metrika'),
                'class' => 'targets-header',
                'attrs' => [
                    'type' => 'title',
                ]
            )
        );

        add_settings_field(
            'yam_brand',
            '',
            [$this, 'displayMultiRowField'],
            YAM_PAGE_SLUG,
            'ec',
            array(
                'name' => 'brand',
                'class' => 'brand',
                "fields" => [
                    "brand_type" => [
                        'callback' => [$this, 'displaySelect2Field'],
                        'name' => 'brand_type',
                        'title' => 'brand_type',
                        'class' => 'brand-select no-search-select',
                        'label' => __('Brand type', 'wp-yandex-metrika'),
                        'flex-wrap' => true,
                        'options' => [
                            __('Term', 'wp-yandex-metrika') => [
                                    'value' => self::BRAND_TYPE_TAXONOMY
                            ],
                            __('Custom field', 'wp-yandex-metrika') => [
                                    'value' => self::BRAND_TYPE_CUSTOM_FIELD
                            ]
                        ]
                    ],
                    "brand_slug" => [
                        'callback' => [$this, 'displayTextField'],
                        'name' => 'brand_slug',
                        'class' => 'brand-input',
                        'default' => '',
                        'label' => __('Taxonomy or custom field of brand', 'wp-yandex-metrika'),
                        'flex-wrap' => true,
                        'placeholder' => 'example-brand',
                        'description' => __('*To transfer the product\'s trademark to the "brand" field, specify the name of the field that is used on your site.', 'wp-yandex-metrika'),
                        'attrs' => [
                            'type' => 'text',
                            'class' => ''
                        ]
                    ]
                ]
            )
        );

        add_settings_field(
            'yam_targets_title_slug',
            __('Targets settings', 'wp-yandex-metrika'),
//            __('Настройка целей', 'wp-yandex-metrika'),
            [$this, 'displaySectionDescription'],
            YAM_PAGE_SLUG,
            'ymct',
            array(
                'name' => 'targets_title_slug',
                'description' => __('Goals help track and analyze the meaningful actions of visitors on the site. <a href="https://yandex.com/support/metrica/general/auto-goals.html" target="_blank">List of goals</a>', 'wp-yandex-metrika'),
//                'description' => __('Цели помогают отследить и проанализировать значимые действия посетителей на сайте. <a href="https://yandex.ru/support/metrica/general/recommend-goals.html" target="_blank">Список целей</a>', 'wp-yandex-metrika'),
                'class' => 'targets-header',
                'attrs' => [
                    'type' => 'title',
                ]
            )
        );

        add_settings_field(
            'yam_custom_targets',
            '',
            [$this, 'displayRepeaterField'],
            YAM_PAGE_SLUG,
            'ymct',
            array(
                'name' => 'custom_targets',
                'class' => 'custom-targets',
                'check_for' => 'target',
                "fields" => [
                    "target" => [
                        'callback' => [$this, 'displaySelect2Field'],
                        'name' => 'target_type',
                        'title' => 'target_type',
                        'class' => 'targets-input',
                        'label' => __('Target', 'wp-yandex-metrika'),
//                        'label' => __('Цель', 'wp-yandex-metrika'),
                        'flex-wrap' => true,
                        'default' => __('Target parameter', 'wp-yandex-metrika'),
//                        'default' => __('Параметр цели', 'wp-yandex-metrika'),
                        'options' => [
                            __('Target parameter', 'wp-yandex-metrika') => [
                                'value' => '',
                            ],
                            __('ym-register', 'wp-yandex-metrika') => [
                                    'value' => 'ym-register',
                                    'description' => __('The visitor has registered on the site', 'wp-yandex-metrika'),
//                                    'description' => __('Посетитель зарегистрировался на сайте', 'wp-yandex-metrika'),
                            ],
                            __('ym-submit-contacts', 'wp-yandex-metrika') => [
                                    'value' => 'ym-submit-contacts',
                                    'description' => __('Left contact information', 'wp-yandex-metrika'),
//                                    'description' => __('Оставил контактную информацию', 'wp-yandex-metrika'),
                            ],
                            __('ym-confirm-contact', 'wp-yandex-metrika') => [
                                    'value' => 'ym-confirm-contact',
                                    'description'  => __('Confirmed contact information', 'wp-yandex-metrika'),
//                                    'description'  => __('Подтвердил контактную информацию', 'wp-yandex-metrika'),
                            ],
                            __('ym-login', 'wp-yandex-metrika') => [
                                    'value' => 'ym-login',
                                    'description'  => __('The visitor is logged in to the site', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель авторизовался на сайте', 'wp-yandex-metrika'),
                            ],
                            __('ym-subscribe', 'wp-yandex-metrika') => [
                                    'value' => 'ym-subscribe',
                                    'description'  => __('The visitor subscribed to the mailing list', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель подписался на рассылку', 'wp-yandex-metrika'),
                            ],
                            __('ym-open-leadform', 'wp-yandex-metrika') => [
                                    'value' => 'ym-open-leadform',
                                    'description'  => __('The visitor opened the application form', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель открыл форму заявки', 'wp-yandex-metrika'),
                            ],
                            __('ym-submit-leadform', 'wp-yandex-metrika') => [
                                    'value' => 'ym-submit-leadform',
                                    'description'  => __('The visitor filled out and sent the application', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель заполнил и отправил заявку', 'wp-yandex-metrika'),
                            ],
                            __('ym-open-chat', 'wp-yandex-metrika') => [
                                    'value' => 'ym-open-chat',
                                    'description'  => __('The visitor opened the chat', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель открыл чат', 'wp-yandex-metrika'),
                            ],
                            __('ym-send-message', 'wp-yandex-metrika') => [
                                    'value' => 'ym-send-message',
                                    'description'  => __('The visitor sent a message in the chat', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель отправил сообщение в чате', 'wp-yandex-metrika'),
                            ],
                            __('ym-add-to-wishlist', 'wp-yandex-metrika') => [
                                    'value' => 'ym-add-to-wishlist',
                                    'description'  => __('The visitor added the service to favorites', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель добавил услугу в избранное', 'wp-yandex-metrika'),
                            ],
                            __('ym-begin-checkout', 'wp-yandex-metrika') => [
                                    'value' => 'ym-begin-checkout',
                                    'description'  => __('The visitor started placing an order', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель начал оформление заказа', 'wp-yandex-metrika'),
                            ],
                            __('ym-add-payment-info', 'wp-yandex-metrika') => [
                                    'value' => 'ym-add-payment-info',
                                    'description'  => __('The visitor has chosen a payment method', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель выбрал способ оплаты', 'wp-yandex-metrika'),
                            ],
                            __('ym-purchase', 'wp-yandex-metrika') => [
                                    'value' => 'ym-purchase',
                                    'description'  => __('Successful payment', 'wp-yandex-metrika'),
//                                    'description'  => __('Успешная оплата', 'wp-yandex-metrika'),
                            ],
                            __('ym-agree-meeting', 'wp-yandex-metrika') => [
                                    'value' => 'ym-agree-meeting',
                                    'description'  => __('The visitor confirmed the meeting', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель подтвердил встречу', 'wp-yandex-metrika'),
                            ],
                            __('ym-add-to-cart', 'wp-yandex-metrika') => [
                                    'value' => 'ym-add-to-cart',
                                    'description'  => __('The visitor added the product to the cart', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель добавил товар в корзину', 'wp-yandex-metrika'),
                            ],
                            __('ym-begin-checkout', 'wp-yandex-metrika') => [
                                    'value' => 'ym-begin-checkout',
                                    'description'  => __('The visitor started placing an order', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель начал оформление заказа', 'wp-yandex-metrika'),
                            ],
                            __('ym-show-contacts', 'wp-yandex-metrika') => [
                                    'value' => 'ym-show-contacts',
                                    'description'  => __('View contacts of a seller or contractor', 'wp-yandex-metrika'),
//                                    'description'  => __('Просмотр контактов продавца или исполнителя', 'wp-yandex-metrika'),
                            ],
                            __('ym-successful-lead', 'wp-yandex-metrika') => [
                                    'value' => 'ym-successful-lead',
                                    'description'  => __('Confirmed ad placement', 'wp-yandex-metrika'),
//                                    'description'  => __('Подтвердил размещение объявления', 'wp-yandex-metrika'),
                            ],
                            __('ym-get-response', 'wp-yandex-metrika') => [
                                    'value' => 'ym-get-response',
                                    'description'  => __('The visitor responded to the ad', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель откликнулся на объявление', 'wp-yandex-metrika'),
                            ],
                            __('ym-contact-constractor', 'wp-yandex-metrika') => [
                                    'value' => 'ym-contact-constractor',
                                    'description'  => __('The visitor contacted the seller or contractor', 'wp-yandex-metrika'),
//                                    'description'  => __('Посетитель связался с продавцом или исполнителем', 'wp-yandex-metrika'),
                            ],
                            __('ym-agree-constractor', 'wp-yandex-metrika') => [
                                    'value' => 'ym-agree-constractor',
                                    'description'  => __('Successful contact', 'wp-yandex-metrika'),
//                                    'description'  => __('Успешный контакт', 'wp-yandex-metrika'),
                            ],
                            __('ym-complete-order', 'wp-yandex-metrika') => [
                                    'value' => 'ym-complete-order',
                                    'description'  => __('The contractor provided a service', 'wp-yandex-metrika'),
//                                    'description'  => __('Исполнитель оказал услугу', 'wp-yandex-metrika'),
                            ],

                        ]
                    ],
                    "selector" => [
                        'callback' => [$this, 'displayTextField'],
                        'name' => 'selector_slug',
                        'class' => 'selector-input',
                        'default' => '',
                        'label' => __('Element selector', 'wp-yandex-metrika'),
//                        'label' => __('Селектор элемента', 'wp-yandex-metrika'),
                        'flex-wrap' => true,
                        'placeholder' => __('Selector name', 'wp-yandex-metrika'),
//                        'placeholder' => __('Название селектора', 'wp-yandex-metrika'),
                        'description' => __('Specify an element\'s CSS selector', 'wp-yandex-metrika'),
                        'attrs' => [
                            'type' => 'text',
                            'class' => ''
                        ]
                    ],
                    "event" => [
                        'callback' => [$this, 'displayTextField'],
                        'name' => 'event_slug',
                        'class' => 'event-input',
                        'default' => '',
                        'label' => __('Event', 'wp-yandex-metrika'),
//                        'label' => __('Событие', 'wp-yandex-metrika'),
                        'flex-wrap' => true,
                        'placeholder' => __('Event property', 'wp-yandex-metrika'),
                        'description' => __('Specify a JS event to broadcast the target', 'wp-yandex-metrika'),
                        'attrs' => [
                            'type' => 'text',
                            'class' => 'event-input suggestion-input',
                        ]
                    ]
                ]
            )
        );

        add_settings_field(
            'yam_data_layer',
            __('Name of data container', 'wp-yandex-metrika'),
            [$this, 'displayTextField'],
            YAM_DATA_LAYER,
            'ec',
            array(
                'name' => 'data_layer',
                'attrs' => [
                    'type' => 'text',
                    'class' => ''
                ]
            )
        );
    }

    public function sanitizeOptions($options)
    {
        if (!isset($options['counters']) || !is_array($options['counters'])) {
            $options['counters'] = [];
        }

        foreach ($options['counters'] as $key => &$row) {
            if (empty($row['number'])) {
                unset($options['counters'][$key]);
            }

            if (!isset($row['webvisor'])) {
                $row['webvisor'] = 0;
            }
        }

        YaMetrika::getInstance()->deactivateMessage('deactivate-other-counters');

        if (!empty($options['counters'])) {
            YaMetrika::getInstance()->deactivateMessage('no-counters');
        }

        if (!isset($options['custom_targets']) || !is_array($options['custom_targets'])) {
            $options['custom_targets'] = [];
        }

        foreach ($options['custom_targets'] as $key => &$row) {
            if (empty($row['selector']) && empty($row['event'])) {
                unset($options['custom_targets'][$key]);
            }
        }

        return $options;
    }

    public function displayRepeaterField($args)
    {
        $rows = YaMetrika::getInstance()->options[$args['name']];
        $fields = $args['fields'];

        if (!is_array($rows) || empty($rows) || (isset($rows[0]) && !is_array($rows[0]))) {
            $rows = [[]];
            foreach ($fields as $fieldName => $field) {
                $rows[0][$fieldName] = isset($field['default']) ? $field['default'] : '';
            }
        }
    ?>
        <div class="yam-repeater-field" data-name="<?php echo $args['name'] ?>">
            <div class="yam-repeater-field__rows">
                <div class="yam-repeater-field__row yam-repeater-field__row_tpl">
                    <?php foreach ($fields as $fieldName => $fieldOptions) {
                        $field = $fields[$fieldName];
                        $field['value'] = $field['default'];
                        $field['name'] = $args['name'] . '][-1][' . $fieldName;

                        if (empty($field['attrs'])) {
                            $field['attrs'] = [];
                        }

                        if (empty($field['attrs']['class'])) {
                            $field['attrs']['class'] = '';
                        }

                        $field['attrs']['class'] .= ' yam-repeater-field__input';
                    ?>
                        <div class="yam-repeater-field__input-wrap<?php echo isset($field['flex-wrap']) ? ' yam-repeater-field__input-wrap--large' : ''; ?>">
                            <?php $field['callback']($field, !empty($args['check_for'])); ?>
                        </div>
                    <?php } ?>
                    <div class="yam-repeater-field__remove-btn yam-repeater-field__btn button<?php echo !empty($args['class']) ? ' ' . $args['class'] . '-remove-btn' : '' ?>">
                        <img class="trashcan" src="<?php echo YaMetrikaHelpers::getAssetUrl('trashcan.svg')?>">
                    </div>
                </div>

                <?php
                foreach ($rows as $index => $row) {
                ?>
                    <div class="yam-repeater-field__row">
                        <?php foreach ($row as $fieldName => $value) {
                            $field = $fields[$fieldName];
                            $field['value'] = $value;
                            $field['name'] = $args['name'] . '][' . $index . '][' . $fieldName;
                            if (empty($field['attrs'])) {
                                $field['attrs'] = [];
                            }

                            if (empty($field['attrs']['class'])) {
                                $field['attrs']['class'] = '';
                            }

                            $disableTextInputs = false;

                            if (!empty($args['check_for'])) {
                                $checkForValue = $row[$args['check_for']];
                                $disableTextInputs = empty($checkForValue) || is_null($checkForValue);

//                                echo '<pre>';
//                                print_r($field['value']);
//                                echo '</pre>';
                            }

                            $field['attrs']['class'] .= ' yam-repeater-field__input';
                        ?>
                            <div class="yam-repeater-field__input-wrap<?php echo isset($field['flex-wrap']) ? ' yam-repeater-field__input-wrap--large' : ''; ?>">
                                <?php $field['callback']($field, $disableTextInputs); ?>
                            </div>
                        <?php } ?>
                        <div class="yam-repeater-field__remove-btn yam-repeater-field__btn button<?php echo !empty($args['class']) ? ' ' . $args['class'] . '-remove-btn' : '' ?>">
                            <img class="trashcan" src="<?php echo YaMetrikaHelpers::getAssetUrl('trashcan.svg')?>">
                        </div>
                    </div>
                <?php
                } ?>
            </div>
            <div>

            </div>
            <div class="yam-repeater-field__add-wrap">
                <div class="yam-repeater-field__add-btn yam-repeater-field__btn yam-repeater-field__btn--primary button button-primary">
                    <?php _e('Add', 'wp-yandex-metrika'); ?>
                </div>
            </div>
        </div>
    <?php
    }

    public function displayMultiRowField($args)
    {
        $rows = YaMetrika::getInstance()->options[$args['name']];
        $fields = $args['fields'];

        if (!is_array($rows) || empty($rows) || (isset($rows[0]) && !is_array($rows[0]))) {
            $rows = [[]];
            foreach ($fields as $fieldName => $field) {
                $rows[0][$fieldName] = isset($field['default']) ? $field['default'] : '';
            }
        }

        ?>
        <div class="yam-repeater-field" data-name="<?php echo $args['name'] ?>">
            <div class="yam-repeater-field__rows">
                <?php
                foreach ($rows as $index => $row) {
                    ?>
                    <div class="yam-repeater-field__row">
                        <?php foreach ($row as $fieldName => $value) {
                            $field = $fields[$fieldName];
                            $field['value'] = $value;
                            $field['name'] = $args['name'] . '][' . $index . '][' . $fieldName;
                            if (empty($field['attrs'])) {
                                $field['attrs'] = [];
                            }

                            if (empty($field['attrs']['class'])) {
                                $field['attrs']['class'] = '';
                            }

                            $field['attrs']['class'] .= ' yam-repeater-field__input';
                            ?>
                            <div class="yam-repeater-field__input-wrap<?php echo $field['flex-wrap'] ? ' yam-repeater-field__input-wrap--large' : ''; ?>">
                                <?php $field['callback']($field); ?>
                            </div>
                        <?php } ?>
                    </div>
                    <?php
                } ?>
            </div>
        </div>
        <?php
    }

    public function displaySelectField($args)
    {
        $name = YAM_OPTIONS_SLUG . '[' . $args['name'] . ']';
        $selectOptions = $args['options'];
        $selectedValue = $args['value'];
    ?>
        <?php
            if (!empty($args['label'])) {
                ?>
                    <label class="yam-repeater-field__label" for="<?php echo esc_attr($name); ?>"><?php echo $args['label']; ?></label>
                <?php
            }
        ?>
        <select class="yam-repeater-field__input" name="<?php echo esc_attr($name); ?>">
            <?php foreach ($selectOptions as $label => $value) { ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $selectedValue); ?>><?php echo esc_html($label); ?></option>
            <?php } ?>
        </select>
        <?php if (!empty($args['description'])) { ?>
            <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php } ?>
    <?php
    }

    public function displaySelect2Field($args)
    {
        $name = YAM_OPTIONS_SLUG . '[' . $args['name'] . ']';
        $selectOptions = $args['options'];
        $selectedValue = $args['value'];
        $classes = 'yam-repeater-field__input yam-select2';

        if (!empty($args['class'])) {
            $classes .= ' '.$args['class'];
        }

        if (!empty($args['label'])) {
            ?>
            <label class="yam-repeater-field__label" for="<?php echo esc_attr($name); ?>"><?php echo $args['label']; ?></label>
            <?php
        }
        ?>
        <select class="<?php echo $classes; ?>" name="<?php echo esc_attr($name); ?>">
            <?php foreach ($selectOptions as $label => $value) {
                $optionDescription = '';

                if (!empty($value['description'])) {
                    $optionDescription = $value['description'];
                }
            ?>
                <option value="<?php echo esc_attr($value['value']); ?>" <?php selected($value['value'], $selectedValue); echo 'data-description="'.$optionDescription.'"'?>><span><?php echo esc_html($label); ?></span></option>
            <?php } ?>
        </select>
        <?php if (!empty($args['description'])) { ?>
        <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
    <?php } ?>
        <?php
    }

    public function displayTextField($args, $disabled = false)
    {
        $name = YAM_OPTIONS_SLUG . '[' . $args['name'] . ']';
        $attrs = $args['attrs'];
        $attrs['name'] = YAM_OPTIONS_SLUG . '[' . $args['name'] . ']';
        $attrs['value']  = isset($args['value']) ? $args['value'] : YaMetrika::getInstance()->options[$args['name']];
        $placeholder = '';

        if (!empty($args['placeholder'])) {
            $placeholder = ' placeholder="'.$args['placeholder'].'"';
        }
    ?>
        <?php
        if (!empty($args['label'])) {
            ?>
                <label class="yam-repeater-field__label" for="<?php echo esc_attr($name); ?>"><?php echo $args['label'];?></label>
            <?php
        }
        if ($disabled) {
            $attrs['class'] .= ' disabled';
        }
        ?>
        <input <?php YaMetrikaHelpers::printHtmlAttrs($attrs); echo $placeholder; ?> autocomplete="off">
        <?php if (!empty($args['description'])) { ?>
            <div class="tooltip">
                <p class="tooltip__inner"><?php echo wp_kses_post($args['description']); ?></p>
            </div>
        <?php } ?>
    <?php
    }

    public function displaySectionDescription($args)
    {
    ?>
        <?php if (!empty($args['description'])) { ?>
            <p class="description section-description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php } ?>
    <?php
    }

    public function displayShowButton($args)
    {
        $selectorsToHide = [];

        if (!empty($args['selectors'])) {
            $selectorsToHide = implode(', ', $args['selectors']);
        }

        ?>
        <div class="show-button__wrap" data-selectors="<?php echo $selectorsToHide; ?>">
            <div class="show-button">
                <?php echo wp_kses_post($args['title']); ?>
                <span class="show-button__arrow">
            </div>
        </div>
        <?php
    }

    public function displayCheckboxField($args)
    {
        $attrs = $args['attrs'];
        $attrs['name'] = YAM_OPTIONS_SLUG . '[' . $args['name'] . ']';
        $attrs['value'] = isset($attrs['value']) ? $attrs['value'] : 1;
        $attrs['type'] = 'checkbox';
        $value = isset($args['value']) ? $args['value'] : YaMetrika::getInstance()->options[$args['name']];

        if ($value) {
            $attrs['checked'] = 'checked';
        }

    ?>
        <input <?php YaMetrikaHelpers::printHtmlAttrs($attrs); ?>>&nbsp;<?php echo isset($args['label']) ? wp_kses_post($args['label']) : ''; ?>
        <?php if (!empty($args['description'])) { ?>
            <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php } ?>
<?php
    }

    private function getPageHtmlFunction($slug)
    {
        return function () use ($slug) {
            require YAM_PATH . '/view/' . $slug . '.php';
        };
    }

    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function do_settings_sections($page)
    {
        global $wp_settings_sections, $wp_settings_fields;

        if (!isset($wp_settings_sections[$page])) {
            return;
        }

        foreach ((array) $wp_settings_sections[$page] as $section) {
            //            if ( $section['title'] ) {
            //                echo "<h2>{$section['title']}</h2>\n";
            //            }

            if ($section['callback']) {
                call_user_func($section['callback'], $section);
            }

            if (!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']])) {
                continue;
            }
            $class = '';
            if ($section['title']) {
                $class = ' ' . $section['title'] . '-table';
            }

            echo '<table class="form-table' . $class . '" role="presentation">';
            $this->do_settings_fields($page, $section['id']);
            echo '</table>';
        }
    }

    public function do_settings_fields($page, $section)
    {
        global $wp_settings_fields;

        if (!isset($wp_settings_fields[$page][$section])) {
            return;
        }

        foreach ((array) $wp_settings_fields[$page][$section] as $field) {
            $class = ' class="table-row"';
            $headerClass = '';

            if (!empty($field['args']['class'])) {
                $class = ' class="table-row ' . esc_attr($field['args']['class']) . '"';
                $headerClass = ' '.esc_attr($field['args']['class']);
            }

            echo "<tr{$class}>";

            if (!empty($field['args']['label_for'])) {
                echo '<th scope="row" class="row-header'. $headerClass .'"><label for="' . esc_attr($field['args']['label_for']) . '">' . $field['title'] . '</label></th>';
            } else if (empty($field['args']['label_for']) && empty($field['title'])) {
                echo '<th scope="row" class="row-header row-blank'. $headerClass .'"></th>';
            } else {
                echo '<th scope="row" class="row-header'. $headerClass .'">' . $field['title'] . '</th>';
            }

            $fieldContainerClass = '';

            if (!empty($field['args']['class'])) {
                $fieldContainerClass = ' ' . esc_attr($field['args']['class']) . '-container';
            }

            echo '<td class="row-content' . $fieldContainerClass . '">';
            call_user_func($field['callback'], $field['args']);
            echo '</td>';
            echo '</tr>';
        }
    }
}
