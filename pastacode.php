<?php 
/*
Plugin Name: Pastacode
Plugin URI: http://wordpress.org/extend/plugins/pastacode/
Description: Embed GitHub, Gist, Pastebin, Bitbucket or whatever remote files and even your own code by copy/pasting.
Version: 1.2
Author: Willy Bahuaud
Author URI: http://wabeo.fr
Contributors, juliobox, willybahuaud
*/

define( 'PASTACODE_VERSION', '1.2' );

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
        'linenumbers'   => 'n',
        'showinvisible' => 'n',
        ), $atts, 'sc_pastacode' );

    if( empty( $atts['provider'] ) && !empty( $content ) )
        $atts['provider'] = md5( $content );

    $code_embed_transient = 'pastacode_' . substr( md5( serialize( $atts ) ), 0, 14 );

    $time = get_option( 'pastacode_cache_duration', DAY_IN_SECONDS * 7 );

    if( $time==-1 || !$source = get_transient( $code_embed_transient ) ){

        $source = apply_filters( 'pastacode_'.$atts['provider'], array(), $atts, $content );

        if( ! empty( $source[ 'code' ] ) ) {
            //Wrap lines
            if( $lines = $atts['lines'] ) {
                $lines = array_map( 'intval', explode( '-', $lines ) );
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

        //Wrap
        $output = array();
        $output[] = '<div class="code-embed-wrapper">';
        $output[] = '<pre class="language-' . sanitize_html_class( $atts['lang'] ) . ' code-embed-pre' . $ln_class . '" ' . $highlight_val . '><code class="language-' . sanitize_html_class( $atts['lang'] ) . ' code-embed-code">'
        . $source[ 'code' ] .
        '</code></pre>';
        $output[] = '<div class="code-embed-infos">';
        if( isset( $source[ 'url' ] ) )
            $output[] = '<a href="' . esc_url( $source[ 'url' ] ) . '" title="' . sprintf( esc_attr__( 'See %s', 'pastacode' ), $source[ 'name' ] ) . '" target="_blank" class="code-embed-name">' . esc_html( $source[ 'name' ] ) . '</a>';
        if( isset( $source[ 'raw' ] ) )
            $output[] = '<a href="' . esc_url( $source[ 'raw' ] ) . '" title="' . sprintf( esc_attr__( 'Back to %s' ), $source[ 'name' ] ) . '" class="code-embed-raw" target="_blank">' . __( 'view raw', 'pastacode' ) . '</a>';
        if( ! isset( $source[ 'url' ] ) && ! isset( $source[ 'raw' ] ) && isset( $source[ 'name' ] ) )
            $output[] = '<span class="code-embed-name">' . $source[ 'name' ] . '</span>';
        $output[] = '</div>';
        $output[] = '</div>';
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
            $data = (array)$data->files;
            $data = reset($data);
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
        $source[ 'code' ] = esc_html( str_replace( array('<br>','<br />', '<br/>','</p>'."\n".'<pre><code>','</code></pre>'."\n".'<p>'), array(''), $content ) );
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
            echo ' <a href="'.$url.'" class="button button-large button-secondary">'.__( 'Purge cache' ).' ('.(int)$transients.')</a>';
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

function pastacode_script_tiny($plugin_array) {
    $plugin_array['pcb'] = plugins_url( '/js/tinymce.js', __FILE__ );
    return $plugin_array;
}

add_action( 'wp_ajax_pastacode_shortcode_printer', 'wp_ajax_pastacode_box' );
function wp_ajax_pastacode_box(){
    global $wp_styles;
    if ( !empty($wp_styles->concat) ) {
        $dir = $wp_styles->text_direction;
        $ver = md5("$wp_styles->concat_version{$dir}");

        // Make the href for the style of box
        $href = $wp_styles->base_url . "/wp-admin/load-styles.php?c={$zip}&dir={$dir}&load=media&ver=$ver";
        echo "<link rel='stylesheet' href='" . esc_attr( $href ) . "' type='text/css' media='all' />\n";
    }
    $services = array( 'manual' => __( 'Manual', 'pastacode' ),
                    'github'    => 'Github',
                    'gist'      => 'Gist',
                    'bitbucket' => 'Bitbucket',
                    'pastebin'  => 'Pastebin',
                    'file'      => __( 'File from uploads', 'pastacode' ),
                    );
    $services = apply_filters( 'pastacode_services', $services );
    ?>
    <h3 class="media-title"><?php _e('Past\'a code', 'pastacode'); ?></h3>

    <form name="pastacode-shortcode-gen" id="pastacode-shortcode-gen">
        <div id="media-items">
            <div class="media-item media-blank">

                <table class="describe" style="width:100%;margin-top:1em;"><tbody>

                    <tr valign="top" class="field">
                        <th class="label" scope="row"><label for="pastacode-provider"><?php _e('Select a provider', 'pastacode'); ?></th>
                        <td>
                            <select name="pastacode-provider" id="pastacode-provider">
                                <optgroup label="<?php _e( 'Select a provider', 'pastacode' ); ?>">
                                <?php
                                foreach( $services as $k => $service )
                                    echo '<option value="' . $k . '">' . $service . '</value>';
                                unset( $k );
                                ?>
                                </optgroup>
                            </select>
                        </td>
                    </tr>

                    <tr valign="top" class="field">
                        <th class="label" scope="row"><label for="pastacode-lang"><?php _e('Select a syntax', 'pastacode'); ?></th>
                        <td>
                            <select name="pastacode-lang" id="pastacode-lang">
                                <optgroup label="<?php _e( 'Select a syntax', 'pastacode' ); ?>">
                                <?php
                                $langs  = array(
                                    'markup'       => 'HTML',
                                    'css'          => 'CSS',
                                    'javascript'   => 'JavaScript',
                                    'php'          => 'PHP',
                                    'c'            => 'C',
                                    'c++'          => 'C++',
                                    'java'         => 'Java',
                                    'sass'         => 'Sass',
                                    'python'       => 'Python',
                                    'sql'          => 'SQL',
                                    'ruby'         => 'Ruby',
                                    'coffeescript' => 'CoffeeScript',
                                    'bash'         => 'Bash',
                                );
                                $langs = apply_filters( 'pastacode_langs', $langs );
                                foreach( $langs as $k => $lang )
                                    echo '<option value="' . $k . '">' . $lang . '</value>';
                                unset( $k );
                                ?>
                                </optgroup>
                            </select>
                        </td>
                    </tr>

                    <tr valign="top" class="field">
                        <th class="label" scope="row"><label for="pastacode-lines"><span class="alignleft"><?php _e('Visibles lines', 'pastacode'); ?></span></label></th>
                        <td>
                            <input type="text" name="pastacode-lines" id="pastacode-lines" placeholder="1-20"/>
                        </td>
                    </tr>

                    <tr valign="top" class="field">
                        <th class="label" scope="row"><label for="pastacode-highlight"><span class="alignleft"><?php _e('Highlited lines', 'pastacode'); ?></span></label></th>
                        <td>
                            <input type="text" name="pastacode-highlight" id="pastacode-highlight" placeholder="1,2,5-6"/>
                        </td>
                    </tr>

                    <?php
                    $fields = array('username' => array( 'classes' => array( 'github','bitbucket' ), 'label' => __('User of repository', 'pastacode'), 'placeholder' => __( 'John Doe', 'pastacode' ), 'name' => 'user' ),
                                    'repository' => array( 'classes' => array( 'github','bitbucket' ), 'label' => __('Repository', 'pastacode'), 'placeholder' => __( 'pastacode', 'pastacode' ), 'name' => 'repos' ),
                                    'path-id' => array( 'classes' => array( 'gist','pastebin' ), 'label' => __('Code ID', 'pastacode'), 'placeholder' => '123456', 'name' => 'path_id' ),
                                    'path-repo' => array( 'classes' => array( 'github','bitbucket' ), 'label' => __('File path inside the repository', 'pastacode'), 'placeholder' => __( 'bin/foobar.php', 'pastebin' ), 'name' => 'path_id'  ),
                                    'path-up' => array( 'classes' => array( 'file' ), 'label' => sprintf( __('File path relative to %s', 'pastacode'), esc_html( WP_CONTENT_URL ) ), 'placeholder' => date( 'Y/m' ).'/source.txt', 'name' => 'path_id'  ),
                                    'revision' => array( 'classes' => array( 'github','bitbucket' ), 'label' => __('Revision', 'pastacode'), 'placeholder' => __('master', 'pastacode'), 'name' => 'revision'  ),
                                    'manual' => array( 'classes' => array( 'manual' ), 'label' => __('Code', 'pastacode'), 'name' => 'manual'  ),
                                    'message' => array( 'classes' => array( 'manual' ), 'label' => __('Code title', 'pastacode'),'placeholder' => __('title', 'pastacode'), 'name' => 'message'  ),
                                    );
                    $fields = apply_filters( 'pastacode_fields', $fields );

                    foreach ($fields as $name => $field) {
                        $classes = array_map( 'sanitize_html_class', $field['classes'] );
                    ?>
                    <tr valign="top" class="field pastacode-args <?php echo implode( ' ', $classes ); ?> <?php  if( ! in_array( array_shift( array_keys( $services ) ), $classes ) ) echo 'hidden'; ?>" id="<?php echo $name; ?>">
                        <th class="label" scope="row"><label for="pastacode-<?php echo $name; ?>"><span class="alignleft"><?php echo esc_html( $field['label'] ); ?></span></label></th>
                        <td>
                            <?php if( isset( $field['placeholder'] ) ) { ?>
                                <input type="text" name="pastacode-<?php echo $field[ 'name' ]; ?>" id="pastacode-<?php echo $name; ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"/>
                            <?php }else{ ?>
                                <textarea name="pastacode-<?php echo $field[ 'name' ]; ?>" id="pastacode-<?php echo $name; ?>" rows="5"></textarea>
                            <?php } ?>
                        </td>
                    </tr>
                    <? } 

                    do_action( 'in_pastacode_fields' );

                    ?>

                    <tr valign="top" class="field">
                        <td>
                            <p class="current-page"><input name="pastacode-insert" type="submit" class="button-primary" id="pastacode-insert" tabindex="5" accesskey="p" value="<?php _e('Insert shortcode', 'pastacode') ?>"></p>
                        </td>
                    </tr>

                </tbody></table>
            </div>
        </div>

    </form>
    <?php die();
}
