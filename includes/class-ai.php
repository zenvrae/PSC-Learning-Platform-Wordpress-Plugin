<?php
namespace PSC_LMS;

if (!defined('ABSPATH')) exit;

class AI
{
    const OPTION_KEY = 'psc_lms_openai_api_key';
    const MODEL_KEY  = 'psc_lms_openai_model';

    public static function init(): void
    {
        add_action('wp_ajax_psc_ai_parse_pages', [self::class, 'ajax_parse_pages']);
        add_action('wp_ajax_psc_ai_test', [self::class, 'ajax_test']);
    }

    public static function key(): string
    {
        return trim((string) get_option(self::OPTION_KEY, ''));
    }

    public static function model(): string
    {
        $model = trim((string) get_option(self::MODEL_KEY, 'gpt-5.6-luna'));
        return $model ?: 'gpt-5.6-luna';
    }

    public static function enabled(): bool
    {
        return self::key() !== '';
    }

    public static function ajax_test(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Access denied.'], 403);
        check_ajax_referer('psc_ai_nonce', 'nonce');
        if (!self::key()) wp_send_json_error(['message' => 'OpenAI API key is not configured.']);

        $result = self::request([
            ['type' => 'text', 'text' => 'Reply with exactly: PSC LMS AI connection OK']
        ], 'connection-test');

        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success(['message' => 'OpenAI connection successful.', 'model' => self::model()]);
    }

    public static function ajax_parse_pages(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Access denied.'], 403);
        check_ajax_referer('psc_ai_nonce', 'nonce');
        if (!self::key()) wp_send_json_error(['message' => 'OpenAI API key is not configured. Go to PSC LMS → Settings → AI.']);

        $images_json = wp_unslash($_POST['images'] ?? '[]');
        $page_no = absint($_POST['page_no'] ?? 0);
        $total_pages = absint($_POST['total_pages'] ?? 0);
        $images = json_decode($images_json, true);
        if (!is_array($images) || !$images) wp_send_json_error(['message' => 'No page images were supplied.']);

        $content = [[
            'type' => 'text',
            'text' => self::parser_prompt($page_no, $total_pages)
        ]];

        $count = 0;
        foreach ($images as $image) {
            if (!is_string($image) || !preg_match('#^data:image/(?:jpeg|jpg|png|webp);base64,#i', $image)) continue;
            if (strlen($image) > 4500000) continue;
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $image,
                    'detail' => 'high'
                ]
            ];
            $count++;
            if ($count >= 4) break;
        }

        if ($count === 0) wp_send_json_error(['message' => 'The page images could not be read.']);

        $result = self::request($content, 'question-extraction');
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);

        $json = self::extract_json($result);
        if (!is_array($json)) wp_send_json_error(['message' => 'AI returned an invalid structured response.']);

        $questions = [];
        foreach ((array)($json['questions'] ?? []) as $q) {
            $number = absint($q['number'] ?? 0);
            $question = trim((string)($q['question'] ?? ''));
            if ($number < 1 || $question === '') continue;
            $options = [];
            foreach ((array)($q['options'] ?? []) as $index => $option) {
                $text = trim((string)($option['text'] ?? ''));
                if ($text === '') continue;
                $options[] = [
                    'key' => chr(65 + count($options)),
                    'text' => $text,
                ];
                if (count($options) >= 5) break;
            }
            if (count($options) < 2) continue;
            $questions[] = [
                'number' => $number,
                'question' => $question,
                'options' => $options,
                'correct' => [],
                'explanation' => ''
            ];
        }

        usort($questions, static function($a, $b) { return $a['number'] <=> $b['number']; });
        wp_send_json_success(['questions' => $questions]);
    }

    private static function parser_prompt(int $page_no = 0, int $total_pages = 0): string
    {
        $page_context = $page_no > 0 ? "This request contains page {$page_no}" . ($total_pages > 0 ? " of {$total_pages}" : '') . " from the exam paper. Extract every complete question visibly present on this page." : "Extract every complete question visibly present in the supplied page images.";

        return <<<PROMPT
You are extracting multiple-choice questions from exam-paper page images for a WordPress Question Bank.
{$page_context}

STRICT RULES:
1. Start importing only from the first real numbered question (normally 1.). Ignore candidate instructions, cover pages, headings, advertisements, answer-key pages, rough-work pages, and other material before Question 1.
2. Question numbers are the primary anchor. Preserve the printed question number.
3. Extract ONLY questions visibly present in these images. Never invent, complete, translate, paraphrase, or guess missing text.
4. Preserve Malayalam exactly as visible as possible. Preserve English exactly as visible as possible.
5. Detect options by their POSITION, not by the OCR/visual label. The first option is A, second B, third C, fourth D, fifth E. Ignore corrupted option labels such as "18)", Malayalam glyphs, or other OCR mistakes.
6. Keep wrapped lines with the question or option they belong to.
7. Do NOT determine or infer the correct answer, even if an answer key is visible. Return no correct answer.
8. Do not include answer-key entries as questions.
9. If a question is incomplete or unreadable, omit it rather than inventing missing text.
10. Return strict JSON only with this shape:
{
  "questions": [
    {
      "number": 1,
      "question": "...",
      "options": [
        {"text": "..."},
        {"text": "..."},
        {"text": "..."},
        {"text": "..."}
      ]
    }
  ]
}
No extra keys. No markdown. No correct-answer field.
PROMPT;
    }

    private static function request(array $content, string $purpose)
    {
        $body = [
            'model' => self::model(),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a precise document extraction engine. Output only the requested JSON when JSON is requested. Never guess missing exam text.'
                ],
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ],
            'temperature' => 0,
            'max_completion_tokens' => 12000,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'psc_question_extraction',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'questions' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => [
                                        'number' => ['type' => 'integer'],
                                        'question' => ['type' => 'string'],
                                        'options' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'additionalProperties' => false,
                                                'properties' => [
                                                    'text' => ['type' => 'string']
                                                ],
                                                'required' => ['text']
                                            ]
                                        ]
                                    ],
                                    'required' => ['number', 'question', 'options']
                                ]
                            ]
                        ],
                        'required' => ['questions']
                    ]
                ]
            ]
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . self::key(),
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) return $response;
        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            $message = (string)($data['error']['message'] ?? 'OpenAI API request failed.');
            return new \WP_Error('openai_error', $message, ['status' => $code, 'purpose' => $purpose]);
        }
        return $data;
    }

    private static function extract_json(array $response): ?array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        if (is_array($content)) {
            $content = wp_json_encode($content);
        }
        $decoded = json_decode((string)$content, true);
        return is_array($decoded) ? $decoded : null;
    }
}
