<?php
/**
 * Plugin Name:     MultiTab Translator
 * Description:     Adds a Translations meta-box with language tabs (excluding English) and GraphQL with RAW/RENDERED.
 * Version: 1.0
 * Author: Osman Calisir
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

class MultiTab_Translator {
    private $languages = [
        'de' => 'German',
        'fr' => 'French',
        'es' => 'Spanish',
        'it' => 'Italian',
        'nl' => 'Dutch',
        'no' => 'Norwegian',
        'sv' => 'Swedish',
        'fi' => 'Finnish',
        'da' => 'Danish',
        'tr' => 'Turkish',
        'zh' => 'Chinese',
        'ar' => 'Arabic',
        'ko' => 'Korean',
        'ja' => 'Japanese',
        'ru' => 'Russian',
        'hi' => 'Hindi',
        'ur' => 'Urdu',
    ];
    const META_KEY = '_translations';

    public function __construct() {
        add_action( 'add_meta_boxes',       [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post',            [ $this, 'save_post' ], 10, 2 );
        add_action( 'admin_enqueue_scripts',[ $this, 'enqueue_scripts' ] );
        add_action( 'graphql_register_types',[ $this, 'register_graphql_fields' ] );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'multitab-translations',
            __( 'Translations', 'multi-tab-translator' ),
            [ $this, 'render_meta_box' ],
            [ 'post', 'page' ],
            'normal',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        $translations = get_post_meta( $post->ID, self::META_KEY, true );
        if ( ! is_array( $translations ) ) {
            $translations = [];
        }
        if ( empty( $translations['en'] ) ) {
            $translations['en'] = $post->post_content;
        }
        $codes = array_keys( $this->languages );
        $first = reset( $codes );

        wp_nonce_field( 'multitab_translations_nonce', 'multitab_translations_nonce' );
        ?>
        <div class="multitab-translations">
            <div class="translations-tabs">
                <?php foreach ( $this->languages as $code => $name ) : ?>
                    <button type="button"
                        class="tab-button <?php echo $code === $first ? 'active' : ''; ?>"
                        data-lang="<?php echo esc_attr( $code ); ?>">
                        <?php echo esc_html( $name ); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="translations-editor">
                <?php
                wp_editor(
                    $translations[ $first ],
                    'mt_translations_editor',
                    [
                        'textarea_name' => "translations[{$first}]",
                        'media_buttons' => true,
                        'textarea_rows' => 10,
                    ]
                );
                ?>
            </div>

            <div class="translations-fields" style="display:none;">
                <?php foreach ( $this->languages as $code => $_name ) : ?>
                    <textarea id="translation_<?php echo esc_attr( $code ); ?>"
                              name="translations[<?php echo esc_attr( $code ); ?>]"
                    ><?php echo esc_textarea( $translations[ $code ] ?? '' ); ?></textarea>
                <?php endforeach; ?>
                <textarea id="translation_en" name="translations[en]" style="display:none;">
                    <?php echo esc_textarea( $translations['en'] ); ?>
                </textarea>
            </div>
        </div>
        <?php
    }

    public function save_post( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( empty( $_POST['multitab_translations_nonce'] )
            || ! wp_verify_nonce( $_POST['multitab_translations_nonce'], 'multitab_translations_nonce' )
        ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $raw = $_POST['translations'] ?? [];
        $clean = [];
        foreach ( $raw as $lang => $html ) {
            $clean[ $lang ] = wp_kses_post( $html );
        }
        if ( empty( $clean['en'] ) ) {
            $clean['en'] = wp_kses_post( $post->post_content );
        }
        update_post_meta( $post_id, self::META_KEY, $clean );
    }

    public function enqueue_scripts( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        $screen = get_current_screen();
        if ( ! in_array( $screen->post_type, [ 'post', 'page' ], true ) ) return;

        wp_enqueue_style(
            'multitab-translator-admin',
            plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
            [],
            '1.3.0'
        );
        wp_enqueue_script(
            'multitab-translator-admin',
            plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
            [ 'jquery', 'wp-tinymce' ],
            '1.3.0',
            true
        );
    }

    public function register_graphql_fields() {
        if ( ! function_exists( 'register_graphql_object_type' ) ) return;

        register_graphql_enum_type( 'ContentFormatEnum', [
            'description' => 'RAW or RENDERED content',
            'values'      => [
              'RAW'      => [ 'value' => 'RAW' ],
              'RENDERED' => [ 'value' => 'RENDERED' ],
            ],
          ] );

        register_graphql_object_type( 'TranslatedContent', [
            'fields' => [
              'content' => [
                'type'        => 'String',
                'description' => 'Content in RAW or RENDERED format',
                'args'        => [
                  'format' => [
                    'type'         => 'ContentFormatEnum',
                    'defaultValue' => 'RAW',
                  ],
                ],
                'resolve' => function( $source, $args ) {
                $val = $source['raw'] ?? '';
                $format = isset( $args['format'] ) ? strtoupper( $args['format'] ) : 'RAW';
                if ( 'RENDERED' === $format ) {
                        global $post;
                        $original_post = $post;
                        $post = get_post( $source['post_id'] );
                        setup_postdata( $post );
                        $rendered = apply_filters( 'the_content', $val );
                        $post = $original_post;
                        return $rendered;
                    }
                    return $val;
                },
                'authCallback' => function() { return true; },
              ],
            ],
          ] );

          $all_codes = array_keys( $this->languages );
          register_graphql_object_type( 'Translations', [
              'fields' => array_reduce(
                  $all_codes,
                  function( $fields, $code ) {
                      $fields[ $code ] = [ 'type' => 'TranslatedContent' ];
                      return $fields;
                  },
                  []
              ),
          ]);

        foreach ( [ 'Post', 'Page' ] as $type ) {
            register_graphql_field( $type, 'translations', [
                'type'    => 'Translations',
                'resolve' => function( $post ) {
                    $meta = get_post_meta( $post->ID, self::META_KEY, true ) ?: [];
                    $out = [];
                    foreach ( $this->languages as $lang => $name ) {
                        $out[ $lang ] = [
                            'raw'     => $meta[ $lang ] ?? '',
                            'post_id' => $post->ID
                        ];
                    }
                    return $out;
                },
            ]);
        }
    }
}

new MultiTab_Translator();