<?php
namespace PSC_LMS;

if (!defined('ABSPATH')) exit;

class Questions_Admin
{
    public static function init(): void
    {
        add_action('admin_post_psc_save_question', [self::class, 'save']);
        add_action('admin_post_psc_delete_question', [self::class, 'delete']);
        add_action('admin_post_psc_bulk_question_action', [self::class, 'bulk_action']);
        add_action('admin_post_psc_import_questions_pdf', [self::class, 'import_pdf']);
    }

    private static function url(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => 'psc-lms-questions'], $args), admin_url('admin.php'));
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) wp_die('Access denied.');

        echo '<style>.psc-question-preview,.psc-question-preview *{font-family:"Noto Sans Malayalam","Nirmala UI","Kartika","Noto Sans",sans-serif;}.psc-question-preview{font-size:15px;line-height:1.65;}.psc-question-preview .psc-option{margin-left:12px;}</style>';

        $action = sanitize_key($_GET['action'] ?? '');
        if ($action === 'new' || $action === 'edit') {
            self::form(absint($_GET['id'] ?? 0));
            return;
        }
        if ($action === 'import_pdf') {
            self::import_form();
            return;
        }

        global $wpdb;
        $p = $wpdb->prefix;

        $rows = $wpdb->get_results(
            "SELECT q.*, s.name subject, t.name topic
             FROM {$p}psc_questions q
             LEFT JOIN {$p}psc_subjects s ON s.id=q.subject_id
             LEFT JOIN {$p}psc_topics t ON t.id=q.topic_id
             ORDER BY q.id DESC"
        );

        echo '<div class="wrap"><h1>Question Bank
            <a class="page-title-action" href="' . esc_url(self::url(['action' => 'new'])) . '">Add New</a>
            <a class="page-title-action" href="' . esc_url(self::url(['action' => 'import_pdf'])) . '">Import Questions from PDF / Word</a>
        </h1>';

        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>Question saved. You are back in the Question Bank.</p></div>';
        }
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success"><p>' . absint($_GET['deleted']) . ' question(s) deleted.</p></div>';
        }
        if (isset($_GET['added_to_exam'])) {
            echo '<div class="notice notice-success"><p>' . absint($_GET['added_to_exam']) . ' selected question(s) added to the exam.</p></div>';
        }
        if (isset($_GET['imported'])) {
            $exam = sanitize_text_field(wp_unslash($_GET['exam'] ?? ''));
            echo '<div class="notice notice-success"><p>Imported ' . absint($_GET['imported']) . ' question(s). Skipped ' . absint($_GET['skipped'] ?? 0) . ' item(s).' .
                ($exam ? ' Exam created: <strong>' . esc_html($exam) . '</strong>.' : '') .
                '</p></div>';
        }

        $exams = $wpdb->get_results("SELECT id, title, status FROM {$p}psc_exams ORDER BY id DESC");

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="psc-question-bulk-form">';
        wp_nonce_field('psc_bulk_question_action');
        echo '<input type="hidden" name="action" value="psc_bulk_question_action">';
        echo '<div style="display:flex;align-items:center;gap:8px;margin:12px 0;">';
        echo '<select name="bulk_action" id="psc-bulk-action">';
        echo '<option value="">Bulk actions…</option>';
        echo '<option value="add_to_exam">Add selected to exam</option>';
        echo '<option value="delete">Delete selected</option>';
        echo '</select>';
        echo '<select name="exam_id" id="psc-bulk-exam" style="min-width:240px;">';
        echo '<option value="0">Select exam…</option>';
        foreach ($exams as $exam) {
            echo '<option value="' . absint($exam->id) . '">' . esc_html($exam->title) . ' (' . esc_html(ucfirst($exam->status)) . ')</option>';
        }
        echo '</select>';
        echo '<button type="submit" class="button" id="psc-apply-bulk">Apply</button>';
        echo '<span id="psc-selected-count" style="color:#50575e;">0 selected</span>';
        echo '</div>';

        echo '<table class="widefat striped">
            <thead><tr>
                <th style="width:40px"><input type="checkbox" id="psc-select-all" aria-label="Select all questions"></th>
                <th style="width:55px">ID</th>
                <th>Question</th>
                <th>Correct Answer</th>
                <th>Subject</th>
                <th>Topic</th>
                <th>Difficulty</th>
                <th>Year</th>
                <th>Status</th>
                <th>Action</th>
            </tr></thead><tbody>';

        foreach ($rows as $r) {
            $answer = self::correct_answer_text((int)$r->id);
            $edit = self::url(['action' => 'edit', 'id' => $r->id]);
            $del = wp_nonce_url(
                add_query_arg(['action' => 'psc_delete_question', 'id' => $r->id], admin_url('admin-post.php')),
                'psc_delete_question_' . $r->id
            );

            echo '<tr>
                <td><input type="checkbox" class="psc-question-checkbox" name="question_ids[]" value="' . absint($r->id) . '" aria-label="Select question ' . absint($r->id) . '"></td>
                <td>' . esc_html($r->id) . '</td>
                <td><strong>' . esc_html(wp_trim_words(wp_strip_all_tags($r->question), 22)) . '</strong></td>
                <td>' . esc_html($answer ?: 'Not set') . '</td>
                <td>' . esc_html($r->subject ?: '—') . '</td>
                <td>' . esc_html($r->topic ?: '—') . '</td>
                <td>' . esc_html(ucfirst($r->difficulty ?: '—')) . '</td>
                <td>' . esc_html($r->exam_year ?: '—') . '</td>
                <td>' . esc_html(ucfirst($r->status)) . '</td>
                <td><a class="button button-small" href="' . esc_url($edit) . '">Edit</a> <a class="button button-small" style="color:#b32d2e;border-color:#b32d2e;" href="' . esc_url($del) . '" onclick="return confirm(\'Delete this question permanently? This will also remove its options and related facts.\');">Delete</a></td>
            </tr>';
        }

        if (!$rows) {
            echo '<tr><td colspan="10">No questions yet.</td></tr>';
        }

        echo '</tbody></table>';
        echo '<div style="margin-top:10px;display:flex;align-items:center;gap:8px;">';
        echo '<span id="psc-selected-count-bottom" style="color:#50575e;">0 selected</span>';
        echo '</div>';
        echo '</form>';

        echo '<script>
        (function(){
            const form=document.getElementById("psc-question-bulk-form");
            if(!form)return;
            const all=document.getElementById("psc-select-all");
            const boxes=()=>Array.from(form.querySelectorAll(".psc-question-checkbox"));
            const topCount=document.getElementById("psc-selected-count");
            const bottomCount=document.getElementById("psc-selected-count-bottom");
            const action=document.getElementById("psc-bulk-action");
            const exam=document.getElementById("psc-bulk-exam");
            function update(){
                const selected=boxes().filter(b=>b.checked).length;
                topCount.textContent=selected+" selected";
                bottomCount.textContent=selected+" selected";
                all.checked=selected>0 && selected===boxes().length;
                all.indeterminate=selected>0 && selected<boxes().length;
            }
            all.addEventListener("change",function(){
                boxes().forEach(b=>b.checked=all.checked);
                update();
            });
            boxes().forEach(b=>b.addEventListener("change",update));
            action.addEventListener("change",function(){
                exam.style.display=this.value==="add_to_exam"?"inline-block":"none";
            });
            exam.style.display="none";
            form.addEventListener("submit",function(e){
                const selected=boxes().filter(b=>b.checked).length;
                if(!selected){e.preventDefault();alert("Select at least one question.");return;}
                if(action.value==="add_to_exam" && exam.value==="0"){e.preventDefault();alert("Select an exam first.");return;}
                if(action.value==="delete" && !confirm("Delete "+selected+" selected question(s)? This cannot be undone.")){e.preventDefault();}
            });
        })();
        </script></div>';
    }

    private static function form(int $id): void
    {
        global $wpdb;
        $p = $wpdb->prefix;
        $q = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}psc_questions WHERE id=%d", $id)) : null;
        $opts = $id ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}psc_question_options WHERE question_id=%d ORDER BY sort_order", $id)) : [];
        $facts = $id ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}psc_question_facts WHERE question_id=%d ORDER BY sort_order", $id)) : [];
        $subjects = $wpdb->get_results("SELECT * FROM {$p}psc_subjects ORDER BY name");
        $topics = $wpdb->get_results("SELECT * FROM {$p}psc_topics ORDER BY name");

        $option_map = [];
        foreach ($opts as $o) $option_map[strtoupper($o->option_key)] = $o;

        echo '<div class="wrap"><h1>' . ($q ? 'Edit Question' : 'Add Question') . '</h1>';
        echo '<p><a href="' . esc_url(self::url()) . '">← Back to Question Bank</a></p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:1050px;background:#fff;padding:24px;border:1px solid #ddd;">';
        wp_nonce_field('psc_save_question');
        echo '<input type="hidden" name="action" value="psc_save_question">
              <input type="hidden" name="id" value="' . esc_attr($id) . '">
              <input type="hidden" name="question_pdf_attachment_id" value="' . esc_attr($q->question_pdf_attachment_id ?? 0) . '">
              <input type="hidden" name="source_question_number" value="' . esc_attr($q->source_question_number ?? '') . '">';

        echo '<p><strong>Question</strong><br><textarea name="question" required rows="6" style="width:100%;">' . esc_textarea($q->question ?? '') . '</textarea></p>';

        echo '<p>
            <select name="subject_id"><option value="0">Subject</option>';
        foreach ($subjects as $s) {
            echo '<option value="' . $s->id . '" ' . selected($q->subject_id ?? 0, $s->id, false) . '>' . esc_html($s->name) . '</option>';
        }
        echo '</select>
            <select name="topic_id"><option value="0">Topic</option>';
        foreach ($topics as $t) {
            echo '<option value="' . $t->id . '" ' . selected($q->topic_id ?? 0, $t->id, false) . '>' . esc_html($t->name) . '</option>';
        }
        echo '</select>
            <select name="question_type">
                <option value="single">Single answer</option>
                <option value="multiple" ' . selected($q->question_type ?? '', 'multiple', false) . '>Multiple answer</option>
            </select>
            <select name="difficulty">
                <option value="easy">Easy</option>
                <option value="medium" ' . selected($q->difficulty ?? 'medium', 'medium', false) . '>Medium</option>
                <option value="hard" ' . selected($q->difficulty ?? '', 'hard', false) . '>Hard</option>
            </select>
            <select name="status">
                <option value="draft">Draft</option>
                <option value="published" ' . selected($q->status ?? '', 'published', false) . '>Published</option>
            </select>
        </p>';

        echo '<h2>Options</h2>';
        for ($i = 0; $i < 5; $i++) {
            $key = chr(65 + $i);
            $o = $option_map[$key] ?? null;
            echo '<p><strong style="display:inline-block;width:24px">' . $key . '</strong>
                <input type="text" name="options[' . $i . ']" value="' . esc_attr($o->option_text ?? '') . '" style="width:65%;">
                <label><input type="checkbox" name="correct[]" value="' . $i . '" ' . checked($o->is_correct ?? 0, 1, false) . '> Correct answer</label>
            </p>';
        }

        $current_answer = self::correct_answer_text($id);
        if ($id) {
            echo '<div style="padding:12px 15px;background:#f0f6fc;border-left:4px solid #2271b1;margin:15px 0;">
                <strong>Correct Answer:</strong> ' . esc_html($current_answer ?: 'Not set') . '
            </div>';
        }

        echo '<p><strong>Explanation</strong><br><textarea name="explanation" rows="6" style="width:100%;">' . esc_textarea($q->explanation ?? '') . '</textarea></p>';
        echo '<p>Source <input name="source" value="' . esc_attr($q->source ?? '') . '" style="width:48%;">
            Exam year <input name="exam_year" value="' . esc_attr($q->exam_year ?? '') . '" style="width:100px;">
            Language <input name="language" value="' . esc_attr(get_post_meta((int)($q->question_pdf_attachment_id ?? 0), '_psc_question_language', true)) . '" style="width:140px;"></p>';

        echo '<h2>Related Facts</h2>';
        for ($i = 0; $i < 3; $i++) {
            echo '<p><textarea name="facts[]" rows="2" style="width:100%;" placeholder="Related fact">' . esc_textarea($facts[$i]->fact ?? '') . '</textarea></p>';
        }

        if ($q && !empty($q->question_pdf_attachment_id)) {
            $pdf_url = wp_get_attachment_url((int)$q->question_pdf_attachment_id);
            if ($pdf_url) echo '<p><strong>Original source file:</strong> <a href="' . esc_url($pdf_url) . '" target="_blank" rel="noopener">View file</a></p>';
        }

        echo '<p><button class="button button-primary button-large">Save Question</button>
            <a class="button" href="' . esc_url(self::url()) . '">Cancel</a>';
        if ($q) {
            $delete_url = wp_nonce_url(
                add_query_arg(['action' => 'psc_delete_question', 'id' => (int)$q->id], admin_url('admin-post.php')),
                'psc_delete_question_' . (int)$q->id
            );
            echo ' <a class="button" style="color:#b32d2e;border-color:#b32d2e;" href="' . esc_url($delete_url) . '" onclick="return confirm(\'Delete this question permanently? This will also remove its options and related facts.\');">Delete Question</a>';
        }
        echo '</p>';
        echo '</form></div>';
    }

    private static function correct_answer_text(int $question_id): string
    {
        if (!$question_id) return '';
        global $wpdb;
        return (string)$wpdb->get_var($wpdb->prepare(
            "SELECT option_text FROM {$wpdb->prefix}psc_question_options WHERE question_id=%d AND is_correct=1 ORDER BY sort_order LIMIT 1",
            $question_id
        ));
    }

    public static function save(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('psc_save_question')) wp_die('Access denied.');

        global $wpdb;
        $p = $wpdb->prefix;
        $id = absint($_POST['id'] ?? 0);
        $data = [
            'subject_id' => absint($_POST['subject_id'] ?? 0) ?: null,
            'topic_id' => absint($_POST['topic_id'] ?? 0) ?: null,
            'question' => wp_kses_post(wp_unslash($_POST['question'] ?? '')),
            'question_pdf_attachment_id' => absint($_POST['question_pdf_attachment_id'] ?? 0) ?: null,
            'question_type' => in_array($_POST['question_type'] ?? 'single', ['single','multiple'], true) ? $_POST['question_type'] : 'single',
            'difficulty' => in_array($_POST['difficulty'] ?? 'medium', ['easy','medium','hard'], true) ? $_POST['difficulty'] : 'medium',
            'explanation' => wp_kses_post(wp_unslash($_POST['explanation'] ?? '')),
            'source' => sanitize_text_field(wp_unslash($_POST['source'] ?? '')),
            'source_question_number' => sanitize_text_field(wp_unslash($_POST['source_question_number'] ?? '')),
            'exam_year' => sanitize_text_field(wp_unslash($_POST['exam_year'] ?? '')),
            'status' => in_array($_POST['status'] ?? 'draft', ['draft','published'], true) ? $_POST['status'] : 'draft',
            'updated_at' => current_time('mysql'),
        ];

        if ($id) {
            $wpdb->update($p.'psc_questions', $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($p.'psc_questions', $data);
            $id = (int)$wpdb->insert_id;
        }

        $wpdb->delete($p.'psc_question_options', ['question_id' => $id], ['%d']);
        $correct = array_map('intval', (array)($_POST['correct'] ?? []));

        foreach ((array)($_POST['options'] ?? []) as $i => $text) {
            $text = wp_kses_post(wp_unslash($text));
            if (trim(wp_strip_all_tags($text)) === '') continue;
            $wpdb->insert($p.'psc_question_options', [
                'question_id' => $id,
                'option_key' => chr(65 + (int)$i),
                'option_text' => $text,
                'is_correct' => in_array((int)$i, $correct, true) ? 1 : 0,
                'sort_order' => (int)$i,
            ]);
        }

        $wpdb->delete($p.'psc_question_facts', ['question_id' => $id], ['%d']);
        foreach ((array)($_POST['facts'] ?? []) as $i => $fact) {
            $fact = wp_kses_post(wp_unslash($fact));
            if ($fact !== '') {
                $wpdb->insert($p.'psc_question_facts', ['question_id' => $id, 'fact' => $fact, 'sort_order' => $i]);
            }
        }

        wp_safe_redirect(self::url(['action' => 'edit', 'id' => $id, 'saved' => 1]));
        exit;
    }

    public static function delete(): void
    {
        $id = absint($_GET['id'] ?? 0);
        if (!current_user_can('manage_options') || !check_admin_referer('psc_delete_question_'.$id)) wp_die('Access denied.');

        global $wpdb;
        $p = $wpdb->prefix;
        $wpdb->delete($p.'psc_question_options', ['question_id' => $id], ['%d']);
        $wpdb->delete($p.'psc_question_facts', ['question_id' => $id], ['%d']);
        $wpdb->delete($p.'psc_questions', ['id' => $id], ['%d']);
        wp_safe_redirect(self::url(['deleted' => 1]));
        exit;
    }

    public static function bulk_action(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('psc_bulk_question_action')) {
            wp_die('Access denied.');
        }

        global $wpdb;
        $p = $wpdb->prefix;
        $action = sanitize_key($_POST['bulk_action'] ?? '');
        $ids = array_values(array_unique(array_filter(array_map('absint', (array)($_POST['question_ids'] ?? [])))));
        $exam_id = absint($_POST['exam_id'] ?? 0);

        if (!$ids) {
            wp_safe_redirect(self::url());
            exit;
        }

        if ($action === 'delete') {
            foreach ($ids as $id) {
                $wpdb->delete($p . 'psc_exam_questions', ['question_id' => $id], ['%d']);
                $wpdb->delete($p . 'psc_question_options', ['question_id' => $id], ['%d']);
                $wpdb->delete($p . 'psc_question_facts', ['question_id' => $id], ['%d']);
                $wpdb->delete($p . 'psc_questions', ['id' => $id], ['%d']);
            }

            wp_safe_redirect(self::url(['deleted' => count($ids)]));
            exit;
        }

        if ($action === 'add_to_exam') {
            if (!$exam_id) {
                wp_die('Please select an exam.');
            }

            $exam_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$p}psc_exams WHERE id=%d",
                $exam_id
            ));
            if (!$exam_exists) {
                wp_die('Selected exam was not found.');
            }

            $max_sort = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(MAX(sort_order), -1) FROM {$p}psc_exam_questions WHERE exam_id=%d",
                $exam_id
            ));

            $added = 0;
            foreach ($ids as $id) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$p}psc_exam_questions WHERE exam_id=%d AND question_id=%d",
                    $exam_id,
                    $id
                ));
                if ($exists) {
                    continue;
                }
                $max_sort++;
                $wpdb->insert($p . 'psc_exam_questions', [
                    'exam_id' => $exam_id,
                    'question_id' => $id,
                    'marks' => 1,
                    'sort_order' => $max_sort,
                ]);
                $added++;
            }

            wp_safe_redirect(self::url(['added_to_exam' => $added, 'exam_id' => $exam_id]));
            exit;
        }

        wp_safe_redirect(self::url());
        exit;
    }

    private static function import_form(): void
    {
        global $wpdb;
        $subjects = $wpdb->get_results("SELECT id,name FROM {$wpdb->prefix}psc_subjects WHERE status='published' ORDER BY name");
        $topics = $wpdb->get_results("SELECT id,name FROM {$wpdb->prefix}psc_topics WHERE status='published' ORDER BY name");

        echo '<div class="wrap"><h1>Import Questions from PDF / Word</h1>';
        echo '<p><a href="'.esc_url(self::url()).'">← Back to Questions</a></p>';
        echo '<div style="background:#fff;border:1px solid #ddd;padding:24px;max-width:1100px;">';
        echo '<h2>PDF / Word → Question Bank</h2>';
        echo '<p>This importer is format-flexible. Upload a <strong>PDF or DOCX Word file</strong>. It uses <strong>numbered questions</strong> as the main anchor, supports A–E options (including two-column option layouts), ignores instructions before Question 1, and keeps every imported question editable.</p>';
        echo '<p><strong>No AI required:</strong> PDF files are read directly from their text layer, and DOCX files are read from their Word text. AI/OCR remains optional for documents that genuinely need it.</p>';

        echo '<form id="psc-pdf-import-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data">';
        wp_nonce_field('psc_import_questions_pdf');
        echo '<input type="hidden" name="action" value="psc_import_questions_pdf">';
        echo '<input type="hidden" name="parsed_json" id="psc-parsed-json">';
        echo '<input type="hidden" id="psc-ai-nonce" value="'.esc_attr(wp_create_nonce('psc_ai_nonce')).'">';

        echo '<p><label><strong>PDF or Word file (.pdf / .docx)</strong><br><input type="file" id="psc-question-source" name="question_source" accept="application/pdf,.pdf,.docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required></label></p>';
        echo '<p style="padding:12px;background:#f0f6fc;border-left:4px solid #2271b1;"><label><input type="checkbox" id="psc-use-ai" name="use_ai" value="1" '.checked(false,true,false).'> <strong>Use AI extraction (recommended)</strong></label><br><span style="color:#50575e;">AI reads the actual page image, ignores corrupted option labels, starts from the first real numbered question, and never marks the correct answer.</span>'.(!\PSC_LMS\AI::enabled() ? '<br><strong style="color:#b32d2e;">Configure OpenAI in PSC LMS → Settings → AI first.</strong>' : '').'</p>';

        echo '<p><label>Subject<br><select name="subject_id"><option value="0">Select subject</option>';
        foreach ($subjects as $x) echo '<option value="'.$x->id.'">'.esc_html($x->name).'</option>';
        echo '</select></label> ';

        echo '<label>Topic<br><select name="topic_id"><option value="0">Select topic</option>';
        foreach ($topics as $x) echo '<option value="'.$x->id.'">'.esc_html($x->name).'</option>';
        echo '</select></label> ';

        echo '<label>Difficulty<br><select name="difficulty"><option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option></select></label></p>';

        echo '<p><label>Source <input name="source" id="psc-source" placeholder="e.g. VFA 134/2017" style="width:320px"></label> ';
        echo '<label>Exam Year <input name="exam_year" id="psc-exam-year" style="width:90px"></label> ';
        echo '<label>Language <input name="language" placeholder="Malayalam / English" style="width:160px"></label></p>';

        echo '<hr><h3>Optional: Create an Exam from this PDF</h3>';
        echo '<p><label><input type="checkbox" name="create_exam" id="psc-create-exam" value="1" checked> Create an exam using the imported questions</label></p>';
        echo '<div id="psc-exam-settings" style="padding:12px;background:#f6f7f7;border:1px solid #dcdcde;">';
        echo '<label>Exam title <input name="exam_title" id="psc-exam-title" style="width:320px" placeholder="Auto from source/year"></label> ';
        echo '<label>Duration (minutes) <input type="number" name="exam_duration" value="75" min="1" style="width:80px"></label> ';
        echo '<label>Marks/question <input type="number" step="0.01" name="exam_marks" value="1" min="0" style="width:70px"></label> ';
        echo '<label>Negative mark <input type="number" step="0.01" name="negative_mark" value="0" min="0" style="width:70px"></label> ';
        echo '<label>Passing % <input type="number" step="0.01" name="passing_percentage" value="40" min="0" max="100" style="width:70px"></label> ';
        echo '<label><input type="checkbox" name="exam_published" value="1"> Publish exam immediately</label>';
        echo '</div>';

        echo '<div id="psc-pdf-status" style="padding:12px;background:#f6f7f7;border:1px solid #dcdcde;margin-top:16px;">Choose a PDF or Word file to extract and preview questions.</div>';
        echo '<div id="psc-pdf-preview" style="margin-top:16px;"></div>';
        echo '<p><button type="submit" id="psc-import-submit" class="button button-primary button-large" disabled>Import Questions</button></p>';
        echo '</form></div>';

        ?>
        <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/mammoth@1.8.0/mammoth.browser.min.js"></script>
        <script type="module">
        (() => {
            const input = document.getElementById('psc-question-source');
            const status = document.getElementById('psc-pdf-status');
            const json = document.getElementById('psc-parsed-json');
            const submit = document.getElementById('psc-import-submit');
            const preview = document.getElementById('psc-pdf-preview');
            const source = document.getElementById('psc-source');
            const year = document.getElementById('psc-exam-year');
            const examTitle = document.getElementById('psc-exam-title');
            const createExam = document.getElementById('psc-create-exam');
            const useAI = document.getElementById('psc-use-ai');
            const aiNonce = document.getElementById('psc-ai-nonce');

            const PDF_URL = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs';
            const PDF_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';

            function esc(s) {
                return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
            }

            function normalizeText(s) {
                return String(s || '')
                    .replace(/\u00a0/g, ' ')
                    .replace(/\r/g, '')
                    .replace(/[ \t]+\n/g, '\n')
                    .replace(/\n{3,}/g, '\n\n')
                    .trim();
            }

            function looksCorrupt(text) {
                if (!text || text.trim().length < 100) return true;
                const replacement = (text.match(/\uFFFD/g) || []).length;
                const control = (text.match(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g) || []).length;
                const privateUse = (text.match(/[\uE000-\uF8FF]/g) || []).length;
                const numbered = (text.match(/(?:^|\n)\s*\d{1,3}\s*[\.\)]\s+/gm) || []).length;
                const ratio = text.length ? privateUse / text.length : 0;
                return replacement >= 3 || control >= 3 || ratio > 0.005 || numbered < 5;
            }

            function containsPrivateUse(text) {
                return /[\uE000-\uF8FF]/u.test(String(text || ''));
            }

            function groupPageText(items) {
                const rows = [];
                for (const item of items) {
                    const str = (item.str || '').trim();
                    if (!str) continue;
                    const y = Math.round((item.transform?.[5] || 0) / 2) * 2;
                    let row = rows.find(r => Math.abs(r.y - y) <= 2);
                    if (!row) { row = {y, items: []}; rows.push(row); }
                    row.items.push(item);
                }
                rows.sort((a,b) => b.y - a.y);
                return rows.map(r => r.items.sort((a,b) => (a.transform?.[4] || 0) - (b.transform?.[4] || 0)).map(x => x.str || '').join(' ')).join('\n');
            }

            async function loadPdf() {
                return await import(PDF_URL);
            }

            async function extractText(pdfjs, file) {
                pdfjs.GlobalWorkerOptions.workerSrc = PDF_WORKER;
                const buf = await file.arrayBuffer();
                const pdf = await pdfjs.getDocument({data: buf}).promise;
                let text = '';
                const pages = [];
                for (let i=1; i<=pdf.numPages; i++) {
                    const page = await pdf.getPage(i);
                    const content = await page.getTextContent();
                    const pageText = groupPageText(content.items || []);
                    pages.push(pageText);
                    text += pageText + '\n\n';
                    status.textContent = `Reading PDF text: page ${i}/${pdf.numPages}…`;
                }
                return {pdf, text: normalizeText(text), pages};
            }

            async function extractWordText(file) {
                if (!window.mammoth) throw new Error('Word document reader could not be loaded. Please try again.');
                status.textContent = 'Reading Word document…';
                const result = await window.mammoth.extractRawText({arrayBuffer: await file.arrayBuffer()});
                return {text: normalizeText(result.value || ''), pdf:null, pages:[]};
            }

            function splitAnswerKey(text) {
                const patterns = [
                    /provisional\s+answer\s+key/i,
                    /answer\s+key/i,
                    /correct\s+answer\s+key/i
                ];
                let pos = -1;
                for (const re of patterns) {
                    const m = re.exec(text);
                    if (m && (pos === -1 || m.index < pos)) pos = m.index;
                }
                if (pos < 0) return {main:text, key:''};
                return {main:text.slice(0,pos), key:text.slice(pos)};
            }

            function parseAnswerKey(keyText) {
                const answers = {};
                const re = /(?:^|\n|\s)(\d{1,4})\s*[\.\):\-]?\s*\(?([A-E])\)?(?=\s|$)/gi;
                let m;
                while ((m = re.exec(keyText))) {
                    const n = Number(m[1]);
                    if (n >= 1 && n <= 2000) answers[n] = m[2].toUpperCase();
                }
                return answers;
            }

            function parseQuestions(text) {
                const {main} = splitAnswerKey(text);
                const starts = [];
                const re = /(?:^|\n)\s*(\d{1,3})\s*[\.\)]\s+/g;
                let m;
                while ((m = re.exec(main))) {
                    starts.push({n:Number(m[1]), start:m.index + m[0].length, matchStart:m.index});
                }

                function clean(s) {
                    return String(s || '')
                        .replace(/\u00a0/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                // PDF text extraction from PSC papers often puts two options on one line,
                // e.g. "( ) A ... ( ) B ...". Split those option markers wherever they occur.
                function splitOptionLine(line) {
                    const hits = [];
                    // PSC PDFs frequently extract the four option markers as combinations
                    // such as (A), (൫), (൦), ൯), or ( ) A. The marker itself is unreliable;
                    // only its position matters. We therefore split every option-marker
                    // token and later assign A/B/C/D/E sequentially.
                    const marker = /(?:\(\s*(?:[A-Ea-e]|[\u0D00-\u0D7F]|[0-9]{1,3}|©)?\s*\)|(?:^|\s)[\u0D00-\u0D7F0-9]{1,5}\)|©)\s*(?:[A-E]\s*)?/gu;
                    let m;
                    while ((m = marker.exec(line))) {
                        hits.push({pos:m.index, end:m.index + m[0].length});
                    }
                    // Also support clean standalone A), B), C), D), E) markers.
                    const standalone = /(?:^|\s)([A-E])\s*[\)\].:\-]\s*/gu;
                    while ((m = standalone.exec(line))) {
                        hits.push({pos:m.index + (m[0].startsWith(' ') ? 1 : 0), end:m.index + m[0].length});
                    }
                    hits.sort((a,b) => a.pos - b.pos);
                    const unique = [];
                    for (const h of hits) {
                        if (!unique.some(x => Math.abs(x.pos - h.pos) < 2)) unique.push(h);
                    }
                    if (!unique.length) return [];
                    const out = [];
                    for (let i=0; i<unique.length; i++) {
                        const h = unique[i];
                        const next = i + 1 < unique.length ? unique[i+1].pos : line.length;
                        const value = line.slice(h.end, next).replace(/^\s*[\)\].:\-]\s*/, '');
                        if (clean(value)) out.push({text:clean(value)});
                    }
                    return out;
                }

                function extractOptions(block) {
                    const lines = block.split(/\n+/).map(x => x.trim()).filter(Boolean);
                    const found = [];
                    let firstOptionLine = -1;
                    for (let i=0; i<lines.length; i++) {
                        const parts = splitOptionLine(lines[i]);
                        const startsLikeOption = /^\s*(?:\(|[\u0D00-\u0D7F0-9©]|[A-E]\s*[\)\].:\-])/u.test(lines[i]);
                        if (parts.length && (startsLikeOption || parts.length >= 2)) {
                            firstOptionLine = i;
                            found.push(...parts);
                            // Remaining lines are continuation text until another option marker.
                            let currentIndex = found.length - 1;
                            for (let j=i+1; j<lines.length; j++) {
                                const next = splitOptionLine(lines[j]);
                                const nextStartsLikeOption = /^\s*(?:\(|[\u0D00-\u0D7F0-9©]|[A-E]\s*[\)\].:\-])/u.test(lines[j]);
                                if (next.length && (nextStartsLikeOption || next.length >= 2)) {
                                    found.push(...next);
                                    currentIndex = found.length - 1;
                                } else if (found[currentIndex]) {
                                    found[currentIndex].text += ' ' + clean(lines[j]);
                                }
                            }
                            break;
                        }
                    }
                    const options = [];
                    found.slice(0, 5).forEach((o, idx) => {
                        const t = clean(o.text)
                            .replace(/\b(?:Correct\s+Answer|Answer|Ans)\s*[:\-]?.*$/is, '')
                            .replace(/134\/2017-M\s*[•\-]?\s*Separated\s+question\s+format/gi, '')
                            .trim();
                        if (t) options.push({key:String.fromCharCode(65+idx), text:t});
                    });
                    return {options, firstOptionLine};
                }

                const candidates = [];
                for (let i=0; i<starts.length; i++) {
                    const s = starts[i];
                    const e = i+1 < starts.length ? starts[i+1].matchStart : main.length;
                    const block = main.slice(s.start, e).trim();
                    if (!block) continue;
                    const {options, firstOptionLine} = extractOptions(block);
                    if (options.length < 2) continue;
                    const lines = block.split(/\n+/).map(x => x.trim()).filter(Boolean);
                    let questionText = firstOptionLine >= 0 ? lines.slice(0, firstOptionLine).join(' ') : block;
                    questionText = clean(questionText)
                        .replace(/^\s*(?:Instructions?\s+to\s+Candidates|Question\s+Paper).*$/i, '')
                        .replace(/134\/2017-M\s*[•\-]?\s*Separated\s+question\s+format/gi, '')
                        .trim();
                    if (!questionText) continue;
                    candidates.push({number:s.n, question:questionText, options, correct:[], explanation:''});
                }

                // The first real Question 1 is the first valid numbered candidate. Ignore
                // instruction-page numbering and everything before it.
                const firstOne = candidates.findIndex(q => Number(q.number) === 1);
                if (firstOne < 0) return [];
                const ordered = candidates.slice(firstOne);

                // Stop at the first answer-key section and keep only the question sequence.
                const result = [];
                const seen = new Set();
                let expected = 1;
                for (const q of ordered) {
                    const n = Number(q.number);
                    if (n < expected) continue;
                    if (n > expected) {
                        // Allow a missing number in the preview; the importer will report it.
                        expected = n;
                    }
                    if (n > 100) break;
                    const key = n + '|' + q.question.replace(/\s+/g,' ').toLowerCase();
                    if (seen.has(key)) continue;
                    seen.add(key);
                    result.push(q);
                    expected = n + 1;
                }
                return result.sort((a,b) => Number(a.number) - Number(b.number));
            }

            function renderPreview(items, mode) {
                preview.innerHTML = '';
                if (!items.length) {
                    preview.innerHTML = '<div class="notice notice-warning"><p>No question candidates were detected. OCR may also be unable to read this document.</p></div>';
                    return;
                }
                const wrap = document.createElement('div');
                wrap.className = 'psc-question-preview';
                wrap.innerHTML = `<p><strong>${items.length}</strong> question candidates detected using ${esc(mode)}.</p>`;
                items.slice(0,12).forEach(q => {
                    const box = document.createElement('div');
                    box.style.cssText = 'border:1px solid #dcdcde;padding:14px;margin:8px 0;background:#fafafa';
                    box.innerHTML = `<div><strong>${q.number}.</strong> ${esc(q.question)}</div>` +
                        (q.options || []).map(o => `<div class="psc-option"><strong>${esc(o.key)})</strong> ${esc(o.text)}</div>`).join('') +
                        `<div style="margin-top:6px;color:#646970"><em>Correct answer: to be set by admin after import</em></div>`;
                    wrap.appendChild(box);
                });
                if (items.length > 12) {
                    const p = document.createElement('p'); p.textContent = 'Showing the first 12 candidates for preview.'; wrap.appendChild(p);
                }
                preview.appendChild(wrap);
            }

            async function renderPageDataUrl(pdf, pageNumber) {
                const page = await pdf.getPage(pageNumber);
                const viewport = page.getViewport({scale: 2.0});
                const canvas = document.createElement('canvas');
                canvas.width = Math.ceil(viewport.width);
                canvas.height = Math.ceil(viewport.height);
                const ctx = canvas.getContext('2d', {willReadFrequently:true});
                await page.render({canvasContext:ctx, viewport}).promise;
                return canvas.toDataURL('image/jpeg', 0.88);
            }

            async function aiParsePdf(pdf) {
                if (!window.ajaxurl) throw new Error('WordPress AJAX endpoint is unavailable.');
                const nonce = aiNonce?.value || '';
                if (!nonce) throw new Error('AI security token is unavailable.');
                const all = [];
                // Process one page per AI request. Sending several dense exam pages in one
                // request can truncate the structured response and lose many questions.
                for (let pageNo = 1; pageNo <= pdf.numPages; pageNo++) {
                    status.textContent = `AI extraction: page ${pageNo}/${pdf.numPages}…`;
                    const images = [await renderPageDataUrl(pdf, pageNo)];
                    const fd = new FormData();
                    fd.append('action', 'psc_ai_parse_pages');
                    fd.append('nonce', nonce);
                    fd.append('images', JSON.stringify(images));
                    fd.append('page_no', String(pageNo));
                    fd.append('total_pages', String(pdf.numPages));
                    const response = await fetch(window.ajaxurl, {method:'POST', body:fd});
                    const data = await response.json();
                    if (!data.success) throw new Error(data.data?.message || `AI extraction failed on page ${pageNo}.`);
                    for (const q of (data.data?.questions || [])) all.push(q);
                }

                // Merge overlaps/repeated pages by question number. Keep the most complete copy.
                const map = new Map();
                for (const q of all) {
                    if (!q?.number || !q?.question) continue;
                    const prev = map.get(Number(q.number));
                    if (!prev || (q.question.length + (q.options||[]).reduce((n,o)=>n+(o.text||'').length,0)) >
                        (prev.question.length + (prev.options||[]).reduce((n,o)=>n+(o.text||'').length,0))) {
                        map.set(Number(q.number), q);
                    }
                }
                return [...map.values()].sort((a,b)=>Number(a.number)-Number(b.number));
            }

            async function ocrPdf(pdf, pages) {
                if (!window.Tesseract) throw new Error('OCR library could not be loaded.');
                const worker = await Tesseract.createWorker(['eng', 'mal'], 1, {
                    langPath: 'https://tessdata.projectnaptha.com/4.0.0',
                    logger: m => {
                        if (m.status) status.textContent = `OCR: ${m.status}${m.progress ? ' ' + Math.round(m.progress*100) + '%' : ''}`;
                    }
                });
                let all = '';
                for (let i=1; i<=pdf.numPages; i++) {
                    status.textContent = `OCR: rendering page ${i}/${pdf.numPages}…`;
                    const page = await pdf.getPage(i);
                    const viewport = page.getViewport({scale: 2.5});
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.ceil(viewport.width);
                    canvas.height = Math.ceil(viewport.height);
                    const ctx = canvas.getContext('2d', {willReadFrequently:true});
                    await page.render({canvasContext:ctx, viewport}).promise;
                    const result = await worker.recognize(canvas);
                    all += (result.data.text || '') + '\n\n';
                }
                await worker.terminate();
                return normalizeText(all);
            }

            input.addEventListener('change', async () => {
                const f = input.files?.[0];
                if (!f) return;
                submit.disabled = true;
                preview.innerHTML = '';
                const ext = (f.name.split('.').pop() || '').toLowerCase();
                const isPdf = ext === 'pdf';
                const isDocx = ext === 'docx';
                status.textContent = isPdf ? 'Loading PDF reader…' : 'Loading Word reader…';
                try {
                    let extracted;
                    let mode;
                    if (isPdf) {
                        const pdfjs = await loadPdf();
                        extracted = await extractText(pdfjs, f);
                        mode = 'PDF text layer';
                    } else if (isDocx) {
                        extracted = await extractWordText(f);
                        mode = 'Word document text';
                    } else {
                        throw new Error('Please select a PDF or DOCX Word file.');
                    }

                    let text = extracted.text;
                    let parsed = parseQuestions(text);

                    if (useAI?.checked) {
                        if (!isPdf) throw new Error('AI visual extraction currently requires a PDF. DOCX files use direct Word text extraction.');
                        if (!aiNonce?.value) throw new Error('AI is not configured. Go to PSC LMS → Settings → AI.');
                        status.textContent = 'AI extraction selected. Rendering PDF pages…';
                        parsed = await aiParsePdf(extracted.pdf);
                        mode = 'AI visual extraction';
                    } else if (!parsed.length) {
                        status.innerHTML = '<strong>No question candidates detected from the file text.</strong> This importer does not automatically use OCR. If the document is genuinely image-only, enable AI extraction or use a text-based PDF/DOCX file.';
                        return;
                    }

                    const detectedNumbers = new Set(parsed.map(q => Number(q.number)).filter(n => n > 0));
                    const expectedCount = /\b100\s+questions?\b/i.test(extracted.text) ? 100 : 0;
                    const missing = expectedCount ? Array.from({length: expectedCount}, (_,i) => i+1).filter(n => !detectedNumbers.has(n)) : [];
                    document.getElementById('psc-parsed-json').value = JSON.stringify(parsed);
                    renderPreview(parsed, mode);
                    if (expectedCount && parsed.length < expectedCount) {
                        status.innerHTML = `Extracted <strong>${parsed.length}</strong> of <strong>${expectedCount}</strong> expected questions using <strong>${esc(mode)}</strong>. <span style="color:#b32d2e;font-weight:600;">Missing question numbers: ${esc(missing.slice(0,30).join(', '))}${missing.length > 30 ? '…' : ''}</span>`;
                        submit.disabled = true;
                        const warning = document.createElement('div');
                        warning.className = 'notice notice-error';
                        warning.style.marginTop = '12px';
                        warning.innerHTML = `<p><strong>Import blocked:</strong> the document indicates 100 questions, but only ${parsed.length} were extracted. No incomplete question bank will be imported.</p>`;
                        preview.prepend(warning);
                    } else {
                        status.innerHTML = `Extracted <strong>${parsed.length}</strong>${expectedCount ? ` of <strong>${expectedCount}</strong>` : ''} question candidates using <strong>${esc(mode)}</strong>.`;
                        submit.disabled = false;
                    }

                    if (!source.value) {
                        source.value = f.name.replace(/\.(pdf|docx)$/i,'');
                    }
                    if (!year.value) {
                        const ym = f.name.match(/(?:19|20)\d{2}/);
                        if (ym) year.value = ym[0];
                    }
                    if (!examTitle.value) {
                        examTitle.value = source.value || f.name.replace(/\.(pdf|docx)$/i,'');
                    }
                } catch (e) {
                    console.error(e);
                    status.innerHTML = '<strong>Import preparation failed:</strong> ' + esc(e.message || e);
                    submit.disabled = true;
                }
            });

            createExam.addEventListener('change', () => {
                document.getElementById('psc-exam-settings').style.display = createExam.checked ? 'block' : 'none';
            });
        })();
        </script>
        <?php
        echo '</div>';
    }

    public static function import_pdf(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('psc_import_questions_pdf')) wp_die('Access denied.');
        if (empty($_FILES['question_source']['tmp_name'])) wp_die('PDF or DOCX file is required.');

        $file = $_FILES['question_source'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'docx'], true)) wp_die('Only PDF and DOCX Word files are allowed.');

        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('question_source', 0);
        if (is_wp_error($attachment_id)) wp_die($attachment_id->get_error_message());

        $parsed = json_decode(wp_unslash($_POST['parsed_json'] ?? '[]'), true);
        if (!is_array($parsed)) $parsed = [];

        global $wpdb;
        $p = $wpdb->prefix;
        $subject_id = absint($_POST['subject_id'] ?? 0) ?: null;
        $topic_id = absint($_POST['topic_id'] ?? 0) ?: null;
        $difficulty = in_array($_POST['difficulty'] ?? 'medium', ['easy','medium','hard'], true) ? $_POST['difficulty'] : 'medium';
        $source = sanitize_text_field(wp_unslash($_POST['source'] ?? ''));
        $year = sanitize_text_field(wp_unslash($_POST['exam_year'] ?? ''));
        $publish = !empty($_POST['publish']);
        $language = sanitize_text_field(wp_unslash($_POST['language'] ?? ''));
        $now = current_time('mysql');
        $imported = 0;
        $skipped = 0;
        $imported_ids = [];

        foreach ($parsed as $item) {
            $question = wp_kses_post($item['question'] ?? '');
            $options = (array)($item['options'] ?? []);
            if ($question === '' || count($options) < 2) {
                $skipped++;
                continue;
            }

            $normalized = strtolower(preg_replace('/\s+/', ' ', wp_strip_all_tags($question)));
            $duplicate = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$p}psc_questions WHERE LOWER(REPLACE(REPLACE(question,'\\n',' '),'  ',' '))=%s LIMIT 1",
                $normalized
            ));
            if ($duplicate) {
                $skipped++;
                continue;
            }

            $correct = array_map('strtoupper', (array)($item['correct'] ?? []));
            $data = [
                'subject_id' => $subject_id,
                'topic_id' => $topic_id,
                'question' => $question,
                'question_pdf_attachment_id' => $attachment_id,
                'question_type' => count($correct) > 1 ? 'multiple' : 'single',
                'difficulty' => $difficulty,
                'explanation' => wp_kses_post($item['explanation'] ?? ''),
                'source' => $source ?: get_the_title($attachment_id),
                'source_question_number' => sanitize_text_field((string)($item['number'] ?? '')),
                'exam_year' => $year,
                'status' => $publish ? 'published' : 'draft',
                'created_at' => $now,
                'updated_at' => $now
            ];

            $ok = $wpdb->insert($p.'psc_questions', $data);
            if (!$ok) {
                $skipped++;
                continue;
            }

            $qid = (int)$wpdb->insert_id;
            $i = 0;
            foreach ($options as $o) {
                $key = strtoupper(preg_replace('/[^A-E]/', '', (string)($o['key'] ?? '')));
                $text = wp_kses_post($o['text'] ?? '');
                if (!$key || trim(wp_strip_all_tags($text)) === '') continue;

                $wpdb->insert($p.'psc_question_options', [
                    'question_id' => $qid,
                    'option_key' => $key,
                    'option_text' => $text,
                    'is_correct' => in_array($key, $correct, true) ? 1 : 0,
                    'sort_order' => $i++
                ]);
            }

            if (!empty($item['explanation'])) {
                $wpdb->insert($p.'psc_question_facts', [
                    'question_id' => $qid,
                    'fact' => wp_kses_post($item['explanation']),
                    'sort_order' => 0
                ]);
            }

            $imported_ids[] = $qid;
            $imported++;
        }

        $exam_title = '';
        if (!empty($_POST['create_exam']) && $imported_ids) {
            $exam_title = sanitize_text_field(wp_unslash($_POST['exam_title'] ?? ''));
            if ($exam_title === '') {
                $exam_title = trim(($source ?: get_the_title($attachment_id)) . ($year ? ' ' . $year : ''));
            }

            $duration = max(1, absint($_POST['exam_duration'] ?? 75));
            $marks_each = max(0, (float)($_POST['exam_marks'] ?? 1));
            $negative = max(0, (float)($_POST['negative_mark'] ?? 0));
            $passing = min(100, max(0, (float)($_POST['passing_percentage'] ?? 40)));
            $exam_status = !empty($_POST['exam_published']) ? 'published' : 'draft';

            $wpdb->insert($p.'psc_exams', [
                'title' => $exam_title,
                'description' => 'Imported from ' . ($source ?: get_the_title($attachment_id)),
                'duration_minutes' => $duration,
                'total_marks' => $marks_each * count($imported_ids),
                'negative_mark' => $negative,
                'passing_percentage' => $passing,
                'max_attempts' => 0,
                'shuffle_questions' => 0,
                'shuffle_options' => 0,
                'status' => $exam_status,
                'created_at' => $now,
                'updated_at' => $now
            ]);

            $exam_id = (int)$wpdb->insert_id;
            if ($exam_id) {
                foreach ($imported_ids as $sort => $qid) {
                    $wpdb->insert($p.'psc_exam_questions', [
                        'exam_id' => $exam_id,
                        'question_id' => $qid,
                        'marks' => $marks_each,
                        'sort_order' => $sort
                    ]);
                }
            } else {
                $exam_title = '';
            }
        }

        $args = ['imported'=>$imported, 'skipped'=>$skipped];
        if ($exam_title) $args['exam'] = $exam_title;
        wp_safe_redirect(self::url($args));
        exit;
    }
}
