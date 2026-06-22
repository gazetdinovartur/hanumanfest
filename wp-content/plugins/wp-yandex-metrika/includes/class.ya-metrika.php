<?php

class YaMetrika{
    protected static $instance;
    public $admin;
    public $options = [
		'counters' => [],
		'brand_type' => 'taxonomy',
		'brand_slug' => '',
		'custom_targets' => [],
        'data_layer' => 'dataLayer',
	];

    public $messages = [];
	private $tests = [];
	private $hooked = [];

	private function __construct(){
		// print_r(get_option(YAM_OPTIONS_SLUG, []));
		$this->options = wp_parse_args(get_option(YAM_OPTIONS_SLUG, []), $this->options);

		add_action('plugins_loaded', [$this, 'loadTextDomain']);
		add_action('plugin_action_links', [$this, 'onActionsLinks'], 10, 2);
		add_action('wp_ajax_yam_dismiss_message', [$this, 'onDismissMessage'] );
    	add_action('admin_notices', [$this, 'onNotices']);
		add_action('current_screen', [$this, 'onScreen'] );

		$this->registerTest(function(){
		    foreach ($this->options['counters'] as $counter) {
		        if (!empty($counter['number']) && !preg_match('/^\d+$/', $counter['number'])) {
					YaMetrikaLogs::getInstance()->error(YaMetrikaLogs::ERROR_WRONG_COUNTER_NUMBER, sprintf(__('Invalid tag number for "%s"', 'wp-yandex-metrika'), htmlspecialchars($counter['number'])));
                }
            };
        });
	}

	public function loadTextDomain(){
		$locale = determine_locale();
		$locale = apply_filters( 'plugin_locale', $locale, YAM_SLUG );

		unload_textdomain( YAM_SLUG );
		load_textdomain( YAM_SLUG, YAM_PATH . '/languages/'. YAM_SLUG .'-'. $locale . '.mo' );
		load_plugin_textdomain( YAM_SLUG, false, dirname(plugin_basename(YAM_FILE)) . '/languages' );

		$this->registerMessages();
    }

	private function registerMessages(){
    	$this->registerMessage('no-counters', sprintf(
			__('No Yandex.Metrica tag has been installed on the site yet. To add a tag, <a href="%s" target="_blank">create one</a> and <a href="%s" target="_blank">add its number</a> on <a href="%s">the settings page</a> of the plugin.', 'wp-yandex-metrika'),
			'https://yandex.ru/support/metrica/general/creating-counter.html',
			'https://yandex.ru/support/metrica/general/tag-id.html',
			'/wp-admin/options-general.php?page='.YAM_PAGE_SLUG
		), 'warning');

		$this->registerMessage('deactivate-other-counters', sprintf(
			__('Yandex.Metrica tags were found installed on the site. We automatically added them to the <a href="%s">settings</a>. We recommend disabling tags in all other plugins or deactivating other plugins that work with the Yandex.Metrica tags.', 'wp-yandex-metrika'),
			'/wp-admin/options-general.php?page='.YAM_PAGE_SLUG
		), 'warning');

	}

	//filters and actions handlers
	public function onDismissMessage(){
		$id = sanitize_key($_POST['messageId']);
		$this->deactivateMessage($id);
		wp_die();
	}

	public function onNotices(){
		$messages = $this->getActiveMessages();

    	if ($messages) {
			foreach ($messages as $id => $message) {
				?>
				<div class="yam-notice notice notice-<?php echo esc_attr($message['type']); ?> is-dismissible" data-id="<?php echo esc_attr($id); ?>">
					<p><?php echo wp_kses_post($message['text']); ?></p>
				</div>
				<?php
			}
		}
	}

	public function onScreen(){
        $screen = get_current_screen();
        if ($screen->id === 'settings_page_'.YAM_PAGE_SLUG) {
            $this->doTests();
		}
    }

    public function onActivation()
    {
    	if (!version_compare(get_bloginfo('version'),'5.2.9', '>=')) {
			YaMetrikaLogs::getInstance()->error(YaMetrikaLogs::WARNING_OUTDATED_WP_VERSION, __('For the plugin to work, the WordPress version must be 5.2.9 or higher', 'wp-yandex-metrika'));
			die(__('For the plugin to work, the WordPress version must be 5.2.9 or higher', 'wp-yandex-metrika'));
    	}

    	if (empty($this->options['counters'])) {
    		$options = $this->getOptionsFromPlugins();
			$this->options = array_merge($this->options, $options);

			if (!empty($options['counters'])) {
				$this->activateMessage('deactivate-other-counters');
			}
		}

		if (empty($this->options['counters'])) {
			$this->activateMessage('no-counters');
		}

		update_option(YAM_OPTIONS_SLUG, $this->options);
    }

    public function onDeactivation()
    {

    }

	public function onActionsLinks( $actions, $plugin_file ) {
		if( strpos( $plugin_file, '/'.YAM_SLUG.'.php') === false )
			return $actions;

		return array_merge( $actions, [
			'settings' => '<a href="' . admin_url( 'options-general.php?page='.YAM_PAGE_SLUG ) . '">' . esc_html__( 'Settings', 'wp-yandex-metrika' ) . '</a>',
		]);
	}


    //other
    public function isHooked($hook)
	{
	    return in_array($hook, $this->hooked);
	}

    public function setHooked($hook){
	    if ($this->isHooked($hook)) {
	        return false;
		}

		$this->hooked[] = $hook;

	    return true;
	}

	public function getOptionsFromPlugins(){
    	$options = [
    		'counters' => []
		];

		//counter-yandex-metrica
		$pluginOptions = get_option('yametrika-counter', []);
		if (!empty($pluginOptions) && is_plugin_active( 'counter-yandex-metrica/counter-yandex-metrica.php' ) && !empty($pluginOptions['ymc_number_counter'])) {
			$options['counters'][] = [
                'number' => $pluginOptions['ymc_number_counter'],
                'webvisor' => 0
            ];
		}

		//yandex-metrika
		$pluginOptions = get_option( 'yandex-metrika', []);
		if (!empty($pluginOptions) && is_plugin_active( 'yandex-metrika/yandex-metrika.php' ) && !empty($pluginOptions['counter-code'])) {
			$options['counters'][] = [
				'number' => $this->parseCounterIdFromCode($pluginOptions['counter-code']),
                'webvisor' => 0
            ];
		}

		//wt-yandex-metrika
		$pluginOptions = get_option( 'wt_yandex_metrika', []);
		if (!empty($pluginOptions) && is_plugin_active( 'wt-yandex-metrika/wt-yandex-metrika.php' ) && !empty($pluginOptions['script'])) {
			$options['counters'][] =  [
				'number' => $this->parseCounterIdFromCode($pluginOptions['script']),
                'webvisor' => 0
            ];
		}

		//vdz-yandex-metrika
		$pluginOptions = get_option( 'vdz_yandex_metrika_code', false);
		if (is_plugin_active( 'vdz-yandex-metrika/vdz-yandex-metrika.php' ) && !empty($pluginOptions)) {
			$options['counters'][] =  [
				'number' => $pluginOptions,
                'webvisor' => 0
            ];
		}

		$options['counters'] = array_unique($options['counters']);

		return $options;
	}

	public function parseCounterIdFromCode($code){
		if(preg_match('/[^\w\d]ym\(\s*(\d+)\s*,/', $code, $matches)) {
			return $matches[1];
		} elseif(preg_match('/mc\.yandex\.ru\/watch\/(\d+)"/', $code, $matches)) {
			return $matches[1];
		}

		return false;
	}

	public function activateMessage($id){
    	$activeMessages = get_option('yam_messages');

    	if (!is_array($activeMessages)) {
			$activeMessages = [];
		}

		$activeMessages[] = $id;

		update_option('yam_messages', array_unique($activeMessages));
	}

	public function deactivateMessage($id){
    	$activeMessages = get_option('yam_messages');

    	if (is_array($activeMessages)) {
			$index = array_search($id, $activeMessages);

    		if ($index === false) {
    			return;
			}

    		array_splice($activeMessages, $index, 1);

			update_option('yam_messages', $activeMessages);
		}
	}

	public function getActiveMessages(){
    	$messages = [];
		$activeMessages = get_option('yam_messages');

		if (!$activeMessages) {
		    return [];
		}

		foreach ($activeMessages as $id) {
			if (empty($this->messages[$id])) {
				continue;
			}
			$messages[$id] = $this->messages[$id];
		}

		return $messages;
	}

	public function registerMessage($id, $text, $type = 'success'){
    	$this->messages[$id] = [
    		'text' => $text,
			'type' => $type
		];
	}


	public function registerTest(callable $func){
        $this->tests[] = $func;
	}

	private function doTests()
	{
        foreach ($this->tests as $test) {
			$test();
		}
	}

    //other
    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }
}
