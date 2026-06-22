<?php
if( ! defined('WP_UNINSTALL_PLUGIN') )
    exit;

delete_option('yam_options');
delete_option('yam_messages');
