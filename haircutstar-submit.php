<?php
/**
 * Plugin Name: Custom Front End Submit
 * Plugin URI: http://www.haircutstar.com
 * Description: Creates a short code which you can put into post to create custom submit form. If the image is presented it will set as featured image and append to the_content
 * Version: 1.1.1
 * Author: Aleksandr
 * Author URI: http://haircutstar.com/
 * Text Domain: haircutstar
 * Domain Path: /haircutstar
 */
if (!defined('ABSPATH')) exit;
define("F_SIZE", "4M");
class HaircutStarPluginForm {
    public static $short_code = 'show_user_form';
    public $errors = [];
    /**
     * Class constructor
     */
    public function __construct($isLoggedIn) {
        $this->isLoggedIn = $isLoggedIn;
        $this->define_hooks();
    }

    public function controller() {
        if (!is_user_logged_in()) {
            return;
        }
        $_post = get_post();
        if (!is_a($_post, 'WP_Post') || !is_singular() || !has_shortcode($_post->post_content, HaircutStarPluginForm::$short_code)) {
            return;
        }
        if ($_SESSION == null || !array_key_exists("new_button_key", $_SESSION)) {
            return;
        }
        $submitButtonName = $_SESSION["new_button_key"];
        if (isset($_POST[$submitButtonName])) {
            $_SESSION["new_button_key"] = $this->generateRandomString();
            $title       = sanitize_text_field(filter_input( INPUT_POST, 'title', FILTER_SANITIZE_STRING ));
            $description = sanitize_text_field(filter_input( INPUT_POST, 'description', FILTER_SANITIZE_STRING ));
            $image       = filter_input( INPUT_POST, 'image', FILTER_SANITIZE_STRING );
            $category    = filter_input( INPUT_POST, 'category', FILTER_SANITIZE_STRING, FILTER_SANITIZE_NUMBER_INT );
            if ($title === NULL || strlen($title) < 5) {
                $this->errors[] = "Title too short";
                return;
            }

            if (get_category($category) === NULL) {
                $this->errors[] = "Unknown Category";
                return;
            }
            $image_good = false;
            if (isset($_FILES["image"]) && isset($_FILES["image"]["tmp_name"]) && $_FILES["image"]["tmp_name"] !== "" && $this->check_img_mime($_FILES["image"]["tmp_name"]) && $this->check_img_size($_FILES["image"]["tmp_name"])) {
                $image_good = true;
            }

            if ($image_good && ($description === NULL || strlen($description) < 5)) {
                $this->errors[] = "Please add image or text";
                return;
            }

            $post_id = $this->create_new_post($title, $description, $category);

            if ($image_good && $post_id > 0) {
                $image = $this->process_image('image', $post_id, ($description === NULL || strlen($description) < 5 ? wp_trim_excerpt($title) : wp_trim_excerpt($description)) );
            }
            if (empty($this->errors)) {
                wp_redirect(get_permalink($post_id));
            }
        }

    }

    /**
     * Display form
     */
    public function display_form() {
        if (!array_key_exists('new_button_key', $_SESSION)) {
            $_SESSION["new_button_key"] = $this->generateRandomString();
        }

        $submitButtonName = $_SESSION["new_button_key"];

        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING | FILTER_SANITIZE_EMAIL);
        $image = filter_input(INPUT_POST, 'image', FILTER_SANITIZE_STRING);
        $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY);
        $output = '';
        $output .= '<form method="post" enctype="multipart/form-data">';
        foreach ($this->errors as $error) {
            $output .= '    <p style="color:red">';
            $output .= '        ' . $this->errors[] = esc_html__($error, 'HaircutStarPluginForm');
            $output .= '    </p>';
        }
        $output .= '    <p>';
        $output .= '        ' . $this->display_text('title', 'Title', $title);
        $output .= '    </p>';

        $output .= '    <p>';
        $output .= '        ' . $this->display_textarea('description', 'Description', $description);
        $output .= '    </p>';
        $output .= '    <p>';
        $output .= '        ' . $this->display_selects('category', 'Category', get_categories() , $category);
        $output .= '    </p>';
        $output .= '    <p>';
        $output .= '        ' . $this->display_upload('image', 'Image');
        $output .= '    </p>';

        $output .= '    <p>';
        $output .= '        <input type="submit" name="' . $submitButtonName . '" value="' . esc_html__('Share With Us', 'HaircutStarPluginForm') . '" />';
        $output .= '    </p>';
        $output .= '</form>';

        return $output;
    }

    /**
     * Display text field
     */
    private function display_text($name, $label, $value = '') {
        $output = '';
        $output .= '<label>' . esc_html__($label, 'HaircutStarPluginForm') . '</label>';
        $output .= '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        return $output;
    }

    /**
     * Display textarea field
     */
    private function display_textarea($name, $label, $value = '') {
        $output = '';
        $output .= '<label> ' . esc_html__($label, 'HaircutStarPluginForm') . '</label>';
        $output .= '<textarea name="' . esc_attr($name) . '" >' . esc_html($value) . '</textarea>';
        return $output;
    }

    /**
     * Display radios field
     */
    private function display_selects($name, $label, $categories, $value = null) {
        $output = '';
        $output .= '<label>' . esc_html__($label, 'HaircutStarPluginForm') . '</label>';
        $output .= '<select  name="' . esc_attr($name) . '" >';
        foreach ($categories as $category):
            $output .= $this->display_option($category->name, $category->term_id, $value);
        endforeach;

        return $output;
    }

    /**
     * Display single checkbox field
     */
    private function display_option($option_name, $option_value, $value = null) {
        $output = '';
        $checked = ($option_value === $value) ? ' selected' : '';
        $output .= '    <option  value="' . esc_attr($option_value) . '"' . esc_attr($checked) . '>';
        $output .= '    ' . esc_html__($option_name, 'HaircutStarPluginForm');
        $output .= '</option>';

        return $output;
    }

    /**
     * Display radios field
     */
    private function display_radios($name, $label, $options, $value = null) {
        $output = '';
        $output .= '<label>' . esc_html__($label, 'HaircutStarPluginForm') . '</label>';
        foreach ($options as $option_value => $option_label):
            $output .= $this->display_radio($name, $option_label, $option_value, $value);
        endforeach;
        return $output;
    }

    /**
     * Display single checkbox field
     */
    private function display_radio($name, $label, $option_value, $value = null) {
        $output = '';
        $checked = ($option_value === $value) ? ' checked' : '';
        $output .= '<label>';
        $output .= '    <input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr($option_value) . '"' . esc_attr($checked) . '>';
        $output .= '    ' . esc_html__($label, 'HaircutStarPluginForm');
        $output .= '</label>';

        return $output;
    }

    private function generateRandomString($length = 6) {
        $characters = 'abcdefghijklmnY82opqrs123t4u5v6w7x8y9z0ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0;$i < $length;$i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1) ];
        }
        return $randomString;
    }

    /**
     * Display file upload box
     */
    private function display_upload($name, $label) {
        $output = '';
        $output .= '<label>';
        $output .= '    <input type="file" name="' . esc_attr($name) . '">';
        $output .= '    ' . esc_html__($label, 'HaircutStarPluginForm');
        $output .= '</label>';
        return $output;
    }

    /**
     * Display checkboxes field
     */
    private function display_checkboxes($name, $label, $options, $values = array()) {
        $output = '';
        $name .= '[]';
        $output .= '<label>' . esc_html__($label, 'HaircutStarPluginForm') . '</label>';
        foreach ($options as $option_value => $option_label):
            $output .= $this->display_checkbox($name, $option_label, $option_value, $values);
        endforeach;
        return $output;
    }

    /**
     * Display single checkbox field
     */
    private function display_checkbox($name, $label, $available_value, $values = array()) {
        $output = '';
        $checked = (in_array($available_value, $values)) ? ' checked' : '';
        $output .= '<label>';
        $output .= '    <input type="checkbox" name="' . esc_attr($name) . '" value="' . esc_attr($available_value) . '"' . esc_attr($checked) . '>';
        $output .= '    ' . esc_html__($label, 'HaircutStarPluginForm');
        $output .= '</label>';
        return $output;
    }

    /* Checks the true mime type of the given file */
    private function check_img_mime($tmpname) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mtype = finfo_file($finfo, $tmpname);
        $this->mtype = $mtype;
        if (strpos($mtype, 'image/') === 0) {
            return true;
        }
        else {
            return false;
        }
        finfo_close($finfo);
    }

    /* Checks if the image isn't to large */
    private function check_img_size($tmpname) {
        $size_conf = substr(F_SIZE, -1);
        $max_size = (int)substr(F_SIZE, 0, -1);
        switch ($size_conf) {
            case 'k':
            case 'K':
                $max_size *= 1024;
            break;
            case 'm':
            case 'M':
                $max_size *= 1024;
                $max_size *= 1024;
            break;
            default:
                $max_size = 1024000;
        }
        if (filesize($tmpname) > $max_size) {
            return false;
        }
        else {
            return true;
        }
    }
    
    /**
    * Create new wordpress post
    */
    private function create_new_post($title, $content, $category) {
        $user = wp_get_current_user();
        $user_id = $user->ID;
        $allowed_roles = array(
            'editor',
            'administrator',
            'author'
        );
        if (array_intersect($allowed_roles, $user->roles)) {
            $status = 'publish';
        }
        else {
            $status = 'pending';
        }
        $thePost = array(
            'post_author'           => $user_id,
            'post_content'          => $content,
            'post_title'            => $title,
            'post_status'           => $status,
            'post_type'             => 'post',
            'comment_status'        => 'closed',
            'ping_status'           => 'closed',
            'post_category'         => array( $category ),
            'post_excerpt'          => wp_trim_excerpt($content)
        );
        return wp_insert_post($thePost);
    }

    private function process_image($file, $post_id, $caption) {
        require_once (ABSPATH . "wp-admin" . '/includes/image.php');
        require_once (ABSPATH . "wp-admin" . '/includes/file.php');
        require_once (ABSPATH . "wp-admin" . '/includes/media.php');

        $attachment_id = media_handle_upload($file, $post_id);
        $img_atts = wp_get_attachment_image_src($attachment_id, 'large');
        update_post_meta($post_id, '_thumbnail_id', $attachment_id);

        $attachment_data = array(
            'ID' => $attachment_id,
            'post_excerpt' => $caption
        );
        $_post = get_post($post_id);

        $my_post = array();
        $my_post['ID'] = $post_id;
        $my_post['post_content'] = '<img class="aligncenter size-full wp-image-'.$attachment_id.'" src="'.$img_atts[0].'" alt="'.esc_html__($caption, 'HaircutStarPluginForm').'" width="'.$img_atts[1].'" height="'.$img_atts[2].'" />'."\n" . $_post->post_content;
        wp_update_post($my_post);

        wp_update_post($attachment_data);

        return $attachment_id;

    }

    /**
     * Define hooks related to plugin
     */
    private function define_hooks() {
        add_action('wp', array(
            $this,
            'controller'
        ));
        add_shortcode(HaircutStarPluginForm::$short_code, array(
            $this,
            'display_form'
        ));
    }
}

function not_logged_in() {
    return "Please loggin to submit<br>" . do_shortcode('[nextend_social_login]');
}

function checkForm() {
    if (is_user_logged_in()) {
        new HaircutStarPluginForm(is_user_logged_in());
    }
    else {
        add_shortcode(HaircutStarPluginForm::$short_code, 'not_logged_in');
    }
}
add_action('init', 'checkForm');