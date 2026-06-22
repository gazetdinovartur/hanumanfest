<?php

class YaMetrikaLogs
{
	protected static $instance;
    protected $filepath;

    const FILENAME = 'logs.txt';

    //error codes
	const WARNING_OUTDATED_WP_VERSION = 1;
	const WARNING_BRAND_TAXONOMY_IS_NOT_EXISTS = 2;
	const ERROR_NO_HOOK = 3;
	const ERROR_WRONG_COUNTER_NUMBER = 4;

    private function __construct()
    {
    	$this->filepath = YAM_PATH.'/'.self::FILENAME;
    }

    public function log($msg){
		$date = date('d.m.Y h:i:s');
		$msg = "[$date] $msg".PHP_EOL;
		file_put_contents($this->filepath, $msg, FILE_APPEND);
    }

	public function error($errorCode, $message){
    	$this->log(__("Error ", 'wp-yandex-metrika').$errorCode.': '.$message);
	}

	public function printLogs()
	{
		if (is_file($this->filepath)) {
			$logs = file_get_contents($this->filepath);
		} else {
			$logs = __('There are no logs', 'wp-yandex-metrika');
		}

		$logs .= "\n------------\n";
		$logs .= "php: ".phpversion()."; wp: ".get_bloginfo('version')."; plugin: ".YAM_VER."\n";

		echo nl2br(htmlspecialchars($logs));
	}

    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }
}
