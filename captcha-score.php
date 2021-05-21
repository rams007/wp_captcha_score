<?php

/*
 * Plugin Name: Captcha score
 * Description: calculate recaptcha score and show page content only if score greted then  user defined value
 * Version: 1.0
 * Author: RAMS
 */


function cs_add_settings_page()
{
    add_options_page('Captcha score', 'Captcha score', 'manage_options', 'cs-captcha-score', 'cs_render_plugin_settings_page');
}

add_action('admin_menu', 'cs_add_settings_page');


function cs_render_plugin_settings_page()
{
    ?>
    <h2>Example Plugin Settings</h2>
    <form action="options.php" method="post">
        <?php
        settings_fields('cs_example_plugin_options');
        do_settings_sections('cs_example_plugin'); ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>"/>
    </form>
    <?php
}


function cs_register_settings()
{
    register_setting('cs_example_plugin_options', 'cs_example_plugin_options', 'cs_example_plugin_options_validate');
    add_settings_section('api_settings', 'API Settings', 'cs_plugin_section_text', 'cs_example_plugin');

    add_settings_field('cs_plugin_setting_captcha_key', 'Captcha key', 'cs_plugin_setting_captcha_key', 'cs_example_plugin', 'api_settings');
    add_settings_field('cs_plugin_setting_captcha_secret', 'Captcha secret', 'cs_plugin_setting_captcha_secret', 'cs_example_plugin', 'api_settings');
    add_settings_field('cs_plugin_setting_minimum_allowed_score', 'Minimum allowed score', 'cs_plugin_setting_minimum_allowed_score', 'cs_example_plugin', 'api_settings');

}

add_action('admin_init', 'cs_register_settings');


function cs_plugin_section_text()
{
    echo '<p>Here you can set all the options for using plugin</p>';
}

function cs_plugin_setting_captcha_key()
{
    $options = get_option('cs_example_plugin_options');
    echo "<input id='cs_plugin_setting_captcha_key' name='cs_example_plugin_options[captcha_key]' type='text' value='" . esc_attr($options['captcha_key']) . "' />";
}

function cs_plugin_setting_captcha_secret()
{
    $options = get_option('cs_example_plugin_options');
    echo "<input id='cs_plugin_setting_captcha_secret' name='cs_example_plugin_options[captcha_secret]' type='text' value='" . esc_attr($options['captcha_secret']) . "' />";
}


function cs_plugin_setting_minimum_allowed_score()
{
    $options = get_option('cs_example_plugin_options');
    echo "<input id='cs_plugin_setting_minimum_allowed_score' name='cs_example_plugin_options[minimum_allowed_score]' type='text' value='" . esc_attr($options['minimum_allowed_score']) . "' />";
}


function recaptcha_scoreScript()
{
    $options = get_option('cs_example_plugin_options');
    //https://stackoverflow.com/questions/29822607/uncaught-referenceerror-grecaptcha-is-not-defined

    if (!empty(trim($options['captcha_key'])) AND !empty(trim($options['minimum_allowed_score'])) AND !empty(trim($options['captcha_secret']))) {

        $script = "      
 
       <script type=\"text/javascript\">
        var minimumAllovedScore = " . $options['minimum_allowed_score'] . ";

try {
 if(window.grecaptcha){
    grecaptcha.ready(function() {
            console.log('recaptcha ready');
            grecaptcha.execute(\"" . $options['captcha_key'] . "\").then(function(token) {
                console.log(token); fetch('/?action=getScore&token=' + token).then(function(response) {
                    response.json().then(function(data) {
                        console.log(data.score);
                        if (data.score < minimumAllovedScore) {
                            jQuery(document).ready(function($) {
                                    $(\".udm-ad\").remove();
                                    $(\".code-block\").remove();
                                        })
                                } else {
                                    jQuery(document).ready(function($) {
                                        $('body').show();
                                    })
                                }
                            });
                    });
                });
            });
            }
    }catch (e) {
        console.error(e);
    };
       </script>    ";

    } else {
        $script = "
        <script type=\"text/javascript\">
        console.log('Please fill captcha score plugin settings');
        </script>
        ";
    }


    echo $script;
}

add_action('wp_footer', 'recaptcha_scoreScript', 100);


function load_jquery()
{
    if (!wp_script_is('jquery', 'enqueued')) {

        //Enqueue
        wp_enqueue_script('jquery');

    }
}

add_action('wp_enqueue_scripts', 'load_jquery');


function hideContent()
{
    $options = get_option('cs_example_plugin_options');
    $script = "
       <script src=\"https://www.google.com/recaptcha/api.js?render=".$options['captcha_key']."\"></script>
      <script type=\"text/javascript\">
        if (window.grecaptcha) {
            jQuery(document).ready(function($) {
                $('body').hide();
            })
        } else {
            console.log(\"We dont have recaptcha enabled\");
        }
    </script>
    ";
    echo $script;
}

add_action('wp_footer', 'hideContent', 1);


add_action("init", "your_form_handler_action");
function your_form_handler_action()
{
    if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "getScore") {

        try {
            $options = get_option('cs_example_plugin_options');

            $secret = $options['captcha_secret'];
            $token = $_GET['token'];

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "secret=" . $secret . "&response=" . $token);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($ch);
            curl_close($ch);

            $output = json_decode($server_output);

            if (isset($output->score)) {
                echo json_encode(['error' => false, 'score' => $output->score]);
            } else {
                echo json_encode(['error' => true, 'score' => 1, 'msg' => 'Score not found']);
            }
        } catch (Throwable $e) {
            echo json_encode(['error' => true, 'score' => 1, 'msg' => $e->getMessage()]);
        }
        exit();
    }
}
