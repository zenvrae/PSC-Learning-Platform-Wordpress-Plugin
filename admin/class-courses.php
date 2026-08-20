<?php
namespace PSC_LMS;
if (!defined('ABSPATH')) exit;

class Courses_Admin {
    public static function init(): void {
        add_action('admin_enqueue_scripts',[self::class,'enqueue_media']);
        add_action('admin_post_psc_save_course',[self::class,'save_course']);
        add_action('admin_post_psc_delete_course',[self::class,'delete_course']);
        add_action('admin_post_psc_save_module',[self::class,'save_module']);
        add_action('admin_post_psc_delete_module',[self::class,'delete_module']);
        add_action('admin_post_psc_save_lesson',[self::class,'save_lesson']);
        add_action('admin_post_psc_delete_lesson',[self::class,'delete_lesson']);
        add_action('admin_post_psc_import_youtube',[self::class,'import_youtube']);
        add_action('admin_post_psc_test_youtube',[self::class,'test_youtube']);
    }

    public static function enqueue_media(string $hook): void {
        if(isset($_GET['page']) && $_GET['page']==='psc-lms-courses') wp_enqueue_media();
    }

    private static function url(array $args=[]): string {
        return add_query_arg(array_merge(['page'=>'psc-lms-courses'],$args),admin_url('admin.php'));
    }

    public static function render(): void {
        if(!current_user_can('manage_options'))wp_die('Access denied.');
        $action=sanitize_key($_GET['action']??'');
        if($action==='new'||$action==='edit'){self::form(absint($_GET['id']??0));return;}
        if($action==='import_youtube'){self::import_form();return;}
        global $wpdb; $table=$wpdb->prefix.'psc_courses';
        $rows=$wpdb->get_results("SELECT * FROM {$table} ORDER BY sort_order,id DESC");
        echo '<div class="wrap"><h1>Courses <a class="page-title-action" href="'.esc_url(self::url(['action'=>'new'])).'">Add New</a> <a class="page-title-action" href="'.esc_url(self::url(['action'=>'import_youtube'])).'">Import YouTube Playlist</a></h1>';
        if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>'.esc_html($_GET['saved']==='course'?'Course saved.':($_GET['saved']==='module'?'Module saved.':'Lesson saved.')).'</p></div>';
        if(isset($_GET['deleted']))echo '<div class="notice notice-success"><p>Deleted successfully.</p></div>';
        if(isset($_GET['error']))echo '<div class="notice notice-error"><p>'.esc_html(sanitize_text_field(wp_unslash($_GET['error']))).'</p></div>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Title</th><th>Pricing</th><th>Price</th><th>Categories</th><th>Difficulty</th><th>Language</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        foreach($rows as $r){
            $edit=self::url(['action'=>'edit','id'=>$r->id]);
            $del=wp_nonce_url(add_query_arg(['action'=>'psc_delete_course','id'=>$r->id],admin_url('admin-post.php')),'psc_delete_course_'.$r->id);
            echo '<tr><td>'.esc_html($r->id).'</td><td><strong>'.esc_html($r->title).'</strong></td><td>'.esc_html($r->pricing_type).'</td><td>'.esc_html($r->price).' '.esc_html($r->currency).'</td><td>'.esc_html(self::category_labels($r->categories??'' )).'</td><td>'.esc_html(self::difficulty_label($r->difficulty??'all_levels')).'</td><td>'.esc_html($r->language).'</td><td>'.esc_html($r->status).'</td><td><a href="'.esc_url($edit).'">Edit</a> | <a href="'.esc_url($del).'" onclick="return confirm(\'Delete course, modules and lessons?\')">Delete</a></td></tr>';
        }
        if(!$rows)echo '<tr><td colspan="9">No courses yet. Click Add New.</td></tr>';
        echo '</tbody></table></div>';
    }

    public static function form(int $id): void {
        global $wpdb;
        $courses=$wpdb->prefix.'psc_courses'; $modules=$wpdb->prefix.'psc_modules'; $lessons=$wpdb->prefix.'psc_lessons';
        $c=$id?$wpdb->get_row($wpdb->prepare("SELECT * FROM {$courses} WHERE id=%d",$id)):null;
        echo '<div class="wrap"><h1>'.($c?'Edit Course':'Add New Course').'</h1><p><a href="'.esc_url(self::url()).'">← Back to Courses</a></p>';
        if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>Saved. You are still on this course page.</p></div>';
        if(isset($_GET['error']))echo '<div class="notice notice-error"><p>'.esc_html(sanitize_text_field(wp_unslash($_GET['error']))).'</p></div>';
        echo '<div style="background:#fff;padding:22px;border:1px solid #ddd;max-width:1050px;"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('psc_save_course');echo '<input type="hidden" name="action" value="psc_save_course"><input type="hidden" name="id" value="'.esc_attr($id).'">';
        self::input('title','Course Title',$c->title??'','text',true);self::input('slug','Slug',$c->slug??'');self::textarea('short_description','Short Description',$c->short_description??'');self::textarea('description','Full Description',$c->description??'',8);
        echo '<p><strong>Course Thumbnail</strong><br><input type="hidden" name="thumbnail_id" id="psc-course-thumb-id" value="'.esc_attr($c->thumbnail_id??'').'"><button type="button" class="button" id="psc-select-thumb">Upload / Select Image</button> <span id="psc-course-thumb-name">'.esc_html($c&&$c->thumbnail_id?get_the_title($c->thumbnail_id):'No image selected').'</span></p>';
        echo '<p><label><strong>Pricing</strong><br><select name="pricing_type"><option value="free" '.selected($c->pricing_type??'free','free',false).'>Free</option><option value="paid" '.selected($c->pricing_type??'','paid',false).'>Paid</option></select></label></p>';
        self::input('price','Regular Price',$c->price??0,'number',false,'step="0.01"');self::input('sale_price','Sale Price',$c->sale_price??'','number',false,'step="0.01"');self::input('currency','Currency',$c->currency??'INR');self::input('language','Language',$c->language??'ml');
        $selected_categories=self::decode_categories($c->categories??'');
        echo '<div style="border:1px solid #dcdcde;padding:16px;margin:18px 0;max-width:850px;background:#fafafa;"><h3 style="margin-top:0;">Course Categories</h3><p style="margin-top:0;color:#50575e;">Choose all categories that apply to this course.</p>';
        echo '<p><label><input type="checkbox" id="psc-category-all" name="category_all" value="1" '.checked(count($selected_categories)===4,true,false).'> <strong>All</strong></label></p>';
        foreach(self::category_options() as $key=>$label){echo '<label style="display:block;margin:8px 0 8px 18px;"><input type="checkbox" class="psc-course-category" name="categories[]" value="'.esc_attr($key).'" '.checked(in_array($key,$selected_categories,true),true,false).'> '.esc_html($label).'</label>';}
        echo '</div>';
        echo '<div style="border:1px solid #dcdcde;padding:16px;margin:18px 0;max-width:850px;background:#fafafa;"><h3 style="margin-top:0;">Difficulty Level</h3>';
        foreach(self::difficulty_options() as $key=>$label){echo '<label style="display:block;margin:8px 0;"><input type="radio" name="difficulty" value="'.esc_attr($key).'" '.checked(($c->difficulty??'all_levels'),$key,false).'> '.esc_html($label).'</label>';}
        echo '</div>';
        echo '<p><label>Status<br><select name="status"><option value="draft" '.selected($c->status??'draft','draft',false).'>Draft</option><option value="published" '.selected($c->status??'','published',false).'>Published</option></select></label></p>';
        echo '<p><label><input type="checkbox" name="featured" value="1" '.checked($c->featured??0,1,false).'> Featured</label></p>';self::input('sort_order','Sort Order',$c->sort_order??0,'number');
        echo '<p><button class="button button-primary button-large">Save Course</button></p></form></div>';
        echo '<div style="background:#fff;padding:22px;border:1px solid #ddd;max-width:1050px;margin-top:16px;"><h2>Import YouTube Playlist</h2><p>Import the playlist as lessons into this course. Imported titles, descriptions, video IDs, thumbnails and durations can all be edited afterward.</p><a class="button button-primary" href="'.esc_url(self::url(['action'=>'import_youtube','course_id'=>$id])).'">Import Playlist Into This Course</a></div>';

        if($c){
            echo '<hr><h2>Modules & Lessons</h2>';
            echo '<div style="background:#fff;padding:18px;border:1px solid #ddd;max-width:1100px;"><h3>Add Module</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('psc_save_module');echo '<input type="hidden" name="action" value="psc_save_module"><input type="hidden" name="id" value="0"><input type="hidden" name="course_id" value="'.$c->id.'"><input name="title" required placeholder="Module title" style="width:300px"> <input name="description" placeholder="Module description" style="width:360px"> <input type="number" name="sort_order" value="0" style="width:80px"> <button class="button button-primary">Add Module</button></form></div>';
            $mods=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$modules} WHERE course_id=%d ORDER BY sort_order,id",$c->id));
            foreach($mods as $m) self::module_card($m,$lessons,$c->id);
            if(!$mods)echo '<p>No modules yet. Add the first module above.</p>';
        }
        echo '</div>';
        self::script();
    }

    private static function module_card($m,string $lessons_table,int $course_id): void {
        global $wpdb;
        $ls=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$lessons_table} WHERE module_id=%d ORDER BY sort_order,id",$m->id));
        echo '<div style="background:#fff;border:1px solid #dcdcde;margin:15px 0;padding:18px;max-width:1100px;">';
        echo '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;"><h3 style="margin:0;">'.esc_html($m->title).'</h3><button type="button" class="button psc-edit-module" data-target="psc-module-edit-'.$m->id.'">Edit Module</button></div>';
        echo '<div id="psc-module-edit-'.$m->id.'" style="margin-top:12px;">';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
        wp_nonce_field('psc_save_module');echo '<input type="hidden" name="action" value="psc_save_module"><input type="hidden" name="id" value="'.$m->id.'"><input type="hidden" name="course_id" value="'.$course_id.'">';
        echo '<label>Module name<br><input name="title" required value="'.esc_attr($m->title).'" style="width:280px"></label><label>Description<br><input name="description" value="'.esc_attr($m->description).'" style="width:360px"></label><label>Order<br><input type="number" name="sort_order" value="'.esc_attr($m->sort_order).'" style="width:80px"></label><label>Status<br><select name="status"><option value="published" '.selected($m->status,'published',false).'>Published</option><option value="draft" '.selected($m->status,'draft',false).'>Draft</option></select></label><button class="button button-primary" style="margin-top:20px;">Save Module Name</button></form>';
        echo '</div>';
        $del=wp_nonce_url(add_query_arg(['action'=>'psc_delete_module','id'=>$m->id,'course_id'=>$course_id],admin_url('admin-post.php')),'psc_delete_module_'.$m->id);
        echo '<p><a href="'.esc_url($del).'" onclick="return confirm(\'Delete this module and all lessons?\')">Delete Module</a></p>';
        echo '<h3>Lessons</h3>';
        foreach($ls as $l) self::lesson_card($l,$m->id,$course_id);
        echo '<div style="background:#f6f7f7;padding:15px;border:1px solid #e2e4e7;"><h4>Add / Edit Lesson</h4>';
        self::lesson_form(null,$m->id,$course_id);
        echo '</div></div>';
    }

    private static function lesson_card($l,int $module_id,int $course_id): void {
        echo '<details style="border-top:1px solid #eee;padding:12px 0;"><summary><strong>'.esc_html($l->title).'</strong> — '.esc_html($l->lesson_type).' — '.($l->is_free?'Free preview':'Paid').'</summary>';
        self::lesson_form($l,$module_id,$course_id);
        $del=wp_nonce_url(add_query_arg(['action'=>'psc_delete_lesson','id'=>$l->id,'module_id'=>$module_id,'course_id'=>$course_id],admin_url('admin-post.php')),'psc_delete_lesson_'.$l->id);
        echo '<p><a href="'.esc_url($del).'" onclick="return confirm(\'Delete this lesson?\')">Delete Lesson</a></p></details>';
    }

    private static function lesson_form($l,$module_id,$course_id): void {
        $prefix='psc-lesson-'.($l?$l->id:'new-'.$module_id);
        $pdf_id=$l->pdf_attachment_id??0; $pdf_url=$pdf_id?wp_get_attachment_url((int)$pdf_id):($l->pdf_url??'');
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin-top:12px;">';
        wp_nonce_field('psc_save_lesson');echo '<input type="hidden" name="action" value="psc_save_lesson"><input type="hidden" name="id" value="'.esc_attr($l->id??0).'"><input type="hidden" name="module_id" value="'.$module_id.'"><input type="hidden" name="course_id" value="'.$course_id.'"><input type="hidden" name="youtube_video_id" value="'.esc_attr($l->youtube_video_id??'').'"><input type="hidden" name="youtube_playlist_id" value="'.esc_attr($l->youtube_playlist_id??'').'"><input type="hidden" name="youtube_playlist_item_id" value="'.esc_attr($l->youtube_playlist_item_id??'').'"><input type="hidden" name="youtube_thumbnail_url" value="'.esc_attr($l->youtube_thumbnail_url??'').'"><input type="hidden" name="imported_at" value="'.esc_attr($l->imported_at??'').'">';
        echo '<p><input name="title" required placeholder="Lesson title" value="'.esc_attr($l->title??'').'" style="width:300px"> <select name="lesson_type"><option value="text" '.selected($l->lesson_type??'text','text',false).'>Text</option><option value="youtube" '.selected($l->lesson_type??'','youtube',false).'>YouTube</option><option value="video" '.selected($l->lesson_type??'','video',false).'>Video</option><option value="audio" '.selected($l->lesson_type??'','audio',false).'>Audio</option><option value="pdf" '.selected($l->lesson_type??'','pdf',false).'>PDF</option><option value="video_pdf" '.selected($l->lesson_type??'','video_pdf',false).'>Video + PDF</option><option value="audio_pdf" '.selected($l->lesson_type??'','audio_pdf',false).'>Audio + PDF</option></select> <input type="number" name="duration_seconds" value="'.esc_attr($l->duration_seconds??0).'" placeholder="Seconds" style="width:100px"> <label><input type="checkbox" name="is_free" value="1" '.checked($l->is_free??0,1,false).'> Free preview</label></p>';
        echo '<p><input type="url" name="youtube_url" value="'.esc_attr($l->youtube_url??'').'" placeholder="YouTube URL" style="width:650px"></p>';
        if(!empty($l->youtube_video_id)) echo '<p><small>YouTube Video ID: <code>'.esc_html($l->youtube_video_id).'</code> — imported data remains fully editable.</small></p>';
        echo '<p><input type="url" name="video_url" value="'.esc_attr($l->video_url??'').'" placeholder="Direct video URL" style="width:650px"></p>';
        echo '<p><input type="url" name="audio_url" value="'.esc_attr($l->audio_url??'').'" placeholder="Audio URL" style="width:650px"></p>';
        echo '<p><textarea name="content" rows="6" placeholder="Lesson text/content" style="width:100%;max-width:850px;">'.esc_textarea($l->content??'').'</textarea></p>';
        echo '<p><strong>PDF</strong><br><input type="hidden" name="pdf_attachment_id" id="'.$prefix.'-pdf-id" value="'.esc_attr($pdf_id).'"><button type="button" class="button psc-pdf-button" data-target="'.$prefix.'-pdf-id" data-label="'.$prefix.'-pdf-label">Upload / Select PDF</button> <span id="'.$prefix.'-pdf-label">'.($pdf_url?'<a href="'.esc_url($pdf_url).'" target="_blank" rel="noopener">Current PDF</a>':'No PDF selected').'</span></p>';
        echo '<p><input type="number" name="sort_order" value="'.esc_attr($l->sort_order??0).'" style="width:90px" placeholder="Order"> <select name="status"><option value="published" '.selected($l->status??'published','published',false).'>Published</option><option value="draft" '.selected($l->status??'','draft',false).'>Draft</option></select> <button class="button button-primary">'.($l?'Save Lesson':'Add Lesson').'</button></p></form>';
    }

    public static function save_course(): void {
        if(!current_user_can('manage_options')||!check_admin_referer('psc_save_course'))wp_die('Access denied.');
        global $wpdb; $table=$wpdb->prefix.'psc_courses'; $id=absint($_POST['id']??0); $title=sanitize_text_field(wp_unslash($_POST['title']??'')); if($title==='')self::redirect($id,'Course title is required.');
        $slug=sanitize_title(wp_unslash($_POST['slug']??$title)); $thumb=absint($_POST['thumbnail_id']??0);
        $categories=self::sanitize_categories($_POST['categories']??[],!empty($_POST['category_all']));
        $difficulty=sanitize_key($_POST['difficulty']??'all_levels'); if(!array_key_exists($difficulty,self::difficulty_options())) $difficulty='all_levels';
        $data=['title'=>$title,'slug'=>$slug,'short_description'=>sanitize_textarea_field(wp_unslash($_POST['short_description']??'')),'description'=>wp_kses_post(wp_unslash($_POST['description']??'')),'thumbnail_id'=>$thumb?:null,'thumbnail_url'=>$thumb?esc_url_raw((string)wp_get_attachment_url($thumb)):'','categories'=>wp_json_encode($categories),'difficulty'=>$difficulty,'pricing_type'=>in_array($_POST['pricing_type']??'free',['free','paid'],true)?$_POST['pricing_type']:'free','price'=>max(0,(float)($_POST['price']??0)),'sale_price'=>($_POST['sale_price']??'')===''?null:max(0,(float)$_POST['sale_price']),'currency'=>sanitize_text_field($_POST['currency']??'INR'),'status'=>in_array($_POST['status']??'draft',['draft','published'],true)?$_POST['status']:'draft','language'=>sanitize_text_field($_POST['language']??'ml'),'featured'=>empty($_POST['featured'])?0:1,'sort_order'=>absint($_POST['sort_order']??0),'updated_at'=>current_time('mysql')];
        if($id)$ok=$wpdb->update($table,$data,['id'=>$id]);else{$data['created_by']=get_current_user_id();$data['created_at']=current_time('mysql');$ok=$wpdb->insert($table,$data);$id=(int)$wpdb->insert_id;}
        if($ok===false)self::redirect($id,$wpdb->last_error?:'Could not save course.');
        self::redirect($id,'course','course');
    }

    public static function delete_course(): void {
        $id=absint($_GET['id']??0);if(!current_user_can('manage_options')||!check_admin_referer('psc_delete_course_'.$id))wp_die('Access denied.');
        global $wpdb;$mods=$wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}psc_modules WHERE course_id=%d",$id));foreach($mods as $mid)$wpdb->delete($wpdb->prefix.'psc_lessons',['module_id'=>(int)$mid],['%d']);$wpdb->delete($wpdb->prefix.'psc_modules',['course_id'=>$id],['%d']);$wpdb->delete($wpdb->prefix.'psc_courses',['id'=>$id],['%d']);wp_safe_redirect(self::url(['deleted'=>1]));exit;
    }

    public static function save_module(): void {
        if(!current_user_can('manage_options')||!check_admin_referer('psc_save_module'))wp_die('Access denied.');
        global $wpdb;$id=absint($_POST['id']??0);$course=absint($_POST['course_id']??0);$title=sanitize_text_field(wp_unslash($_POST['title']??''));if(!$course||$title==='')self::redirect($course,'Module title is required.');
        $data=['course_id'=>$course,'title'=>$title,'description'=>sanitize_textarea_field(wp_unslash($_POST['description']??'')),'sort_order'=>absint($_POST['sort_order']??0),'status'=>in_array($_POST['status']??'published',['draft','published'],true)?$_POST['status']:'published','updated_at'=>current_time('mysql')];
        if($id)$ok=$wpdb->update($wpdb->prefix.'psc_modules',$data,['id'=>$id]);else{$data['created_at']=current_time('mysql');$ok=$wpdb->insert($wpdb->prefix.'psc_modules',$data);$id=(int)$wpdb->insert_id;}
        if($ok===false)self::redirect($course,$wpdb->last_error?:'Could not save module.');
        self::redirect($course,'module');
    }

    public static function delete_module(): void {
        $id=absint($_GET['id']??0);$course=absint($_GET['course_id']??0);if(!current_user_can('manage_options')||!check_admin_referer('psc_delete_module_'.$id))wp_die('Access denied.');
        global $wpdb;$wpdb->delete($wpdb->prefix.'psc_lessons',['module_id'=>$id],['%d']);$wpdb->delete($wpdb->prefix.'psc_modules',['id'=>$id],['%d']);self::redirect($course,'deleted');
    }

    public static function save_lesson(): void {
        if(!current_user_can('manage_options')||!check_admin_referer('psc_save_lesson'))wp_die('Access denied.');
        global $wpdb;$id=absint($_POST['id']??0);$module=absint($_POST['module_id']??0);$course=absint($_POST['course_id']??0);$title=sanitize_text_field(wp_unslash($_POST['title']??''));if(!$module||!$course||$title==='')self::redirect($course,'Lesson title is required.');
        $type=sanitize_key($_POST['lesson_type']??'text');$allowed=['text','youtube','video','audio','pdf','video_pdf','audio_pdf'];if(!in_array($type,$allowed,true))$type='text';
        $pdf=absint($_POST['pdf_attachment_id']??0);$data=['module_id'=>$module,'title'=>$title,'lesson_type'=>$type,'content'=>wp_kses_post(wp_unslash($_POST['content']??'')),'youtube_url'=>esc_url_raw(wp_unslash($_POST['youtube_url']??'')),
'youtube_video_id'=>sanitize_text_field(wp_unslash($_POST['youtube_video_id']??'')),
'youtube_playlist_id'=>sanitize_text_field(wp_unslash($_POST['youtube_playlist_id']??'')),
'youtube_playlist_item_id'=>sanitize_text_field(wp_unslash($_POST['youtube_playlist_item_id']??'')),
'youtube_thumbnail_url'=>esc_url_raw(wp_unslash($_POST['youtube_thumbnail_url']??'')),'video_url'=>esc_url_raw(wp_unslash($_POST['video_url']??'')),'audio_url'=>esc_url_raw(wp_unslash($_POST['audio_url']??'')),'pdf_url'=>$pdf?esc_url_raw((string)wp_get_attachment_url($pdf)):'','pdf_attachment_id'=>$pdf?:null,'imported_at'=>!empty($_POST['imported_at'])?sanitize_text_field(wp_unslash($_POST['imported_at'])):null,'duration_seconds'=>absint($_POST['duration_seconds']??0),'is_free'=>empty($_POST['is_free'])?0:1,'sort_order'=>absint($_POST['sort_order']??0),'status'=>in_array($_POST['status']??'published',['draft','published'],true)?$_POST['status']:'published','updated_at'=>current_time('mysql')];
        if($id)$ok=$wpdb->update($wpdb->prefix.'psc_lessons',$data,['id'=>$id]);else{$data['created_at']=current_time('mysql');$ok=$wpdb->insert($wpdb->prefix.'psc_lessons',$data);$id=(int)$wpdb->insert_id;}
        if($ok===false)self::redirect($course,$wpdb->last_error?:'Could not save lesson.');
        self::redirect($course,'lesson');
    }

    public static function delete_lesson(): void {
        $id=absint($_GET['id']??0);$course=absint($_GET['course_id']??0);if(!current_user_can('manage_options')||!check_admin_referer('psc_delete_lesson_'.$id))wp_die('Access denied.');
        global $wpdb;$wpdb->delete($wpdb->prefix.'psc_lessons',['id'=>$id],['%d']);self::redirect($course,'deleted');
    }

    private static function redirect(int $course_id,string $message='',string $kind='error'): void {
        $args=$course_id?['action'=>'edit','id'=>$course_id]:[];
        if($kind==='error')$args['error']=$message;elseif($kind==='deleted')$args['deleted']=1;else$args['saved']=$kind;
        wp_safe_redirect(self::url($args));exit;
    }

    private static function category_options(): array {
        return ['degree_level'=>'Degree Level','school_level'=>'10th/12th Level','special_topics'=>'Special Topics','language_proficiency'=>'Language Proficiency'];
    }
    private static function category_keys(): array { return array_keys(self::category_options()); }
    private static function difficulty_options(): array { return ['all_levels'=>'All Levels','beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced']; }
    private static function decode_categories($raw): array { $decoded=json_decode((string)$raw,true); return self::sanitize_categories(is_array($decoded)?$decoded:[],false); }
    private static function sanitize_categories($raw,bool $all=false): array { $allowed=self::category_keys(); if($all)return $allowed; $out=[]; foreach((array)$raw as $v){$v=sanitize_key($v);if(in_array($v,$allowed,true)&&!in_array($v,$out,true))$out[]=$v;} return $out; }
    private static function category_labels($raw): string { $cats=self::decode_categories($raw); if(!$cats)return '—'; $labels=self::category_options(); return implode(', ',array_map(fn($k)=>$labels[$k]??$k,$cats)); }
    private static function difficulty_label(string $key): string { $o=self::difficulty_options(); return $o[$key]??'All Levels'; }

    private static function input(string $name,string $label,$value,string $type='text',bool $required=false,string $extra=''):void{echo '<p><label><strong>'.esc_html($label).'</strong><br><input type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'" '.($required?'required':'').' '.$extra.' style="width:100%;max-width:700px;"></label></p>';}
    private static function textarea(string $name,string $label,$value,int $rows=4):void{echo '<p><label><strong>'.esc_html($label).'</strong><br><textarea name="'.esc_attr($name).'" rows="'.$rows.'" style="width:100%;max-width:850px;">'.esc_textarea($value).'</textarea></label></p>';}

    private static function script(): void {
        echo '<script>
        jQuery(function($){
            $(document).on("change","#psc-category-all",function(){ $(".psc-course-category").prop("checked",this.checked); });
            $(document).on("change",".psc-course-category",function(){ $("#psc-category-all").prop("checked",$(".psc-course-category").length === $(".psc-course-category:checked").length); });
            $(document).on("click","#psc-select-thumb",function(e){e.preventDefault();var frame=wp.media({title:"Select Course Thumbnail",button:{text:"Use image"},library:{type:"image"},multiple:false});frame.on("select",function(){var a=frame.state().get("selection").first().toJSON();$("#psc-course-thumb-id").val(a.id);$("#psc-course-thumb-name").text(a.filename||a.title);});frame.open();});
            $(document).on("click",".psc-edit-module",function(e){e.preventDefault();var t=$("#"+$(this).data("target"));t.slideToggle(120);});
            $(document).on("click",".psc-pdf-button",function(e){e.preventDefault();var b=$(this),target=$("#"+b.data("target")),label=$("#"+b.data("label"));var frame=wp.media({title:"Select Study PDF",button:{text:"Use PDF"},library:{type:"application/pdf"},multiple:false});frame.on("select",function(){var a=frame.state().get("selection").first().toJSON();if(a.mime!=="application/pdf"){alert("Please select a PDF.");return;}target.val(a.id);label.html("<a href=\""+String(a.url).replace(/\"/g,"&quot;")+"\" target=\"_blank\" rel=\"noopener\">"+String(a.filename||a.title).replace(/</g,"&lt;")+"</a>");});frame.open();});
        });
        </script>';
    }
    private static function import_form(): void {
        global $wpdb;
        $course_id=absint($_GET['course_id']??0);
        $courses=$wpdb->get_results("SELECT id,title FROM {$wpdb->prefix}psc_courses ORDER BY title");
        $api_key=get_option('psc_lms_youtube_api_key','');
        echo '<div class="wrap"><h1>Import YouTube Playlist</h1>';
        echo '<p><a href="'.esc_url(self::url($course_id?['action'=>'edit','id'=>$course_id]:[])).'">← Back to Courses</a></p>';
        if(isset($_GET['imported'])) echo '<div class="notice notice-success"><p>Imported '.absint($_GET['imported']).' lesson(s). Updated '.absint($_GET['updated']??0).' existing lesson(s).</p></div>';
        if(isset($_GET['error'])) echo '<div class="notice notice-error"><p>'.esc_html(sanitize_text_field(wp_unslash($_GET['error']))).'</p></div>';
        echo '<div style="background:#fff;border:1px solid #ddd;padding:24px;max-width:900px;"><h2>Playlist Importer</h2>';
        echo '<p>Paste a public YouTube playlist URL. The importer reads the real playlist metadata through the YouTube Data API, creates a module, and creates one editable lesson per video. Existing imported videos are updated instead of duplicated.</p>';
        if(!$api_key) echo '<div class="notice notice-warning inline"><p>YouTube API key is not configured. Add it under <strong>PSC LMS → Settings</strong>.</p></div>';
        else { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin:12px 0 20px;">'; wp_nonce_field('psc_test_youtube'); echo '<input type="hidden" name="action" value="psc_test_youtube"><button class="button">Test YouTube API Key</button></form>'; }
        if(isset($_GET['test'])) echo '<div class="notice notice-'.($_GET['test']==='ok'?'success':'error').' inline"><p>'.esc_html(sanitize_text_field(wp_unslash($_GET['message']??''))).'</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('psc_import_youtube');
        echo '<input type="hidden" name="action" value="psc_import_youtube">';
        echo '<p><label><strong>Playlist URL</strong><br><input type="url" name="playlist_url" required placeholder="https://www.youtube.com/playlist?list=..." style="width:100%;max-width:750px;"></label></p>';
        echo '<p><label><strong>Destination course</strong><br><select name="course_id" style="min-width:400px;"><option value="0">Create a new course from the playlist</option>';
        foreach($courses as $c) echo '<option value="'.$c->id.'" '.selected($course_id,$c->id,false).'>'.esc_html($c->title).'</option>';
        echo '</select></label></p>';
        echo '<p><label><strong>New course title (only when creating)</strong><br><input name="new_course_title" style="width:100%;max-width:750px;"></label></p>';
        echo '<p><label><strong>Module name</strong><br><input name="module_title" placeholder="Leave blank to use the YouTube playlist title" style="width:100%;max-width:750px;"></label></p>';
        echo '<p><label><input type="checkbox" name="publish" value="1" checked> Publish imported course/module/lessons</label></p>';
        echo '<p><label><input type="checkbox" name="free_preview" value="1" checked> Mark imported lessons as free preview</label></p>';
        echo '<p><button class="button button-primary button-large" '.($api_key?'':'disabled').'>Import Playlist</button></p></form>';
        echo '<hr><h3>What gets imported</h3><ul><li>Playlist title and description</li><li>Video title and description</li><li>YouTube video URL and video ID</li><li>Video thumbnail</li><li>Playlist order</li><li>Video duration</li><li>Playlist and playlist-item IDs for future re-sync</li></ul>';
        echo '<p><strong>Everything remains editable after import.</strong> You can rename the course/module/lesson, change descriptions, replace the YouTube URL, attach a WordPress PDF, change lesson type, reorder, and change publish/free-preview settings.</p></div></div>';
    }

    public static function test_youtube(): void {
        if(!current_user_can('manage_options') || !check_admin_referer('psc_test_youtube')) wp_die('Access denied.');
        $key=trim((string)get_option('psc_lms_youtube_api_key',''));
        if($key===''){wp_safe_redirect(self::url(['action'=>'import_youtube','test'=>'fail','message'=>'No API key is saved. Go to PSC LMS → Settings and save the YouTube Data API v3 key.']));exit;}
        $r=self::youtube_request('channels',['part'=>'snippet','id'=>'UC_x5XG1OV2P6uZZ5FSM9Ttw','key'=>$key]);
        if(is_wp_error($r)) { wp_safe_redirect(self::url(['action'=>'import_youtube','test'=>'fail','message'=>'YouTube API test failed: '.$r->get_error_message()]));exit; }
        wp_safe_redirect(self::url(['action'=>'import_youtube','test'=>'ok','message'=>'YouTube API key is working.']));exit;
    }

    public static function import_youtube(): void {
        if(!current_user_can('manage_options') || !check_admin_referer('psc_import_youtube')) wp_die('Access denied.');
        $api_key=trim((string)get_option('psc_lms_youtube_api_key',''));
        if($api_key==='') self::import_redirect('YouTube API key is not configured. Go to PSC LMS → Settings.');
        $url=esc_url_raw(wp_unslash($_POST['playlist_url']??''));
        $playlist_id=self::extract_playlist_id($url);
        if(!$playlist_id) self::import_redirect('Invalid YouTube playlist URL. Please paste a URL containing a playlist list ID.');
        $api=self::youtube_request('playlists',['part'=>'snippet,contentDetails','id'=>$playlist_id,'key'=>$api_key]);
        if(is_wp_error($api)) self::import_redirect($api->get_error_message());
        if(empty($api['items'][0])) self::import_redirect('Playlist not found or not accessible.');
        $playlist=$api['items'][0];
        $playlist_title=sanitize_text_field($playlist['snippet']['title']??'YouTube Playlist');
        $playlist_description=wp_kses_post($playlist['snippet']['description']??'');
        $course_id=absint($_POST['course_id']??0);
        $publish=!empty($_POST['publish']);
        $free=!empty($_POST['free_preview']);
        global $wpdb;
        if(!$course_id){
            $title=sanitize_text_field(wp_unslash($_POST['new_course_title']??'')) ?: $playlist_title;
            $slug=sanitize_title($title);
            $existing=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}psc_courses WHERE slug=%s",$slug));
            if($existing)$course_id=$existing;
            else{
                $now=current_time('mysql');
                $wpdb->insert($wpdb->prefix.'psc_courses',['title'=>$title,'slug'=>$slug,'description'=>$playlist_description,'short_description'=>wp_trim_words(wp_strip_all_tags($playlist_description),35),'categories'=>wp_json_encode(self::category_keys()),'difficulty'=>'all_levels','pricing_type'=>'free','price'=>0,'currency'=>get_option('psc_lms_default_currency','INR'),'language'=>get_option('psc_lms_default_language','ml'),'status'=>$publish?'published':'draft','featured'=>0,'sort_order'=>0,'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now]);
                $course_id=(int)$wpdb->insert_id;
            }
        }
        if(!$course_id) self::import_redirect('Could not create or select a course.');
        $module_title=sanitize_text_field(wp_unslash($_POST['module_title']??'')) ?: $playlist_title;
        $module=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}psc_modules WHERE course_id=%d AND title=%s LIMIT 1",$course_id,$module_title));
        $now=current_time('mysql');
        if($module){$module_id=(int)$module->id;$wpdb->update($wpdb->prefix.'psc_modules',['description'=>wp_trim_words(wp_strip_all_tags($playlist_description),80),'status'=>$publish?'published':'draft','updated_at'=>$now],['id'=>$module_id]);}
        else{$wpdb->insert($wpdb->prefix.'psc_modules',['course_id'=>$course_id,'title'=>$module_title,'description'=>wp_trim_words(wp_strip_all_tags($playlist_description),80),'sort_order'=>0,'status'=>$publish?'published':'draft','created_at'=>$now,'updated_at'=>$now]);$module_id=(int)$wpdb->insert_id;}
        $items=[];$token='';
        do{
            $params=['part'=>'snippet,contentDetails','playlistId'=>$playlist_id,'maxResults'=>50,'key'=>$api_key];
            if($token)$params['pageToken']=$token;
            $resp=self::youtube_request('playlistItems',$params);
            if(is_wp_error($resp)) self::import_redirect($resp->get_error_message());
            foreach((array)($resp['items']??[]) as $item){
                $vid=sanitize_text_field($item['contentDetails']['videoId']??$item['snippet']['resourceId']['videoId']??'');
                if($vid) $items[]=['playlist_item_id'=>sanitize_text_field($item['id']??''),'video_id'=>$vid,'title'=>sanitize_text_field($item['snippet']['title']??'Untitled video'),'description'=>wp_kses_post($item['snippet']['description']??''),'position'=>absint($item['snippet']['position']??0),'thumb'=>esc_url_raw($item['snippet']['thumbnails']['high']['url']??$item['snippet']['thumbnails']['medium']['url']??$item['snippet']['thumbnails']['default']['url']??'')];
            }
            $token=$resp['nextPageToken']??'';
        }while($token!=='');
        $ids=array_values(array_unique(array_column($items,'video_id')));
        $details=[];
        foreach(array_chunk($ids,50) as $chunk){
            $v=self::youtube_request('videos',['part'=>'contentDetails,snippet','id'=>implode(',',$chunk),'key'=>$api_key]);
            if(is_wp_error($v))continue;
            foreach((array)($v['items']??[]) as $video)$details[$video['id']]=$video;
        }
        $imported=0;$updated=0;$errors=[];$lessons_table=$wpdb->prefix.'psc_lessons';
        foreach($items as $item){
            $video_id=$item['video_id'];$detail=$details[$video_id]??[];$duration=self::youtube_duration_seconds($detail['contentDetails']['duration']??'');
            $thumbnail=$item['thumb'] ?: esc_url_raw($detail['snippet']['thumbnails']['high']['url']??'');
            $data=['module_id'=>$module_id,'title'=>$item['title'],'lesson_type'=>'youtube','content'=>$item['description'],'youtube_url'=>'https://www.youtube.com/watch?v='.$video_id,'youtube_video_id'=>$video_id,'youtube_playlist_id'=>$playlist_id,'youtube_playlist_item_id'=>$item['playlist_item_id'],'youtube_thumbnail_url'=>$thumbnail,'duration_seconds'=>$duration,'is_free'=>$free?1:0,'sort_order'=>$item['position'],'status'=>$publish?'published':'draft','updated_at'=>$now,'imported_at'=>$now];
            $existing=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$lessons_table} WHERE youtube_video_id=%s AND youtube_playlist_id=%s LIMIT 1",$video_id,$playlist_id));
            if($existing){$ok=$wpdb->update($lessons_table,$data,['id'=>$existing]); if($ok===false){$errors[]='Update failed for "'. $item['title'] .'": '.$wpdb->last_error;} else {$updated++;}}
            else{$data['created_at']=$now;$ok=$wpdb->insert($lessons_table,$data);if($ok===false){$errors[]='Insert failed for "'. $item['title'] .'": '.$wpdb->last_error;}else{$imported++;}}
        }
        $args=['action'=>'edit','id'=>$course_id,'saved'=>'course','imported'=>$imported,'updated'=>$updated];
        if(!empty($errors)) $args['error']=implode(' | ',array_slice($errors,0,3));
        wp_safe_redirect(self::url($args));exit;
    }

    private static function import_redirect(string $message): void {
        wp_safe_redirect(self::url(['action'=>'import_youtube','error'=>$message]));exit;
    }

    private static function extract_playlist_id(string $url): string {
        $parts=wp_parse_url($url);
        if(!empty($parts['query'])){
            parse_str($parts['query'],$query);
            if(!empty($query['list'])) return preg_replace('/[^A-Za-z0-9_-]/','',(string)$query['list']);
        }
        if(preg_match('~(?:playlist/|list=)([A-Za-z0-9_-]+)~',$url,$m)) return $m[1];
        return '';
    }

    private static function youtube_request(string $resource,array $params) {
        $endpoint='https://www.googleapis.com/youtube/v3/'.ltrim($resource,'/');
        $response=wp_remote_get(add_query_arg($params,$endpoint),['timeout'=>30,'redirection'=>3,'headers'=>['Accept'=>'application/json']]);
        if(is_wp_error($response)) return $response;
        $code=wp_remote_retrieve_response_code($response);$body=json_decode(wp_remote_retrieve_body($response),true);
        if($code<200||$code>=300){
            $message=$body['error']['message']??'YouTube API request failed.';
            $reason=$body['error']['errors'][0]['reason']??'';
            if($reason!=='') $message.=' ('.$reason.')';
            return new \WP_Error('youtube_api_error',$message,['status'=>$code]);
        }
        return is_array($body)?$body:new \WP_Error('youtube_invalid_response','YouTube returned an invalid response.');
    }

    private static function youtube_duration_seconds(string $iso): int {
        if($iso==='')return 0;
        try{
            $d=new \DateInterval($iso);
            return ((int)$d->days*86400)+($d->h*3600)+($d->i*60)+$d->s;
        }catch(\Exception $e){return 0;}
    }

}
