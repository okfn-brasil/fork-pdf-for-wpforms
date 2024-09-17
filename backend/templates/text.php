<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
add_action("yeepdf_builder_block","superaddons_pdf_builder_block_text",10);
function superaddons_pdf_builder_block_text(){
    ?>
    <li>
        <div class="momongaDraggable" data-type="text">
            <i class="pdf-creator-icon icon-text-height"></i>
            <div class="yeepdf-tool-text"><?php esc_html_e("Text/HTML","pdf-for-wpforms") ?></div>
        </div>
    </li>
    <?php
}
add_filter( 'yeepdf_builder_block_html', "superaddons_pdf_builder_block_text_load" );
function superaddons_pdf_builder_block_text_load($type){
    $type["block"]["text"]["builder"] = '
    <div class="builder-elements">
        <div class="builder-elements-content" data-type="text">
            <div class="text-content-data hidden">Lorem Ipsum is simply dummy text of the printing and typesetting industry.</div>
            <div class="text-content">Lorem Ipsum is simply dummy text of the printing and typesetting industry.</div>
        </div>
    </div>';
    $type["block"]["text"]["editor"]["container"]["show"]= ["text-align","padding","background","html","margin","condition"];
    $padding = yeepdf_Global_Data::$padding;
    $margin = yeepdf_Global_Data::$margin;
    $text_align = yeepdf_Global_Data::$text_align;
    $container_style = array(
            ".builder__editor--item-background .builder__editor_color"=>"background-color",
            ".builder__editor--item-background .image_url"=>"background-image",
        );
    $type["block"]["text"]["editor"]["container"]["style"]= array_merge($padding,$container_style,$text_align,$margin);
    $type["block"]["text"]["editor"]["inner"]["style"]= array();
    $type["block"]["text"]["editor"]["inner"]["attr"] = array(".text-content"=>array(".builder__editor--html .builder__editor--js"=>"html"),
                                                              ".text-content-data"=>array(".builder__editor--html .builder__editor--js"=>"html_hide"));
    return $type; 
}