<?php 
/*
Plugin Name: Pastacode
Plugin URI: http://wordpress.org/extend/plugins/pastacode/
Description: Embed GitHub, Gist, Pastebin, Bitbucket or whatever remote files and even your own code by copy/pasting.
Version: 1.0
Author: Willy Bahuaud
Author URI: http://wabeo.fr
Contributors, juliobox, willybahuaud
*/

define( 'PASTACODE_VERSION', '1.0' );

add_shortcode( 'pastacode', 'sc_pastacode' );
function sc_pastacode( $atts, $content ) {

    extract( shortcode_atts( array(
        'type'     => '',
        'user'     => '',
        'path'     => '',
        'repos'    => '',
        'revision' => 'master',
        'lines'    => '',
        'lang'     => 'markup',
        'highlight' => '',
        'message'   => '',
        'linenumbers'   => 'n',
        'showinvisible' => 'n',
        ), $atts, 'pastacode' ) );

    if( empty( $type ) && !empty( $content ) )
        $type = $content;

    $code_embed_transient = 'pastacode_' . substr( md5( serialize( $atts ) ), 0, 14 );

    $time = get_option( 'pastacode_cache_duration', DAY_IN_SECONDS * 7 );

    if( $time==-1 || !$source = get_transient( $code_embed_transient ) ){

        $source = array();

        switch( $type ){

            case 'github' :
                if( $user && $repos && $path ) {
                    $req  = wp_sprintf( 'https://api.github.com/repos/%s/%s/contents/%s', $user, $repos, $path );
                    $code = wp_remote_get( $req );
                    if( ! is_wp_error( $code ) && 200 == wp_remote_retrieve_response_code( $code ) ) {
                        $data = json_decode( wp_remote_retrieve_body( $code ) );
                        $source[ 'name' ] = $data->name;
                        $source[ 'code' ] = esc_html( base64_decode( $data->content ) );
                        $source[ 'url' ]  = $data->html_url;
                        $source[ 'raw' ]  = wp_sprintf( 'https://raw.github.com/%s/%s/%s/%s', $user, $repos, $revision, $path );
                    }
                }
                break;

            case 'gist' :
                if( $path ) {
                    $req  = wp_sprintf( 'https://api.github.com/gists/%s', $path );
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
                break;

            case 'bitbucket' :
                if( $user && $repos && $path ) {
                    $req  = wp_sprintf( 'https://bitbucket.org/api/1.0/repositories/%s/%s/raw/%s/%s', $user, $repos, $revision, $path );

                    $code = wp_remote_get( $req );
                    if( ! is_wp_error( $code ) && 200 == wp_remote_retrieve_response_code( $code ) ) {
                        $source[ 'name' ] = basename( $path );
                        $source[ 'code' ] = esc_html( wp_remote_retrieve_body( $code ) );
                        $source[ 'url' ]  = wp_sprintf( 'https://bitbucket.org/%s/%s/src/%s/%s', $user, $repos, $revision, $path );
                        $source[ 'raw' ]  = $req;
                    }
                }
                break;

            case 'file' :
                if( $path ) {
                    $upload_dir = wp_upload_dir();
                    $path = str_replace( '../', '', $path );
                    $req  = esc_url( trailingslashit( $upload_dir[ 'baseurl' ] ) . $path );
                    $code = wp_remote_get( $req );
                    if( ! is_wp_error( $code ) && 200 == wp_remote_retrieve_response_code( $code ) ) {

                        $source[ 'name' ] = basename( $path );
                        $source[ 'code' ] = esc_html( wp_remote_retrieve_body( $code ) );
                        $source[ 'url' ]  = ( $req );
                    }
                }
                break;

            case 'pastebin' :
                if( $path ) {
                    $req  = wp_sprintf( 'http://pastebin.com/raw.php?i=%s', $path );
                    $code = wp_remote_get( $req );
                    if( ! is_wp_error( $code ) && 200 == wp_remote_retrieve_response_code( $code ) ) {
                        $source[ 'name' ] = $path;
                        $source[ 'code' ] = esc_html( wp_remote_retrieve_body( $code ) );
                        $source[ 'url' ]  = wp_sprintf( 'http://pastebin.com/%s', $path );
                        $source[ 'raw' ]  = wp_sprintf( 'http://pastebin.com/raw.php?i=%s', $path );
                    }
                }
                break;
            default : 
                if( !empty( $content ) ){
                        $source[ 'code' ] = esc_html( str_replace( array('<br>','<br />', '<br/>','</p>'."\n".'<pre><code>','</code></pre>'."\n".'<p>'), array(""), $content ) );
                }elseif( ! empty( $message ) ){
                    return '<span class="wabeo_ce_message">' . esc_html( $message ) . '</span>';
                }
                break;
        }

        if( ! empty( $source[ 'code' ] ) ) {
            //Wrap lines
            if( $lines ) {
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

        $ln = '';
        if( 'y' === get_option( 'pastacode_linenumbers', 'n' ) ) {
            wp_enqueue_style( 'prism-linenumbercss' );
            wp_enqueue_script( 'prism-linenumber' );
            $ln = ' line-numbers';
        }
        if( 'y' === get_option( 'pastacode_showinvisible', 'n' ) ) {
            wp_enqueue_style( 'prism-show-invisiblecss' );
            wp_enqueue_script( 'prism-show-invisible' );  
        }
        //highlight
        if( preg_match( '/([0-9-,]+)/', $highlight ) ) {
            $highlight_val = ' data-line="' . $highlight . '"';
            wp_enqueue_script( 'prism-highlight' );
            wp_enqueue_style( 'prism-highlightcss' );
        } else {
            $highlight_val = '';
        }

        //Wrap
        $output = array();
        $output[] = '<div class="code-embed-wrapper">';
        $output[] = '<pre class="language-' . sanitize_html_class( $lang ) . ' code-embed-pre' . $ln . '" ' . $highlight_val . '><code class="language-' . sanitize_html_class( $lang ) . ' code-embed-code">'
        . $source[ 'code' ] .
        '</code></pre>';
        $output[] = '<div class="code-embed-infos">';
        if( isset( $source[ 'url' ] ) )
            $output[] = '<a href="' . esc_url( $source[ 'url' ] ) . '" title="' . sprintf( esc_attr__( 'See %s', 'pastacode' ), $source[ 'name' ] ) . '" target="_blank" class="code-embed-name">' . esc_html( $source[ 'name' ] ) . '</a>';
        if( isset( $source[ 'raw' ] ) )
            $output[] = '<a href="' . esc_url( $source[ 'raw' ] ) . '" title="' . sprintf( esc_attr__( 'Back to %s' ), $source[ 'name' ] ) . '" class="code-embed-raw" target="_blank">' . __( 'view raw', 'pastacode' ) . '</a>';
        $output[] = '</div>';
        $output[] = '</div>';
        $output = implode( "\n", $output );

        return $output;
    } elseif( ! empty( $message ) ) {
        return '<span class="pastacode_message">' . esc_html( $message ) . '</span>';
    }
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pastacode_settings_action_links', 10, 2 );
function pastacode_settings_action_links( $links, $file )

{
    if( current_user_can( 'manage_options' ) )
        array_unshift( $links, '<a href="' . admin_url( 'options-general.php?page=pastacode' ) . '">' . __( 'Settings' ) . '</a>' );
    return $links;
}

add_filter( 'plugin_row_meta', 'pastacode_plugin_row_meta', 10, 2 );
function pastacode_plugin_row_meta( $plugin_meta, $plugin_file )
{
    if( plugin_basename( __FILE__ ) == $plugin_file ){
        $last = end( $plugin_meta );
        $plugin_meta = array_slice( $plugin_meta, 0, -2 );
        $a = array();
        $authors = array(
            array(  'name'=>'Willy Bahuaud', 'url'=>'http://www.wabeo.fr' ),
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
    wp_register_script( 'prismjs', plugins_url( '/js/prism.js', __FILE__ ), false, PASTACODE_VERSION, true );
    wp_register_script( 'prism-highlight', plugins_url( '/plugins/line-highlight/prism-line-highlight.min.js', __FILE__ ), array( 'prismjs' ), PASTACODE_VERSION, true );
    wp_register_script( 'prism-linenumber', plugins_url( '/plugins/line-numbers/prism-line-numbers.min.js', __FILE__ ), array( 'prismjs' ), PASTACODE_VERSION, true );
    wp_register_script( 'prism-show-invisible', plugins_url( '/plugins/show-invisibles/prism-show-invisibles.min.js', __FILE__ ), array( 'prismjs' ), PASTACODE_VERSION, true );
    wp_register_style( 'prismcss', plugins_url( '/css/' . get_option( 'pastacode_style', 'prism' ) . '.css', __FILE__ ), false, PASTACODE_VERSION, 'all' );      
    wp_register_style( 'prism-highlightcss', plugins_url( '/plugins/line-highlight/prism-line-highlight.css', __FILE__ ), false, PASTACODE_VERSION, 'all' );      
    wp_register_style( 'prism-linenumbercss', plugins_url( '/plugins/line-numbers/prism-line-numbers.css', __FILE__ ), false, PASTACODE_VERSION, 'all' );  
    wp_register_style( 'prism-show-invisiblecss', plugins_url( '/plugins/show-invisibles/prism-show-invisibles.css', __FILE__ ), false, PASTACODE_VERSION, 'all' );      
    
}

add_filter( 'pre_update_option_pastacode_cache_duration', 'pastacode_drop_wge_transients' );
function pastacode_drop_wge_transients( $param ) {
    global $wpdb;
    $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_pastacode_%'" );
    return $param;
}

/**
//Admin Settings
*/
add_action( 'admin_menu', 'pastacode_create_menu' );
function pastacode_create_menu() {
    add_options_page( 'Pastacode '.__( 'Settings' ), 'Pastacode', 'manage_options', 'pastacode', 'pastacode_settings_page' );
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
                HOUR_IN_SECONDS     => sprintf( __( '%s hour' ), '1' ),
                HOUR_IN_SECONDS * 12 => __( 'Twice Daily' ),
                DAY_IN_SECONDS      => __( 'Once Daily' ),
                DAY_IN_SECONDS * 7     => __( 'Once Weekly', 'pastacode' ),
                0      => __( 'Never reload', 'pastacode' ),
                -1      => __( 'No cache (dev mode)', 'pastacode' ),
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
                'prism'          => __( 'Prism', 'pastacode' ),
                'prism-dark'     => __( 'Dark', 'pastacode' ),
                'prism-funky'    => __( 'Funky', 'pastacode' ),
                'prism-coy'      => __( 'Coy', 'pastacode' ),
                'prism-okaidia'  => __( 'Okaïdia', 'pastacode' ),
                'prism-tomorrow' => __( 'Tomorrow', 'pastacode' ),
                'prism-twilight' => __( 'Twilight', 'pastacode' ),
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
                'y'          => __( 'Yes', 'pastacode' ),
                'n'     => __( 'No', 'pastacode' ),
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
                'y'          => __( 'Yes', 'pastacode' ),
                'n'     => __( 'No', 'pastacode' ),
                ),
            'name' => 'pastacode_showinvisible'
         ) );

     ?>
    <form method="post" action="options.php">
        <?php 
        settings_fields( 'pastacode' );
        do_settings_sections( 'pastacode' );
        submit_button();
        ?>
    </form>
</div>
<?php
}

register_activation_hook( __FILE__, 'pastacode_activation' );
function pastacode_activation()
{
        add_option( 'pastacode_cache_duration', DAY_IN_SECONDS * 7 );
        add_option( 'pastacode_style', 'prism' );
        add_option( 'pastacode_showinvisible', 'n' );
        add_option( 'pastacode_linenumbers', 'n' );
}

register_uninstall_hook( __FILE__, 'pastacode_uninstaller' );
function pastacode_uninstaller()
{
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

    ?>
    <h3 class="media-title"><?php _e('Past\'a code', 'pastacode'); ?></h3>

    <form name="pastacode-shortcode-gen" id="pastacode-shortcode-gen">
        <div id="media-items">
            <div class="media-item media-blank">

                <table class="describe" style="width:100%;margin-top:1em;"><tbody>

                    <tr valign="top" class="field">
                        <th class="label" scope="row"><label for="pastacode-service"><?php _e('Select a service', 'pastacode'); ?></th>
                        <td>
                            <select name="pastacode-type" id="pastacode-service">
                                <?php
                                $types  = array(
                                    'manual'    => __( 'Manual', 'pastacode' ),
                                    'github'    => 'Github',
                                    'gist'      => 'Gist',
                                    'bitbucket' => 'Bitbucket',
                                    'pastebin'  => 'Pastebin',
                                    'file'      => __( 'File', 'pastacode' ),
                                );
                                ?><optgroup label="<?php _e( 'Select a provider', 'pastacode' ); ?>">
                                <?php
                                foreach( $types as $k => $type )
                                    echo '<option value="' . $k . '">' . $type . '</value>';
                                ?>
                                </optgroup>
                            </select>
                        </td>
                    </tr>

                    <tr valign="top" class="field">
                        <th class="label" scope="row"><label for="pastacode-lang"><?php _e('Select a syntax', 'pastacode'); ?></th>
                        <td>
                            <select name="pastacode-lang" id="pastacode-lang">
                                <?php
                                $types  = array(
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
                                foreach( $types as $k => $type )
                                    echo '<option value="' . $k . '">' . $type . '</value>';
                                    ?>
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

                    <tr valign="top" class="field pastacode-args github bitbucket hidden">
                        <th class="label" scope="row"><label for="pastacode-username"><span class="alignleft"><?php _e('User of repository', 'pastacode'); ?></span></label></th>
                        <td>
                            <input type="text" name="pastacode-user" id="pastacode-username" placeholder="<?php _e( 'John Doe', 'pastacode' ); ?>"/>
                        </td>
                    </tr>

                    <tr valign="top" class="field pastacode-args github bitbucket hidden">
                        <th class="label" scope="row"><label for="pastacode-repository"><span class="alignleft"><?php _e('Repository', 'pastacode'); ?></span></label></th>
                        <td>
                            <input type="text" name="pastacode-repos" id="pastacode-repository" placeholder="<?php _e( 'pastacode', 'pastacode' ); ?>"/>
                        </td>
                    </tr>

                    <tr valign="top" class="field pastacode-args gist pastebin hidden">
                        <th class="label" scope="row"><label for="pastacode-path"><span class="alignleft"><?php _e('Code ID', 'pastacode'); ?></span></label></th>
                        <td>
                            <input type="text" name="pastacode-path" id="pastacode-path" placeholder="123456"/>
                        </td>
                    </tr>

                    <tr valign="top" class="field pastacode-args github bitbucket hidden">
                        <th class="label" scope="row"><label for="pastacode-path"><span class="alignleft"><?php _e('File path inside the repository', 'pastacode'); ?></span></label></th>
                        <td>
                            <input type="text" name="pastacode-path" id="pastacode-path" placeholder="bin/foobar.php"/>
                        </td>
                    </tr>

                    <tr valign="top" class="field pastacode-args file hidden">
                        <th class="label" scope="row"><label for="pastacode-path"><span class="alignleft"><?php printf( __('File path relative to %s', 'pastacode'), esc_html( WP_CONTENT_URL ) ); ?></span></label></th>
                        <td>
                            <input type="text" name="pastacode-path" id="pastacode-path" placeholder="<?php echo date( 'Y/m' ) ?>/source.txt"/>
                        </td>
                    </tr>

                    <tr valign="top" class="field pastacode-args github bitbucket hidden">
                        <th class="label" scope="row"><label for="pastacode-revision"><span class="alignleft"><?php _e('Revision', 'pastacode'); ?></span></label></th>
                        <td>
                            <input type="text" name="pastacode-revision" id="pastacode-revision" placeholder="<?php _e( 'master', 'pastacode' ); ?>"/>
                        </td>
                    </tr>

                    <tr valign="top" class="field pastacode-args manual">
                        <th class="label" scope="row"><label for="pastacode-highlight"><span class="alignleft"><?php _e('Manual input', 'pastacode'); ?></span></label></th>
                        <td>
                            <textarea name="pastacode-manual" id="pastacode-manual"></textarea>
                        </td>
                    </tr>

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