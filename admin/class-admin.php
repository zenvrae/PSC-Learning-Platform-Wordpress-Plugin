<?php
namespace PSC_LMS;
if (!defined('ABSPATH')) exit;

class Admin {
    public static function init(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_psc_save_subject', [self::class, 'save_subject']);
        add_action('admin_post_psc_delete_subject', [self::class, 'delete_subject']);
        add_action('admin_post_psc_save_topic', [self::class, 'save_topic']);
        add_action('admin_post_psc_delete_topic', [self::class, 'delete_topic']);
        add_action('admin_post_psc_save_settings', [self::class, 'save_settings']);
        add_action('admin_post_psc_save_student_profile', [self::class, 'save_student_profile']);
    }

    private static function url(string $page, array $args=[]): string {
        return add_query_arg(array_merge(['page'=>$page], $args), admin_url('admin.php'));
    }

    public static function menu(): void {
        add_menu_page('PSC LMS','PSC LMS','manage_options','psc-lms',[self::class,'dashboard'],'dashicons-welcome-learn-more',25);
        add_submenu_page('psc-lms','Dashboard','Dashboard','manage_options','psc-lms',[self::class,'dashboard']);
        add_submenu_page('psc-lms','Courses','Courses','manage_options','psc-lms-courses',[Courses_Admin::class,'render']);
        add_submenu_page('psc-lms','Subjects & Topics','Subjects & Topics','manage_options','psc-lms-subjects',[self::class,'subjects']);
        add_submenu_page('psc-lms','Questions','Questions','manage_options','psc-lms-questions',[Questions_Admin::class,'render']);
        add_submenu_page('psc-lms','Exams','Exams','manage_options','psc-lms-exams',[Exams_Admin::class,'render']);
        add_submenu_page('psc-lms','Students','Students','manage_options','psc-lms-students',[self::class,'students']);
        add_submenu_page('psc-lms','Orders','Orders','manage_options','psc-lms-orders',[self::class,'orders']);
        add_submenu_page('psc-lms','Analytics','Analytics','manage_options','psc-lms-analytics',[self::class,'analytics']);
        add_submenu_page('psc-lms','Settings','Settings','manage_options','psc-lms-settings',[self::class,'settings']);
    }

    public static function dashboard(): void {
        global $wpdb;
        $items = [
            'Courses'=>'psc_courses','Modules'=>'psc_modules','Lessons'=>'psc_lessons',
            'Questions'=>'psc_questions','Exams'=>'psc_exams','Subjects'=>'psc_subjects',
            'Students'=>'students','Enrollments'=>'psc_enrollments','Orders'=>'psc_orders'
        ];
        echo '<div class="wrap"><h1>PSC Learning Platform</h1><p>Complete backend control center for the PSC learning application.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;max-width:1250px;">';
        foreach ($items as $label=>$suffix) {
            $table=$suffix==='students'?self::student_table():($suffix==='users'?$wpdb->users:$wpdb->prefix.$suffix);
            $count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            echo '<div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:18px;"><strong style="font-size:30px;">'.esc_html($count).'</strong><div>'.esc_html($label).'</div></div>';
        }
        echo '</div><div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;margin-top:20px;max-width:1250px;"><h2>Workflow</h2><p>Courses → Modules → Lessons → Questions → Exams. PDFs are stored in the WordPress Media Library and student lesson views are recorded in progress.</p></div></div>';
    }

    public static function subjects(): void {
        global $wpdb;
        $subjects=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}psc_subjects ORDER BY name");
        $edit_id=absint($_GET['edit']??0);
        $edit=$edit_id?$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}psc_subjects WHERE id=%d",$edit_id)):null;
        echo '<div class="wrap"><h1>Subjects & Topics</h1>';
        if(isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Saved successfully.</p></div>';
        if(isset($_GET['deleted'])) echo '<div class="notice notice-success"><p>Deleted successfully.</p></div>';
        echo '<div style="background:#fff;padding:18px;border:1px solid #ddd;max-width:1000px;"><h2>'.($edit?'Edit Subject':'Add Subject').'</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('psc_save_subject'); echo '<input type="hidden" name="action" value="psc_save_subject"><input type="hidden" name="id" value="'.esc_attr($edit_id).'">';
        echo '<input name="name" required placeholder="Subject name" value="'.esc_attr($edit->name??'').'" style="width:320px"> <input name="description" placeholder="Description" value="'.esc_attr($edit->description??'').'" style="width:420px"> <select name="status"><option value="published" '.selected($edit->status??'published','published',false).'>Published</option><option value="draft" '.selected($edit->status??'','draft',false).'>Draft</option></select> <button class="button button-primary">'.($edit?'Update Subject':'Add Subject').'</button>';
        if($edit) echo ' <a class="button" href="'.esc_url(self::url('psc-lms-subjects')).'">Cancel</a>';
        echo '</form></div><h2>Subjects</h2><table class="widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        foreach($subjects as $s){
            $edit_url=self::url('psc-lms-subjects',['edit'=>$s->id]);
            $del=wp_nonce_url(add_query_arg(['action'=>'psc_delete_subject','id'=>$s->id],admin_url('admin-post.php')),'psc_delete_subject_'.$s->id);
            echo '<tr><td>'.esc_html($s->id).'</td><td>'.esc_html($s->name).'</td><td>'.esc_html($s->slug).'</td><td>'.esc_html($s->status).'</td><td><a href="'.esc_url($edit_url).'">Edit</a> | <a href="'.esc_url($del).'" onclick="return confirm(\'Delete subject and its topics?\')">Delete</a></td></tr>';
        }
        if(!$subjects) echo '<tr><td colspan="5">No subjects yet.</td></tr>';
        echo '</tbody></table><h2>Topics</h2><div style="background:#fff;padding:18px;border:1px solid #ddd;max-width:1000px;"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('psc_save_topic'); echo '<input type="hidden" name="action" value="psc_save_topic"><input type="hidden" name="id" value="0"><select name="subject_id" required><option value="">Select subject</option>';
        foreach($subjects as $s) echo '<option value="'.$s->id.'">'.esc_html($s->name).'</option>';
        echo '</select> <input name="name" required placeholder="Topic name" style="width:320px"> <input name="description" placeholder="Description" style="width:420px"> <button class="button button-primary">Add Topic</button></form></div>';
        $topics=$wpdb->get_results("SELECT t.*,s.name subject_name FROM {$wpdb->prefix}psc_topics t LEFT JOIN {$wpdb->prefix}psc_subjects s ON s.id=t.subject_id ORDER BY s.name,t.name");
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Subject</th><th>Topic</th><th>Slug</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        foreach($topics as $t){$del=wp_nonce_url(add_query_arg(['action'=>'psc_delete_topic','id'=>$t->id],admin_url('admin-post.php')),'psc_delete_topic_'.$t->id);echo '<tr><td>'.esc_html($t->id).'</td><td>'.esc_html($t->subject_name).'</td><td>'.esc_html($t->name).'</td><td>'.esc_html($t->slug).'</td><td>'.esc_html($t->status).'</td><td><a href="'.esc_url(self::url('psc-lms-subjects',['edit_topic'=>$t->id])).'">Edit</a> | <a href="'.esc_url($del).'">Delete</a></td></tr>';}
        if(!$topics) echo '<tr><td colspan="6">No topics yet.</td></tr>';
        echo '</tbody></table></div>';
    }

    public static function save_subject(): void {
        if(!current_user_can('manage_options') || !check_admin_referer('psc_save_subject')) wp_die('Access denied.');
        global $wpdb; $id=absint($_POST['id']??0); $name=sanitize_text_field(wp_unslash($_POST['name']??'')); if($name==='') wp_die('Subject name is required.');
        $data=['name'=>$name,'slug'=>sanitize_title($name),'description'=>sanitize_textarea_field(wp_unslash($_POST['description']??'')),'status'=>in_array($_POST['status']??'published',['draft','published'],true)?$_POST['status']:'published'];
        if($id)$wpdb->update($wpdb->prefix.'psc_subjects',$data,['id'=>$id]);else{$data['created_at']=current_time('mysql');$wpdb->insert($wpdb->prefix.'psc_subjects',$data);}
        wp_safe_redirect(self::url('psc-lms-subjects',['saved'=>1])); exit;
    }

    public static function delete_subject(): void {
        $id=absint($_GET['id']??0); if(!current_user_can('manage_options') || !check_admin_referer('psc_delete_subject_'.$id)) wp_die('Access denied.');
        global $wpdb; $wpdb->delete($wpdb->prefix.'psc_topics',['subject_id'=>$id],['%d']); $wpdb->delete($wpdb->prefix.'psc_subjects',['id'=>$id],['%d']);
        wp_safe_redirect(self::url('psc-lms-subjects',['deleted'=>1])); exit;
    }

    public static function save_topic(): void {
        if(!current_user_can('manage_options') || !check_admin_referer('psc_save_topic')) wp_die('Access denied.');
        global $wpdb; $id=absint($_POST['id']??0); $subject_id=absint($_POST['subject_id']??0); $name=sanitize_text_field(wp_unslash($_POST['name']??'')); if(!$subject_id||$name==='') wp_die('Subject and topic are required.');
        $data=['subject_id'=>$subject_id,'name'=>$name,'slug'=>sanitize_title($name),'description'=>sanitize_textarea_field(wp_unslash($_POST['description']??'')),'status'=>'published'];
        if($id)$wpdb->update($wpdb->prefix.'psc_topics',$data,['id'=>$id]);else{$data['created_at']=current_time('mysql');$wpdb->insert($wpdb->prefix.'psc_topics',$data);}
        wp_safe_redirect(self::url('psc-lms-subjects',['saved'=>1])); exit;
    }

    public static function delete_topic(): void {
        $id=absint($_GET['id']??0); if(!current_user_can('manage_options') || !check_admin_referer('psc_delete_topic_'.$id)) wp_die('Access denied.');
        global $wpdb; $wpdb->delete($wpdb->prefix.'psc_topics',['id'=>$id],['%d']); wp_safe_redirect(self::url('psc-lms-subjects',['deleted'=>1])); exit;
    }

    private static function student_table(): string { return Database::student_registry_table(); }

    public static function students(): void {
        global $wpdb; $table=self::student_table(); $search=sanitize_text_field(wp_unslash($_GET['s']??'')); $view=sanitize_text_field((string)($_GET['student_id']??'')); if($view!==''){self::student_detail($view);return;}
        $rows=[];$sql="SELECT * FROM `{$table}`";$params=[];if($search!==''){$like='%'.$wpdb->esc_like($search).'%';$sql.=' WHERE name LIKE %s OR email LIKE %s OR phone LIKE %s OR district LIKE %s OR qualification LIKE %s';$params=[$like,$like,$like,$like,$like];}$sql.=' ORDER BY registered_date DESC,id DESC LIMIT 500';$rows=$params?$wpdb->get_results($wpdb->prepare($sql,...$params),ARRAY_A):$wpdb->get_results($sql,ARRAY_A);
        echo '<div class="wrap"><h1>Students</h1><p>Student data is managed directly in <code>'.esc_html($table).'</code>.</p><form method="get"><input type="hidden" name="page" value="psc-lms-students"><input name="s" value="'.esc_attr($search).'" placeholder="Search name, email or phone" style="width:320px"> <button class="button">Search</button></form><div style="background:#fff;border:1px solid #ddd;padding:18px;margin-top:16px;max-width:1050px"><h2 style="margin-top:0">Add Student</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="psc_add_student">';wp_nonce_field('psc_add_student');echo '<input type="email" name="email" required placeholder="Email" style="width:260px"> <input name="full_name" placeholder="Full Name" style="width:220px"> <button class="button button-primary">Add Student</button></form></div><table class="widefat striped" style="margin-top:15px"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>District</th><th>Qualification</th><th>DOB</th><th>Onboarding</th><th>Status</th><th>Registered</th><th>Action</th></tr></thead><tbody>';
        foreach($rows as $r){$id=(string)$r['id'];$name=$r['name']??($r['full_name']??'');$url=self::url('psc-lms-students',['student_id'=>$id]);$status=(string)($r['status']??'active');$color=$status==='removed'?'#b32d2e':'#008a20';echo '<tr><td>'.esc_html($id).'</td><td><strong>'.esc_html($name).'</strong></td><td>'.esc_html($r['email']??'').'</td><td>'.esc_html($r['phone']??'').'</td><td>'.esc_html($r['district']??($r['home_district']??'')).'</td><td>'.esc_html($r['qualification']??($r['highest_qualification']??'')).'</td><td>'.esc_html($r['dob']??($r['date_of_birth']??'')).'</td><td>'.(!empty($r['onboarding_completed'])?'<span style="color:#008a20;font-weight:600">Completed</span>':'<span style="color:#b32d2e;font-weight:600">Pending</span>').'</td><td><span style="color:'.esc_attr($color).';font-weight:600">'.esc_html(ucfirst($status)).'</span></td><td>'.esc_html($r['registered_date']??($r['created_at']??'')).'</td><td><a class="button button-small" href="'.esc_url($url).'">View / Edit</a></td></tr>';}if(!$rows)echo '<tr><td colspan="11">No students found.</td></tr>';echo '</tbody></table></div>';
    }

    private static function student_detail(string $student_id): void {
        global $wpdb;$table=self::student_table();$r=$wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id=%s LIMIT 1",$student_id));if(!$r){echo '<div class="wrap"><div class="notice notice-error"><p>Student not found.</p></div></div>';return;}$wpid=(int)($r->wp_user_id??0);
        echo '<div class="wrap"><h1>Student Profile</h1><p><a href="'.esc_url(self::url('psc-lms-students')).'">← Back to Students</a></p>';if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>Student saved.</p></div>';echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="background:#fff;border:1px solid #ddd;padding:24px;max-width:900px">';wp_nonce_field('psc_save_student_profile_'.$student_id);echo '<input type="hidden" name="action" value="psc_save_student_profile"><input type="hidden" name="student_id" value="'.esc_attr($student_id).'">';echo '<p><strong>Student ID:</strong> '.esc_html($student_id).' &nbsp; <strong>WordPress User ID:</strong> '.esc_html($wpid?:'Not linked').'</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">';self::student_input('full_name','Full Name',(string)($r->name??($r->full_name??'')));self::student_input('email','Email',(string)($r->email??''),'email');self::student_input('phone','Phone',(string)($r->phone??''));self::student_input('district','District',(string)($r->district??($r->home_district??'')));self::student_input('qualification','Qualification',(string)($r->qualification??($r->highest_qualification??'')));self::student_input('dob','Date of Birth',(string)($r->dob??($r->date_of_birth??'')),'date');self::student_input('target_exam','Target Exam',(string)($r->target_exam??''));self::student_input('study_medium','Study Medium',(string)($r->study_medium??''));echo '</div><p><label><input type="checkbox" name="onboarding_completed" value="1" '.checked(!empty($r->onboarding_completed),true,false).'> <strong>Onboarding completed</strong></label></p><p><label>Status<br><select name="status"><option value="active" '.selected($r->status??'active','active',false).'>Active</option><option value="removed" '.selected($r->status??'','removed',false).'>Removed</option></select></label></p><p><button class="button button-primary">Save Student</button></p></form>';$act=$r->status==='removed'?'restore':'remove';$url=wp_nonce_url(add_query_arg(['action'=>'psc_'.$act.'_student','id'=>$student_id],admin_url('admin-post.php')),'psc_'.$act.'_student_'.$student_id);echo '<p><a class="button" href="'.esc_url($url).'" onclick="return confirm(\''.($act==='remove'?'Remove this student? Data will be retained.':'Restore this student?').'\');">'.($act==='remove'?'Remove Student':'Restore Student').'</a></p></div>';
    }

    private static function student_input(string $key,string $label,string $value,string $type='text'): void {
        echo '<p style="margin:0;"><label><strong>'.esc_html($label).'</strong><br><input type="'.esc_attr($type).'" name="'.esc_attr($key).'" value="'.esc_attr($value).'" style="width:100%;box-sizing:border-box;"></label></p>';
    }

    private static function calculate_age(string $dob): ?int {
        if(!$dob)return null; try{$birth=new \DateTime($dob);$today=new \DateTime('today');return (int)$birth->diff($today)->y;}catch(\Exception $e){return null;}
    }

    public static function save_student_profile(): void {
        $id=sanitize_text_field(wp_unslash($_POST['student_id']??''));if($id===''||!current_user_can('manage_options')||!check_admin_referer('psc_save_student_profile_'.$id))wp_die('Access denied.');global $wpdb;$table=self::student_table();$cols=array_map('strval',(array)$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0));$data=[];$put=function($k,$v)use(&$data,$cols){if(in_array($k,$cols,true))$data[$k]=$v;};$name=sanitize_text_field(wp_unslash($_POST['full_name']??''));$email=sanitize_email(wp_unslash($_POST['email']??''));$district=sanitize_text_field(wp_unslash($_POST['district']??''));$qualification=sanitize_text_field(wp_unslash($_POST['qualification']??''));$dob=sanitize_text_field(wp_unslash($_POST['dob']??''));$put('name',$name);$put('full_name',$name);$put('email',$email);$put('phone',sanitize_text_field(wp_unslash($_POST['phone']??'')));$put('district',$district);$put('home_district',$district);$put('qualification',$qualification);$put('highest_qualification',$qualification);$put('dob',$dob?:null);$put('date_of_birth',$dob?:null);$put('age',$dob?self::calculate_age($dob):null);$put('target_exam',sanitize_text_field(wp_unslash($_POST['target_exam']??'')));$put('study_medium',sanitize_text_field(wp_unslash($_POST['study_medium']??'')));$put('onboarding_completed',empty($_POST['onboarding_completed'])?0:1);$put('status',in_array($_POST['status']??'active',['active','removed'],true)?$_POST['status']:'active');$put('updated_at',current_time('mysql'));$ok=$wpdb->update($table,$data,['id'=>$id]);if($ok===false)wp_die('Could not save student: '.esc_html($wpdb->last_error));wp_safe_redirect(self::url('psc-lms-students',['student_id'=>$id,'saved'=>1]));exit;
    }
    public static function add_student(): void {
        if(!current_user_can('manage_options')||!check_admin_referer('psc_add_student'))wp_die('Access denied.');global $wpdb;$table=self::student_table();$email=sanitize_email(wp_unslash($_POST['email']??''));$name=sanitize_text_field(wp_unslash($_POST['full_name']??''));if(!$email||!is_email($email))wp_die('A valid email address is required.');$u=get_user_by('email',$email);if(!$u){$base=sanitize_user(current(explode('@',$email)),true)?:'student';$login=$base;$n=1;while(username_exists($login))$login=$base.$n++;$uid=wp_create_user($login,wp_generate_password(32,true,true),$email);if(is_wp_error($uid))wp_die($uid->get_error_message());$u=get_user_by('id',$uid);$u->set_role('subscriber');}$now=current_time('mysql');$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE wp_user_id=%d OR email=%s LIMIT 1",$u->ID,$email),ARRAY_A);$cols=array_map('strval',(array)$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0));$data=[];$put=function($k,$v)use(&$data,$cols){if(in_array($k,$cols,true))$data[$k]=$v;};$put('wp_user_id',(int)$u->ID);$put('name',$name);$put('full_name',$name);$put('email',$email);$put('status','active');$put('onboarding_completed',0);$put('registration_source','admin');$put('auth_provider','email');$put('updated_at',$now);if($existing){$id=$existing['id'];$ok=$wpdb->update($table,$data,['id'=>$id]);}else{$id='STU-'.strtoupper(substr(hash('sha256',$email.'|'.microtime(true)),0,12));if(in_array('id',$cols,true))$data['id']=$id;if(in_array('registered_date',$cols,true))$data['registered_date']=$now;if(in_array('created_at',$cols,true))$data['created_at']=$now;$ok=$wpdb->insert($table,$data);}if($ok===false)wp_die('Could not add student: '.esc_html($wpdb->last_error));wp_safe_redirect(self::url('psc-lms-students',['student_id'=>$id,'saved'=>1]));exit;
    }
    public static function remove_student(): void {$id=sanitize_text_field(wp_unslash($_GET['id']??''));if($id===''||!current_user_can('manage_options')||!check_admin_referer('psc_remove_student_'.$id))wp_die('Access denied.');global $wpdb;$ok=$wpdb->update(self::student_table(),['status'=>'removed','removed_at'=>current_time('mysql'),'removed_by'=>get_current_user_id(),'updated_at'=>current_time('mysql')],['id'=>$id]);if($ok===false)wp_die('Could not remove student.');wp_safe_redirect(self::url('psc-lms-students',['student_id'=>$id,'saved'=>1]));exit;}
    public static function restore_student(): void {$id=sanitize_text_field(wp_unslash($_GET['id']??''));if($id===''||!current_user_can('manage_options')||!check_admin_referer('psc_restore_student_'.$id))wp_die('Access denied.');global $wpdb;$ok=$wpdb->update(self::student_table(),['status'=>'active','removed_at'=>null,'removed_by'=>null,'updated_at'=>current_time('mysql')],['id'=>$id]);if($ok===false)wp_die('Could not restore student.');wp_safe_redirect(self::url('psc-lms-students',['student_id'=>$id,'saved'=>1]));exit;}

    private static function table_columns(string $table): array {
        global $wpdb;
        return array_map('strval',(array)$wpdb->get_col("SHOW COLUMNS FROM {$table}",0));
    }

    public static function orders(): void {
        global $wpdb;
        $table=$wpdb->prefix.'psc_orders';
        $cols=self::table_columns($table);
        echo '<div class="wrap"><h1>Orders</h1><p>Orders stored in the PSC orders table.</p>';
        if(!$cols){echo '<div class="notice notice-warning"><p>Orders table is not available.</p></div></div>';return;}
        $preferred=array_intersect(['id','user_id','status','total','subtotal','currency','payment_status','created_at','updated_at'],$cols);
        if(!$preferred)$preferred=array_slice($cols,0,8);
        $select=implode(',',array_map(fn($c)=>"`{$c}`",$preferred));
        $rows=$wpdb->get_results("SELECT {$select} FROM {$table} ORDER BY id DESC LIMIT 100",ARRAY_A);
        echo '<table class="widefat striped"><thead><tr>';foreach($preferred as $c)echo '<th>'.esc_html(ucwords(str_replace('_',' ',$c))).'</th>';echo '</tr></thead><tbody>';
        foreach($rows as $r){echo '<tr>';foreach($preferred as $c)echo '<td>'.esc_html((string)($r[$c]??'')).'</td>';echo '</tr>';}
        if(!$rows)echo '<tr><td colspan="'.count($preferred).'">No orders yet.</td></tr>';
        echo '</tbody></table></div>';
    }

    public static function analytics(): void {
        global $wpdb;
        $stats=[
            'Courses'=>$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psc_courses"),
            'Published Courses'=>$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psc_courses WHERE status='published'"),
            'Modules'=>$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psc_modules"),
            'Lessons'=>$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psc_lessons"),
            'Questions'=>$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psc_questions"),
            'Exams'=>$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psc_exams"),
            'Viewed Lessons'=>$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psc_progress WHERE completed=1"),
        ];
        echo '<div class="wrap"><h1>Analytics</h1><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;max-width:1100px;">';
        foreach($stats as $k=>$v)echo '<div style="background:#fff;border:1px solid #ddd;padding:20px;border-radius:10px;"><strong style="font-size:28px;">'.esc_html($v).'</strong><div>'.esc_html($k).'</div></div>';
        echo '</div><div style="background:#fff;border:1px solid #ddd;padding:20px;margin-top:20px;max-width:1100px;"><h2>Recent Student Activity</h2>';
        $rows=$wpdb->get_results("SELECT p.user_id,p.lesson_id,p.progress_percent,p.completed,p.updated_at,u.display_name FROM {$wpdb->prefix}psc_progress p LEFT JOIN {$wpdb->users} u ON u.ID=p.user_id ORDER BY p.updated_at DESC LIMIT 25");
        echo '<table class="widefat striped"><thead><tr><th>Student</th><th>Lesson</th><th>Progress</th><th>Completed</th><th>Updated</th></tr></thead><tbody>';
        foreach($rows as $r)echo '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->lesson_id).'</td><td>'.esc_html($r->progress_percent).'%</td><td>'.($r->completed?'Yes':'No').'</td><td>'.esc_html($r->updated_at).'</td></tr>';
        if(!$rows)echo '<tr><td colspan="5">No student lesson activity yet.</td></tr>';
        echo '</tbody></table></div></div>';
    }

    public static function settings(): void {
        $defaults=['platform_name'=>'PSC Learning Platform','default_currency'=>'INR','default_language'=>'ml','student_progress_on_open'=>'1','api_version'=>'v1','support_email'=>''];
        foreach($defaults as $k=>$v) if(get_option('psc_lms_'.$k,null)===null) update_option('psc_lms_'.$k,$v);
        echo '<div class="wrap"><h1>PSC LMS Settings</h1>';
        if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="max-width:850px;background:#fff;padding:24px;border:1px solid #ddd;">';wp_nonce_field('psc_save_settings');echo '<input type="hidden" name="action" value="psc_save_settings">';
        self::setting_input('platform_name','Platform Name'); self::setting_input('default_currency','Default Currency'); self::setting_input('default_language','Default Language'); self::setting_input('support_email','Support Email');
        self::setting_input('youtube_api_key','YouTube Data API v3 Key'); self::setting_input('firebase_api_key','Firebase Web API Key');
        echo '<hr><h2>AI PDF Question Import</h2>';
        echo '<p>AI extraction is used to read difficult PDF layouts and Malayalam/English pages visually. The AI never sets the correct answer; the admin sets it later.</p>';
        echo '<p><label><strong>OpenAI API Key</strong><br><input type="password" name="openai_api_key" value="" autocomplete="new-password" placeholder="Leave blank to keep the saved key" style="width:100%;max-width:650px;"></label></p>';
        echo '<p><label><strong>AI Model</strong><br><select name="openai_model"><option value="gpt-5.6-luna" '.selected(get_option('psc_lms_openai_model','gpt-5.6-luna'),'gpt-5.6-luna',false).'>GPT-5.6 Luna — recommended</option><option value="gpt-5.6-terra" '.selected(get_option('psc_lms_openai_model','gpt-5.6-luna'),'gpt-5.6-terra',false).'>GPT-5.6 Terra — higher quality</option><option value="gpt-5.6-sol" '.selected(get_option('psc_lms_openai_model','gpt-5.6-luna'),'gpt-5.6-sol',false).'>GPT-5.6 Sol — highest quality</option></select></label></p>';
        echo '<p><span id="psc-ai-status">'.(\PSC_LMS\AI::enabled() ? '<strong style="color:#008a20;">AI configured</strong>' : '<strong style="color:#b32d2e;">AI not configured</strong>').'</span> <button type="button" class="button" id="psc-ai-test">Test AI Connection</button></p>';
        echo '<script>document.getElementById("psc-ai-test")?.addEventListener("click",async()=>{const s=document.getElementById("psc-ai-status");s.textContent="Testing…";const fd=new FormData();fd.append("action","psc_ai_test");fd.append("nonce","'.esc_js(wp_create_nonce('psc_ai_nonce')).'");try{const r=await fetch(ajaxurl,{method:"POST",body:fd});const j=await r.json();s.innerHTML=j.success?"<strong style=\"color:#008a20;\">"+j.data.message+"</strong>":"<strong style=\"color:#b32d2e;\">"+(j.data?.message||"Connection failed")+"</strong>";}catch(e){s.textContent=e.message;}});</script>';
        echo '<p><label><strong>Mark lesson viewed when opened</strong><br><select name="student_progress_on_open"><option value="1" '.selected(get_option('psc_lms_student_progress_on_open','1'),'1',false).'>Yes</option><option value="0" '.selected(get_option('psc_lms_student_progress_on_open','1'),'0',false).'>No</option></select></label></p>';
        echo '<p><strong>REST API</strong><br>Base namespace: <code>/wp-json/psc/'.esc_html(get_option('psc_lms_api_version','v1')).'/</code></p>';
        echo '<p><button class="button button-primary">Save Settings</button></p></form></div>';
    }

    private static function setting_input(string $key,string $label): void {
        echo '<p><label><strong>'.esc_html($label).'</strong><br><input type="text" name="'.esc_attr($key).'" value="'.esc_attr(get_option('psc_lms_'.$key,'' )).'" style="width:100%;max-width:650px;"></label></p>';
    }

    public static function save_settings(): void {
        if(!current_user_can('manage_options') || !check_admin_referer('psc_save_settings'))wp_die('Access denied.');
        $keys=['platform_name','default_currency','default_language','support_email','youtube_api_key','firebase_api_key'];
        foreach($keys as $key) update_option('psc_lms_'.$key,sanitize_text_field(wp_unslash($_POST[$key]??'')));
        $openai_key = trim((string)wp_unslash($_POST['openai_api_key'] ?? ''));
        if ($openai_key !== '') update_option('psc_lms_openai_api_key', sanitize_text_field($openai_key));
        $model = sanitize_text_field(wp_unslash($_POST['openai_model'] ?? 'gpt-5.6-luna'));
        if (!in_array($model,['gpt-5.6-luna','gpt-5.6-terra','gpt-5.6-sol'],true)) $model='gpt-5.6-luna';
        update_option('psc_lms_openai_model',$model);
        update_option('psc_lms_student_progress_on_open',empty($_POST['student_progress_on_open'])?'0':'1');
        wp_safe_redirect(self::url('psc-lms-settings',['saved'=>1]));exit;
    }
}
