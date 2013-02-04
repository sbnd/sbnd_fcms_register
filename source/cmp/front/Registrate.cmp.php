<?php
/**
* SBND F&CMS - Framework & CMS for PHP developers
*
* Copyright (C) 1999 - 2013, SBND Technologies Ltd, Sofia, info@sbnd.net, http://sbnd.net
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @author SBND Techologies Ltd <info@sbnd.net>
* @package cms.cmp.registrate
* @version 1.1
*/
BASIC::init()->imported("Profiles.cmp", "cms/controlers/back");
class Registrate extends Profiles {

	public $regist_user_level 	= -2;
	public $use_auto_login 		= true;
	public $go_to_after_reg 	= '';
	public $use_free_password 	= false;
	public $use_auto_active 	= true;
	public $parm_fields			= array('name', 'email', 'password', 'confirm_password');
	
	public $capcha_settings = array(
		'ttf:alger.ttf',
		'width:110',
		'height:30',
		'lenght:6',
		'mode:2',
		'mime:png',
		'text_size:17',
		'bg_color:#F1F1F1',
		'text_color:#6F6F6F',
		'line_color:#D7D7D7',
		'noise_color:#D7D7D7',
		'num_lines:5',
		'noise_level:3',
	);
	/**
	 * Main function - the constructor of the component
	 * @access public
	 * @see CmsComponent::main()
	 */
	function main(){
		parent::main();
		
		if($parent = Builder::init()->getRegisterComponent('profiles')){
			foreach ($parent->cmp_settings as $k => $v){
				$this->$k = $v;
			}	
		}
		
		$this->unsetField(BASIC_USERS::init()->level_column);
		$this->unsetField(BASIC_USERS::init()->perm_column);
		$this->unsetField("language");
		$this->unsetField("page_max_rows");
		
		$this->updateField(BASIC_USERS::init()->pass_column, array(
			'messages' => array(
        		2 => BASIC_LANGUAGE::init()->get('too_short_password'),
        		3 => BASIC_LANGUAGE::init()->get('not_exist_number'),
        		4 => BASIC_LANGUAGE::init()->get('not_exist_upper_case'),
        		5 => BASIC_LANGUAGE::init()->get('not_exist_lower_case')
        	)
		));
		$this->setField('reg_code', array(
			'text' => BASIC_LANGUAGE::init()->get('contact_user_capcha'),
			'formtype' => 'capcha',
			'dbtype' => 'none',
			'perm' => '*',
			'attributes' => array(
		
			),
			'messages' => array(
				2 => BASIC_LANGUAGE::init()->get('invalid_sec_code')
			)
		));
		
		foreach ($this->fields as $k => $v){
			$this->updateField($k, array(
				'perm' => ''
			));
		}
		
		$this->delAction('edit');
		$this->delAction('delete');
		$this->delAction('cancel');
		
		$this->updateAction('list', 'ActionFormAdd');
		$this->updateAction('save', 'ActionAdd');
		
		$this->errorAction = 'add';
	}
	/**
	 * Call actions and return generated from component html
	 * @access public
	 * @return string
	 */
	function startPanel(){
		if(BASIC_USERS::init()->checked()){
			BASIC_URL::init()->redirect('/');
		}	
		$this->startManager();
		
		if($this->regist_user_level == -1){
			Builder::init()->registerComponent('profiles-types', array(
				'class' => 'ProfilesTypes',
				'folder' => 'cms/controlers/back'
			));
			$res = Builder::init()->build('profiles-types')->read(" ORDER BY `id` DESC LIMIT 1 ")->read();
			$this->regist_user_level = (int)$res['id'];
		}		
		return $this->createInterface();
	}
	/**
	 * Generate html form for action Add
	 * @access public
	 * @return string
	 */
	function ActionFormAdd(){
		$this->updateField('countries', array(
			'attributes' => array(
				'data' => $this->getCountries()
			)
		));
		
		foreach($this->parm_fields as $name){
			$this->updateField($name, array(
				'perm' => '*'
			));
		}
		return $this->FORM_MANAGER();
	}
	/**
	 * Add new record in db, used action Save
	 * @access public
	 * @return integer
	 */
	function ActionAdd(){
		if($id = parent::ActionAdd()){
			
			if($this->use_auto_login){
				BASIC_USERS::init()->autoLogin($id);
			}
			if($this->go_to_after_reg){
				BASIC_URL::init()->redirect(BASIC_URL::init()->link("/".
					$page = Builder::init()->pagesControler->getPageTreeByName($this->go_to_after_reg)
				));
			}else{
				BASIC_URL::init()->link("/");
			}
		}
		return $id;
	}
	/**
	 * Method called before save, its name is saved in parent specialTest property
	 * 
	 * @access public
	 * @return void
	 */
	function validator(){
		$rerr = false;	
		if(!$this->use_free_password){ 
		
			if($err = BASIC_USERS::passwordValidator($this->getDataBuffer(BASIC_USERS::init()->pass_column))){
				$rerr = $this->setMessage(BASIC_USERS::init()->pass_column, $err);
			}
		}
		if(strtolower($this->getDataBuffer('reg_code')) != strtolower(BASIC_GENERATOR::init()->getControl('capcha')->code('reg_code'))){
			$rerr = $this->setMessage('reg_code', 2);
		}
		$rerr = parent::validator();
		if(!$rerr){
			$this->setDataBuffer(BASIC_USERS::init()->level_column, $this->regist_user_level);
			$this->setDataBuffer(BASIC_USERS::init()->perm_column, $this->use_auto_active ? 1 : 0);
		}
		
		return $rerr;
	}
	/**
	 * Define module settings fields, which values will override value of class properties
	 * @access public
	 * @return hashmap
	 */
	function settingsData(){
		return array(
			'template_form' 	=> $this->template_form,
			'capcha_settings' 	=> $this->capcha_settings,
			'regist_user_level' => $this->regist_user_level,
			'use_auto_login' 	=> $this->use_auto_login,
			'go_to_after_reg' 	=> $this->go_to_after_reg,
			'use_free_password' => $this->use_free_password,
			'use_auto_active' 	=> $this->use_auto_active,
			'parm_fields'		=> $this->parm_fields
		);
	}
	/**
	 * Module settings fields description 
	 * @access public
	 * @return value
	 */
	function settingsUI(){
		Builder::init()->build('profiles-types')->read(" ORDER BY `id` ");
		
		$fields = array();
		foreach($this->fields as $k => $v){
			$fields[$k] = $v[4];
		}
		
		return array(
			'template_form' => array(
				'text' => BASIC_LANGUAGE::init()->get('template_form')
			),
			'capcha_settings' => array(
				'text' => BASIC_LANGUAGE::init()->get('capcha_settings'),
				'formtype' => 'selectmanage',
				'attributes' => array(
					'data' => array(
						BASIC_LANGUAGE::init()->get('variable'),
						BASIC_LANGUAGE::init()->get('value')
					),
				)
			),
			'use_auto_login' => array(
				'text' => BASIC_LANGUAGE::init()->get('use_auto_login'),
				'formtype' => 'radio',
				'attributes' => array(
					'data' => array(
						BASIC_LANGUAGE::init()->get('no'),
						BASIC_LANGUAGE::init()->get('yes')
					)
				)
			),
			'use_auto_active' => array(
				'text' => BASIC_LANGUAGE::init()->get('use_auto_active'),
				'formtype' => 'radio',
				'attributes' => array(
					'data' => array(
						BASIC_LANGUAGE::init()->get('no'),
						BASIC_LANGUAGE::init()->get('yes')
					)
				)
			),
			'use_free_password' => array(
				'text' => BASIC_LANGUAGE::init()->get('use_free_password'),
				'formtype' => 'radio',
				'attributes' => array(
					'data' => array(
						BASIC_LANGUAGE::init()->get('no'),
						BASIC_LANGUAGE::init()->get('yes')
					)
				)
			),
			'regist_user_level' => array(
				'text' => BASIC_LANGUAGE::init()->get('regist_user_level'),
				'formtype' => 'select',
				'attributes' => array(
					'data' => Builder::init()->build('profiles-types')->read(" ORDER BY `id` ")->getSelectData()
				)
			),
			'go_to_after_reg' => array(
				'text' => BASIC_LANGUAGE::init()->get('go_to_after_reg'),
				'formtype' => 'select',
				'attributes' => array(
					'data' => Builder::init()->build('contents')->getSelTree('',0, 'name', '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', null)
				)
			),
			'parm_fields' => array(
				'text' => BASIC_LANGUAGE::init()->get('parm_fields'),
				'formtype' => 'selectmove',
				'attributes' => array(
					'data' => $fields
				)
			)
		);
	}	
}