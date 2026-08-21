<?php
/**
 * Plugin Name: PSC Learning Platform
 * Description: PSC LMS backend with complete admin content management, progress tracking and Next.js REST API.
 * Version: 3.1.8
 * Author: PSC Learning Platform
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */
if (!defined('ABSPATH')) exit;

define('PSC_LMS_VERSION', '3.1.8');
define('PSC_LMS_FILE', __FILE__);
define('PSC_LMS_DIR', plugin_dir_path(__FILE__));
define('PSC_LMS_URL', plugin_dir_url(__FILE__));

require_once PSC_LMS_DIR . 'includes/class-database.php';
require_once PSC_LMS_DIR . 'includes/class-plugin.php';
require_once PSC_LMS_DIR . 'admin/class-admin.php';
require_once PSC_LMS_DIR . 'admin/class-courses.php';
require_once PSC_LMS_DIR . 'admin/class-questions.php';
require_once PSC_LMS_DIR . 'admin/class-exams.php';
require_once PSC_LMS_DIR . 'api/class-rest.php';
require_once PSC_LMS_DIR . 'includes/class-ai.php';

register_activation_hook(__FILE__, ['PSC_LMS\\Database', 'activate']);

add_action('plugins_loaded', function () {
    PSC_LMS\Database::upgrade();
    PSC_LMS\Plugin::init();
    PSC_LMS\Admin::init();
    PSC_LMS\Courses_Admin::init();
    PSC_LMS\Questions_Admin::init();
    PSC_LMS\AI::init();
    PSC_LMS\Exams_Admin::init();
    PSC_LMS\REST::init();
});
