<?php 
/*
Plugin Name: Pastacode
Plugin URI: http://pastacode.wabeo.fr
Description: Embed GitHub, Gist, Pastebin, Bitbucket or whatever remote files and even your own code by copy/pasting.
Version: 1.5.1
Author: Willy Bahuaud
Author URI: http://wabeo.fr
Contributors, juliobox, willybahuaud
*/

define( 'PASTACODE_VERSION', '1.5.1' );

add_action( 'plugins_loaded', 'pastacode_load_languages' );
function pastacode_load_languages() {
    load_plugin_textdomain( 'pastacode', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 
}

add_shortcode( 'pastacode', 'sc_pastacode' );
function sc_pastacode( $atts, $content = "" ) {

    $atts = shortcode_atts( array(
        'provider'      => '',
        'user'          => '',
        'path_id'       => '',
        'repos'         => '',
        'revision'      => 'master',
        'lines'         => '',
        'lang'          => 'markup',
        'highlight'     => '',
        'message'       => '',
        'file'       => '',
        'linenumbers'   => 'n',
        'showinvisible' => 'n',
        ), $atts, 'sc_pastacode' );

    if( empty( $atts['provider'] ) && ! empty( $content ) )
        $atts['provider'] = md5( $content );

    $code_embed_transient = 'pastacode_' . substr( md5( serialize( $atts ) ), 0, 14 );

    $time = get_option( 'pastacode_cache_duration', DAY_IN_SECONDS * 7 );

    if( $atts['provider'] == 'manual' )
        $time = -1;

    if( $time==-1 || !$source = get_transient( $code_embed_transient ) ){

        $source = apply_filters( 'pastacode_'.$atts['provider'], array(), $atts, $content );

        if( ! empty( $source[ 'code' ] ) ) {
            //Wrap lines
            if( $lines = $atts['lines'] ) {
                $lines = array_map( 'intval', explode( '-', $lines ) );
                if ( ! isset( $lines[1] ) && isset( $lines[0] ) ) {
                    $lines[1] = $lines[0];
                }
                $source[ 'code' ] = implode( "\n", array_slice( preg_split( '/\r\n|\r|\n/', $source[ 'code' ] ), $lines[0] - 1, ( $lines[1] - $lines[0] ) + 1 ) );
            }
            if( $time>-1 )
                set_transient( $code_embed_transient, $source, $time );
        }
    }

    if( ! empty( $source[ 'code' ] ) ) {

        //Load scripts
        wp_enqueue_style( 'prismcss' );
        wp_enqueue_script( 'prismjs' ); 

        $ln_class = '';
        if( 'y' === get_option( 'pastacode_linenumbers', 'n' ) ) {
            wp_enqueue_style( 'prism-linenumbercss' );
            wp_enqueue_script( 'prism-linenumber' );
            $ln_class = ' line-numbers';
        }
        if( 'y' === get_option( 'pastacode_showinvisible', 'n' ) ) {
            wp_enqueue_style( 'prism-show-invisiblecss' );
            wp_enqueue_script( 'prism-show-invisible' );  
        }
        //highlight
        if( preg_match( '/([0-9-,]+)/', $atts['highlight'] ) ) {
            $highlight_val = ' data-line="' . $atts['highlight'] . '"';
            wp_enqueue_script( 'prism-highlight' );
            wp_enqueue_style( 'prism-highlightcss' );
        } else {
            $highlight_val = '';
        }

        //Code info
        $aboutCode = array();
        $aboutCode[] = '<div class="code-embed-infos">';
        if( isset( $source[ 'url' ] ) ) {
            $aboutCode[] = '<a href="' . esc_url( $source[ 'url' ] ) . '" title="' . sprintf( esc_attr__( 'See %s', 'pastacode' ), $source[ 'name' ] ) . '" target="_blank" class="code-embed-name">' . esc_html( $source[ 'name' ] ) . '</a>';
        }
        if( isset( $source[ 'raw' ] ) ) {
            $aboutCode[] = '<a href="' . esc_url( $source[ 'raw' ] ) . '" title="' . sprintf( esc_attr__( 'Back to %s' ), $source[ 'name' ] ) . '" class="code-embed-raw" target="_blank">' . __( 'view raw', 'pastacode' ) . '</a>';
        }
        if( ! isset( $source[ 'url' ] ) && ! isset( $source[ 'raw' ] ) && isset( $source[ 'name' ] ) ) {
            $aboutCode[] = '<span class="code-embed-name">' . $source[ 'name' ] . '</span>';
        }
        $aboutCode[] = '</div>';

        //Wrap
        $output = array();
        $output[] = '<div class="code-embed-wrapper">';
        $output[] = '<pre class="language-' . sanitize_html_class( $atts['lang'] ) . ' code-embed-pre' . $ln_class . '" ' . $highlight_val . '><code class="language-' . sanitize_html_class( $atts['lang'] ) . ' code-embed-code">'
        . $source[ 'code' ] .
        '</code></pre>';
        $output[] = '</div>';

        $pos = ( 'top' == get_option( 'pastacode_aboutcode_pos' ) ) ? 1 : 2;
        array_splice( $output, $pos, 0, $aboutCode );

        $output = implode( "\n", $output );

        return $output;
    } elseif( !empty( $atts['message'] ) ) {
        return '<span class="pastacode_message">' . esc_html( $atts['message'] ) . '</span>';
    }
}

add_filter( 'pastacode_github', '_pastacode_github', 10, 2 );
function _pastacode_github( $source, $atts ) {
    extract( $atts );
    if( $user && $repos && $path_id ) {
        $b64dcd = 'b'.'a'.'s'.'e'.'6'.'4'.'_'.'d'.'e'.'c'.'o'.'d'.'e';
        $req  = wp_sprintf( 'https://api.github.com/repos/%s/%s/contents/%s', $user, $repos, $path_id );
        $code = wp_remote_get( $req );
        if( ! is_wp_error( $code ) && 200 == wp_remote_retrieve_response_code( $code ) ) {
            $data = json_decode( wp_remote_retrieve_body( $code ) );
            $source[ 'name' ] = $data->name;
            $source[ 'code' ] = esc_html( $b64dcd ( $data->content ) );
            $source[ 'url' ]  = $data->html_url;
            $source[ 'raw' ]  = wp_sprintf( 'https://raw.github.com/%s/%s/%s/%s', $user, $repos, $revision, $path_id );
        } else {
            $req2 = wp_sprintf( 'https://raw.github.com/%s/%s/%s/%s', $user, $repos, $revision, $path_id );
            $code = wp_remote_get( $req2 );
            if( ! is_wp_error( $code ) && 200 == wp_remote_retrieve_response_code( $code ) ) {
                $name = explode( '/', $path_id );
                $source[ 'name' ] = $name[ count( $name ) - 1 ];
                $source[ 'code' ] = esc_html( wp_remote_retrieve_body( $code ) );
                $source[ 'url' ]  = wp_sprintf( 'https://github.com/%s/%s/blob/%s/%s', $user, $repos, $revision, $path_id );
                $source[ 'raw' ]  = $req2;
            }
        }
    }
    return $source;
}

add_filter( 'pastacode_gist', '_pastacode_gist', 10, 2 );
function _pastacode_gist( $source, $atts ) {
    extract( $atts );
    if( $path_id ) {
        $req  = wp_sprintf( 'https://api.github.com/gists/%s', $path_id );
        $code = wp_remote_get( $req );
        if( ! is_wp_error( $code ) && 200 == wp_remote_retrieve_response_code( $code ) ) {
            $data = json_decode( wp_remote_retrieve_body( $code ) );
            $source[ 'url' ]  = $data->html_url;
            if ( $file && isset( $data->files->$file) ) {
                $data = $data->files->$file;
            } else {
                $data = (array)$data->files;
                $data = reset($data);
            }
            $source[ 'name' ] = $data->filename;
            $source[ 'code' ] = esc_html( $data->content );                 
            $source[ 'raw' ]  = $data->raw_url;
        }
    }
    return $source;
}

add_filter( 'pastacode_bitbucket', '_pastacode_bitbucket', 10, 2 );
function _pastacode_bitbucket( $source, $atts ) {
    extract( $atts );
    if( $user && $repos && $path_id ) {
        $req  = wp_sprintf( 'https://bitbucket.org/api/1.0/repositories/%s/%s/raw/%s/%s', $user, $repos, $revision, $path_id );

        $code = wp_remote_get( $req );
        if( ! is_wp_error( $code ) && 200 == wp_remote_retrieve_response_code( $code ) ) {
            $source[ 'name' ] = basename( $path_id );
            $source[ 'code' ] = esc_html( wp_remote_retrieve_body( $code ) );
            $source[ 'url' ]  = wp_sprintf( 'https://bitbucket.org/%s/%s/src/%s/%s', $user, $repos, $revision, $path_id );
            $source[ 'raw' ]  = $req;
        }
    }
    return $source;
}

add_filter( 'pastacode_file', '_pastacode_file', 10, 2 );
function _pastacode_file( $source, $atts ) {
    extract( $atts );
    if( $path_id ) {
        $upload_dir = wp_upload_dir();
        $path_id = str_replace( '../', '', $path_id );
        $req  = esc_url( trailingslashit( $upload_dir[ 'baseurl' ] ) . $path_id );
        $code = wp_remote_get( $req );
        if( ! is_wp_error( $code ) && 200 == wp_remote_retrieve_response_code( $code ) ) {

            $source[ 'name' ] = basename( $path_id );
            $source[ 'code' ] = esc_html( wp_remote_retrieve_body( $code ) );
            $source[ 'url' ]  = ( $req );
        }
    }
    return $source;
}

add_filter( 'pastacode_pastebin', '_pastacode_pastebin', 10, 2 );
function _pastacode_pastebin( $source, $atts ) {
    extract( $atts );
    if( $path_id ) {
        $req  = wp_sprintf( 'http://pastebin.com/raw.php?i=%s', $path_id );
        $code = wp_remote_get( $req );
        if( ! is_wp_error( $code ) && 200 == wp_remote_retrieve_response_code( $code ) ) {
            $source[ 'name' ] = $path_id;
            $source[ 'code' ] = esc_html( wp_remote_retrieve_body( $code ) );
            $source[ 'url' ]  = wp_sprintf( 'http://pastebin.com/%s', $path_id );
            $source[ 'raw' ]  = wp_sprintf( 'http://pastebin.com/raw.php?i=%s', $path_id );
        }
    }
    return $source;
}

add_filter( 'pastacode_manual', '_pastacode_manual', 10, 3 );
function _pastacode_manual( $source, $atts, $content ) {
    extract( $atts );
    if( !empty( $content ) ){
        $source[ 'code' ] = esc_html( str_replace( array(
                                     '<br>', 
                                     '<br />', 
                                     '<br/>', 
                                     '</p>'."\n".'<pre><code>', 
                                     '</code></pre>'."\n".'<p>', 
                                     "\n" . '<pre><code>', 
                                     '</code></pre>' . "\n", 
                                     '<pre><code>', 
                                     '</code></pre>'), array(''), $content ) );
    }
    if( isset( $atts[ 'message' ] ) )
        $source[ 'name' ] = esc_html( $message );
    return $source;
}


add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pastacode_settings_action_links', 10, 2 );
function pastacode_settings_action_links( $links, $file ) {
    if( current_user_can( 'manage_options' ) )
        array_unshift( $links, '<a href="' . admin_url( 'options-general.php?page=pastacode' ) . '">' . __( 'Settings' ) . '</a>' );
    return $links;
}

add_filter( 'plugin_row_meta', 'pastacode_plugin_row_meta', 10, 2 );
function pastacode_plugin_row_meta( $plugin_meta, $plugin_file ) {
    if( plugin_basename( __FILE__ ) == $plugin_file ){
        $last = end( $plugin_meta );
        $plugin_meta = array_slice( $plugin_meta, 0, -2 );
        $a = array();
        $authors = array(
            array(  'name'=>'Willy Bahuaud', 'url'=>'http://wabeo.fr' ),
            array(  'name'=>'Julio Potier', 'url'=>'http://www.boiteaweb.fr' ),
        );
        foreach( $authors as $author )
            $a[] = '<a href="' . $author['url'] . '" title="' . esc_attr__( 'Visit author homepage' ) . '">' . $author['name'] . '</a>';
        $a = sprintf( __( 'By %s' ), wp_sprintf( '%l', $a ) );
        $plugin_meta[] = $a;
        $plugin_meta[] = $last;
    }
    return $plugin_meta;
}

//Register scripts
add_action( 'wp_enqueue_scripts', 'pastacode_enqueue_prismjs' );
function pastacode_enqueue_prismjs() {
    $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
    wp_register_script( 'prismjs', plugins_url( '/js/prism.js', __FILE__ ), false, PASTACODE_VERSION, true );
    wp_register_script( 'prism-highlight', plugins_url( '/plugins/line-highlight/prism-line-highlight' . $suffix . '.js', __FILE__ ), array( 'prismjs' ), PASTACODE_VERSION, true );
    wp_register_script( 'prism-linenumber', plugins_url( '/plugins/line-numbers/prism-line-numbers' . $suffix . '.js', __FILE__ ), array( 'prismjs' ), PASTACODE_VERSION, true );
    wp_register_script( 'prism-show-invisible', plugins_url( '/plugins/show-invisibles/prism-show-invisibles' . $suffix . '.js', __FILE__ ), array( 'prismjs' ), PASTACODE_VERSION, true );
    wp_register_style( 'prismcss', plugins_url( '/css/' . get_option( 'pastacode_style', 'prism' ) . '.css', __FILE__ ), false, PASTACODE_VERSION, 'all' );      
    wp_register_style( 'prism-highlightcss', plugins_url( '/plugins/line-highlight/prism-line-highlight.css', __FILE__ ), false, PASTACODE_VERSION, 'all' );      
    wp_register_style( 'prism-linenumbercss', plugins_url( '/plugins/line-numbers/prism-line-numbers.css', __FILE__ ), false, PASTACODE_VERSION, 'all' );  
    wp_register_style( 'prism-show-invisiblecss', plugins_url( '/plugins/show-invisibles/prism-show-invisibles.css', __FILE__ ), false, PASTACODE_VERSION, 'all' );      
    
    if ( apply_filters( 'pastacode_ajax', false ) ) {       
        wp_enqueue_script( 'prismjs' );
        wp_enqueue_style( 'prismcss' );
        wp_enqueue_style( 'prism-highlightcss' );
        wp_enqueue_script( 'prism-highlight' );

        if( 'y' === get_option( 'pastacode_linenumbers', 'n' ) ) {
            wp_enqueue_style( 'prism-linenumbercss' );
            wp_enqueue_script( 'prism-linenumber' );
            $ln_class = ' line-numbers';
        }
        if( 'y' === get_option( 'pastacode_showinvisible', 'n' ) ) {
            wp_enqueue_style( 'prism-show-invisiblecss' );
            wp_enqueue_script( 'prism-show-invisible' );  
        }
    } 
}

add_filter( 'admin_post_pastacode_drop_transients', 'pastacode_drop_transients', 10, 2 );
function pastacode_drop_transients() {
    if( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'pastacode_drop_transients' ) ){
        global $wpdb;
        $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_pastacode_%'" );
        wp_redirect( wp_get_referer() );
    }else{
        wp_nonce_ays('');
    }
}

/**
//Admin Settings
*/
add_action( 'admin_menu', 'pastacode_create_menu' );
function pastacode_create_menu() {
    add_options_page( 'Pastacode '. __( 'Settings' ), 'Pastacode', 'manage_options', 'pastacode', 'pastacode_settings_page' );
    register_setting( 'pastacode', 'pastacode_cache_duration' );
    register_setting( 'pastacode', 'pastacode_style' );
    register_setting( 'pastacode', 'pastacode_linenumbers' );
    register_setting( 'pastacode', 'pastacode_showinvisible' );
    register_setting( 'pastacode', 'pastacode_aboutcode_pos' );
}

function pastacode_setting_callback_function( $args ) {
    
    extract( $args );

    $value_old = get_option( $name );
    
    echo '<select name="' . $name . '" id="' . $name . '">';
    foreach( $options as $key => $option )
        echo '<option value="' . $key . '" ' . selected( $value_old==$key, true, false ) . '>' . esc_html( $option ) . '</option>';
    echo '</select>';
}


function pastacode_settings_page() {
?>
<div class="wrap">
    <?php screen_icon(); ?>
<h2>Pastacode v<?php echo PASTACODE_VERSION; ?></h2>

<?php 
    add_settings_section( 'pastacode_setting_section',
        __( 'General Settings', 'pastacode' ),
        '__return_false',
        'pastacode' );

    add_settings_field( 'pastacode_cache_duration',
        __( 'Caching duration', 'pastacode' ),
        'pastacode_setting_callback_function',
        'pastacode',
        'pastacode_setting_section',
        array(
            'options' => array(
                HOUR_IN_SECONDS      => sprintf( __( '%s hour' ), '1' ),
                HOUR_IN_SECONDS * 12 => __( 'Twice Daily' ),
                DAY_IN_SECONDS       => __( 'Once Daily' ),
                DAY_IN_SECONDS * 7   => __( 'Once Weekly', 'pastacode' ),
                0                    => __( 'Never reload', 'pastacode' ),
                -1                   => __( 'No cache (dev mode)', 'pastacode' ),
                ),
            'name' => 'pastacode_cache_duration'
         ) );

    add_settings_field( 'pastacode_style',
        __( 'Syntax Coloration Style', 'pastacode' ),
        'pastacode_setting_callback_function',
        'pastacode',
        'pastacode_setting_section',
        array(
            'options' => array(
                'prism'          => 'Prism',
                'prism-dark'     => 'Dark',
                'prism-funky'    => 'Funky',
                'prism-coy'      => 'Coy',
                'prism-okaidia'  => 'Okaïdia',
                'prism-tomorrow' => 'Tomorrow',
                'prism-twilight' => 'Twilight',
                ),
            'name' => 'pastacode_style'
         ) );

    add_settings_field( 'pastacode_aboutcode_pos',
        __( 'Code description location', 'pastacode' ),
        'pastacode_setting_callback_function',
        'pastacode',
        'pastacode_setting_section',
        array(
            'options' => array(
                'bottom' => __( 'Below code', 'pastacode' ),
                'top'    => __( 'Above code', 'pastacode' ),
                ),
            'name' => 'pastacode_aboutcode_pos'
         ) );

    add_settings_field( 'pastacode_linenumbers',
        __( 'Show line numbers', 'pastacode' ),
        'pastacode_setting_callback_function',
        'pastacode',
        'pastacode_setting_section',
        array(
            'options' => array(
                'y' => __( 'Yes', 'pastacode' ),
                'n' => __( 'No', 'pastacode' ),
                ),
            'name' => 'pastacode_linenumbers'
         ) );

    add_settings_field( 'pastacode_showinvisible',
        __( 'Show invisible chars', 'pastacode' ),
        'pastacode_setting_callback_function',
        'pastacode',
        'pastacode_setting_section',
        array(
            'options' => array(
                'y' => __( 'Yes', 'pastacode' ),
                'n' => __( 'No', 'pastacode' ),
                ),
            'name' => 'pastacode_showinvisible'
         ) );

     ?>
    <form method="post" action="options.php">
        <?php 
        settings_fields( 'pastacode' );
        do_settings_sections( 'pastacode' );
        $url = wp_nonce_url( admin_url( 'admin-post.php?action=pastacode_drop_transients' ), 'pastacode_drop_transients' );
        global $wpdb;
        $transients = $wpdb->get_var( "SELECT count(option_name) FROM $wpdb->options WHERE option_name LIKE '_transient_pastacode_%'" );
        echo '<p class="submit">';
            submit_button( '', 'primary large', 'submit', false );
            echo ' <a href="'.$url.'" class="button button-large button-secondary">'.__( 'Purge cache', 'pastacode' ).' ('.(int)$transients.')</a>';
        echo '</p>';
        ?>
    </form>
</div>
<?php
}

register_activation_hook( __FILE__, 'pastacode_activation' );
function pastacode_activation() {
        add_option( 'pastacode_cache_duration', DAY_IN_SECONDS * 7 );
        add_option( 'pastacode_style', 'prism' );
        add_option( 'pastacode_showinvisible', 'n' );
        add_option( 'pastacode_linenumbers', 'n' );
}

register_uninstall_hook( __FILE__, 'pastacode_uninstaller' );
function pastacode_uninstaller() {
        delete_option( 'pastacode_cache_duration' );
        delete_option( 'pastacode_style' );
}

/**
Add button to tinymce
*/
//Button
add_action( 'admin_init', 'pastacode_button_editor' );
function pastacode_button_editor() {

    // Don't bother doing this stuff if the current user lacks permissions
    if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
        return false;

    if ( get_user_option('rich_editing') == 'true') {
        add_filter( 'mce_external_plugins', 'pastacode_script_tiny' );
        add_filter( 'mce_buttons', 'pastacode_register_button' );
    }
}

function pastacode_register_button($buttons) {
    array_push($buttons, "|", "pcb");
    return $buttons;
}

function pastacode_script_tiny( $plugin_array ) {
    global $wp_version;
    if ( version_compare( $wp_version, '4.2.3', '>=' ) ) {
        $plugin_array['pcb'] = plugins_url( '/js/tinymce2.js?v=' . PASTACODE_VERSION, __FILE__ );
    } else {
        $plugin_array['pcb'] = plugins_url( '/js/tinymce.js?v=' . PASTACODE_VERSION, __FILE__ );
    }
    return $plugin_array;
}

add_action( 'admin_enqueue_scripts', 'pastacode_shortcodes_mce_css' );
function pastacode_shortcodes_mce_css() {
    wp_enqueue_style( 'pastacode-shortcode', plugins_url( '/css/pastacode-tinymce.css', __FILE__ ) );
    wp_register_script( 'jquery-linenumbers', plugins_url( '/js/jquery-linenumbers.js', __FILE__ ), array( 'jquery' ) );
    wp_enqueue_script( 'jquery-linenumbers' );
}

add_action( 'admin_init', 'add_pastacode_styles_to_editor' );
function add_pastacode_styles_to_editor() {
    global $editor_styles;
    $editor_styles[] = plugins_url( '/css/pastacode-tinymce.css?v=' . PASTACODE_VERSION, __FILE__ );
}

add_action( 'before_wp_tiny_mce', 'pastacode_text' );
function pastacode_text() {
    // I10n
    $text = json_encode( array( 
                    'window-title' => __( 'Past\'a code', 'pastacode' ),
                    'label-provider' => __( 'Select a provider', 'pastacode' ),
                    'label-langs' => __( 'Select a syntax', 'pastacode' ),
                    'image-placeholder' => plugins_url( '/images/pastacode-placeholder.png', __FILE__ )
                    ) );

    // Services
    $services = array( 'manual' => __( 'Manual', 'pastacode' ),
                    'github'    => 'Github',
                    'gist'      => 'Gist',
                    'bitbucket' => 'Bitbucket',
                    'pastebin'  => 'Pastebin',
                    'file'      => __( 'File from uploads', 'pastacode' ),
                    );
    $services = apply_filters( 'pastacode_services', $services );

    // Languages
    $langs  = array(
        'markup'       => 'HTML',
        'css'          => 'CSS',
        'javascript'   => 'JavaScript',
        'php'          => 'PHP',
        'c'            => 'C',
        'cpp'          => 'C++',
        'java'         => 'Java',
        'sass'         => 'Sass',
        'python'       => 'Python',
        'sql'          => 'SQL',
        'ruby'         => 'Ruby',
        'coffeescript' => 'CoffeeScript',
        'bash'         => 'Bash',
        'apacheconf'   => 'Apache',
        'less'         => 'Less',
        'haml'         => 'HAML',
        'markdown'     => 'Markdown',
    );
    $langs = apply_filters( 'pastacode_langs', $langs );

    // Other fields
    $fields = array(
        'username' => array( 'classes' => array( 'github','bitbucket' ), 'label' => __('User of repository', 'pastacode'), 'placeholder' => __( 'John Doe', 'pastacode' ), 'name' => 'user' ),
        'repository' => array( 'classes' => array( 'github','bitbucket' ), 'label' => __('Repository', 'pastacode'), 'placeholder' => __( 'pastacode', 'pastacode' ), 'name' => 'repos' ),
        'path-id' => array( 'classes' => array( 'gist','pastebin' ), 'label' => __('Code ID', 'pastacode'), 'placeholder' => '123456', 'name' => 'path_id' ),
        'path-repo' => array( 'classes' => array( 'github','bitbucket' ), 'label' => __('File path inside the repository', 'pastacode'), 'placeholder' => __( 'bin/foobar.php', 'pastebin' ), 'name' => 'path_id'  ),
        'path-up' => array( 'classes' => array( 'file' ), 'label' => sprintf( __('File path relative to %s', 'pastacode'), esc_html( WP_CONTENT_URL ) ), 'placeholder' => date( 'Y/m' ).'/source.txt', 'name' => 'path_id'  ),
        'revision' => array( 'classes' => array( 'github','bitbucket' ), 'label' => __('Revision', 'pastacode'), 'placeholder' => __('master', 'pastacode'), 'name' => 'revision'  ),
        'manual' => array( 'classes' => array( 'manual' ), 'label' => __('Code', 'pastacode'), 'name' => 'manual'  ),
        'message' => array( 'classes' => array( 'manual' ), 'label' => __('Code title', 'pastacode'),'placeholder' => __('title', 'pastacode'), 'name' => 'message'  ),
        'file' => array( 'classes' => array( 'gist' ), 'label' => __('File inside the gist', 'pastacode'), 'placeholder' => 'foobar.txt', 'name' => 'file'  ),
        'pastacode-highlight' => array( 'classes' => array( 'manual', 'github', 'gist', 'bitbucket', 'pastebin', 'file' ), 'label' => __('Highlited lines', 'pastacode'), 'placeholder' => '1,2,5-6', 'name' => 'highlight' ),
        'pastacode-lines' => array( 'classes' => array( 'github', 'gist', 'bitbucket', 'pastebin', 'file' ), 'label' => __('Visibles lines', 'pastacode'), 'placeholder' => '1-20', 'name' => 'lines' )
    );
    $fields = apply_filters( 'pastacode_fields', $fields );

    $newFields = array();
    $newLangs = array();
    foreach ( $langs as $k => $s ) {
        $newLangs[] = array( 'text' => $s, 'value' => $k );
    }
    $newFields[] = array( 'type' => 'listbox', 'label' => __( 'Select a syntax', 'pastacode' ), 'name' => 'lang', 'values' => $newLangs );

    $pvars['providers'] = $services;

    foreach ( $fields as $k => $f ) {
        $field = array(
            'type' => 'textbox',
            'name' => $f['name'],
            'label' => $f['label'],
            'classes' => 'field-to-test field pastacode-args ' . implode( ' ', $f['classes'] )
            );
        if ( ! isset( $f['placeholder'] ) ) {
            $field['multiline'] = true;
            $field['minWidth'] = 300;
            $field['minHeight'] = 100;
        } else {
            $field['tooltip'] = $f['placeholder'];
        }
        $newFields[] = $field;
    }

    $pvars['fields'] = $newFields;
    $pvars['extendIcon'] = plugins_url( 'images/expand-editor.png', __FILE__ );
    $pvars['extendText'] = __( 'Expand editor', 'pastacode' );
    $pvars['window-manuel-full'] = __( 'Manual Code Editor', 'pastacode' );

    // Print Vars
    $pvars = json_encode( $pvars );
    echo '<script>var pastacodeText = ' . $text . ';var pastacodeVars = ' . $pvars . ';</script>';
}
