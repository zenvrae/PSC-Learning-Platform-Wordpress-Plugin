<?php
namespace PSC_LMS;
if (!defined('ABSPATH')) exit;

class Plugin {
    public static function init(): void {
        add_action('init', [self::class, 'register_roles']);
    }

    public static function register_roles(): void {
        $roles = [
            'psc_content_manager' => [
                'name' => 'PSC Content Manager',
                'caps' => ['read'=>true,'psc_manage_courses'=>true,'psc_manage_lessons'=>true]
            ],
            'psc_question_manager' => [
                'name' => 'PSC Question Manager',
                'caps' => ['read'=>true,'psc_manage_questions'=>true,'psc_manage_exams'=>true]
            ],
        ];
        foreach ($roles as $slug => $role) {
            if (!get_role($slug)) {
                add_role($slug, $role['name'], $role['caps']);
            } else {
                $r = get_role($slug);
                foreach ($role['caps'] as $cap => $grant) {
                    $r->add_cap($cap, $grant);
                }
            }
        }
    }
}
