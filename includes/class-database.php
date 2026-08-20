<?php
namespace PSC_LMS;
if (!defined('ABSPATH')) exit;

class Database {
    public static function upgrade(): void {
        $version = get_option('psc_lms_db_version', '0');
        if (version_compare((string)$version, PSC_LMS_VERSION, '<')) {
            self::activate();
        }
        self::ensure_student_registry();
    }

    public static function student_registry_table(): string {
        global $wpdb;
        $candidates=['students',$wpdb->prefix.'students',$wpdb->prefix.'psc_student_profiles'];
        foreach($candidates as $table){$found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table));if($found===$table)return $table;}
        return $wpdb->prefix.'psc_student_profiles';
    }

    public static function ensure_student_registry(): void {
        global $wpdb; $table=self::student_registry_table();
        if(!$table || $table===$wpdb->prefix.'psc_student_profiles') return;
        $cols=array_map('strval',(array)$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0));
        $add=[
            'wp_user_id'=>'bigint(20) unsigned NULL','firebase_uid'=>'varchar(255) NULL','google_sub'=>'varchar(255) NULL','target_exam'=>'varchar(255) NULL','study_medium'=>'varchar(100) NULL',
            'onboarding_completed'=>'tinyint(1) NOT NULL DEFAULT 0','status'=>"varchar(20) NOT NULL DEFAULT 'active'",'registration_source'=>"varchar(30) NOT NULL DEFAULT 'onboarding'",'auth_provider'=>"varchar(30) NOT NULL DEFAULT 'google'",'registration_mode'=>"varchar(30) NOT NULL DEFAULT 'self'",
            'allow_data_retrieval'=>'tinyint(1) NOT NULL DEFAULT 1','allow_course_retrieval'=>'tinyint(1) NOT NULL DEFAULT 1','allow_progress_retrieval'=>'tinyint(1) NOT NULL DEFAULT 1','allow_exam_history'=>'tinyint(1) NOT NULL DEFAULT 1','allow_order_history'=>'tinyint(1) NOT NULL DEFAULT 1',
            'removed_at'=>'datetime NULL','removed_by'=>'bigint(20) unsigned NULL','created_at'=>'datetime NULL','updated_at'=>'datetime NULL'
        ];
        foreach($add as $col=>$def)if(!in_array($col,$cols,true))$wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
        // IMPORTANT: schema upgrades never write student records or student field values.\n        // Defaults are applied only by the explicit onboarding/profile Submit endpoint.\n        $indexes=$wpdb->get_results("SHOW INDEX FROM `{$table}`",ARRAY_A);
        $hasUser=false;$hasFb=false;foreach($indexes as $ix){if(($ix['Key_name']??'')==='idx_wp_user_id')$hasUser=true;if(($ix['Key_name']??'')==='idx_firebase_uid')$hasFb=true;}
        if(!$hasUser && in_array('wp_user_id',$cols,true))$wpdb->query("ALTER TABLE `{$table}` ADD INDEX `idx_wp_user_id` (`wp_user_id`)");
        if(!$hasFb && in_array('firebase_uid',$cols,true))$wpdb->query("ALTER TABLE `{$table}` ADD INDEX `idx_firebase_uid` (`firebase_uid`(100))");
    }

    public static function activate(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;
        $sql = [];

        $sql[] = "CREATE TABLE {$p}psc_courses (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            slug varchar(200) NOT NULL,
            description longtext NULL,
            short_description text NULL,
            thumbnail_id bigint(20) unsigned NULL,
            thumbnail_url text NULL,
            categories text NULL,
            difficulty varchar(20) NOT NULL DEFAULT 'all_levels',
            pricing_type varchar(30) NOT NULL DEFAULT 'free',
            price decimal(12,2) NOT NULL DEFAULT 0,
            sale_price decimal(12,2) NULL,
            currency varchar(10) NOT NULL DEFAULT 'INR',
            status varchar(20) NOT NULL DEFAULT 'draft',
            language varchar(20) NOT NULL DEFAULT 'ml',
            featured tinyint(1) NOT NULL DEFAULT 0,
            sort_order int NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY slug (slug), KEY status (status), KEY pricing_type (pricing_type), KEY difficulty (difficulty)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_modules (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            description text NULL,
            sort_order int NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'published',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY course_id (course_id), KEY course_order (course_id, sort_order)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_lessons (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            module_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            lesson_type varchar(30) NOT NULL DEFAULT 'text',
            content longtext NULL,
            youtube_url text NULL,
            youtube_video_id varchar(32) NULL,
            youtube_playlist_id varchar(64) NULL,
            youtube_playlist_item_id varchar(128) NULL,
            youtube_thumbnail_url text NULL,
            imported_at datetime NULL,
            video_url text NULL,
            audio_url text NULL,
            pdf_url text NULL,
            pdf_attachment_id bigint(20) unsigned NULL,
            duration_seconds int unsigned NOT NULL DEFAULT 0,
            is_free tinyint(1) NOT NULL DEFAULT 0,
            sort_order int NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'published',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY module_id (module_id), KEY status (status), KEY pdf_attachment_id (pdf_attachment_id), KEY youtube_video_id (youtube_video_id), KEY youtube_playlist_id (youtube_playlist_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_subjects (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text NULL,
            status varchar(20) NOT NULL DEFAULT 'published',
            created_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY slug (slug)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_topics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            subject_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text NULL,
            status varchar(20) NOT NULL DEFAULT 'published',
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY subject_id (subject_id), UNIQUE KEY subject_slug (subject_id, slug)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_questions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            subject_id bigint(20) unsigned NULL,
            topic_id bigint(20) unsigned NULL,
            question longtext NOT NULL,
            question_pdf_attachment_id bigint(20) unsigned NULL,
            question_type varchar(20) NOT NULL DEFAULT 'single',
            difficulty varchar(20) NOT NULL DEFAULT 'medium',
            explanation longtext NULL,
            source text NULL,
            source_question_number varchar(50) NULL,
            source_page int unsigned NULL,
            exam_year varchar(20) NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY subject_id (subject_id), KEY topic_id (topic_id), KEY status (status), KEY source_question_number (source_question_number)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_question_options (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            question_id bigint(20) unsigned NOT NULL,
            option_key varchar(5) NOT NULL,
            option_text text NOT NULL,
            is_correct tinyint(1) NOT NULL DEFAULT 0,
            sort_order int NOT NULL DEFAULT 0,
            PRIMARY KEY (id), KEY question_id (question_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_question_facts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            question_id bigint(20) unsigned NOT NULL,
            fact longtext NOT NULL,
            sort_order int NOT NULL DEFAULT 0,
            PRIMARY KEY (id), KEY question_id (question_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_exams (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description longtext NULL,
            duration_minutes int unsigned NOT NULL DEFAULT 60,
            total_marks decimal(8,2) NOT NULL DEFAULT 0,
            negative_mark decimal(8,2) NOT NULL DEFAULT 0,
            passing_percentage decimal(5,2) NOT NULL DEFAULT 40,
            max_attempts int unsigned NOT NULL DEFAULT 0,
            shuffle_questions tinyint(1) NOT NULL DEFAULT 1,
            shuffle_options tinyint(1) NOT NULL DEFAULT 1,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY status (status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_exam_questions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            exam_id bigint(20) unsigned NOT NULL,
            question_id bigint(20) unsigned NOT NULL,
            marks decimal(8,2) NOT NULL DEFAULT 1,
            sort_order int NOT NULL DEFAULT 0,
            PRIMARY KEY (id), UNIQUE KEY exam_question (exam_id, question_id), KEY exam_id (exam_id), KEY question_id (question_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_student_profiles (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            full_name varchar(255) NULL,
            phone varchar(30) NULL,
            home_district varchar(100) NULL,
            highest_qualification varchar(100) NULL,
            date_of_birth date NULL,
            target_exam varchar(255) NULL,
            study_medium varchar(100) NULL,
            onboarding_completed tinyint(1) NOT NULL DEFAULT 0,
            auth_provider varchar(30) NOT NULL DEFAULT 'google',
            google_sub varchar(255) NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            registration_mode varchar(30) NOT NULL DEFAULT 'self',
            allow_data_retrieval tinyint(1) NOT NULL DEFAULT 0,
            allow_course_retrieval tinyint(1) NOT NULL DEFAULT 0,
            allow_progress_retrieval tinyint(1) NOT NULL DEFAULT 0,
            allow_exam_history tinyint(1) NOT NULL DEFAULT 0,
            allow_order_history tinyint(1) NOT NULL DEFAULT 0,
            removed_at datetime NULL,
            removed_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY user_id (user_id), KEY phone (phone), KEY home_district (home_district), KEY target_exam (target_exam), KEY onboarding_completed (onboarding_completed), KEY status (status), KEY registration_mode (registration_mode)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_progress (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            lesson_id bigint(20) unsigned NOT NULL,
            progress_percent decimal(5,2) NOT NULL DEFAULT 0,
            completed tinyint(1) NOT NULL DEFAULT 0,
            last_position_seconds int unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY user_lesson (user_id, lesson_id), KEY user_id (user_id), KEY lesson_id (lesson_id)
        ) {$charset};";


        $sql[] = "CREATE TABLE {$p}psc_exam_attempts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            exam_id bigint(20) unsigned NOT NULL,
            start_time datetime NULL,
            submit_time datetime NOT NULL,
            score decimal(10,2) NOT NULL DEFAULT 0,
            total_marks decimal(10,2) NOT NULL DEFAULT 0,
            percentage decimal(6,2) NOT NULL DEFAULT 0,
            correct_count int unsigned NOT NULL DEFAULT 0,
            wrong_count int unsigned NOT NULL DEFAULT 0,
            skipped_count int unsigned NOT NULL DEFAULT 0,
            time_taken_seconds int unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'submitted',
            PRIMARY KEY (id), KEY user_id (user_id), KEY exam_id (exam_id), KEY submitted (submit_time)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_attempt_answers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attempt_id bigint(20) unsigned NOT NULL,
            question_id bigint(20) unsigned NOT NULL,
            selected_option varchar(5) NULL,
            is_correct tinyint(1) NOT NULL DEFAULT 0,
            mark_obtained decimal(10,2) NOT NULL DEFAULT 0,
            mark_for_review tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id), UNIQUE KEY attempt_question (attempt_id, question_id), KEY attempt_id (attempt_id), KEY question_id (question_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_enrollments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            enrolled_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY user_course (user_id, course_id), KEY user_id (user_id), KEY course_id (course_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}psc_bookmarks (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            question_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY user_question (user_id, question_id), KEY user_id (user_id), KEY question_id (question_id)
        ) {$charset};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        // Student profiles are created by onboarding or explicit admin actions.
        // Do NOT create blank student records for every WordPress subscriber.
        // New student records use the safer default of no historical-data retrieval
        // until an administrator explicitly enables it.
        // Existing student rows are preserved. No automatic student creation or backfill occurs here.

        self::ensure_student_registry();
        update_option('psc_lms_db_version', PSC_LMS_VERSION);
        update_option('psc_lms_plugin_version', PSC_LMS_VERSION);
    }
}
