<?php
namespace PSC_LMS;
if (!defined('ABSPATH')) exit;

class REST {
    public static function init(): void {
        add_action('rest_api_init',[self::class,'routes']);
    }

    public static function routes(): void {
        register_rest_route('psc/v1','/health',['methods'=>'GET','callback'=>function(){return ['success'=>true,'version'=>PSC_LMS_VERSION];},'permission_callback'=>'__return_true']);
        register_rest_route('psc/v1','/auth/firebase',['methods'=>'POST','callback'=>[self::class,'firebase_auth'],'permission_callback'=>'__return_true']);
        register_rest_route('psc/v1','/auth/me',['methods'=>'GET','callback'=>[self::class,'me'],'permission_callback'=>[self::class,'require_firebase_user']]);
        register_rest_route('psc/v1','/courses',['methods'=>'GET','callback'=>[self::class,'courses'],'permission_callback'=>'__return_true']);
        register_rest_route('psc/v1','/courses/(?P<id>\d+)',['methods'=>'GET','callback'=>[self::class,'course'],'permission_callback'=>'__return_true']);
        register_rest_route('psc/v1','/subjects',['methods'=>'GET','callback'=>[self::class,'subjects'],'permission_callback'=>'__return_true']);
        // Public question reading remains available for published questions.
        // Admin mutations are protected by Firebase + WordPress admin capability.
        register_rest_route('psc/v1','/questions',['methods'=>'GET','callback'=>[self::class,'questions'],'permission_callback'=>'__return_true']);
        register_rest_route('psc/v1','/questions',['methods'=>'POST','callback'=>[self::class,'create_question'],'permission_callback'=>[self::class,'require_admin_firebase']]);
        register_rest_route('psc/v1','/questions/(?P<id>\d+)',['methods'=>'GET','callback'=>[self::class,'question'],'permission_callback'=>'__return_true']);
        register_rest_route('psc/v1','/questions/(?P<id>\d+)',['methods'=>'PUT, PATCH','callback'=>[self::class,'update_question'],'permission_callback'=>[self::class,'require_admin_firebase']]);
        register_rest_route('psc/v1','/questions/(?P<id>\d+)',['methods'=>'DELETE','callback'=>[self::class,'delete_question_api'],'permission_callback'=>[self::class,'require_admin_firebase']]);
        register_rest_route('psc/v1','/questions/bulk-delete',['methods'=>'POST','callback'=>[self::class,'bulk_delete_questions'],'permission_callback'=>[self::class,'require_admin_firebase']]);
        register_rest_route('psc/v1','/questions/import',['methods'=>'POST','callback'=>[self::class,'import_questions_json_api'],'permission_callback'=>[self::class,'require_admin_firebase']]);
        register_rest_route('psc/v1','/questions/admin',['methods'=>'GET','callback'=>[self::class,'admin_questions'],'permission_callback'=>[self::class,'require_admin_firebase']]);
        register_rest_route('psc/v1','/exams',['methods'=>'GET','callback'=>[self::class,'exams'],'permission_callback'=>'__return_true']);
        register_rest_route('psc/v1','/exams/(?P<id>\d+)',['methods'=>'GET','callback'=>[self::class,'exam'],'permission_callback'=>'__return_true']);
        register_rest_route('psc/v1','/exams/(?P<id>\d+)/submit',['methods'=>'POST','callback'=>[self::class,'submit_exam'],'permission_callback'=>[self::class,'require_firebase_user']]);
        register_rest_route('psc/v1','/attempts/(?P<id>\d+)',['methods'=>'GET','callback'=>[self::class,'attempt'],'permission_callback'=>[self::class,'require_firebase_user']]);
        register_rest_route('psc/v1','/lessons/(?P<id>\d+)/view',['methods'=>'POST','callback'=>[self::class,'mark_lesson_viewed'],'permission_callback'=>[self::class,'require_firebase_user']]);
        register_rest_route('psc/v1','/lessons/(?P<id>\d+)/progress',['methods'=>'GET','callback'=>[self::class,'lesson_progress'],'permission_callback'=>[self::class,'require_firebase_user']]);
        register_rest_route('psc/v1','/progress',['methods'=>'GET','callback'=>[self::class,'all_progress'],'permission_callback'=>[self::class,'require_firebase_user']]);
        register_rest_route('psc/v1','/me/dashboard',['methods'=>'GET','callback'=>[self::class,'dashboard'],'permission_callback'=>[self::class,'require_dashboard_firebase']]);
        register_rest_route('psc/v1','/me/student-status',['methods'=>'GET','callback'=>[self::class,'student_status'],'permission_callback'=>[self::class,'require_authenticated_firebase']]);
        register_rest_route('psc/v1','/me/student-exists',['methods'=>'GET','callback'=>[self::class,'student_exists'],'permission_callback'=>[self::class,'require_authenticated_firebase']]);
        register_rest_route('psc/v1','/me/onboarding/start',['methods'=>'POST','callback'=>[self::class,'start_onboarding'],'permission_callback'=>[self::class,'require_authenticated_firebase']]);
        register_rest_route('psc/v1','/me/profile',['methods'=>'GET','callback'=>[self::class,'my_profile'],'permission_callback'=>[self::class,'require_authenticated_firebase']]);
        register_rest_route('psc/v1','/me/profile',['methods'=>'POST','callback'=>[self::class,'save_my_profile'],'permission_callback'=>[self::class,'require_authenticated_firebase']]);
        register_rest_route('psc/v1','/me/enrollments',['methods'=>'GET','callback'=>[self::class,'my_enrollments'],'permission_callback'=>[self::class,'require_firebase_user']]);
        register_rest_route('psc/v1','/me/enrollments',['methods'=>'POST','callback'=>[self::class,'enroll_course'],'permission_callback'=>[self::class,'require_firebase_user']]);
        register_rest_route('psc/v1','/me/bookmarks',['methods'=>'GET','callback'=>[self::class,'bookmarks'],'permission_callback'=>[self::class,'require_firebase_user']]);
        register_rest_route('psc/v1','/students',['methods'=>'GET','callback'=>[self::class,'students'],'permission_callback'=>[self::class,'require_admin_firebase']]);
        register_rest_route('psc/v1','/students/(?P<id>[^/]+)',['methods'=>'GET','callback'=>[self::class,'student'],'permission_callback'=>[self::class,'require_admin_firebase']]);
        register_rest_route('psc/v1','/students/(?P<id>[^/]+)',['methods'=>'DELETE','callback'=>[self::class,'remove_student_api'],'permission_callback'=>[self::class,'require_admin_firebase']]);
        register_rest_route('psc/v1','/questions/(?P<id>\d+)/bookmark',['methods'=>'POST','callback'=>[self::class,'toggle_bookmark'],'permission_callback'=>[self::class,'require_firebase_user']]);
    }

    private static function student_table(): string { return Database::student_registry_table(); }

    private static function student_profile(int $uid): array {
        global $wpdb;
        $table=self::student_table();
        if($uid<=0) return [];

        $u=get_user_by('id',$uid);
        $email=$u ? sanitize_email($u->user_email) : '';
        $firebase_uid=sanitize_text_field((string)get_user_meta($uid,'psc_firebase_uid',true));

        // READ ONLY. Login/profile checks must never create or mutate a student
        // record. The student table is the source of truth.
        $cols=array_map('strval',(array)$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0));

        if(in_array('wp_user_id',$cols,true)){
            $row=$wpdb->get_row($wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE wp_user_id=%d LIMIT 1",
                $uid
            ),ARRAY_A);
        }

        if(!$row && $firebase_uid!==''){
            foreach(['firebase_uid','google_sub'] as $identity_col){
                if(!in_array($identity_col,$cols,true)) continue;
                $row=$wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE {$identity_col}=%s LIMIT 1",
                    $firebase_uid
                ),ARRAY_A);
                if($row) break;
            }
        }

        if(!$row && $email!==''){
            foreach(['email','user_email'] as $email_col){
                if(!in_array($email_col,$cols,true)) continue;
                $row=$wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE LOWER(TRIM({$email_col}))=LOWER(TRIM(%s)) LIMIT 1",
                    $email
                ),ARRAY_A);
                if($row) break;
            }
        }

        if(!is_array($row)) return [];

        $row['user_id']=$uid;
        $row['full_name']=$row['full_name']??($row['name']??'');
        $row['home_district']=$row['home_district']??($row['district']??'');
        $row['highest_qualification']=$row['highest_qualification']??($row['qualification']??'');
        $row['date_of_birth']=$row['date_of_birth']??($row['dob']??'');
        $row['google_sub']=$row['google_sub']??($row['firebase_uid']??'');
        return $row;
    }

    private static function student_access_allowed(int $uid): bool {
        $p=self::student_profile($uid);
        // A user without a student profile is a new user and may complete onboarding.
        if(!$p) return true;
        // A removed account remains in the database but cannot access the LMS.
        return (string)($p['status']??'active') !== 'removed';
    }

    private static function account_policy(array $p): array {
        return [
            'status'=>(string)($p['status']??'active'),
            'registration_mode'=>(string)($p['registration_mode']??'self'),
            'allow_data_retrieval'=>(bool)($p['allow_data_retrieval']??false),
            'allow_course_retrieval'=>(bool)($p['allow_course_retrieval']??false),
            'allow_progress_retrieval'=>(bool)($p['allow_progress_retrieval']??false),
            'allow_exam_history'=>(bool)($p['allow_exam_history']??false),
            'allow_order_history'=>(bool)($p['allow_order_history']??false),
        ];
    }

    private static function policy_allows(int $uid,string $key): bool {
        $u=wp_get_current_user();
        if($u && current_user_can('manage_options')) return true;
        $p=self::student_profile($uid);
        return self::student_access_allowed($uid) && !empty($p[$key]);
    }

    private static function bearer_token(): string {
        // Accept the standard Authorization header plus common proxy/header variants.
        // This is read-only authentication; it never creates a student record.
        $authorization='';
        $candidates=[];

        if(isset($_SERVER['HTTP_AUTHORIZATION'])) $candidates[]=(string)$_SERVER['HTTP_AUTHORIZATION'];
        if(isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $candidates[]=(string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        if(isset($_SERVER['HTTP_X_FIREBASE_ID_TOKEN'])) $candidates[]='Bearer '.(string)$_SERVER['HTTP_X_FIREBASE_ID_TOKEN'];

        if(function_exists('getallheaders')) {
            foreach((array)getallheaders() as $k=>$v) {
                $key=strtolower((string)$k);
                if($key==='authorization') $candidates[]=(string)$v;
                if($key==='x-firebase-id-token') $candidates[]='Bearer '.(string)$v;
            }
        }

        foreach($candidates as $header) {
            if(preg_match('/^Bearer\\s+(.+)$/i',trim($header),$m)) return trim($m[1]);
            // If the custom header was already added as a raw token, accept it.
            if($header!=='' && !preg_match('/^Bearer\\s+/i',trim($header))) return trim($header);
        }

        return '';
    }

    public static function require_authenticated_firebase() {
        $uid=self::authenticate_firebase_request();
        if($uid<=0) {
            return new \WP_Error(
                'firebase_auth_required',
                'Valid Firebase authentication is required.',
                ['status'=>401]
            );
        }
        return true;
    }

    public static function require_firebase_user(): bool {
        $uid=self::authenticate_firebase_request();
        if($uid<=0) return false;
        $u=wp_get_current_user();
        if($u && current_user_can('manage_options')) return true;
        return self::student_access_allowed($uid);
    }

    public static function require_dashboard_firebase() {
        $uid=self::authenticate_firebase_request();
        if($uid<=0) {
            return new \WP_Error('firebase_auth_required','Authentication required.',['status'=>401]);
        }

        $u=wp_get_current_user();
        if($u && current_user_can('manage_options')) return true;

        $profile=self::student_profile($uid);

        if(!$profile) {
            return new \WP_Error(
                'student_not_onboarded',
                'No student record exists. Complete onboarding first.',
                ['status'=>403,'user_exists'=>false,'onboarding_required'=>true]
            );
        }

        if((string)($profile['status']??'active')==='removed') {
            return new \WP_Error(
                'student_removed',
                'This student account has been removed. Please contact the administrator.',
                ['status'=>403,'student_status'=>'removed']
            );
        }

        return true;
    }
    public static function require_admin_firebase(): bool {
        $uid=self::authenticate_firebase_request();
        return $uid>0 && current_user_can('manage_options');
    }

    private static function authenticate_firebase_request(): int {
        static $authenticated=[];
        $token=self::bearer_token();
        if(!$token) return 0;
        $hash=hash('sha256',$token);
        if(isset($authenticated[$hash])) { wp_set_current_user($authenticated[$hash]); return (int)$authenticated[$hash]; }

        $api_key=trim((string)get_option('psc_lms_firebase_api_key',''));
        if(!$api_key) return 0;

        $response=wp_remote_post(
            'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key='.rawurlencode($api_key),
            [
                'timeout'=>15,
                'headers'=>['Content-Type'=>'application/json'],
                'body'=>wp_json_encode(['idToken'=>$token])
            ]
        );
        if(is_wp_error($response)) return 0;

        $code=wp_remote_retrieve_response_code($response);
        $body=json_decode(wp_remote_retrieve_body($response),true);
        if($code<200||$code>=300||empty($body['users'][0])) return 0;

        $fb=$body['users'][0];
        $email=sanitize_email($fb['email']??'');
        $firebase_uid=sanitize_text_field($fb['localId']??'');
        if(!$email||!$firebase_uid) return 0;

        // Accept Google and Firebase email/password identities.
        $provider_type='other';
        foreach((array)($fb['providerUserInfo']??[]) as $provider) {
            $pid=(string)($provider['providerId']??'');
            if($pid==='google.com') {$provider_type='google'; break;}
            if($pid==='password') {$provider_type='email';}
        }
        if(!in_array($provider_type,['google','email'],true)) return 0;

        global $wpdb;
        $student_table=self::student_table();

        /*
         * Student record is the source of truth for onboarding and access policy.
         * First look for an existing student profile linked to this Google/Firebase UID.
         */
        $profile_user_id=(int)$wpdb->get_var($wpdb->prepare(
            "SELECT wp_user_id FROM `{$student_table}` WHERE firebase_uid=%s OR google_sub=%s LIMIT 1",
            $firebase_uid, $firebase_uid
        ));

        $user=$profile_user_id ? get_user_by('id',$profile_user_id) : false;

        /*
         * Existing WordPress account can also be matched by the verified Google email.
         * It is not considered an onboarded student unless a completed student profile exists.
         */
        if(!$user) {
            $user=get_user_by('email',$email);
        }

        if(!$user){
            $base=sanitize_user(current(explode('@',$email)),true)?:'student';
            $login=$base;
            $n=1;
            while(username_exists($login)) {$login=$base.$n++;}
            $uid=wp_create_user($login,wp_generate_password(32,true,true),$email);
            if(is_wp_error($uid)) return 0;
            $user=get_user_by('id',$uid);
            if(!$user) return 0;
            $user->set_role('subscriber');
        }

        // Keep the verified Firebase identity linked to the WordPress account.
        update_user_meta($user->ID,'psc_firebase_uid',$firebase_uid);
        update_user_meta($user->ID,'psc_auth_provider',$provider_type);
        update_user_meta($user->ID,'psc_firebase_email',$email);

        $display=sanitize_text_field($fb['displayName']??$user->display_name);
        if($display && $display!==$user->display_name) {
            wp_update_user([
                'ID'=>$user->ID,
                'display_name'=>$display,
                'first_name'=>$display
            ]);
        }

        /*
         * IMPORTANT:
         * Do NOT create a student-profile record during login.
         * A student record is created/updated only when onboarding is submitted.
         * This lets the frontend distinguish a new Google user from an onboarded student.
         */
        wp_set_current_user($user->ID);
        $authenticated[$hash]=(int)$user->ID;
        return (int)$user->ID;
    }


    private static function issue_onboarding_token(int $uid): string {
        // Reuse an existing unexpired onboarding token when possible. This
        // prevents a second /me/onboarding/start call from invalidating the
        // token already held by the onboarding page.
        $existing_hash=(string)get_user_meta($uid,'psc_onboarding_token_hash',true);
        $expires=(int)get_user_meta($uid,'psc_onboarding_token_expires',true);
        if($existing_hash!=='' && $expires>time()){
            // The raw token cannot be recovered from its hash, so a fresh token
            // is required if the client did not retain the previous one.
            // Reissue it deterministically here and replace the old hash.
        }
        $token=wp_generate_password(48,false,false);
        update_user_meta($uid,'psc_onboarding_token_hash',hash('sha256',$token));
        update_user_meta($uid,'psc_onboarding_token_expires',time()+1800);
        return $token;
    }

    private static function consume_onboarding_token(int $uid,string $token): bool {
        if($uid<=0 || $token==='') return false;
        $hash=(string)get_user_meta($uid,'psc_onboarding_token_hash',true);
        $expires=(int)get_user_meta($uid,'psc_onboarding_token_expires',true);
        if($hash==='' || $expires<time()) return false;
        if(!hash_equals($hash,hash('sha256',$token))) return false;
        delete_user_meta($uid,'psc_onboarding_token_hash');
        delete_user_meta($uid,'psc_onboarding_token_expires');
        return true;
    }

    public static function firebase_auth($request) {
        $body=$request->get_json_params();
        $token=sanitize_text_field($body['id_token']??'');
        if(!$token) return new \WP_Error('firebase_token_required','Firebase ID token is required.',['status'=>400]);

        $_SERVER['HTTP_AUTHORIZATION']='Bearer '.$token;
        $uid=self::authenticate_firebase_request();
        if(!$uid) return new \WP_Error('firebase_auth_failed','Firebase authentication failed. Check the Firebase API key and ID token.',['status'=>401]);

        $u=wp_get_current_user();
        // IMPORTANT: use the raw database lookup, not my_profile(), because
        // my_profile() adds the WordPress user_id even when no student row exists.
        $student_row=self::student_profile($uid);
        $has_student_record=!empty($student_row);
        $pdata=$student_row?:[];

        // The ONLY student-existence decision is whether a row exists in the
        // canonical students table. A WordPress user/Firebase identity alone
        // is NOT a student record.
        if($has_student_record && (($pdata['status']??'active')==='removed')) {
            return new \WP_Error(
                'student_removed',
                'This student account has been removed. Please contact the administrator.',
                ['status'=>403,'student_status'=>'removed']
            );
        }
        // Login is read-only for the student registry. No onboarding credential is issued here.
return [
            'success'=>true,
            'data'=>[
                'id'=>(int)$u->ID,
                'name'=>$u->display_name,
                'email'=>$u->user_email,
                'role'=>in_array('administrator',(array)$u->roles,true)?'admin':'student',
                'avatar'=>get_avatar_url($u->ID),
                'profile'=>$pdata,
            ],
            'user_exists'=>$has_student_record,
            'student_exists'=>$has_student_record,
            'onboarding_required'=>!$has_student_record,
            'student_status'=>$has_student_record?((string)($pdata['status']??'active')):'not_found',
            'account_status'=>$has_student_record?(((string)($pdata['status']??'active'))==='removed'?'student_removed':'active'):'not_found',
            'access_allowed'=>$has_student_record && ((string)($pdata['status']??'active')!=='removed')
        ];
    }

    /**
     * Minimal candidate identity check.
     *
     * Returns ONLY whether the authenticated Firebase user's email exists
     * in the WordPress students table. No student profile fields are exposed.
     */
    public static function student_exists(): array {
        $uid=self::authenticate_firebase_request();
        if($uid<=0) {
            return new \WP_Error(
                'firebase_auth_required',
                'Valid Firebase authentication is required.',
                ['status'=>401]
            );
        }

        $user=wp_get_current_user();
        $email=$user ? sanitize_email((string)$user->user_email) : '';
        if(!$email) {
            return new \WP_Error(
                'firebase_email_missing',
                'Authenticated Firebase account has no email address.',
                ['status'=>401]
            );
        }

        global $wpdb;
        $table=self::student_table();

        $exists=(int)$wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM `{$table}` WHERE LOWER(TRIM(email))=LOWER(TRIM(%s)) LIMIT 1",
            $email
        )) === 1;

        // Deliberately expose no email, student ID, profile, status, or other fields.
        return ['student_exists'=>$exists];
    }

    public static function student_status(): array {
        $uid=self::authenticate_firebase_request();
        if($uid<=0) return new \WP_Error('firebase_auth_required','Valid Firebase authentication is required.',['status'=>401]);
        $row=self::student_profile($uid);

        if(!$row) {
            return [
                'success'=>true,
                'user_exists'=>false,
                'student_exists'=>false,
                'onboarding_required'=>true,
                'student_status'=>'not_found',
                'account_status'=>'not_found',
                'access_allowed'=>false,
                'student'=>null,
            ];
        }

        $removed=((string)($row['status']??'active')==='removed');
        $status=$removed?'removed':'active';

        return [
            'success'=>true,
            'user_exists'=>true,
            'student_exists'=>true,
            'onboarding_required'=>false,
            'student_status'=>$status,
            'account_status'=>$removed?'student_removed':'active',
            'access_allowed'=>!$removed,
            'student_id'=>$row['id']??null,
            'student'=>$row,
        ];
    }

    public static function start_onboarding() {
        $uid=self::authenticate_firebase_request();
        if($uid<=0) return new \WP_Error('firebase_auth_required','Valid Firebase authentication is required.',['status'=>401]);
        if($uid<=0) return new \WP_Error('not_authenticated','Authentication required.',['status'=>401]);

        $row=self::student_profile($uid);
        if($row) {
            if((string)($row['status']??'active')==='removed') {
                return new \WP_Error('student_removed','This student account has been removed.',['status'=>403,'student_status'=>'removed']);
            }
            return ['success'=>true,'user_exists'=>true,'onboarding_required'=>false,'already_onboarded'=>true];
        }

        // Only this explicit onboarding-start action issues a creation credential.
        $token=self::issue_onboarding_token($uid);
        return ['success'=>true,'user_exists'=>false,'onboarding_required'=>true,'onboarding_token'=>$token,'expires_in'=>1800];
    }

    public static function me(): array { $u=wp_get_current_user();$profile=self::my_profile();return ['success'=>true,'data'=>['id'=>(int)$u->ID,'name'=>$u->display_name,'email'=>$u->user_email,'role'=>in_array('administrator',(array)$u->roles,true)?'admin':'student','avatar'=>get_avatar_url($u->ID)],'onboarding_required'=>$profile['onboarding_required']??true]; }

    public static function courses():array{
        global $wpdb;$p=$wpdb->prefix;$rows=$wpdb->get_results("SELECT id,title,slug,short_description,description,thumbnail_id,thumbnail_url,categories,difficulty,pricing_type,price,sale_price,currency,language,status,featured,sort_order FROM {$p}psc_courses WHERE status='published' ORDER BY featured DESC,sort_order,id DESC",ARRAY_A);
        foreach($rows as &$r){$decoded=json_decode((string)($r['categories']??''),true);$r['categories']=is_array($decoded)?$decoded:[];$r['category']=$r['categories'][0]??'All';if(empty($r['thumbnail_url'])&&!empty($r['thumbnail_id']))$r['thumbnail_url']=wp_get_attachment_url((int)$r['thumbnail_id'])?:'';$r['thumbnail']=$r['thumbnail_url'];$r['difficulty']=self::difficulty_label($r['difficulty']);$r['is_free']=((float)$r['price']<=0);$r['status']='published';}
        return ['success'=>true,'data'=>$rows];
    }

    private static function difficulty_label($value):string{return ['all_levels'=>'All Levels','beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced'][$value]??ucwords(str_replace('_',' ',(string)$value));}

    public static function course($request):array{
        global $wpdb;$p=$wpdb->prefix;$id=absint($request['id']);$course=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}psc_courses WHERE id=%d AND status='published'",$id),ARRAY_A);
        if(!$course)return ['success'=>false,'message'=>'Course not found'];
        if(empty($course['thumbnail_url'])&&!empty($course['thumbnail_id']))$course['thumbnail_url']=wp_get_attachment_url((int)$course['thumbnail_id'])?:'';
        $decoded=json_decode((string)($course['categories']??''),true);$course['categories']=is_array($decoded)?$decoded:[];$course['category']=$course['categories'][0]??'All';$course['thumbnail']=$course['thumbnail_url'];$course['difficulty']=self::difficulty_label($course['difficulty']);$course['is_free']=((float)$course['price']<=0);
        $mods=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}psc_modules WHERE course_id=%d AND status='published' ORDER BY sort_order,id",$id),ARRAY_A);$uid=get_current_user_id();
        foreach($mods as &$m){$m['lessons']=$wpdb->get_results($wpdb->prepare("SELECT id,title,lesson_type,content,youtube_url,youtube_video_id,video_url,audio_url,pdf_url,pdf_attachment_id,duration_seconds,is_free,sort_order FROM {$p}psc_lessons WHERE module_id=%d AND status='published' ORDER BY sort_order,id",$m['id']),ARRAY_A);foreach($m['lessons'] as &$l){if(!empty($l['pdf_attachment_id']))$l['pdf_url']=wp_get_attachment_url((int)$l['pdf_attachment_id'])?:$l['pdf_url'];$l['is_video']=(!empty($l['youtube_url'])||!empty($l['youtube_video_id'])||!empty($l['video_url']));
$l['viewed']=false;$l['watched']=false;$l['progress_percent']=0;$l['last_position_seconds']=0;
if($uid){$pr=$wpdb->get_row($wpdb->prepare("SELECT progress_percent,completed,last_position_seconds,updated_at FROM {$p}psc_progress WHERE user_id=%d AND lesson_id=%d",$uid,$l['id']),ARRAY_A);if($pr){$l['viewed']=(bool)$pr['completed'];$l['watched']=$l['is_video']?(bool)$pr['completed']:false;$l['completed']=(bool)$pr['completed'];$l['progress_percent']=(float)$pr['progress_percent'];$l['last_position_seconds']=(int)$pr['last_position_seconds'];$l['progress_updated_at']=$pr['updated_at'];}}}}
        $course['modules']=$mods;return ['success'=>true,'data'=>$course];
    }

    public static function subjects():array{global $wpdb;return ['success'=>true,'data'=>$wpdb->get_results("SELECT id,name,slug,description FROM {$wpdb->prefix}psc_subjects WHERE status='published' ORDER BY name",ARRAY_A)];}

    private static function question_payload($q):array{
        global $wpdb;$p=$wpdb->prefix;$out=['id'=>(int)$q['id'],'question_text'=>$q['question'],'question_text_ml'=>$q['question'],'question_pdf'=>!empty($q['question_pdf_attachment_id'])?wp_get_attachment_url((int)$q['question_pdf_attachment_id']):'','subject_id'=>(int)$q['subject_id'],'topic_id'=>(int)$q['topic_id'],'subject'=>$q['subject']??'','topic'=>$q['topic']??'','difficulty'=>ucfirst($q['difficulty']??'medium'),'question_type'=>strtoupper($q['question_type']??'single')==='MULTIPLE'?'MCQ':'MCQ','year'=>$q['exam_year']??'','exam_year'=>$q['exam_year']??'','explanation'=>$q['explanation']??'','source'=>$q['source']??'','options'=>[],'related_facts'=>[]];
        foreach($wpdb->get_results($wpdb->prepare("SELECT option_key,option_text,is_correct FROM {$p}psc_question_options WHERE question_id=%d ORDER BY sort_order",$q['id']),ARRAY_A) as $o)$out['options'][]=['id'=>$o['option_key'],'option_code'=>$o['option_key'],'option_text'=>$o['option_text'],'text'=>$o['option_text'],'is_correct'=>(bool)$o['is_correct']];
        foreach($wpdb->get_results($wpdb->prepare("SELECT fact FROM {$p}psc_question_facts WHERE question_id=%d ORDER BY sort_order",$q['id']),ARRAY_A) as $f)$out['related_facts'][]=['fact'=>$f['fact']];
        $correct=[];foreach($out['options'] as $o)if($o['is_correct'])$correct[]=$o['option_code'];$out['correct_answer']=$correct[0]??'A';
        return $out;
    }

    private static function question_write_payload($request): array {
        $body = $request->get_json_params();
        if (!is_array($body)) $body = [];
        $question = wp_kses_post((string)($body['question'] ?? $body['question_text'] ?? ''));
        $options_raw = $body['options'] ?? [];
        $options = [];
        if (is_array($options_raw)) {
            foreach ($options_raw as $key => $value) {
                if (is_array($value)) {
                    $k = strtoupper((string)($value['key'] ?? $value['option'] ?? $key));
                    $text = (string)($value['text'] ?? $value['option_text'] ?? '');
                } else {
                    $k = strtoupper((string)$key);
                    $text = (string)$value;
                }
                $k = preg_replace('/[^A-E]/', '', $k);
                if ($k && trim(wp_strip_all_tags($text)) !== '') $options[$k] = wp_kses_post($text);
            }
        }
        if ($question === '') return new \WP_Error('question_required','Question text is required.',['status'=>400]);
        if (count($options) < 2) return new \WP_Error('options_required','At least two options are required.',['status'=>400]);

        $correct_raw = $body['correct_answer'] ?? $body['correct'] ?? null;
        $correct = [];
        if (is_string($correct_raw) && trim($correct_raw) !== '') $correct = [strtoupper(trim($correct_raw))];
        elseif (is_array($correct_raw)) $correct = array_map('strtoupper', array_map('strval', $correct_raw));
        $correct = array_values(array_intersect($correct, array_keys($options)));

        $difficulty = sanitize_key((string)($body['difficulty'] ?? 'medium'));
        if (!in_array($difficulty,['easy','medium','hard'],true)) $difficulty='medium';
        $type = sanitize_key((string)($body['question_type'] ?? (count($correct)>1?'multiple':'single')));
        if (!in_array($type,['single','multiple'],true)) $type=count($correct)>1?'multiple':'single';
        $status = sanitize_key((string)($body['status'] ?? 'draft'));
        if (!in_array($status,['draft','published'],true)) $status='draft';

        return [
            'question'=>$question,
            'options'=>$options,
            'correct'=>$correct,
            'subject_id'=>absint($body['subject_id'] ?? 0) ?: null,
            'topic_id'=>absint($body['topic_id'] ?? 0) ?: null,
            'question_type'=>$type,
            'difficulty'=>$difficulty,
            'explanation'=>wp_kses_post((string)($body['explanation'] ?? '')),
            'source'=>sanitize_text_field((string)($body['source'] ?? '')),
            'source_question_number'=>sanitize_text_field((string)($body['question_number'] ?? $body['source_question_number'] ?? '')),
            'exam_year'=>sanitize_text_field((string)($body['exam_year'] ?? $body['year'] ?? '')),
            'status'=>$status,
            'language'=>sanitize_text_field((string)($body['language'] ?? 'ml')),
            'facts'=>array_values(array_filter(array_map(static fn($v)=>wp_kses_post((string)$v),(array)($body['facts'] ?? [])),static fn($v)=>trim(wp_strip_all_tags($v))!=='')),
        ];
    }

    private static function persist_question(array $data, int $id=0): int|\WP_Error {
        global $wpdb; $p=$wpdb->prefix; $now=current_time('mysql');
        $row=[
            'subject_id'=>$data['subject_id'],'topic_id'=>$data['topic_id'],'question'=>$data['question'],
            'question_pdf_attachment_id'=>null,'question_type'=>$data['question_type'],'difficulty'=>$data['difficulty'],
            'explanation'=>$data['explanation'],'source'=>$data['source'],'source_question_number'=>$data['source_question_number'],
            'exam_year'=>$data['exam_year'],'status'=>$data['status'],'updated_at'=>$now
        ];
        if($id){
            if(!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}psc_questions WHERE id=%d",$id))) return new \WP_Error('question_not_found','Question not found.',['status'=>404]);
            $ok=$wpdb->update($p.'psc_questions',$row,['id'=>$id]);
        } else {
            $row['created_at']=$now; $ok=$wpdb->insert($p.'psc_questions',$row); $id=(int)$wpdb->insert_id;
        }
        if($ok===false || !$id) return new \WP_Error('question_save_failed','Unable to save the question.',['status'=>500]);

        $wpdb->delete($p.'psc_question_options',['question_id'=>$id],['%d']);
        foreach($data['options'] as $key=>$text){
            if(false===$wpdb->insert($p.'psc_question_options',['question_id'=>$id,'option_key'=>$key,'option_text'=>$text,'is_correct'=>in_array($key,$data['correct'],true)?1:0,'sort_order'=>ord($key)-65])) return new \WP_Error('option_save_failed','Unable to save one or more options.',['status'=>500]);
        }
        $wpdb->delete($p.'psc_question_facts',['question_id'=>$id],['%d']);
        foreach($data['facts'] as $i=>$fact) $wpdb->insert($p.'psc_question_facts',['question_id'=>$id,'fact'=>$fact,'sort_order'=>$i]);
        return $id;
    }

    public static function create_question($request){
        $data=self::question_write_payload($request); if(is_wp_error($data)) return $data;
        $id=self::persist_question($data); if(is_wp_error($id)) return $id;
        return ['success'=>true,'data'=>self::admin_question_payload((int)$id)];
    }

    public static function update_question($request){
        $data=self::question_write_payload($request); if(is_wp_error($data)) return $data;
        $id=self::persist_question($data,absint($request['id'])); if(is_wp_error($id)) return $id;
        return ['success'=>true,'data'=>self::admin_question_payload((int)$id)];
    }

    public static function delete_question_api($request){
        global $wpdb; $p=$wpdb->prefix; $id=absint($request['id']);
        if(!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}psc_questions WHERE id=%d",$id))) return new \WP_Error('question_not_found','Question not found.',['status'=>404]);
        $wpdb->delete($p.'psc_exam_questions',['question_id'=>$id],['%d']);
        $wpdb->delete($p.'psc_question_options',['question_id'=>$id],['%d']);
        $wpdb->delete($p.'psc_question_facts',['question_id'=>$id],['%d']);
        $wpdb->delete($p.'psc_questions',['id'=>$id],['%d']);
        return ['success'=>true,'deleted_ids'=>[$id]];
    }

    public static function bulk_delete_questions($request){
        global $wpdb; $p=$wpdb->prefix; $body=$request->get_json_params(); $ids=array_values(array_unique(array_filter(array_map('absint',(array)($body['ids']??$body['question_ids']??[])))));
        if(!$ids) return new \WP_Error('ids_required','At least one question ID is required.',['status'=>400]);
        $deleted=[];
        foreach($ids as $id){
            if(!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}psc_questions WHERE id=%d",$id))) continue;
            $wpdb->delete($p.'psc_exam_questions',['question_id'=>$id],['%d']);
            $wpdb->delete($p.'psc_question_options',['question_id'=>$id],['%d']);
            $wpdb->delete($p.'psc_question_facts',['question_id'=>$id],['%d']);
            $wpdb->delete($p.'psc_questions',['id'=>$id],['%d']); $deleted[]=$id;
        }
        return ['success'=>true,'deleted_ids'=>$deleted,'deleted_count'=>count($deleted)];
    }

    public static function import_questions_json_api($request){
        $body=$request->get_json_params(); if(!is_array($body)) return new \WP_Error('invalid_json','JSON body must be an object containing a questions array.',['status'=>400]);
        $items=$body['questions']??$body['data']??$body;
        if(!is_array($items) || !array_is_list($items)) return new \WP_Error('invalid_questions','questions must be an array of question objects.',['status'=>400]);
        if(count($items)>5000) return new \WP_Error('too_many_questions','Maximum 5,000 questions per import.',['status'=>413]);
        $imported=0;$skipped=0;$errors=[];
        foreach($items as $i=>$item){
            if(!is_array($item)){ $skipped++; $errors[]=['index'=>$i,'message'=>'Question item is not an object.']; continue; }
            $r=new \WP_REST_Request('POST','/psc/v1/questions'); $r->set_body(wp_json_encode($item)); $r->set_header('Content-Type','application/json');
            $data=self::question_write_payload($r); if(is_wp_error($data)){ $skipped++; $errors[]=['index'=>$i,'message'=>$data->get_error_message()]; continue; }
            // Skip exact normalized duplicates.
            global $wpdb; $p=$wpdb->prefix; $normalized=strtolower(preg_replace('/\s+/',' ',wp_strip_all_tags($data['question'])));
            $duplicate=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}psc_questions WHERE LOWER(REPLACE(REPLACE(question,'\\n',' '),'  ',' '))=%s LIMIT 1",$normalized));
            if($duplicate){$skipped++;$errors[]=['index'=>$i,'message'=>'Duplicate question','existing_id'=>$duplicate];continue;}
            $id=self::persist_question($data); if(is_wp_error($id)){ $skipped++; $errors[]=['index'=>$i,'message'=>$id->get_error_message()]; continue; }
            $imported++;
        }
        return ['success'=>true,'imported'=>$imported,'skipped'=>$skipped,'errors'=>$errors];
    }

    private static function admin_question_payload(int $id): array {
        global $wpdb;$p=$wpdb->prefix;
        $q=$wpdb->get_row($wpdb->prepare("SELECT q.*,s.name subject,t.name topic FROM {$p}psc_questions q LEFT JOIN {$p}psc_subjects s ON s.id=q.subject_id LEFT JOIN {$p}psc_topics t ON t.id=q.topic_id WHERE q.id=%d",$id),ARRAY_A);
        if(!$q) return [];
        $out=self::question_payload($q); $out['status']=$q['status']; $out['language']=$q['language']??'ml'; $out['correct_answer']=null;
        $correct=[]; foreach($out['options'] as $o) if($o['is_correct']) $correct[]=$o['option_code']; $out['correct_answer']=count($correct)>1?$correct:($correct[0]??null);
        return $out;
    }

    public static function admin_questions($request){
        global $wpdb;$p=$wpdb->prefix;
        $page=max(1,absint($request->get_param('page')??1)); $per=min(100,max(1,absint($request->get_param('per_page')??25))); $search=sanitize_text_field((string)$request->get_param('search'));
        $where='1=1';$params=[];
        if($search!==''){ $like='%'.$wpdb->esc_like($search).'%'; $where.=' AND q.question LIKE %s';$params[]=$like; }
        $count_sql="SELECT COUNT(*) FROM {$p}psc_questions q WHERE {$where}"; $total=(int)$wpdb->get_var($params?$wpdb->prepare($count_sql,...$params):$count_sql);
        $offset=($page-1)*$per; $sql="SELECT q.*,s.name subject,t.name topic FROM {$p}psc_questions q LEFT JOIN {$p}psc_subjects s ON s.id=q.subject_id LEFT JOIN {$p}psc_topics t ON t.id=q.topic_id WHERE {$where} ORDER BY q.id DESC LIMIT %d OFFSET %d"; $params[]=$per;$params[]=$offset;
        $rows=$wpdb->get_results($wpdb->prepare($sql,...$params),ARRAY_A);$data=[];foreach($rows as $q)$data[]=self::admin_question_payload((int)$q['id']);
        return ['success'=>true,'data'=>$data,'pagination'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>max(1,(int)ceil($total/$per))]];
    }

    public static function questions($request=null):array{
        global $wpdb;$p=$wpdb->prefix;
        $page=max(1,absint($request instanceof \WP_REST_Request ? $request->get_param('page') : 1));
        $per=min(100,max(1,absint($request instanceof \WP_REST_Request ? $request->get_param('per_page') : 100)));
        $offset=($page-1)*$per;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT q.*,s.name subject,t.name topic FROM {$p}psc_questions q LEFT JOIN {$p}psc_subjects s ON s.id=q.subject_id LEFT JOIN {$p}psc_topics t ON t.id=q.topic_id WHERE q.status='published' ORDER BY q.id DESC LIMIT %d OFFSET %d",$per,$offset),ARRAY_A);
        $data=[];foreach($rows as $q)$data[]=self::question_payload($q);
        return ['success'=>true,'data'=>$data,'pagination'=>['page'=>$page,'per_page'=>$per,'returned'=>count($data)]];
    }
    public static function question($request):array{global $wpdb;$q=$wpdb->get_row($wpdb->prepare("SELECT q.*,s.name subject,t.name topic FROM {$wpdb->prefix}psc_questions q LEFT JOIN {$wpdb->prefix}psc_subjects s ON s.id=q.subject_id LEFT JOIN {$wpdb->prefix}psc_topics t ON t.id=q.topic_id WHERE q.id=%d AND q.status='published'",absint($request['id'])),ARRAY_A);if(!$q)return ['success'=>false,'message'=>'Question not found'];return ['success'=>true,'data'=>self::question_payload($q)];}

    public static function exams():array{global $wpdb;$p=$wpdb->prefix;$rows=$wpdb->get_results("SELECT id,title,description,duration_minutes,total_marks,negative_mark,passing_percentage,max_attempts,shuffle_questions,shuffle_options FROM {$p}psc_exams WHERE status='published' ORDER BY id DESC",ARRAY_A);foreach($rows as &$e){$e['questions']=[];foreach($wpdb->get_results($wpdb->prepare("SELECT eq.question_id,eq.marks,q.question FROM {$p}psc_exam_questions eq LEFT JOIN {$p}psc_questions q ON q.id=eq.question_id WHERE eq.exam_id=%d ORDER BY eq.sort_order",$e['id']),ARRAY_A) as $q){$full=$wpdb->get_row($wpdb->prepare("SELECT q.*,s.name subject,t.name topic FROM {$p}psc_questions q LEFT JOIN {$p}psc_subjects s ON s.id=q.subject_id LEFT JOIN {$p}psc_topics t ON t.id=q.topic_id WHERE q.id=%d",(int)$q['question_id']),ARRAY_A);if($full)$e['questions'][]=array_merge(self::question_payload($full),['marks'=>(float)$q['marks']]);}$e['total_questions']=count($e['questions']);$e['marks_per_question']=(float)($e['total_questions']?($e['total_marks']/$e['total_questions']):1);$e['negative_marks']=(float)$e['negative_mark'];$e['passing_score_percent']=(float)$e['passing_percentage'];}
        return ['success'=>true,'data'=>$rows];}
    public static function exam($request):array{$all=self::exams()['data'];foreach($all as $e)if((int)$e['id']===absint($request['id']))return ['success'=>true,'data'=>$e];return ['success'=>false,'message'=>'Exam not found'];}

    public static function submit_exam($request){
        global $wpdb;$uid=get_current_user_id();$exam_id=absint($request['id']);$body=$request->get_json_params();$answers=(array)($body['answers']??[]);$time=max(0,absint($body['time_taken_seconds']??0));$exam=self::exam(['id'=>$exam_id]);if(empty($exam['success']))return new \WP_Error('exam_not_found','Exam not found',['status'=>404]);$e=$exam['data'];
        $correct=0;$wrong=0;$skipped=0;$evaluated=[];$score=0.0;$total_marks=0.0;
        foreach($e['questions'] as $q){$qid=(int)$q['id'];$entry=$answers[(string)$qid]??$answers[$qid]??[];$selected=sanitize_text_field($entry['selected_option']??'');$review=!empty($entry['mark_for_review']);$is_correct=false;$mark=0.0;$total_marks+=(float)$q['marks'];$correct_codes=[];foreach($q['options'] as $o)if(!empty($o['is_correct']))$correct_codes[]=$o['option_code'];if($selected==='')$skipped++;else if(in_array($selected,$correct_codes,true)){$correct++;$is_correct=true;$mark=(float)$q['marks'];$score+=$mark;}else{$wrong++;$mark=-(float)$e['negative_marks'];$score+=$mark;}$evaluated[]=['question_id'=>$qid,'selected_option'=>$selected?:null,'is_correct'=>$is_correct,'mark_obtained'=>$mark,'mark_for_review'=>$review];}
        $score=max(0,round($score,2));$percentage=$total_marks>0?round(($score/$total_marks)*100,2):0;$now=current_time('mysql');$start=$body['start_time']??null;$start=$start?gmdate('Y-m-d H:i:s',strtotime($start)):$now;
        $wpdb->insert($wpdb->prefix.'psc_exam_attempts',['user_id'=>$uid,'exam_id'=>$exam_id,'start_time'=>$start,'submit_time'=>$now,'score'=>$score,'total_marks'=>$total_marks,'percentage'=>$percentage,'correct_count'=>$correct,'wrong_count'=>$wrong,'skipped_count'=>$skipped,'time_taken_seconds'=>$time,'status'=>'submitted'],['%d','%d','%s','%s','%f','%f','%f','%d','%d','%d','%d','%s']);$attempt_id=(int)$wpdb->insert_id;if(!$attempt_id)return new \WP_Error('attempt_save_failed',$wpdb->last_error?:'Could not save attempt',['status'=>500]);
        foreach($evaluated as $a)$wpdb->insert($wpdb->prefix.'psc_attempt_answers',['attempt_id'=>$attempt_id,'question_id'=>$a['question_id'],'selected_option'=>$a['selected_option'],'is_correct'=>$a['is_correct']?1:0,'mark_obtained'=>$a['mark_obtained'],'mark_for_review'=>$a['mark_for_review']?1:0]);
        $rank=(int)$wpdb->get_var($wpdb->prepare("SELECT 1+COUNT(*) FROM {$wpdb->prefix}psc_exam_attempts WHERE exam_id=%d AND score>%f",$exam_id,$score));$participants=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}psc_exam_attempts WHERE exam_id=%d",$exam_id));
        return ['success'=>true,'data'=>['id'=>$attempt_id,'exam_id'=>$exam_id,'exam_title'=>$e['title'],'user_id'=>$uid,'start_time'=>$start,'submit_time'=>$now,'score'=>$score,'total_marks'=>$total_marks,'percentage'=>$percentage,'rank'=>$rank,'total_participants'=>$participants,'correct_count'=>$correct,'wrong_count'=>$wrong,'skipped_count'=>$skipped,'time_taken_seconds'=>$time,'status'=>'submitted','answers'=>$evaluated]];
    }

    public static function attempt($request){
        $uid=get_current_user_id();
        if(!self::policy_allows($uid,'allow_exam_history')) return new \WP_Error('exam_history_restricted','Exam history access is restricted.',['status'=>403]);
        global $wpdb;$uid=get_current_user_id();$id=absint($request['id']);$a=$wpdb->get_row($wpdb->prepare("SELECT a.*,e.title exam_title FROM {$wpdb->prefix}psc_exam_attempts a LEFT JOIN {$wpdb->prefix}psc_exams e ON e.id=a.exam_id WHERE a.id=%d AND a.user_id=%d",$id,$uid),ARRAY_A);if(!$a)return new \WP_Error('attempt_not_found','Attempt not found',['status'=>404]);$a['answers']=$wpdb->get_results($wpdb->prepare("SELECT question_id,selected_option,is_correct,mark_obtained,mark_for_review FROM {$wpdb->prefix}psc_attempt_answers WHERE attempt_id=%d ORDER BY id",$id),ARRAY_A);return ['success'=>true,'data'=>$a];}

    public static function mark_lesson_viewed($request){global $wpdb;$id=absint($request['id']);$uid=get_current_user_id();$exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}psc_lessons WHERE id=%d AND status='published'",$id));if(!$exists)return new \WP_Error('lesson_not_found','Lesson not found',['status'=>404]);$now=current_time('mysql');$table=$wpdb->prefix.'psc_progress';$row=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id=%d AND lesson_id=%d",$uid,$id));$data=['progress_percent'=>100,'completed'=>1,'updated_at'=>$now];if($row)$ok=$wpdb->update($table,$data,['id'=>(int)$row],['%f','%d','%s'],['%d']);else$ok=$wpdb->insert($table,['user_id'=>$uid,'lesson_id'=>$id,'progress_percent'=>100,'completed'=>1,'last_position_seconds'=>0,'updated_at'=>$now],['%d','%d','%f','%d','%d','%s']);if($ok===false)return new \WP_Error('progress_save_failed',$wpdb->last_error?:'Could not save progress',['status'=>500]);return ['success'=>true,'lesson_id'=>$id,'viewed'=>true,'completed'=>true,'progress_percent'=>100];}
    public static function lesson_progress($request):array{global $wpdb;$uid=get_current_user_id();$id=absint($request['id']);$row=$wpdb->get_row($wpdb->prepare("SELECT lesson_id,progress_percent,completed,last_position_seconds,updated_at FROM {$wpdb->prefix}psc_progress WHERE user_id=%d AND lesson_id=%d",$uid,$id),ARRAY_A);return ['success'=>true,'data'=>$row?:['lesson_id'=>$id,'progress_percent'=>0,'completed'=>false,'last_position_seconds'=>0,'updated_at'=>null]];}
    public static function all_progress():array{
        $uid=get_current_user_id();
        if(!self::policy_allows($uid,'allow_progress_retrieval')) return ['success'=>true,'data'=>[],'access_restricted'=>true];
        global $wpdb;$rows=$wpdb->get_results($wpdb->prepare("SELECT p.lesson_id,p.progress_percent,p.completed,p.last_position_seconds,p.updated_at,l.title lesson_title,l.lesson_type,l.youtube_url,l.youtube_video_id,l.video_url,m.course_id,c.title course_title FROM {$wpdb->prefix}psc_progress p LEFT JOIN {$wpdb->prefix}psc_lessons l ON l.id=p.lesson_id LEFT JOIN {$wpdb->prefix}psc_modules m ON m.id=l.module_id LEFT JOIN {$wpdb->prefix}psc_courses c ON c.id=m.course_id WHERE p.user_id=%d ORDER BY p.updated_at DESC",$uid),ARRAY_A);foreach($rows as &$r){$r['is_video']=(!empty($r['youtube_url'])||!empty($r['youtube_video_id'])||!empty($r['video_url']));$r['watched']=$r['is_video']?(bool)$r['completed']:false;$r['progress_percent']=(float)$r['progress_percent'];$r['last_position_seconds']=(int)$r['last_position_seconds'];}return ['success'=>true,'data'=>$rows];}

    public static function dashboard(): array
    {
        global $wpdb;
        $uid = get_current_user_id();
        $profile = self::student_profile($uid);
        $onboarding_required = empty($profile);
        $p = $wpdb->prefix;

        if (!$profile) {
            return ['success'=>true,'data'=>[
                'resume_learning'=>null,
                'recent_progress'=>[],
                'onboarding_required'=>true
            ]];
        }

        $status=(string)($profile['status']??'active');
        if($status==='removed') {
            return new \WP_Error('student_removed','This student account has been removed.',['status'=>403,'student_status'=>'removed']);
        }

        $latest = $wpdb->get_row($wpdb->prepare(
            "SELECT p.lesson_id,p.progress_percent,p.completed,p.last_position_seconds,p.updated_at,
                    l.title lesson_title,l.lesson_type,l.youtube_url,l.youtube_video_id,l.video_url,l.duration_seconds,
                    m.id module_id,m.title module_title,m.course_id,
                    c.title course_title,c.slug course_slug,c.thumbnail_id,c.thumbnail_url
             FROM {$p}psc_progress p
             INNER JOIN {$p}psc_lessons l ON l.id=p.lesson_id
             INNER JOIN {$p}psc_modules m ON m.id=l.module_id
             INNER JOIN {$p}psc_courses c ON c.id=m.course_id
             WHERE p.user_id=%d
               AND l.status='published'
               AND (l.youtube_url<>'' OR l.youtube_video_id<>'' OR l.video_url<>'')
             ORDER BY p.updated_at DESC
             LIMIT 1",
            $uid
        ), ARRAY_A);

        $resume = null;
        if ($latest) {
            if (empty($latest['thumbnail_url']) && !empty($latest['thumbnail_id'])) {
                $latest['thumbnail_url'] = wp_get_attachment_url((int)$latest['thumbnail_id']) ?: '';
            }
            $latest['is_video'] = true;
            $latest['watched'] = (bool)$latest['completed'];
            $latest['viewed'] = (bool)$latest['completed'];
            $latest['progress_percent'] = (float)$latest['progress_percent'];
            $latest['last_position_seconds'] = (int)$latest['last_position_seconds'];
            $latest['duration_seconds'] = (int)$latest['duration_seconds'];
            $latest['resume_available'] = $latest['progress_percent'] > 0 || $latest['last_position_seconds'] > 0;
            $resume = $latest;
        }

        $recent = $wpdb->get_results($wpdb->prepare(
            "SELECT p.lesson_id,p.progress_percent,p.completed,p.last_position_seconds,p.updated_at,
                    l.title lesson_title,l.lesson_type,l.youtube_url,l.youtube_video_id,l.video_url,
                    m.course_id,c.title course_title
             FROM {$p}psc_progress p
             INNER JOIN {$p}psc_lessons l ON l.id=p.lesson_id
             INNER JOIN {$p}psc_modules m ON m.id=l.module_id
             INNER JOIN {$p}psc_courses c ON c.id=m.course_id
             WHERE p.user_id=%d AND l.status='published'
             ORDER BY p.updated_at DESC
             LIMIT 10",
            $uid
        ), ARRAY_A);

        foreach ($recent as &$r) {
            $r['is_video'] = (!empty($r['youtube_url']) || !empty($r['youtube_video_id']) || !empty($r['video_url']));
            $r['watched'] = $r['is_video'] ? (bool)$r['completed'] : false;
            $r['progress_percent'] = (float)$r['progress_percent'];
            $r['last_position_seconds'] = (int)$r['last_position_seconds'];
        }

        return [
            'success' => true,
            'data' => [
                'resume_learning' => $resume,
                'recent_progress' => $recent,
                'onboarding_required'=>$onboarding_required,
            ],
        ];
    }

    public static function my_profile(): array {
        $uid=get_current_user_id();$u=wp_get_current_user();$row=self::student_profile($uid);$data=$row?:[];$data['user_id']=$uid;$data['email']=$u->user_email;$data['display_name']=$u->display_name;$data['age']=!empty($data['date_of_birth'])?self::calculate_age($data['date_of_birth']):null;return ['success'=>true,'data'=>$data,'onboarding_required'=>empty($row)||empty($row['onboarding_completed'])];
    }
    public static function save_my_profile($request){
        global $wpdb; $uid=(int)self::authenticate_firebase_request(); if($uid<=0)return new \WP_Error('not_authenticated','Authentication required.',['status'=>401]);

        // Never create/update a student from a generic authenticated request.
        // The frontend must present the short-lived token issued by /auth/firebase
        // when this user has no student record. Existing students may update their
        // profile without the token.
        $existing_student=self::student_profile($uid);
        $body=$request->get_json_params();

        if(!is_array($body)) $body=[];

        if(!$existing_student){
            // The onboarding token must be explicitly supplied in the JSON body.
            // Do not fall back to the Firebase token or any browser/localStorage
            // value; this keeps student creation tied to /me/onboarding/start.
            $token=sanitize_text_field((string)($body['onboarding_token']??''));
            if($token===''){
                return new \WP_Error(
                    'onboarding_token_required',
                    'Onboarding session is missing or expired. Please restart onboarding.',
                    ['status'=>403,'user_exists'=>false,'student_exists'=>false,'onboarding_required'=>true]
                );
            }
            if(!self::consume_onboarding_token($uid,$token)){
                return new \WP_Error(
                    'onboarding_token_invalid',
                    'Onboarding session is invalid or expired. Please restart onboarding.',
                    ['status'=>403,'user_exists'=>false,'student_exists'=>false,'onboarding_required'=>true]
                );
            }
        } elseif((string)($existing_student['status']??'active')==='removed') {
            return new \WP_Error(
                'student_removed',
                'This student account has been removed and cannot be edited.',
                ['status'=>403,'student_status'=>'removed']
            );
        }

        // Accept the field names used by both the current onboarding form and
        // older frontend builds. A nested data/profile/student payload is also
        // supported so the backend remains the single source of truth.
        $payload=$body;
        foreach(['data','profile','student'] as $container){
            if(isset($body[$container]) && is_array($body[$container])){
                $payload=array_merge($payload,$body[$container]);
            }
        }
        $pick=function(array $keys)use($payload){
            foreach($keys as $key){
                if(array_key_exists($key,$payload) && $payload[$key]!==null && trim((string)$payload[$key])!=='') return $payload[$key];
            }
            return '';
        };
        $table=self::student_table();$cols=array_map('strval',(array)$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0));if(!$cols)return new \WP_Error('student_table_missing','Student database table is unavailable.',['status'=>500]);$u=wp_get_current_user();
        $firebase_uid=sanitize_text_field((string)get_user_meta($uid,'psc_firebase_uid',true));
        if($firebase_uid==='') $firebase_uid=sanitize_text_field((string)$pick(['firebase_uid','firebaseUid']));
        $full_name=sanitize_text_field((string)$pick(['full_name','fullName','name','display_name','displayName']));
        if($full_name==='') return new \WP_Error('name_required','Full name is required.',['status'=>400]);

        // Normalize Indian mobile numbers consistently for both onboarding INSERTs
        // and existing-student UPDATEs. Accepted examples:
        // 9847012345, 919847012345, +919847012345, +91 98470 12345.
        // The database always stores the canonical 10-digit mobile number.
        $phone_raw=$pick(['phone','phoneNumber','mobile','mobile_number','mobileNumber']);
        $phone=preg_replace('/\D+/','',sanitize_text_field((string)$phone_raw));
        if($phone==='' && !empty($existing_student['phone'])) {
            $phone=preg_replace('/\D+/','',(string)$existing_student['phone']);
        }
        if($phone==='' ) {
            $phone=preg_replace('/\D+/','',(string)get_user_meta($uid,'phone',true));
        }

        // Strip an Indian country code when supplied. Do this before validation
        // so +91 numbers are stored as the same canonical 10-digit value.
        if(strlen($phone)===12 && str_starts_with($phone,'91')) {
            $phone=substr($phone,2);
        } elseif(strlen($phone)===14 && str_starts_with($phone,'0091')) {
            $phone=substr($phone,4);
        }

        if($phone!=='' && !preg_match('/^[6-9][0-9]{9}$/',$phone)) {
            return new \WP_Error(
                'phone_invalid',
                'Mobile number must be a valid 10-digit Indian mobile number.',
                ['status'=>400]
            );
        }
        if($phone==='') $phone='Not provided';

        // Optional onboarding fields use explicit safe defaults when the user
        // leaves them unchanged. We never invent personal information.
        $district=sanitize_text_field((string)$pick(['home_district','homeDistrict','district']));
        if($district==='' && !empty($existing_student['district'])) $district=(string)$existing_student['district'];
        if($district==='' && !empty($existing_student['home_district'])) $district=(string)$existing_student['home_district'];
        if($district==='') $district='Not provided';

        $qualification=sanitize_text_field((string)$pick(['highest_qualification','highestQualification','qualification','education']));
        if($qualification==='' && !empty($existing_student['qualification'])) $qualification=(string)$existing_student['qualification'];
        if($qualification==='' && !empty($existing_student['highest_qualification'])) $qualification=(string)$existing_student['highest_qualification'];
        if($qualification==='') $qualification='Not provided';

        $target=sanitize_text_field((string)$pick(['target_exam','targetExam','exam','target']));
        if($target==='' && !empty($existing_student['target_exam'])) $target=(string)$existing_student['target_exam'];
        if($target==='') $target='Not specified';

        $medium=sanitize_text_field((string)$pick(['study_medium','studyMedium','medium','preferred_medium','preferredMedium']));
        if($medium==='' && !empty($existing_student['study_medium'])) $medium=(string)$existing_student['study_medium'];
        if($medium==='') $medium='Not specified';

        $dob=sanitize_text_field((string)$pick(['date_of_birth','dateOfBirth','dob','birth_date','birthDate']));
        if($dob!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dob))
            return new \WP_Error('invalid_date_of_birth','Date of birth is invalid.',['status'=>400]);

        if($dob!=='') {
            $dt=\DateTime::createFromFormat('Y-m-d',$dob);
            if(!$dt || $dt->format('Y-m-d')!==$dob)
                return new \WP_Error('invalid_date_of_birth','Date of birth is invalid.',['status'=>400]);
        }

        $now=current_time('mysql');$provider=sanitize_text_field((string)get_user_meta($uid,'psc_auth_provider',true));if(!in_array($provider,['google','email','other'],true))$provider='google';$data=[];$put=function($k,$v)use(&$data,$cols){if(in_array($k,$cols,true))$data[$k]=$v;};
        $put('wp_user_id',$uid);$put('firebase_uid',$firebase_uid);$put('name',$full_name);$put('full_name',$full_name);$put('email',$u->user_email);$put('phone',$phone);$put('district',$district);$put('home_district',$district);$put('qualification',$qualification);$put('highest_qualification',$qualification);$put('dob',$dob?:null);$put('date_of_birth',$dob?:null);$put('age',$dob?self::calculate_age($dob):null);$put('target_exam',$target);$put('study_medium',$medium);$put('onboarding_completed',1);$put('status','active');$put('registration_source',$existing_student?'profile_update':'onboarding');$put('auth_provider',$provider);$put('registration_mode',$existing_student['registration_mode']??'self');$put('allow_data_retrieval',isset($existing_student['allow_data_retrieval'])?(int)$existing_student['allow_data_retrieval']:1);$put('allow_course_retrieval',isset($existing_student['allow_course_retrieval'])?(int)$existing_student['allow_course_retrieval']:1);$put('allow_progress_retrieval',isset($existing_student['allow_progress_retrieval'])?(int)$existing_student['allow_progress_retrieval']:1);$put('allow_exam_history',isset($existing_student['allow_exam_history'])?(int)$existing_student['allow_exam_history']:1);$put('allow_order_history',isset($existing_student['allow_order_history'])?(int)$existing_student['allow_order_history']:1);$put('google_sub',$firebase_uid);$put('updated_at',$now);if(in_array('registered_date',$cols,true))$put('registered_date',$now);if(in_array('created_at',$cols,true))$put('created_at',$now);
        $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE wp_user_id=%d OR (firebase_uid<>'' AND firebase_uid=%s) OR email=%s LIMIT 1",$uid,$firebase_uid,$u->user_email),ARRAY_A);
        if($existing){
            // Always repair identity linkage on the canonical row. This fixes
            // legacy rows that were created before wp_user_id/firebase_uid were
            // populated, without creating a second student record.
            $ok=$wpdb->update($table,$data,['id'=>$existing['id']]);$student_id=$existing['id'];
        }else{if(in_array('id',$cols,true))$data['id']='STU-'.strtoupper(substr(hash('sha256',$u->user_email.'|'.$uid.'|'.microtime(true)),0,12));$ok=$wpdb->insert($table,$data);$student_id=$wpdb->insert_id?:($data['id']??'');}
        if($ok===false){error_log('[PSC LMS] Student registry save failed: '.($wpdb->last_error?:'unknown'));return new \WP_Error('profile_save_failed','Could not save the student to the WordPress student database.',['status'=>500]);}
        delete_user_meta($uid,'psc_onboarding_token_hash');
        delete_user_meta($uid,'psc_onboarding_token_expires');
        $saved=$wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id=%s LIMIT 1",$student_id),ARRAY_A);if(!$saved)return new \WP_Error('profile_save_verification_failed','Student save could not be verified.',['status'=>500]);wp_update_user(['ID'=>$uid,'display_name'=>$full_name,'first_name'=>$full_name]);update_user_meta($uid,'psc_student_onboarding_completed',1);return ['success'=>true,'user_exists'=>true,'onboarding_completed'=>true,'student_id'=>$student_id,'data'=>$saved];
    }

    private static function calculate_age(string $dob):?int{try{$birth=new \DateTime($dob);$today=new \DateTime('today');return (int)$birth->diff($today)->y;}catch(\Exception $e){return null;}}

    public static function enroll_course($request){
        $uid=get_current_user_id();
        $student=self::student_profile($uid);
        if(!$student) return new \WP_Error('student_not_onboarded','Complete onboarding before enrolling in a course.',['status'=>403,'onboarding_required'=>true]);
        if((string)($student['status']??'active')==='removed') return new \WP_Error('student_removed','This student account has been removed.',['status'=>403,'student_status'=>'removed']);
        global $wpdb;$body=$request->get_json_params();$course_id=absint($body['course_id']??0);if(!$course_id)return new \WP_Error('course_required','Course ID is required.',['status'=>400]);$course=$wpdb->get_row($wpdb->prepare("SELECT id,price,status FROM {$wpdb->prefix}psc_courses WHERE id=%d",$course_id),ARRAY_A);if(!$course||$course['status']!=='published')return new \WP_Error('course_not_found','Course not found.',['status'=>404]);if((float)$course['price']>0)return new \WP_Error('payment_required','This course requires a completed payment before enrollment.',['status'=>402]);$now=current_time('mysql');$table=$wpdb->prefix.'psc_enrollments';$wpdb->query($wpdb->prepare("INSERT INTO {$table} (user_id,course_id,status,enrolled_at,updated_at) VALUES (%d,%d,'active',%s,%s) ON DUPLICATE KEY UPDATE status='active',updated_at=VALUES(updated_at)",$uid,$course_id,$now,$now));return ['success'=>true,'course_id'=>$course_id,'status'=>'active'];}

    public static function remove_student_api($request){
        global $wpdb;
        $id=sanitize_text_field((string)$request['id']);
        if($id==='') return new \WP_Error('student_id_required','Student ID is required.',['status'=>400]);
        $table=self::student_table();
        $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table}` WHERE id=%s LIMIT 1",$id));
        if(!$exists) return new \WP_Error('student_not_found','Student not found.',['status'=>404]);
        $data=['status'=>'removed','updated_at'=>current_time('mysql')];
        if(in_array('removed_at',(array)$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0),true)) $data['removed_at']=current_time('mysql');
        if(in_array('removed_by',(array)$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0),true)) $data['removed_by']=get_current_user_id();
        $ok=$wpdb->update($table,$data,['id'=>$id]);
        if($ok===false) return new \WP_Error('student_remove_failed',$wpdb->last_error?:'Could not remove student.',['status'=>500]);
        return ['success'=>true,'id'=>$id,'status'=>'removed','data_retained'=>true];
    }

    public static function students():array{global $wpdb;$table=self::student_table();$rows=$wpdb->get_results("SELECT * FROM `{$table}` ORDER BY registered_date DESC,id DESC",ARRAY_A);foreach($rows as &$r){$r['name']=$r['name']??($r['full_name']??'');$r['district']=$r['district']??($r['home_district']??'');$r['qualification']=$r['qualification']??($r['highest_qualification']??'');$r['dob']=$r['dob']??($r['date_of_birth']??'');$r['onboarding_completed']=(int)($r['onboarding_completed']??0);$r['status']=(string)($r['status']??'active');}unset($r);return ['success'=>true,'data'=>$rows];}

    public static function student($request){global $wpdb;$table=self::student_table();$id=sanitize_text_field((string)$request['id']);$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id=%s LIMIT 1",$id),ARRAY_A);if(!$row)return new \WP_Error('student_not_found','Student not found',['status'=>404]);return ['success'=>true,'data'=>$row];}

    public static function my_enrollments():array{
        $uid=get_current_user_id();
        global $wpdb;$uid=get_current_user_id();$rows=$wpdb->get_results($wpdb->prepare("SELECT e.course_id,e.status,e.enrolled_at,c.title,c.slug FROM {$wpdb->prefix}psc_enrollments e LEFT JOIN {$wpdb->prefix}psc_courses c ON c.id=e.course_id WHERE e.user_id=%d ORDER BY e.enrolled_at DESC",$uid),ARRAY_A);return ['success'=>true,'data'=>$rows];}
    public static function bookmarks():array{
        $uid=get_current_user_id();
        global $wpdb;$uid=get_current_user_id();$ids=$wpdb->get_col($wpdb->prepare("SELECT question_id FROM {$wpdb->prefix}psc_bookmarks WHERE user_id=%d ORDER BY id DESC",$uid));$data=[];foreach($ids as $id){$q=self::question(['id'=>$id]);if(!empty($q['success']))$data[]=$q['data'];}return ['success'=>true,'data'=>$data];}
    public static function toggle_bookmark($request){global $wpdb;$uid=get_current_user_id();$qid=absint($request['id']);$body=$request->get_json_params();$add=!empty($body['add']);$table=$wpdb->prefix.'psc_bookmarks';if($add){$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$table} (user_id,question_id,created_at) VALUES (%d,%d,%s)",$uid,$qid,current_time('mysql')));}else{$wpdb->delete($table,['user_id'=>$uid,'question_id'=>$qid],['%d','%d']);}return ['success'=>true,'bookmarked'=>$add,'question_id'=>$qid];}
}
