<?php
namespace PSC_LMS;
if (!defined('ABSPATH')) exit;

class Exams_Admin {
    public static function init():void{
        add_action('admin_post_psc_save_exam',[self::class,'save']);
        add_action('admin_post_psc_delete_exam',[self::class,'delete']);
        add_action('admin_post_psc_assign_exam_questions',[self::class,'assign_questions']);
    }
    private static function url(array $args=[]):string{return add_query_arg(array_merge(['page'=>'psc-lms-exams'],$args),admin_url('admin.php'));}

    public static function render():void{
        if(!current_user_can('manage_options'))wp_die('Access denied.');
        $action=sanitize_key($_GET['action']??'');
        if($action==='new'||$action==='edit'){self::form(absint($_GET['id']??0));return;}
        global $wpdb;$rows=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}psc_exams ORDER BY id DESC");
        echo '<div class="wrap"><h1>Exams <a class="page-title-action" href="'.esc_url(self::url(['action'=>'new'])).'">Add New</a></h1>';
        if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>Exam saved.</p></div>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Title</th><th>Duration</th><th>Marks</th><th>Passing</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        foreach($rows as $r){$edit=self::url(['action'=>'edit','id'=>$r->id]);$del=wp_nonce_url(add_query_arg(['action'=>'psc_delete_exam','id'=>$r->id],admin_url('admin-post.php')),'psc_delete_exam_'.$r->id);echo '<tr><td>'.$r->id.'</td><td>'.esc_html($r->title).'</td><td>'.$r->duration_minutes.' min</td><td>'.$r->total_marks.'</td><td>'.$r->passing_percentage.'%</td><td>'.esc_html($r->status).'</td><td><a href="'.esc_url($edit).'">Edit</a> | <a href="'.esc_url($del).'">Delete</a></td></tr>';}
        if(!$rows)echo '<tr><td colspan="7">No exams yet.</td></tr>';echo '</tbody></table></div>';
    }

    private static function form(int $id):void{
        global $wpdb;$p=$wpdb->prefix;$e=$id?$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}psc_exams WHERE id=%d",$id)):null;
        echo '<div class="wrap"><h1>'.($e?'Edit Exam':'Add Exam').'</h1><p><a href="'.esc_url(self::url()).'">← Back</a></p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="max-width:900px;background:#fff;padding:22px;">';wp_nonce_field('psc_save_exam');echo '<input type="hidden" name="action" value="psc_save_exam"><input type="hidden" name="id" value="'.$id.'">';
        echo '<p><strong>Title</strong><br><input name="title" required value="'.esc_attr($e->title??'').'" style="width:100%;"></p><p>Description<br><textarea name="description" rows="5" style="width:100%;">'.esc_textarea($e->description??'').'</textarea></p>';
        foreach([['duration_minutes','Duration minutes',60],['total_marks','Total marks',0],['negative_mark','Negative mark',0],['passing_percentage','Passing percentage',40],['max_attempts','Max attempts (0 unlimited)',0]] as $x)echo '<p><label><strong>'.esc_html($x[1]).'</strong><br><input type="number" step="0.01" name="'.esc_attr($x[0]).'" value="'.esc_attr($e->{$x[0]}??$x[2]).'"></label></p>';
        echo '<p><label><input type="checkbox" name="shuffle_questions" value="1" '.checked($e->shuffle_questions??1,1,false).'> Shuffle questions</label> <label><input type="checkbox" name="shuffle_options" value="1" '.checked($e->shuffle_options??1,1,false).'> Shuffle options</label> <select name="status"><option value="draft">Draft</option><option value="published" '.selected($e->status??'','published',false).'>Published</option></select></p><p><button class="button button-primary button-large">Save Exam</button></p></form>';
        if($e)self::question_assignment($e->id);
        echo '</div>';
    }

    private static function question_assignment(int $exam_id):void{
        global $wpdb;$p=$wpdb->prefix;$questions=$wpdb->get_results("SELECT id,question FROM {$p}psc_questions WHERE status='published' ORDER BY id DESC");$assigned=$wpdb->get_results($wpdb->prepare("SELECT question_id,marks FROM {$p}psc_exam_questions WHERE exam_id=%d",$exam_id));$map=[];foreach($assigned as $a)$map[$a->question_id]=$a->marks;
        echo '<hr><h2>Exam Questions</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('psc_assign_exam_questions');echo '<input type="hidden" name="action" value="psc_assign_exam_questions"><input type="hidden" name="exam_id" value="'.$exam_id.'"><table class="widefat striped" style="max-width:1000px;"><thead><tr><th>Use</th><th>Question</th><th>Marks</th></tr></thead><tbody>';
        foreach($questions as $q)echo '<tr><td><input type="checkbox" name="question_ids[]" value="'.$q->id.'" '.checked(isset($map[$q->id]),true,false).'></td><td>'.esc_html(wp_trim_words(wp_strip_all_tags($q->question),25)).'</td><td><input type="number" step="0.01" name="marks['.$q->id.']" value="'.esc_attr($map[$q->id]??1).'" style="width:80px"></td></tr>';
        if(!$questions)echo '<tr><td colspan="3">Create published questions first.</td></tr>';
        echo '</tbody></table><p><button class="button button-primary">Save Exam Questions</button></p></form>';
    }

    public static function save():void{
        if(!current_user_can('manage_options')||!check_admin_referer('psc_save_exam'))wp_die('Access denied.');
        global $wpdb;$p=$wpdb->prefix;$id=absint($_POST['id']??0);$data=['title'=>sanitize_text_field(wp_unslash($_POST['title']??'')),'description'=>wp_kses_post(wp_unslash($_POST['description']??'')),'duration_minutes'=>absint($_POST['duration_minutes']??60),'total_marks'=>(float)($_POST['total_marks']??0),'negative_mark'=>(float)($_POST['negative_mark']??0),'passing_percentage'=>(float)($_POST['passing_percentage']??40),'max_attempts'=>absint($_POST['max_attempts']??0),'shuffle_questions'=>empty($_POST['shuffle_questions'])?0:1,'shuffle_options'=>empty($_POST['shuffle_options'])?0:1,'status'=>in_array($_POST['status']??'draft',['draft','published'],true)?$_POST['status']:'draft','updated_at'=>current_time('mysql')];
        if($id)$wpdb->update($p.'psc_exams',$data,['id'=>$id]);else{$data['created_at']=current_time('mysql');$wpdb->insert($p.'psc_exams',$data);$id=(int)$wpdb->insert_id;}
        wp_safe_redirect(self::url(['action'=>'edit','id'=>$id,'saved'=>1]));exit;
    }

    public static function assign_questions():void{
        if(!current_user_can('manage_options')||!check_admin_referer('psc_assign_exam_questions'))wp_die('Access denied.');
        global $wpdb;$p=$wpdb->prefix;$exam=absint($_POST['exam_id']??0);$wpdb->delete($p.'psc_exam_questions',['exam_id'=>$exam],['%d']);$ids=array_map('absint',(array)($_POST['question_ids']??[]));$marks=(array)($_POST['marks']??[]);
        foreach($ids as $i=>$qid)$wpdb->insert($p.'psc_exam_questions',['exam_id'=>$exam,'question_id'=>$qid,'marks'=>(float)($marks[$qid]??1),'sort_order'=>$i]);
        wp_safe_redirect(self::url(['action'=>'edit','id'=>$exam,'saved'=>1]));exit;
    }

    public static function delete():void{
        $id=absint($_GET['id']??0);if(!current_user_can('manage_options')||!check_admin_referer('psc_delete_exam_'.$id))wp_die('Access denied.');
        global $wpdb;$wpdb->delete($wpdb->prefix.'psc_exam_questions',['exam_id'=>$id],['%d']);$wpdb->delete($wpdb->prefix.'psc_exams',['id'=>$id],['%d']);wp_safe_redirect(self::url(['deleted'=>1]));exit;
    }
}
