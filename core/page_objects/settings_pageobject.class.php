<?php
 /*
 * Project:		EQdkp-Plus
 * License:		Creative Commons - Attribution-Noncommercial-Share Alike 3.0 Unported
 * Link:		http://creativecommons.org/licenses/by-nc-sa/3.0/
 * -----------------------------------------------------------------------
 * Began:		2002
 * Date:		$Date$
 * -----------------------------------------------------------------------
 * @author		$Author$
 * @copyright	2006-2011 EQdkp-Plus Developer Team
 * @link		http://eqdkp-plus.com
 * @package		eqdkp-plus
 * @version		$Rev$
 *
 * $Id$
 */

 
//AJAX
if(register('in')->get('ajax', 0) === 1){
	if($_POST['username']){
		if(register('in')->exists('olduser') && register('in')->get('olduser') === $_POST['username']){
			echo 'true';
		}else{
			echo register('pdh')->get('user', 'check_username', array(register('in')->get('username')));
		}
	}
	if($_POST['email_address']){
		if(register('in')->exists('oldmail') && register('in')->get('oldmail') === $_POST['email_address']){
			echo 'true';
		}else{
			echo register('pdh')->get('user', 'check_email', array(register('in')->get('email_address')));
		}
	}
	exit;
}


class settings_pageobject extends pageobject {
	public static $shortcuts = array('form' => array('form', array('user_settings')));
	private $logo_upload = false;

	public function __construct() {
	
		if (!$this->user->is_signedin()){
			redirect($this->controller_path_plain.'Login/'.$this->SID);
		}

		$handler = array(
			'newexchangekey' => array('process' => 'renew_exchangekey', 'csrf' => true),
			'submit' => array('process' => 'update', 'csrf' => true),
			'mode' => array(
				array('process' => 'delete_authaccount', 'value' => 'delauthacc', 'csrf' => true),
				array('process' => 'add_authaccount', 'value' => 'addauthacc'),
				array('process' => 'delete_avatar', 'value' => 'deleteavatar',  'csrf' => true),
			),

		);
		parent::__construct(false, $handler);

		$this->process();
	}

	public function renew_exchangekey(){
		$app_key = $this->pdh->put('user', 'create_new_exchangekey', array($this->user->id));
		$this->user->data['exchange_key'] = $app_key;
		if ($app_key) $this->core->message($this->user->lang('user_create_new appkey_success'), $this->user->lang('success'), 'green');
	}

	public function delete_avatar() {
		$this->pdh->put('user', 'delete_avatar', array($this->user->data['user_id']));
		$this->pdh->process_hook_queue();
		unset($this->user->data['custom_fields']['user_avatar']);
	}

	public function delete_authaccount() {
		$strMethod = $this->in->get('lmethod');
		$this->pdh->put('user', 'delete_authaccount', array($this->user->id, $strMethod));
		$this->pdh->process_hook_queue();
		unset($this->user->data['auth_account'][$strMethod]);
	}

	public function add_authaccount() {
		$strMethod = $this->in->get('lmethod');
		$account = $this->user->handle_login_functions('get_account', $strMethod);
		if ($strMethod && !is_array($account) && $this->pdh->get('user', 'check_auth_account', array($account))){
			$this->pdh->put('user', 'add_authaccount', array($this->user->id, $account, $strMethod));
			$this->pdh->process_hook_queue();
			$this->user->data['auth_account'][$strMethod] = $account;
		} else {
			$this->core->message($this->user->lang('auth_connect_account_error'), $this->user->lang('error'), 'red');
		}
	}

	public function update() {
		$this->create_form();
		$values = $this->form->return_values();
		// Error-check the form
		$change_username = ( $values['username'] != $this->user->data['username'] ) ? true : false;
		$change_password = ( $values['new_password'] != '' || $values['confirm_password'] != '') ? true : false;
		$change_email = ( $values['user_email'] != $this->user->data['user_email']) ? true : false;

		//Check username
		if ($change_username && $this->pdh->get('user', 'check_username', array($values['username'])) == 'false'){
			$this->core->message(str_replace('{0}', $values['username'], $this->user->lang('fv_username_alreadyuse')), $this->user->lang('error'), 'red');
			$this->display();
			return;
		}

		//Check email
		if ($change_email){
			if ($this->pdh->get('user', 'check_email', array($values['user_email'])) == 'false'){
				$this->core->message(str_replace('{0}', $values['user_email'], $this->user->lang('fv_email_alreadyuse')), $this->user->lang('error'), 'red');
				$this->display();
				return;
			} elseif ( !preg_match("/^([a-zA-Z0-9])+([\.a-zA-Z0-9_-])*@([a-zA-Z0-9_-])+(\.[a-zA-Z0-9_-]+)+/", $values['user_email']) ){
					$this->core->message($this->user->lang('fv_invalid_email'), $this->user->lang('error'), 'red');
					$this->display();
					return;
			}
		}
		
		//Check matching new passwords
		if($change_password) {
			if($values['new_password'] != $values['confirm_password']) {
				$this->core->message($this->user->lang('password_not_match'), $this->user->lang('error'), 'red');
				$this->display();
				return;
			}
		}
		if ($change_password && strlen($values['new_password']) > 64) {
			$this->core->message($this->user->lang('password_too_long'), $this->user->lang('error'), 'red');
			$this->display();
			return;
		}
		
		// If they changed their username or password, we have to confirm their current password
		if ( ($change_username) || ($change_password) || ($change_email)){
			if (!$this->user->checkPassword($values['current_password'], $this->user->data['user_password'])){
				$this->core->message($this->user->lang('incorrect_password'), $this->user->lang('error'), 'red');
				$this->display();
				return;
			}
		}

		// Errors have been checked at this point, build the query
		$query_ary = array();
		if ( $change_username ) $query_ary['username'] = $values['username'];
		if ( $change_password ) {
			$new_salt = $this->user->generate_salt();
			$query_ary['user_password'] = $this->user->encrypt_password($values['new_password'], $new_salt).':'.$new_salt;
			$strApiKey = $this->user->generate_apikey($values['new_password'], $new_salt);
			$query_ary['api_key'] = $strApiKey;
			$query_ary['user_login_key'] = '';
		}

		$query_ary['user_email']	= $this->encrypt->encrypt($values['user_email']);
		$query_ary['exchange_key']	= $this->pdh->get('user', 'exchange_key', array($this->user->id));
		
		//copy all other values to appropriate array
		$ignore = array('username', 'user_email', 'current_password', 'new_password', 'confirm_password');
		$privArray = array();
		$customArray = array();
		foreach($values as $name => $value) {
			if(in_array($name, $ignore)) continue;
			if(in_array($name, user_core::$privFields)) 
				$privArray[$name] = $value;
			elseif(in_array($name, user_core::$customFields)) 
				$customArray[$name] = $value;
			else 
				$query_ary[$name] = $value;
		}
		
		//Create Thumbnail for User Avatar
		if ($customArray['user_avatar'] != "" && $this->pdh->get('user', 'avatar', array($this->user->id)) != $customArray['user_avatar']){
			$image = $this->pfh->FolderPath('users/'.$this->user->id,'files').$customArray['user_avatar'];
			$this->pfh->thumbnail($image, $this->pfh->FolderPath('users/thumbs','files'), 'useravatar_'.$this->user->id.'_68.'.pathinfo($image, PATHINFO_EXTENSION), 68);
		}
		
		$query_ary['privacy_settings']		= serialize($privArray);
		$query_ary['custom_fields']			= serialize($customArray);

		/* NYI - TODO
		$plugin_settings = array();
		if (is_array($this->pm->get_menus('settings'))){
			foreach ($this->pm->get_menus('settings') as $plugin => $values){
				
				foreach ($values as $key=>$setting){
				if ($key == 'icon' || $key == 'name') continue;
					$name = $setting['name'];
					$setting['name'] = $plugin.':'.$setting['name'];
					$setting['plugin'] = $plugin;
					$plugin_settings[$plugin][$name] = $this->html->widget_return($setting);
				}
			}
		}
		$query_ary['plugin_settings']	= serialize($plugin_settings);
		*/
		
		$blnResult = $this->pdh->put('user', 'update_user', array($this->user->id, $query_ary));
		$this->pdh->process_hook_queue();
		//Only redirect if saving was successfull so we can grad an error message
		if ($blnResult) redirect($this->controller_path_plain.'Settings/'.$this->SID.'&amp;save');
		return;
	}
	
	public function display() {
		if ($this->in->exists('save')){
			$this->core->message( $this->user->lang('update_settings_success'),$this->user->lang('save_suc'), 'green');
		}
		
		$this->create_form();
		
		$userdata = array_merge($this->user->data, $this->user->data['privacy_settings'], $this->user->data['custom_fields']);

		// Output
		$this->form->output($userdata);

		$this->jquery->Tab_header('usersettings_tabs', true);
		$this->jquery->Dialog('template_preview', $this->user->lang('template_preview'), array('url'=>$this->controller_path.$this->SID."&style='+ $(\"select[name='style'] option:selected\").val()+'", 'width'=>'750', 'height'=>'520', 'modal'=>true));
		$this->tpl->assign_vars(array(
			'S_CURRENT_PASSWORD'			=> true,
			'S_NEW_PASSWORD'				=> true,
			'S_SETTING_ADMIN'				=> false,
			'S_MU_TABLE'					=> false,
			'USERNAME'						=> $this->user->data['username'],

			// Validation
			'AJAXEXTENSION_USER'			=> '&olduser='.$this->user->data['username'],
			'AJAXEXTENSION_MAIL'			=> '&oldmail='.urlencode($this->user->data['user_email']),
		));

		$this->set_vars(array(
			'page_title'	=> $this->user->lang('settings_title'),
			'template_file'	=> 'settings.html',
			'display'		=> true)
		);
	}
	
	public function create_form() {
		// initialize form class
		$this->form->lang_prefix = 'user_sett_';
		$this->form->use_tabs = true;
		$this->form->use_fieldsets = true;
		
		$settingsdata = user_core::get_settingsdata($this->user->id);
		// set username readonly
		if($this->config->get('disable_username_change')) {
			$settingsdata['registration_info']['registration_info']['username']['help'] =  'register_help_disabled_username';
			$settingsdata['registration_info']['registration_info']['username']['readonly'] = true;
		}
		// add delete-avatar link and set upload-type to user
		$settingsdata['profile']['user_avatar']['user_avatar']['imgup_type'] = 'user';
		$settingsdata['profile']['user_avatar']['user_avatar']['deletelink'] = 'settings.php'.$this->SID.'&mode=deleteavatar&link_hash='.$this->CSRFGetToken('mode');
		//Deactivate Profilefields synced by Bridge
		if ($this->config->get('cmsbridge_active') == 1 && (int)$this->config->get('cmsbridge_disable_sync') != 1) {
			$synced_fields = array('user_email', 'username', 'current_password', 'new_password', 'confirm_password');
			if ($this->bridge->get_sync_fields()){;
				$synced_fields = array_merge($synced_fields, $this->bridge->get_sync_fields());
			}
			foreach($synced_fields as $sync_field) {
				foreach($settingsdata as &$fieldsets) {
					foreach($fieldsets as &$fields) {
						if(isset($fields[$sync_field])) {
							$fields[$sync_field]['readonly'] = true;
							$fields[$sync_field]['help'] = 'user_sett_bridge_note';
						}
					}
				}
			}
		}
		
		$this->form->add_tabs($settingsdata);
		
		// add user-app-key 
		$this->form->add_field('exchange_key', array('lang' => 'user_app_key', 'text' => $this->user->data['exchange_key'].'<br /><button class="" type="submit" name="newexchangekey"><i class="fa fa-refresh"></i>'.$this->user->lang('user_create_new appkey').'</button>'), 'registration_info', 'registration_info');
		
		// add various auth-accounts
		$auth_options = $this->user->get_loginmethod_options();
		$auth_array = array();
		foreach($auth_options as $method => $options){
			if (isset($options['connect_accounts']) && $options['connect_accounts']){
				if (isset($this->user->data['auth_account'][$method]) && strlen($this->user->data['auth_account'][$method])){
					$display = $this->user->handle_login_functions('display_account', $method, array($this->user->data['auth_account'][$method]));
					if (is_array($display)) $display = $this->user->data['auth_account'][$method];
					$field_opts = array(
						'dir_lang'	=> ($this->user->lang('login_'.$method)) ? $this->user->lang('login_'.$method) : ucfirst($method),
						'text'		=> $display.' <a href="settings.php'.$this->SID.'&amp;mode=delauthacc&amp;lmethod='.$method.'&amp;link_hash='.$this->CSRFGetToken('mode').'"><i class="fa fa-trash-o fa-lg" title="{L_delte}"></i></a>',
						'help'		=> 'auth_accounts_help',
					);
				} else {
					$field_opts = array(
						'dir_lang'	=> ($this->user->lang('login_'.$method)) ? $this->user->lang('login_'.$method) : ucfirst($method),
						'text'		=> $this->user->handle_login_functions('account_button', $method),
						'help'		=> 'auth_accounts_help',
					);
				}
				$this->form->add_field('auth_account_'.$method, $field_opts, 'auth_accounts', 'registration_information');
			}
		}
		
		//Generate Plugin-Tabs - NYI
		/* TODO
		if (is_array($this->pm->get_menus('settings'))){
			foreach ($this->pm->get_menus('settings') as $plugin => $values){
				$name = ($values['name']) ? $values['name'] : $this->user->lang($plugin);

				$this->tpl->assign_block_vars('plugin_settings_row', array(
					'KEY'		=> $plugin,
					'PLUGIN'	=> $name,
					'ICON'		=> $this->core->icon_font((isset($values['icon'])) ? $values['icon'] : 'fa-puzzle-piece', 'fa-lg', $image_path),
				));
				unset($values['name'], $values['icon']);
				$this->tpl->assign_block_vars('plugin_usersettings_div', array(
					'KEY'		=> $plugin,
					'PLUGIN'	=> $name,
				));

				foreach ($values as $key=>$setting){
					$helpstring = ($this->user->lang(@$setting['help'])) ? $this->user->lang(@$setting['help']) : @$setting['help'];
					$help = (isset($setting['help'])) ? " ".$helpstring : '';
					$setting['value']	= $setting['selected'] = @$this->user->data['plugin_settings'][$plugin][$setting['name']];
					$setting['name'] = $plugin.'['.((isset($setting['name'])) ? $setting['name'] : '').']';
					$setting['plugin'] = $plugin;
					$this->tpl->assign_block_vars('plugin_usersettings_div.plugin_usersettings', array(
						'NAME'	=> $this->user->lang($setting['language']),
						'FIELD'	=> $this->html->widget($setting),
						'HELP'	=> $help,
						'S_TH'	=> ($setting['type'] == 'tablehead') ? true : false,
					));
				}
			}
		}*/
	}

}

?>