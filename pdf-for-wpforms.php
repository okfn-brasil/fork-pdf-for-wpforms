<?php
/**
 * Plugin Name: PDF for WPForms + Drag And Drop Template Builder Opará Fork
 * Description:  WPForms PDF Customizer is a helpful tool that helps you build and customize the PDF Templates for WPforms.
 * Plugin URI: https://github.com/okfn-brasil/fork-pdf-for-wpforms
 * Version: 3.6.6
 * Requires PHP: 5.6
 * Author: Opará Tecnologia
 * Author URI: https://opara.me
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
define( 'BUIDER_PDF_WPFORMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BUIDER_PDF_WPFORMS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
if(!class_exists('Yeepdf_Creator_Builder')) {
    require 'vendor/autoload.php';
    if(!defined('YEEPDF_CREATOR_BUILDER_PATH')) {
        define( 'YEEPDF_CREATOR_BUILDER_PATH', plugin_dir_path( __FILE__ ) );
    }
    if(!defined('YEEPDF_CREATOR_BUILDER_URL')) {
        define( 'YEEPDF_CREATOR_BUILDER_URL', plugin_dir_url( __FILE__ ) );
    }
    class Yeepdf_Creator_Builder {
        function __construct(){
            $dir = new RecursiveDirectoryIterator(YEEPDF_CREATOR_BUILDER_PATH."backend");
            $ite = new RecursiveIteratorIterator($dir);
            $files = new RegexIterator($ite, "/\.php/", RegexIterator::MATCH);
            foreach ($files as $file) {
                if (!$file->isDir()){
                    require_once $file->getPathname();
                }
            }
            include_once YEEPDF_CREATOR_BUILDER_PATH."libs/phpqrcode.php";
            include_once YEEPDF_CREATOR_BUILDER_PATH."frontend/index.php";
        }
    }
    new Yeepdf_Creator_Builder;
}
class Yeepdf_Creator_Wpforms_Builder { 
    function __construct(){
        register_activation_hook( __FILE__, array($this,'activation') );
        include BUIDER_PDF_WPFORMS_PLUGIN_PATH."wpforms/index.php";
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this,'add_link') );
        include BUIDER_PDF_WPFORMS_PLUGIN_PATH."superaddons/check_purchase_code.php";
        new Superaddons_Check_Purchase_Code( 
            array(
                "plugin" => "pdf-for-wpforms/pdf-for-wpforms.php",
                "id"=>"1537",
                "pro"=>"https://add-ons.org/plugin/wpforms-pdf-generator-attachment/",
                "plugin_name"=> "PDF Creator For WPForms",
                "document"=>"https://pdf.add-ons.org/document/"
            )
        );
    }
    function add_link( $actions ) {
        $actions[] = '<a target="_blank" href="https://pdf.add-ons.org/document/" target="_blank">'.esc_html__( "Document", "pdf-for-wpforms" ).'</a>';
        $actions[] = '<a target="_blank" href="https://add-ons.org/supports/" target="_blank">'.esc_html__( "Supports", "pdf-for-wpforms" ).'</a>';
        return $actions;
    }
    function activation() {
        $check = get_option( "yeepdf_wpforms_setup" );
        if( !$check ){           
            $data = file_get_contents(BUIDER_PDF_WPFORMS_PLUGIN_PATH."wpforms/form-import.json");
            $my_template = array(
            'post_title'    => "WPForms Default PDF",
            'post_content'  => "",
            'post_status'   => 'publish',
            'post_type'     => 'yeepdf'
            );
            $id_template = wp_insert_post( $my_template );
            add_post_meta($id_template,"data_email",$data);      
            add_post_meta($id_template,"_builder_pdf_settings_font_family",'dejavu sans');
            update_option( "yeepdf_wpforms_setup",$id_template );     
        } 
    }
}
new Yeepdf_Creator_Wpforms_Builder;
if(!class_exists('Superaddons_List_Addons')) {  
    include BUIDER_PDF_WPFORMS_PLUGIN_PATH."add-ons.php"; 
}