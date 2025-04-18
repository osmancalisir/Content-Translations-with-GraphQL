<?php
/*
 * Plugin Name: Content Translations
 * Description: Adds structured translations for posts/pages with GraphQL support.
 * Version: 1.0.0
 * Author: Osman Calisir
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

class Content_Translations {
    private $languages;
    private $current_lang;

    public function __construct() {
        $this->languages = apply_filters('wctp_languages', [
            'en' => 'English',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
        ]);

        add_action('init', [$this, 'register_query_vars_and_rewrites']);
        add_action('add_meta_boxes', [$this, 'add_translation_meta_boxes']);
        add_action('save_post', [$this, 'save_translations'], 10, 2);
        add_filter('post_link', [$this, 'filter_content_link'], 10, 2);
        add_filter('page_link', [$this, 'filter_content_link'], 10, 2);
        add_action('template_redirect', [$this, 'apply_translations_on_frontend']);
        add_filter('do_redirect_guess_404_permalink', [$this, 'disable_redirect_guess']);

        add_action('graphql_register_types', [$this, 'register_graphql_support'], 10, 0);

        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);
    }

    public function register_query_vars_and_rewrites() {
        $lang_codes = array_keys($this->languages);
        add_rewrite_tag('%lang%', '(' . implode('|', $lang_codes) . ')');

        foreach ($lang_codes as $code) {
            if ($code === 'en') continue;

            add_rewrite_rule(
                "^$code/([^/]+)/?$",
                "index.php?name=$matches[1]&lang=$code",
                'top'
            );
            add_rewrite_rule(
                "^$code/?$",
                "index.php?lang=$code",
                'top'
            );
        }
    }

    public function add_translation_meta_boxes() {
        foreach (['post', 'page'] as $type) {
            add_meta_box(
                'wctp_translations',
                __('Translations', 'content-translations'),
                [$this, 'render_translation_metabox'],
                $type,
                'normal',
                'high'
            );
        }
    }

    public function render_translation_metabox($post) {
        wp_nonce_field('wctp_save_translations', 'wctp_nonce');
        $translations = get_post_meta($post->ID, '_wctp_translations', true) ?: [];
        
        echo '<div id="wctp-tabs"><ul>';
        foreach ($this->languages as $code => $label) {
            if ($code === 'en') continue;
            echo '<li><a href="#tab-' . esc_attr($code) . '">' . esc_html($label) . '</a></li>';
        }
        echo '</ul>';

        foreach ($this->languages as $code => $label) {
            if ($code === 'en') continue;
            $value = $translations[$code] ?? '';
            echo '<div id="tab-' . esc_attr($code) . '">';
            wp_editor(
                wp_kses_post($value),
                'wctp_editor_' . $code,
                [
                    'textarea_name' => "wctp_trans[{$code}]",
                    'textarea_rows' => 10,
                    'editor_class' => 'wctp-translation-editor'
                ]
            );
            echo '</div>';
        }
        echo '</div>';

        echo '<style>
            #wctp-tabs .ui-tabs-nav { padding: 0; margin: 0 0 1em; }
            #wctp-tabs .ui-tabs-nav li { display: inline-block; margin: 0 0.5em 0 0; }
            #wctp-tabs .ui-tabs-nav a { padding: 0.5em 1em; text-decoration: none; background: #f5f5f5; }
            #wctp-tabs .ui-tabs-nav .ui-tabs-active a { background: #fff; }
            #wctp-tabs .ui-tabs-panel { padding: 1em; border: 1px solid #ddd; background: #fff; }
            .wctp-translation-editor { border: 1px solid #ddd; padding: 10px; }
        </style>';
        
        wp_enqueue_script('jquery-ui-tabs');
    }

    public function save_translations($post_id, $post) {
        $nonce = isset($_POST['wctp_nonce']) 
            ? sanitize_text_field(wp_unslash($_POST['wctp_nonce'])) 
            : '';
        
        if (!wp_verify_nonce($nonce, 'wctp_save_translations')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
    
        $translations = isset($_POST['wctp_trans']) 
            ? array_map('wp_kses_post', wp_unslash($_POST['wctp_trans'])) 
            : [];
    
        update_post_meta($post_id, '_wctp_translations', $translations);
    }

    public function filter_content_link($url, $post) {
        $lang = get_query_var('lang') ?: 'en';
        if ($lang !== 'en' && array_key_exists($lang, $this->languages)) {
            $url = home_url("/{$lang}/" . $post->post_name . '/');
        }
        return $url;
    }

    public function apply_translations_on_frontend() {
        $this->current_lang = get_query_var('lang', 'en');
        if (!array_key_exists($this->current_lang, $this->languages)) {
            $this->current_lang = 'en';
        }

        add_filter('the_content', [$this, 'replace_content_with_translation']);
    }

    public function replace_content_with_translation($content) {
        global $post;
        
        if ($this->current_lang === 'en') {
            return $content;
        }

        $translations = get_post_meta($post->ID, '_wctp_translations', true);
        return $translations[$this->current_lang] ?? $content;
    }

    public function disable_redirect_guess($do_redirect) {
        return get_query_var('lang') ? false : $do_redirect;
    }

    // === GraphQL Integration === //
    public function register_graphql_support() {
        // Register ContentFormatEnum first
        $this->register_content_format_enum();
        
        // Register TranslationContent type
        $this->register_translation_content_type();
        
        // Register Translations type
        $this->register_translations_type();
        
        // Add translations field to post types
        $this->register_translations_field();
    }

    private function register_content_format_enum() {
        register_graphql_enum_type('WCTP_ContentFormat', [
            'description' => __('Content output format', 'content-translations'),
            'values' => [
                'RAW' => [
                    'value' => 'raw',
                    'description' => __('Raw unformatted content', 'content-translations')
                ],
                'RENDERED' => [
                    'value' => 'rendered',
                    'description' => __('Content formatted for display', 'content-translations')
                ]
            ]
        ]);
    }

    private function register_translation_content_type() {
        register_graphql_object_type('WCTP_TranslationContent', [
            'description' => __('Translated content for a language', 'content-translations'),
            'fields' => [
                'content' => [
                    'type' => 'String',
                    'description' => __('Translated content in requested format', 'content-translations'),
                    'args' => [
                        'format' => [
                            'type' => 'WCTP_ContentFormat',
                            'defaultValue' => 'rendered'
                        ]
                    ],
                    'resolve' => function($source, $args) {
                        $raw_content = $source['rawContent'] ?? '';
                        
                        if ('raw' === $args['format']) {
                            return $raw_content;
                        }
                        
                        return apply_filters('the_content', $raw_content);
                    }
                ]
            ]
        ]);
    }

    private function register_translations_type() {
        $fields = [];
        foreach ($this->languages as $code => $label) {
            if ($code === 'en') continue;
            $fields[$code] = [
                'type' => 'WCTP_TranslationContent',
                /* translators: %s: Language name */
                'description' => sprintf(__('%s translation', 'content-translations'), $label)
            ];
        }

        if (empty($fields)) {
            throw new \Exception('No translation languages configured');
        }

        register_graphql_object_type('WCTP_Translations', [
            'description' => __('Content translations keyed by language code', 'content-translations'),
            'fields' => $fields
        ]);
    }

    private function register_translations_field() {
        foreach (['Post', 'Page'] as $type) {
            register_graphql_field($type, 'translations', [
                'type' => 'WCTP_Translations',
                'description' => __('Available translations for this content', 'content-translations'),
                'resolve' => function($post) {
                    $translations = get_post_meta($post->databaseId, '_wctp_translations', true) ?: [];
                    $output = [];
                    
                    foreach ($this->languages as $code => $label) {
                        if ($code === 'en') continue;
                        $output[$code] = [
                            'rawContent' => $translations[$code] ?? null
                        ];
                    }
                    
                    return $output;
                }
            ]);
        }
    }

    public static function activate() {
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}

new Content_Translations();