<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class Superaddons_Pdf_Creator_Wpfroms_Backend {
	private $attachments_notifications = array();
	private $created = false;
	public $form_data;
    public $fields;
    public $entry_id;
    public $entry;
	function __construct(){
		add_filter("yeepdf_shortcodes",array($this,"add_shortcode"));
		add_action("yeepdf_head_settings",array($this,"add_head_settings"));
		add_action("save_post_yeepdf",array( $this, 'save_metabox' ), 10, 2 );
        add_filter("wpforms_entry_details_sidebar_actions_link",array($this,"add_link_download"),10,3);
        add_action("wpforms_form_settings_panel_content",array($this,"add_tab_settings"));
        add_filter("wpforms_builder_settings_sections", array($this,"add_tab_settings_sections"));
        add_action("wpforms_process_entry_saved",array($this,"save_pdf"),10,4);
        add_filter("wpforms_emails_send_email_data",array($this,"add_attachments"),10,2);
        add_filter("wpforms_builder_strings",array($this,"wpforms_builder_strings"),10);
        add_filter("wpforms_smarttags_process_value",array($this,"wpforms_smarttags_process_value"),10,3);
        add_filter("superaddons_pdf_check_pro",array($this,"check_pro"));
        add_filter("yeepdf_add_libs",array($this,"yeepdf_add_libs"));
		//resend 
		add_filter("wpforms_entry_email_before_send",array($this,"resend"),10);
		add_action('admin_enqueue_scripts', array($this,"settings_js"));
	}
	function settings_js(){
		if(isset($_GET["page"]) && $_GET["page"] == "wpforms-builder"){
			wp_enqueue_script('yeepdf_wpforms', BUIDER_PDF_WPFORMS_PLUGIN_URL. 'wpforms/yeepdf-wpforms.js',array("jquery"));
			$pro ="free";
			if (wpforms()->is_pro()) { 
				$pro ="pro";
			}
			wp_localize_script("yeepdf_wpforms","yeepdf_wpforms",array("pro"=>$pro));
		}
	}
	function check_pro($pro){
		$check = get_option( '_redmuber_item_1537');
		if($check == "ok"){
			$pro = true;
		}
		return $pro;
	}
	function yeepdf_add_libs($add){
		if(isset($_GET["page"]) && $_GET["page"] == "wpforms-builder"){
			$add = true;
		}
		return $add;
	}
	function wpforms_smarttags_process_value($content,$tag_name,$form_data){
		if( isset($form_data["cover_r_to_br"]) && $form_data["cover_r_to_br"] && isset($content)){
			$content = str_replace("\r", "<br />", $content);
		}
		return $content;
	}
	function wpforms_builder_strings($strings){
		$add = array(
			'pdf_creator_delete'            => esc_html__( 'Are you sure you want to delete this pdf?', "pdf-for-wpforms" ),
			'pdf_creator_prompt'            => esc_html__( 'Enter a pdf name', "pdf-for-wpforms" ),
			'pdf_creator_ph'                => esc_html__( 'Eg: User pdf', "pdf-for-wpforms" ),
			'pdf_creator_error'             => esc_html__( 'You must provide a pdf name', "pdf-for-wpforms" )
		);
		return array_merge($strings,$add);
	}
	public function clear_empty_rules( $conditionals ) {
		if ( empty( $conditionals ) || ! is_array( $conditionals ) ) {
			return [];
		}
		foreach ( $conditionals as $group_id => $group ) {
			if ( empty( $group ) || ! is_array( $group ) ) {
				unset( $conditionals[ $group_id ] );
				continue;
			}
			foreach ( $group as $rule_id => $rule ) {
				if ( ! isset( $rule['field'] ) || '' === $rule['field'] ) {
					unset( $conditionals[ $group_id ][ $rule_id ] );
				}
			}
			if ( empty( $conditionals[ $group_id ] ) ) {
				unset( $conditionals[ $group_id ] );
			}
		}
		return $conditionals;
	}
	function process_pdf_conditionals( $process, $fields, $form_data, $id ) {
		$settings = $form_data['settings'];
		if (
			empty( $settings['pdf_creator'][ $id ]['conditional_logic'] ) ||
			empty( $settings['pdf_creator'][ $id ]['conditional_type'] ) ||
			empty( $settings['pdf_creator'][ $id ]['conditionals'] )
		) {
			return $process;
		}
		$conditionals = $this->clear_empty_rules( $settings['pdf_creator'][ $id ]['conditionals'] );
		if ( empty( $conditionals ) ) {
			return $process;
		}
		$type    = $settings['pdf_creator'][ $id ]['conditional_type'];
		if( function_exists("wpforms_conditional_logic") ){
			$process = wpforms_conditional_logic()->process( $fields, $form_data, $conditionals );
		}
		if ( 'stop' === $type ) {
			$process = ! $process;
		}
		if ( ! $process ) {
		}
		return $process;
	}
	function add_link_download($action_links, $entry, $form_data){
		$upload_dir = wp_upload_dir();
		$datas = wpforms()->entry_meta->get_meta(array("entry_id"=>$entry->entry_id,"type"=>"pdf_link"));
		if( isset($datas[0]->data)) {
			$path_main = json_decode($datas[0]->data,true);
			if(count($path_main)>0){
				$i =1;
				foreach($path_main as $url ){
					$action_links["pdf".$i] = array(
						'url'   => $url,
						'target' => 'blank',
						'icon'   => 'dashicons-media-text',
						'label' =>  esc_html__("Download PDF","pdf-for-wpforms"). " ".$i,
					);
					$i++;
				}
			}
		}
		return $action_links;
	}
	function resend($emails){
		if (wpforms()->is_pro()) {
			$fields = $emails->fields;
			$entry = (array) wpforms()->entry->get($emails->entry_id);
			$form_data = $emails->form_data;
			$entry_id = $emails->entry_id;
			$entry_fields = json_decode($entry["fields"],true);
			$entry["fields"] = $entry_fields;
			$this->save_pdf($fields, $entry, $form_data,$entry_id);
		}
		return $emails;
	}
	function save_pdf($fields, $entry, $form_data,$entry_id){
		$this->form_data = $form_data;
        $this->fields = $fields;
        $this->entry_id = $entry_id;
        $this->entry = $entry;
		if($this->created){
			return;
		}	
		$this->created = true;
		$form_id = $form_data["id"]; 
		$form_data["cover_r_to_br"] = true;
		$attachments_notifications = $this->attachments_notifications;
		$attachments = array();
		$upload_dir   = wp_upload_dir();
		if(isset($form_data["settings"]["pdf_creator_enable"]) && $form_data["settings"]["pdf_creator_enable"] ==1 ) {
			$pdfs = $form_data["settings"]["pdf_creator"];
			$data_entry = array();
			foreach( $entry  as $k => $v){
				if($k == "fields"){
					foreach( $v as $key => $vl ){
						if( is_array($vl) ){
							if(isset($vl["value"])){
								if(is_array($vl["value"])){
									$vl = implode(", ",$vl["value"]);
								}else{
									$vl = $vl["value"];
								}
							}else{
								$vl = implode(", ",$vl);
							}
						}
						$vl = str_replace("\r", "<br />", $vl);
						$data_entry["{field_id='".$key."'}"] = $vl;
					}
				}else{
					if( is_array($v) ){
						$v = implode(", ",$v);
					}
					$vl = str_replace("\r", "<br />", $v);
					$data_entry['{'.$k.'}'] = $v;
				}
			}
			foreach( $pdfs as $id => $pdf ){
				$list_notifitions = array();
				foreach($pdf as $pdf_key => $pdf_value){
					if(preg_match("/pdf_notifications_/", $pdf_key)){
						if($pdf_value == 1){
							$keys = explode("_",$pdf_key);
							$list_notifitions[] = end($keys);
						}
					}
				}
				$template_id = $pdf["template_id"];
				$name ="";
				if(isset($pdf["yeepdf_name"])){
					$name = $pdf["yeepdf_name"];
				}
				$password= "";
				if(isset($pdf["yeepdf_password"])){
					$password= $pdf["yeepdf_password"];
				}
				$conditional_logic= "";
				if(isset($pdf["conditional_logic"])){
					$conditional_logic= $pdf["conditional_logic"];
				}
				$process = $this->process_pdf_conditionals(true,$fields,$form_data,$id);
				if(!$process ){
					continue;
				}
				if( $name == ""){
					$name= "contact-form";
				}else{
					$name = wpforms_process_smart_tags($name,$form_data,$fields,$entry_id);
				}
				if( $password != ""){
					$password = wpforms_process_smart_tags($password,$form_data,$fields,$entry_id);
				}
				if( isset($pdf["yeepdf_name_number"]) && $pdf["yeepdf_name_number"] == 1 ){
					$name = rand(1000,9999)."-".sanitize_file_name($name);
				}else{
					$name = sanitize_file_name($name);
				}
				$data_send_settings = array(
					"id_template"=> $template_id,
					"type"=> "html",
					"name"=> $name,
					"datas" =>$data_entry,
					"return_html" =>true,
				);
				$message =Yeepdf_Create_PDF::pdf_creator_preview($data_send_settings);
				$message = $this->add_all_fields($message,$form_data,$fields,$entry_id);
				$message = wpforms_process_smart_tags($message,$form_data,$fields,$entry_id);
				$data_send_settings_download = array(
					"id_template"=> $template_id,
					"type"=> "upload",
					"name"=> $name,
					"datas" =>$data_entry,
					"html" =>$message,
					"password" =>$password,
				);
				$data_send_settings_download = apply_filters("pdf_before_render_datas",$data_send_settings_download);
				$path =Yeepdf_Create_PDF::pdf_creator_preview($data_send_settings_download);
				$path_main = $upload_dir['baseurl'] . '/pdfs/'.$name.".pdf";
				$attachments[] = $path_main;
				foreach($list_notifitions as $notifi){
					$attachments_notifications[$notifi][] = $path;
				}
			}
			if (wpforms()->is_pro()) {
				wpforms()->entry_meta->add(
						[
							'entry_id' => absint( $entry_id),
							'form_id'  => absint( $form_id),
							'user_id'  => get_current_user_id(),
							'type'     => 'pdf_link',
							'data'     => json_encode($attachments),
						],
						'entry_meta'
				);
			}
			$this->attachments_notifications = $attachments_notifications;
		}
	}
	function add_attachments($email, $email_obj){
		$attachments = array();
		$attachments_notifications = $this->attachments_notifications;
		if ( ! isset( $email_obj->form_data, $email_obj->notification_id, $email_obj->fields ) ) {
			return $email;
		}
		$form_data       = $email_obj->form_data;
		$notification_id = $email_obj->notification_id;
		$entry_fields    = $email_obj->fields;
		if (array_key_exists($notification_id, $attachments_notifications)) {
			$email['attachments'] = array_merge( (array) $email['attachments'], $attachments_notifications[$notification_id]);
		}		
		return $email;
	}
	function add_tab_settings_sections($sections ){
		$sections["pdf_creator"] = esc_html__("PDF","pdf-for-wpforms");
		return $sections;
	}
	function add_tab_settings($settings){
		?>
		<div class="wpforms-panel-content-section wpforms-panel-content-section-pdf_creator" data-panel="pdf_creator">
			<?php $this->form_settings_pdf_creator($settings) ?>
		</div>
		<?php
	}
	public function form_settings_pdf_creator( $settings ) {
		$form_id = $settings->form->ID;
		$pro = Yeepdf_Settings_Builder_PDF_Backend::check_pro();
		$form_settings = ! empty( $settings->form_data['settings'] ) ? $settings->form_data['settings'] : [];
		$pdfs = is_array( $form_settings ) && isset( $form_settings['pdf_creator'] ) ? $form_settings['pdf_creator'] : [];
		$form_settings = ! empty( $settings->form_data['settings'] ) ? $settings->form_data['settings'] : [];
		if ( empty( $pdfs ) ) {
			$next_id = 2;
			$pdfs[1]['template_id']    = ! empty( $form_settings['pdf_creator_template_id'] ) ? $form_settings['pdf_creator_template_id'] : '';
			$pdfs[1]['name']        = ! empty( $form_settings['pdf_creator_name'] ) ? $form_settings['pdf_creator_name'] : '';
			$pdfs[1]['password']        = ! empty( $form_settings['pdf_creator_password'] ) ? $form_settings['pdf_creator_password'] : '';
		} else {
			$next_id = max( array_keys( $pdfs ) ) + 1;
		}
		$default_pdfs_key = min( array_keys( $pdfs ) );
		$hidden = empty( $settings->form_data['settings']['pdf_creator_enable'] ) ? 'hidden' : '';
		?>
		<div class="wpforms-panel-content-section-title">
			<span id="wpforms-builder-settings-pdf_creator-title">
				<?php esc_html_e( 'PDF', "pdf-for-wpforms" ) ?>
			</span>
			<button class="wpforms-pdf_creator-add wpforms-builder-settings-block-add" data-block-type="pdf_creator" data-next-id="<?php echo absint( $next_id ) ?>"><?php esc_html_e("Add New PDF","pdf-for-wpforms") ?></button>
		</div>
		<?php
		wpforms_panel_field(
			'toggle',
			'settings',
			'pdf_creator_enable',
			$settings->form_data,
			esc_html__( 'Enable PDF', "pdf-for-wpforms" ),
			[
				'value' => empty( $form_settings['pdf_creator_enable'] ) ? 0 : 1,
				'class' => 'pdfcreator_datas_enable',
				'input_class' => 'pdfcreator_datas_enable',
				'data' => array("tab"=>".wpforms-pdf_creator")
			]
		);
		$yeepdfs = get_posts(array( 'post_type' => 'yeepdf','post_status' => 'publish','numberposts'=>-1 ) );
		$list_templates = array();
		if($yeepdfs){
			foreach ( $yeepdfs as $post ) {
				$post_id = $post->ID;
				$list_templates[$post_id] = $post->post_title;
			}
		}else{
			$list_templates[-1] = esc_html__("No template","pdf-for-wpforms");
		}
		$notifications_datas = is_array( $form_settings ) && isset( $form_settings['notifications'] ) ? $form_settings['notifications'] : [];	
		if ( empty( $notifications_datas ) ) {
			$notifications_datas[1]['name'] ="Default"; 
		}		
		foreach ( $pdfs as $id => $pdf ) {
			$name          = ! empty( $pdf['name'] ) ? $pdf['name'] : esc_html__( 'Default PDF', "pdf-for-wpforms" );
			$closed_state  = '';
			$toggle_state  = '<i class="fa fa-chevron-circle-up"></i>';
			$block_classes = 'wpforms-pdf_creator wpforms-builder-settings-block '.$hidden;
			$from_name_after = apply_filters( 'wpforms_builder_pdf_creator_from_name_after', '', $settings->form_data, $id );
			if ( ! empty( $settings->form_data['id'] ) && 'closed' === wpforms_builder_settings_block_get_state( $settings->form_data['id'], $id, 'pdf_creator' ) ) {
				$closed_state = 'style="display:none"';
				$toggle_state = '<i class="fa fa-chevron-circle-down"></i>';
			}
			if ( $default_pdfs_key === $id ) {
				$block_classes .= ' wpforms-builder-settings-block-default';
			}
			do_action( 'wpforms_form_settings_pdf_creator_single_before', $settings, $id );
			?>
			<div class="<?php echo esc_attr( $block_classes ); ?>" data-block-type="pdf_creator" data-block-id="<?php echo absint( $id ); ?>">
				<div class="wpforms-builder-settings-block-header">
					<div class="wpforms-builder-settings-block-actions">
						<?php do_action( 'wpforms_form_settings_pdf_creator_single_action', $id, $pdf, $settings ); ?>
						<button class="wpforms-builder-settings-block-clone" title="<?php esc_attr_e( 'Clone', "pdf-for-wpforms" ); ?>"><i class="fa fa-copy"></i></button><!--
						--><button class="wpforms-builder-settings-block-delete" title="<?php esc_attr_e( 'Delete', "pdf-for-wpforms" ); ?>"><i class="fa fa-trash-o"></i></button><!--
						--><button class="wpforms-builder-settings-block-toggle" title="<?php esc_attr_e( 'Open / Close', "pdf-for-wpforms" ); ?>">
							<?php echo $toggle_state; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</button>
					</div>
					<div class="wpforms-builder-settings-block-name-holder">
						<span class="wpforms-builder-settings-block-name">
							<?php echo esc_html( $name ); ?>
						</span>
						<div class="wpforms-builder-settings-block-name-edit">
							<input type="text" name="settings[pdf_creator][<?php echo absint( $id ); ?>][name]" value="<?php echo esc_attr( $name ); ?>">
						</div>
						<button class="wpforms-builder-settings-block-edit" title="<?php esc_attr_e( 'Edit', "pdf-for-wpforms" ); ?>"><i class="fa fa-pencil"></i></button>
					</div>
				</div>
				<div class="wpforms-builder-settings-block-content" <?php echo $closed_state; ?>>
					<?php
					$class_logic_type_upload = "";
					$class_logic_type_id = "";
					wpforms_panel_field(
						'select',
						'pdf_creator',
						'template_id',
						$settings->form_data,
						esc_html__( 'Choose template', "pdf-for-wpforms" ),
						[
							'options'    => $list_templates,
							'parent'     => 'settings',
							'subsection' => $id,
							'input_id'   => 'wpforms-panel-field-pdf-1-' . $id,
							'input_class' => 'wpforms-panel-field-pdf-1',
							'class' => 'gform-kitchen-sink-template_id '.$class_logic_type_id,
						]
					);
					?>
					<div class="wpforms-panel-field email-msg wpforms-panel-field-text">
						<label ><?php esc_html_e( 'Attachment pdf in Notifications', "pdf-for-wpforms" ) ?></label>
					</div>
					<?php
					foreach( $notifications_datas as $id_n => $notifications_data ){
						if(isset($notifications_data["notification_name"])){
							$notification_name = $notifications_data["notification_name"];
						}else{
							$notification_name = "Default Notification";
						}
						wpforms_panel_field(
								'checkbox',
								'pdf_creator',
								'pdf_notifications_'.$id_n,
								$settings->form_data,
								esc_html( $notification_name),
								[
									'tooltip'    => esc_html__("Send the PDF as an email attachment for the selected notifications.","pdf-for-wpforms") ,
									'parent'     => 'settings',
									'subsection' => $id,
								]
							);
					}
					wpforms_panel_field(
								'text',
								'pdf_creator',
								'yeepdf_name',
								$settings->form_data,
								esc_html__( 'PDF Template Custom Name', "pdf-for-wpforms" ),
								[
									'rows'       => 6,
									'default'    => "",
									'smarttags'  => [
										'type' => 'all',
									],
									'parent'     => 'settings',
									'subsection' => $id,
									'class'      => 'email-msg',
									'after'      => '<p class="note">{name}-id.pdf</p>',
								]
							);
					if($pro){
						wpforms_panel_field(
								'checkbox',
								'pdf_creator',
								'yeepdf_name_number',
								$settings->form_data,
								esc_html__( 'Show name key number', "pdf-for-wpforms" ),
								[
									'rows'       => 6,
									'default'    => "1",
									'smarttags'  => [
										'type' => 'all',
									],
									'parent'     => 'settings',
									'subsection' => $id,
									'class'      => 'email-msg',
									'after'      => '<p class="note">name-{number}.pdf</p>',
								]
							);
					}else{
						?>
						<div class="wpforms-panel-field email-msg wpforms-panel-field-checkbox pro_disable pro_disable_padding">
							<input type="checkbox" disabled checked  class="" >
							<label for="wpforms-panel-field-pdf_creator-1-yeepdf_name_number_pro" class="inline"><?php esc_attr_e( "Show name key number", "pdf-for-wpforms" ) ?></label>
							<p class="note">{number}-name.pdf</p>
						</div>
						<?php	
					}
					if($pro){
					wpforms_panel_field(
								'text',
								'pdf_creator',
								'yeepdf_password',
								$settings->form_data,
								esc_html__( 'PDF Password', "pdf-for-wpforms" ),
								[
									'rows'       => 6,
									'default'    => "",
									'smarttags'  => [
										'type' => 'all',
									],
									'parent'     => 'settings',
									'subsection' => $id,
									'class'      => 'email-msg',
								]
							);
					}else{
						?>
						<div class="wpforms-panel-field email-msg wpforms-panel-field-text pro_disable pro_disable_padding">
							<label for="wpforms-panel-field-pdf_creator-1-yeepdf_name_number_pro" class="inline"><?php esc_attr_e( "PDF Password", "pdf-for-wpforms" ) ?></label>	
							<input type="text" disabled  class="">
						</div>
						<?php
					}
					if($pro){
						if( function_exists("wpforms_conditional_logic") ){
							wpforms_conditional_logic()->builder_block(
								[
									'form'        => $settings->form_data,
									'type'        => 'panel',
									'panel'       => 'pdf_creator',
									'parent'      => 'settings',
									'subsection'  => $id,
									'actions'     => [
										'go'   => esc_html__( 'Enable', "pdf-for-wpforms" ),
										'stop' => esc_html__( 'Disable', "pdf-for-wpforms" ),
									],
									'action_desc' => esc_html__( 'this pdf if', "pdf-for-wpforms" ),
									'reference'   => esc_html__( 'pdf', "pdf-for-wpforms" ),
								]
							);
						}else{
							?>
							<div class="wpforms-panel-field email-msg ">
								<p class="note"><?php esc_attr_e( "Conditional Logic only works on WPForms Pro", "pdf-for-wpforms" ) ?></p>
							</div>
							<?php
						}
					}else{
						?>
						<div class="wpforms-panel-field email-msg wpforms-panel-field-checkbox pro_disable pro_disable_padding">
							<input type="checkbox" disabled  class="" checked="checked">
							<label for="wpforms-panel-field-pdf_creator-1-yeepdf_name_number_pro" class="inline"><?php esc_attr_e( "Conditional Logic", "pdf-for-wpforms" ) ?></label>
							<p class="note"><?php esc_attr_e( "allows you to enable or disable a PDF if a user responded a specific way.", "pdf-for-wpforms" ) ?></p>
						</div>
						<?php
					}
					do_action( 'wpforms_form_settings_pdf_single_after', $settings, $id );
					?>
				</div><!-- /.wpforms-builder-settings-block-content -->
			</div><!-- /.wpforms-builder-settings-block -->
			<?php
		}
	}
	function wpforms_process_smart_tags($value,
				$tag_name,
				$form_data,
				$fields,
				$entry_id,
				$smart_tag_object){
		$lists = preg_split('/\r\n|[\r\n]/', $value);
		$datas = array();
		foreach(  $lists as $image ){
			$image = trim($image);
			if( preg_match('/^(http|https):\/\/(.*?)\.(png|JPEG|jpeg|jpg|gif|PNG|JPG|GIF)$/i',$image) ) {
				$datas[] = '<img style="width:150px;height:39px" src="'.$image.'" />';
			}
		}
		if( count($datas) > 0  ){
			return implode("</br>",$datas);
		}
		return $value;
	}
	function add_head_settings($post){
		global $wpdb;
		if( isset($_GET["post"] ) ) {
			$post_id= sanitize_text_field($_GET["post"]);
		}else{
			$post_id= $post->ID;
		}
		$data = get_post_meta( $post_id,'_pdfcreator_wpforms',true);
        ?>
        <div class="yeepdf-testting-order">
            <select name="pdfcreator_wpforms" class="builder_pdf_woo_testing">
			<option value='-1'>--- <?php esc_html_e("WPForms","pdf-for-wpforms") ?> ---</option>
                <?php
				$the_query = new WP_Query( array("post_type"=>"wpforms","posts_per_page"=>-1) );
				if( $the_query->have_posts()  ){
					while ( $the_query->have_posts() ) {
						$the_query->the_post();
						$form_id = get_the_ID();
						$form_title = get_the_title();
						?>
							<option <?php selected($data,$form_id) ?> value="<?php echo esc_attr($form_id) ?>"><?php echo esc_html($form_title) ?></option>
						<?php
					}
				}else{
					?>
					<option value='0'><?php esc_html_e("Create a form","pdf-for-wpforms") ?></option>
					<?php
				}
				wp_reset_postdata();
                ?>
            </select>
        </div>
        <?php
    }
    function save_metabox($post_id, $post){
        if( isset($_POST['pdfcreator_wpforms'])) {
            $id = sanitize_text_field($_POST['pdfcreator_wpforms']);
            update_post_meta($post_id,'_pdfcreator_wpforms',$id);
        }
    }
	function add_shortcode($shortcode) {
		if( isset($_GET["post"]) ){
            $post_id = sanitize_text_field($_GET["post"]);
			$form_id = get_post_meta( $post_id,'_pdfcreator_wpforms',true);	
			$fields = array();
			if($form_id){
                $inner_shortcode = array(
					"{form_name}" => "Form Name",
					"{form_id}"=>"Form ID",
					"{entry_id}"=>"Entry ID",
					"{entry_details_url}"=>"Entry Details URL",
					"{all_fields}"=>"{all_fields}",
				);
				$form = wpforms()->form->get( absint( $form_id) );
                // If the form doesn't exists, abort.
                if ( empty( $form ) ) {
                    return $shortcode;
                }
                // Pull and format the form data out of the form object.
                $form_data = ! empty( $form->post_content ) ? wpforms_decode( $form->post_content ) : '';
                // Check to see if we are showing all allowed fields, or only specific ones.
                $form_field_ids = isset( $atts['fields'] ) && $atts['fields'] !== '' ? explode( ',', str_replace( ' ', '', $atts['fields'] ) ) : [];
                // Setup the form fields.
                $form_fields = array();
                if ( empty( $form_field_ids ) ) {
                    if( isset($form_data['fields']) ) {
                        $form_fields = $form_data['fields'];
                    }
                } else {
                    $form_fields = [];
                    foreach ( $form_field_ids as $field_id ) {
                        if ( isset( $form_data['fields'][ $field_id ] ) ) {
                            $form_fields[ $field_id ] = $form_data['fields'][ $field_id ];
                        }
                    }
                }
                if( is_array($form_fields)) {
                    foreach( $form_fields as $id => $datas){
                        $label = $id;
                        if(isset($datas["label"])){
                            $label = $datas["label"];
                        }
                        $inner_shortcode["{field_id='".$id."'}"] = $label;
                    }
                } 
				$inner_shortcode["{entry_id}"] = 'Entry Id';
                $shortcode["WPForms"] = $inner_shortcode;         
			}
		}
		return $shortcode;
	}
	function add_all_fields($message,$form_data,$fields,$entry_id){
		if ( strpos( $message, '{all_fields}' ) === false ) {
            // Wrap the message with a table row after processing tags.
            //$message = $this->wrap_content_with_table_row( $message,$form_data,$fields,$entry_id );
        } else {
            // If {all_fields} is present, extract content before and after into separate variables.
            list( $before, $after ) = array_map( 'trim', explode( '{all_fields}', $message, 2 ) );
            // Wrap before and after content with <tr> tags if they are not empty to maintain styling.
            // Note that whatever comes after the {all_fields} should be wrapped in a table row to avoid content misplacement.
            $before_tr = ! empty( $before ) ? $this->wrap_content_with_table_row( $before,$form_data,$fields,$entry_id ) : '';
            $after_tr  = ! empty( $after ) ? $this->wrap_content_with_table_row( $after,$form_data,$fields,$entry_id  ) : '';
            // Replace {all_fields} with $this->process_field_values() output.
            $message = $before_tr . $this->process_field_values() . $after_tr;
        }
		return $message;
	}
	public function process_field_values() {
		// If fields are empty, return an empty message.
		if ( empty( $this->fields ) ) {
			return '';
		}
		// If no message was generated, create an empty message.
		$default_message = esc_html__( 'An empty form was submitted.', 'wpforms-lite' );
		/**
		 * Filter whether to display empty fields in the email.
		 *
		 * @since 1.8.5
		 * @deprecated 1.8.5.2
		 *
		 * @param bool $show_empty_fields Whether to display empty fields in the email.
		 */
		$show_empty_fields = apply_filters_deprecated( // phpcs:disable WPForms.Comments.ParamTagHooks.InvalidParamTagsQuantity
			'wpforms_emails_notifications_display_empty_fields',
			[ false ],
			'1.8.5.2 of the WPForms plugin',
			'wpforms_email_display_empty_fields'
		);
		/** This filter is documented in /includes/emails/class-emails.php */
		$show_empty_fields = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'wpforms_email_display_empty_fields',
			false
		);
		$message = $this->process_html_message( $show_empty_fields );
		return empty( $message ) ? $default_message : $message;
	}
    public function process_html_message( $show_empty_fields = false ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity
		$message = '<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; border-spacing: 0px; padding: 0px; vertical-align: top;">';
		/**
		 * Filter the list of field types to display in the email.
		 *
		 * @since 1.8.5
		 * @deprecated 1.8.5.2
		 *
		 * @param array $other_fields List of field types.
		 * @param array $form_data    Form data.
		 */
		$other_fields = apply_filters_deprecated( // phpcs:disable WPForms.Comments.ParamTagHooks.InvalidParamTagsQuantity
			'wpforms_emails_notifications_display_other_fields',
			[ [], $this->form_data ],
			'1.8.5.2 of the WPForms plugin',
			'wpforms_email_display_other_fields'
		);
		/** This filter is documented in /includes/emails/class-emails.php */
		$other_fields = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'wpforms_email_display_other_fields',
			[],
			$this
		);
		foreach ( $this->form_data['fields'] as $field_id => $field ) {
			$field_type = ! empty( $field['type'] ) ? $field['type'] : '';
			// Check if the field is empty in $this->fields.
			if ( empty( $this->fields[ $field_id ] ) ) {
				// Check if the field type is in $other_fields, otherwise skip.
				if ( empty( $other_fields ) || ! in_array( $field_type, $other_fields, true ) ) {
					continue;
				}
				// Handle specific field types.
				list( $field_name, $field_val ) = $this->process_special_field_values( $field );
			} else {
				// Handle fields that are not empty in $this->fields.
				if ( ! $show_empty_fields && ( ! isset( $this->fields[ $field_id ]['value'] ) || (string) $this->fields[ $field_id ]['value'] === '' ) ) {
					continue;
				}
				$field_name = isset( $this->fields[ $field_id ]['name'] ) ? $this->fields[ $field_id ]['name'] : '';
				$field_val  = empty( $this->fields[ $field_id ]['value'] ) && ! is_numeric( $this->fields[ $field_id ]['value'] ) ? '<em>' . esc_html__( '(empty)', 'wpforms-lite' ) . '</em>' : $this->fields[ $field_id ]['value'];
			}
			// Set a default field name if empty.
			if ( empty( $field_name ) && $field_name !== null ) {
				$field_name = $this->get_default_field_name( $field_id );
			}
			/** This filter is documented in src/SmartTags/SmartTag/FieldHtmlId.php.*/
			$field_val = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
				'wpforms_html_field_value',
				$field_val,
				isset( $this->fields[ $field_id ] ) ? $this->fields[ $field_id ] : $field,
				$this->form_data,
				'email-html'
			);
			// Replace new lines with <br/> tags.
			$field_val = str_replace( [ "\r\n", "\r", "\n" ], '<br/>', $field_val );
			// Append the field item to the message.
            $message .= '<tr style="padding: 0px; vertical-align: top;">';
			$message .= '<td style="overflow-wrap: break-word; vertical-align: top; font-weight: normal; padding: 25px 10px 25px 0px; margin: 0px; font-size: 15px; color: rgb(51, 51, 51); border-bottom: 1px solid rgb(226, 226, 226); min-width: 113px; line-height: 22px; border-collapse: collapse;">
                        <strong style="margin-bottom: 0px;">'.$field_name.'</strong>
                    </td>';
            $message .= '<td valign="middle" style="overflow-wrap: break-word; font-weight: normal; padding: 25px 0px; margin: 0px; font-size: 15px; color: rgb(51, 51, 51);line-height: 20px; border-bottom: 1px solid rgb(226, 226, 226); vertical-align: middle; border-collapse: collapse;"> '.$field_val.' </td>';
            $message .= '</tr>';
        }
		return $message.'</table>';
	}
    public function get_default_field_name( $field_id ) {
		return sprintf( /* translators: %1$d - field ID. */
			esc_html__( 'Field ID #%1$d', 'wpforms-lite' ),
			absint( $field_id )
		);
	}
    public function process_special_field_values( $field ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity
		$field_name = null;
		$field_val  = null;
		// Use a switch-case statement to handle specific field types.
		switch ( $field['type'] ) {
			case 'divider':
				$field_name = ! empty( $field['label'] ) ? str_repeat( '&mdash;', 3 ) . ' ' . $field['label'] . ' ' . str_repeat( '&mdash;', 3 ) : null;
				$field_val  = ! empty( $field['description'] ) ? $field['description'] : '';
				break;
			case 'pagebreak':
				// Skip if position is 'bottom'.
				if ( ! empty( $field['position'] ) && $field['position'] === 'bottom' ) {
					break;
				}
				$title      = ! empty( $field['title'] ) ? $field['title'] : esc_html__( 'Page Break', 'wpforms-lite' );
				$field_name = str_repeat( '&mdash;', 6 ) . ' ' . $title . ' ' . str_repeat( '&mdash;', 6 );
				break;
			case 'html':
				// Skip if the field is conditionally hidden.
				if ( $this->is_field_conditionally_hidden( $field['id'] ) ) {
					break;
				}
				$field_name = ! empty( $field['name'] ) ? $field['name'] : esc_html__( 'HTML / Code Block', 'wpforms-lite' );
				$field_val  = $field['code'];
				break;
			case 'content':
				// Skip if the field is conditionally hidden.
				if ( $this->is_field_conditionally_hidden( $field['id'] ) ) {
					break;
				}
				$field_name = esc_html__( 'Content', 'wpforms-lite' );
				$field_val  = $field['content'];
				break;
			default:
				$field_name = '';
				$field_val  = '';
				break;
		}
		return [ $field_name, $field_val ];
	}
    public function is_field_conditionally_hidden( $field_id ) {
		return ! empty( $this->form_data['fields'][ $field_id ]['conditionals'] ) && ! wpforms_conditional_logic_fields()->field_is_visible( $this->form_data, $field_id );
	}
    public function wrap_content_with_table_row( $content,$form_data,$fields,$entry_id ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
		// If the content is empty, return it as is.
		if ( empty( $content ) ) {
			return $content;
		}
		// Process the smart tags in the content.
		$processed_content = $this->process_tag( $content,$form_data,$fields,$entry_id );
		// If the content doesn't contain any smart tags, wrap it in a table row, and return early.
		// Don't go beyond this point if the content doesn't contain any smart tags.
		if ( ! preg_match( '/{\w+}/', $processed_content ) ) {
			//return '<tr class="smart-tag"><td class="field-name field-value" colspan="2">' . $processed_content . '</td></tr>';
            return $processed_content;
		}
		// Split the content into lines and remove empty lines.
		$lines = array_filter( explode( "\n", $content ), 'strlen' );
		// Initialize an empty string to store the modified content.
		$modified_content = '';
		// Iterate through each line.
		foreach ( $lines as $line ) {
			// Trim the line.
			$trimmed_line = $this->process_tag( trim( $line ),$form_data,$fields,$entry_id );
			// Extract tags at the beginning of the line.
			preg_match( '/^(?:\{[^}]+}\s*)+/i', $trimmed_line, $before_line_tags );
			if ( ! empty( $before_line_tags[0] ) ) {
				// Include the extracted tags at the beginning to the modified content.
				$modified_content .= trim( $before_line_tags[0] );
				// Remove the extracted tags from the trimmed line.
				$trimmed_line = trim( substr( $trimmed_line, strlen( $before_line_tags[0] ) ) );
			}
			// Extract all smart tags from the remaining content.
			preg_match_all( '/\{([^}]+)}/i', $trimmed_line, $after_line_tags );
			// Remove the smart tags from the content.
			$content_without_smart_tags = str_replace( $after_line_tags[0], '', $trimmed_line );
			if ( ! empty( $content_without_smart_tags ) ) {
				// Wrap the content without the smart tags in a new table row.
				//$modified_content .= '<tr class="smart-tag"><td class="field-name field-value" colspan="2">' . $content_without_smart_tags . '</td></tr>';
				$modified_content .= $content_without_smart_tags;
			}
			if ( ! empty( $after_line_tags[0] ) ) {
				// Move all smart tags to the end of the line after the closing </tr> tag.
				$modified_content .= implode( ' ', $after_line_tags[0] );
			}
		}
		// Return the modified content.
		return $modified_content;
    }
    public function process_tag( $input = '',$form_data='',$fields='',$entry_id='' ) {
		return wpforms_process_smart_tags( $input, $form_data,$fields,$entry_id);
	}
}
new Superaddons_Pdf_Creator_Wpfroms_Backend;