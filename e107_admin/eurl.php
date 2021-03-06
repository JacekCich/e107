<?php
/*
 * e107 website system
 *
 * Copyright (C) e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * URL and front controller Management
 *
 * $URL$
 * $Id$
*/

require_once('../class2.php');
if (!ADMIN || !getperms('L'))
{
	header('location:'.e_BASE.'index.php');
	exit;
}

e107::coreLan('eurl', true);
// TODO - admin interface support, remove it from globals
$e_sub_cat = 'eurl';


class eurl_admin extends e_admin_dispatcher
{
	protected $modes = array(
		'main' => array(
			'controller' 	=> 'eurl_admin_ui',
			'path' 			=> null,
			'ui' 			=> 'eurl_admin_form_ui',
			'uipath' 		=> null
		)
	);

	protected $adminMenu = array(
		'main/config'		=> array('caption'=> LAN_EURL_MENU_CONFIG, 'perm' => 'L'),
		'main/alias' 		=> array('caption'=> LAN_EURL_MENU_ALIASES, 'perm' => 'L'),
		'main/settings' 	=> array('caption'=> LAN_EURL_MENU_SETTINGS, 'perm' => 'L'),
		'main/simple' 		=> array('caption'=> LAN_EURL_MENU_REDIRECTS, 'perm' => 'L'),
	//	'main/help' 		=> array('caption'=> LAN_EURL_MENU_HELP, 'perm' => 'L'),
	);

	protected $adminMenuAliases = array();
	
	protected $defaultAction = 'config';

	protected $menuTitle = LAN_EURL_MENU;
}

class eurl_admin_ui extends e_admin_controller_ui
{
	public $api;
	
	protected $prefs = array(
		'url_disable_pathinfo'	=> array('title'=>LAN_EURL_SETTINGS_PATHINFO,	'type'=>'boolean', 'help'=>LAN_EURL_MODREWR_DESCR),
		'url_main_module'	=> array('title'=>LAN_EURL_SETTINGS_MAINMODULE,	'type'=>'dropdown', 'data' => 'string', 'help'=>LAN_EURL_SETTINGS_MAINMODULE_HELP),
		'url_error_redirect'	=> array('title'=>LAN_EURL_SETTINGS_REDIRECT,	'type'=>'boolean', 'help'=>LAN_EURL_SETTINGS_REDIRECT_HELP),
		'url_sef_translate'	=> array('title'=>LAN_EURL_SETTINGS_SEFTRANSLATE,	'type'=>'dropdown', 'data' => 'string', 'help'=>LAN_EURL_SETTINGS_SEFTRANSLATE_HELP),
	);
	
	public function init()
	{


		$htaccess = file_exists(e_BASE.".htaccess");

		if(function_exists('apache_get_modules'))
		{
			$modules = apache_get_modules();
			$modRewrite = in_array('mod_rewrite', $modules );
		}
		else
		{
			$modRewrite = true; //we don't really know.

		}

		if($modRewrite === false)
		{
			e107::getMessage()->addInfo("Apache mod_rewrite was not found on this server and is required to use this feature. ");
			e107::getMessage()->addDebug(print_a($modules,true));

		}

		if($htaccess && $modRewrite && !deftrue('e_MOD_REWRITE'))
		{
			e107::getMessage()->addInfo("Mod-rewrite is disabled. To enable, please add the following line to your <b>e107_config.php</b> file:<br /><pre>define('e_MOD_REWRITE',true);</pre>");
		}
	
		if(is_array($_POST['rebuild']))
		{
			$table = key($_POST['rebuild']);
			list($primary, $input, $output) = explode("::",$_POST['rebuild'][$table]);
			$this->rebuild($table, $primary, $input, $output);	
		}
		
		
		$this->api = e107::getInstance();
		$this->addTitle(LAN_EURL_NAME);
		
		if($this->getAction() != 'settings') return;
		
	
		

	}
	
	/**
	 * Rebuild SEF Urls for a particular table
	 * @param $table
	 * @param primary field id. 
	 * @param input field (title)
	 * @param output field (sef)
	 */
	private function rebuild($table, $primary, $input,$output)
	{
		if(empty($table) || empty($input) || empty($output) || empty($primary))
		{
			e107::getMessage()->addError("Missing Generator data");	
			return;
		}
		
		$sql = e107::getDb();
		
		$data = $sql->retrieve($table, $primary.",".$input, $input ." != '' ", true);
		
		$success = 0;
		$failed = 0;
		
		foreach($data as $row)
		{
			$sef = eHelper::title2sef($row[$input]);
			
			if($sql->update($table, $output ." = '".$sef."' WHERE ".$primary. " = ".intval($row[$primary]). " LIMIT 1")!==false)
			{
				$success++;
			}
			else
			{
				$failed++;
			}
			
			// echo $row[$input]." => ".$output ." = '".$sef."'  WHERE ".$primary. " = ".intval($row[$primary]). " LIMIT 1 <br />";

		}
			
		if($success)
		{
			e107::getMessage()->addSuccess($success." SEF URLs were updated.");
		}
		
		if($failed)
		{
			e107::getMessage()->addError($failed." SEF URLs were NOT updated.");	
		}
		
		
	}
	
	
	
	
	public function HelpObserver()
	{
		
	}
	
	public function HelpPage()
	{
		$this->addTitle(LAN_EURL_NAME_HELP);
		return LAN_EURL_UC;
	}

	//TODO Checkbox for each plugin to enable/disable
	public function simplePage()
	{
		// $this->addTitle("Simple Redirects");
		$eUrl =e107::getAddonConfig('e_url');
		
		if(empty($eUrl))
		{
			return; 		
		}


		$text = "";
			
		foreach($eUrl as $plug=>$val)
		{
			$text .= "<h5>".$plug."</h5>";
			$text .= "<table class='table table-striped table-bordered'>";
			$text .= "<tr><th>Key</th><th>Regular Expression</th>
			<th>".LAN_URL."</th>
			</tr>";
			
			foreach($val as $k=>$v)
			{
					$text .= "<tr><td style='width:20%'>".$k."</td><td style='width:40%'>".$v['regex']."</td><td style='width:40%'>".$v['redirect']."</td></tr>";
			}
		
					
			$text .= "</table>";
		}	
		
		return $text;		
	}
		
	
	public function SettingsObserver()
	{
		// main module pref dropdown
		$this->prefs['url_main_module']['writeParms'][''] = 'None';
		$modules = e107::getPref('url_config', array());
		ksort($modules);
		foreach ($modules as $module => $location) 
		{
			$labels = array();
			$obj = eDispatcher::getConfigObject($module, $location); 
			if(!$obj) continue;
			$config = $obj->config();
			if(!$config || !vartrue($config['config']['allowMain'])) continue;
			$admin = $obj->admin();
			$labels = vartrue($admin['labels'], array());
			
			
			$this->prefs['url_main_module']['writeParms'][$module] = vartrue($section['name'], eHelper::labelize($module));
		}
		
		// title2sef transform type pref  
		$types = explode('|', 'none|dashl|dashc|dash|underscorel|underscorec|underscore|plusl|plusc|plus');
		$this->prefs['url_sef_translate']['writeParms'] = array();
		foreach ($types as $type) 
		{
			$this->prefs['url_sef_translate']['writeParms'][$type] = deftrue('LAN_EURL_SETTINGS_SEFTRTYPE_'.strtoupper($type), ucfirst($type));
		}
		
		if(isset($_POST['etrigger_save']))
		{
			$this->getConfig()
						->setPostedData($this->getPosted(), null, false, false)
						//->setPosted('not_existing_pref_test', 1)
						->save(true);
		
			$this->getConfig()->setMessages();
		}
	}
	
	public function SettingsPage()
	{
		$this->addTitle(LAN_EURL_NAME_SETTINGS);
		return $this->getUI()->urlSettings();
	}
	
	public function AliasObserver()
	{
		if(isset($_POST['update']))
		{
			$posted = is_array($_POST['eurl_aliases']) ? e107::getParser()->post_toForm($_POST['eurl_aliases']) : '';
			$locations = array_keys(e107::getPref('url_locations', array()));
			$aliases = array();
			$message = e107::getMessage();
			
			foreach ($posted as $lan => $als) 
			{
				foreach ($als as $module => $alias) 
				{
					$alias = trim($alias);
					$module = trim($module);
					if($module !== $alias) 
					{
						$cindex = array_search($module, $locations);
						$sarray = $locations;
						unset($sarray[$cindex]);
						
						if(!in_array(strtolower($alias), $sarray)) $aliases[$lan][$alias] = $module;
						else $message->addError(sprintf(LAN_EURL_ERR_ALIAS_MODULE, $alias, $module));
					}
				}
			}
			e107::getConfig()->set('url_aliases', e107::getParser()->post_toForm($aliases))->save(false);
		}
	}
	
	public function AliasPage()
	{
		$this->addTitle(LAN_EURL_NAME_ALIASES);
		
		$aliases = e107::getPref('url_aliases', array());
		
		$form = $this->getUI();
		$text = "
			<form action='".e_SELF."?mode=main&action=alias' method='post' id='urlconfig-form'>
				<fieldset id='core-eurl-core'>
					<legend>".LAN_EURL_LEGEND_ALIASES."</legend>
					<table class='table adminlist'>
						<colgroup>
							<col class='col-label' />
							<col class='col-control' />
						</colgroup>
						<tbody>
		";
		
		$text .= $this->renderAliases($aliases);
		
		$text .= "
						</tbody>
					</table>
					<div class='buttons-bar center'>
						".$form->admin_button('update', LAN_UPDATE, 'update')."
					</div>
				</fieldset>
			</form>
		";
		
		return $text;
	}
	
	public function ConfigObserver()
	{
		if(isset($_POST['update']))
		{
			$config = is_array($_POST['eurl_config']) ? e107::getParser()->post_toForm($_POST['eurl_config']) : '';
			$modules = eRouter::adminReadModules();
			$locations = eRouter::adminBuildLocations($modules);
			
			$aliases = eRouter::adminSyncAliases(e107::getPref('url_aliases'), $config);
			
			e107::getConfig()
				->set('url_aliases', $aliases)
				->set('url_config', $config)
				->set('url_modules', $modules)
				->set('url_locations', $locations)
				->save();
				
			eRouter::clearCache();
		}
	}
	
	public function ConfigPage()
	{
		$this->addTitle(LAN_EURL_NAME_CONFIG);
		$active = e107::getPref('url_config');

		$set = array();
		// all available URL modules
		$set['url_modules'] = eRouter::adminReadModules();
		// set by user URL config locations
		$set['url_config'] = eRouter::adminBuildConfig($active, $set['url_modules']);
		// all available URL config locations
		$set['url_locations'] = eRouter::adminBuildLocations($set['url_modules']);
		
		$form = $this->getUI();
		$text = "
			<form action='".e_SELF."?mode=main&action=config' method='post' id='urlconfig-form'>
				<fieldset id='core-eurl-core'>
					<legend>".LAN_EURL_LEGEND_CONFIG."</legend>
					<table class='table adminlist'>
						<colgroup>
							<col class='col-label' style='width:20%' />
							<col class='col-control' style='width:60%' />
							<col style='width:20%' />
						</colgroup>
						<thead>
						  <tr>
						      <th>".LAN_TYPE."</th>
						      <th>".LAN_URL."</th>
						      <th>".LAN_OPTIONS."</th>
						  </tr>
						</thead>
						
						
						<tbody>
		";
		
		$text .= $this->renderConfig($set['url_config'], $set['url_locations']);
		
		$text .= "
						</tbody>
					</table>
					<div class='buttons-bar center'>
						".$form->admin_button('update', LAN_UPDATE, 'update')."
					</div>
				</fieldset>
			</form>
		";
		
		return $text;
	}

	public function renderConfig($current, $locations)
	{

		$ret = array();
		$url = e107::getUrl();
		
		
		ksort($locations);
		foreach ($locations as $module => $l) 
		{
			$data = new e_vars(array(
				'current' => $current,
			));
			$obj = eDispatcher::getConfigObject($module, $l[0]);
			if(null === $obj) $obj = new eurlAdminEmptyConfig;

			$data->module = $module;
			$data->locations = $l;
			$data->defaultLocation = $l[0];
			$data->config = $obj;
			
			$ret[] = $data;
		}
		
		return $this->getUI()->moduleRows($ret);
	}
	

	public function renderAliases($aliases)
	{

		$ret = array();
		$lans = array();
		
		$lng = e107::getLanguage();
		$lanList = $lng->installed();
		sort($lanList);
		
		$lanDef = e107::getPref('sitelanguage') ? e107::getPref('sitelanguage') : e_LANGUAGE;
		$lanDef = array($lng->convert($lanDef), $lanDef);
		
		foreach ($lanList as $index => $lan) 
		{
			$lanCode = $lng->convert($lan);
			if($lanDef[0] == $lanCode) continue;
			$lans[$lanCode] = $lan;
		}
		
		$modules = e107::getPref('url_config');
		if(!$modules)
		{
			$modules = array();
			e107::getConfig()->set('url_aliases', array())->save(false);
			// do not output message
			e107::getMessage()->reset(false, 'default');
		}
		
		foreach ($modules as $module => $location) 
		{
			$data = new e_vars();
			$obj = eDispatcher::getConfigObject($module, $location);
			if(null === $obj) $obj = new eurlAdminEmptyConfig;

			$data->module = $module;
			$data->location = $location;
			$data->config = $obj;
			$modules[$module] = $data;
		}
		
		return $this->getUI()->aliasesRows($aliases, $modules, $lanDef, $lans);
	}
	
	
	/**
	 * Set extended (UI) Form instance
	 * @return e_admin_ui
	 */
	public function _setUI()
	{
		$this->_ui = $this->getParam('ui');
		$this->setParam('ui', null);
		
		return $this;
	}
	
	/**
	 * Set Config object
	 * @return e_admin_ui
	 */
	protected function _setConfig()
	{
		$this->_pref = e107::getConfig();

		$dataFields = $validateRules = array();
		foreach ($this->prefs as $key => $att)
		{
			// create dataFields array
			$dataFields[$key] = vartrue($att['data'], 'string');

			// create validation array
			if(vartrue($att['validate']))
			{
				$validateRules[$key] = array((true === $att['validate'] ? 'required' : $att['validate']), varset($att['rule']), $att['title'], varset($att['error'], $att['help']));
			}
			/* Not implemented in e_model yet
			elseif(vartrue($att['check']))
			{
				$validateRules[$key] = array($att['check'], varset($att['rule']), $att['title'], varset($att['error'], $att['help']));
			}*/
		}
		$this->_pref->setDataFields($dataFields)->setValidationRules($validateRules);

		return $this;
	}
}

class eurl_admin_form_ui extends e_admin_form_ui
{
	public function urlSettings()
	{
		return $this->getSettings();
	}
    
    
    
    public function moreInfo($title,$info)
    {
        $tp = e107::getParser();
       
        $id = 'eurl_'.$this->name2id($title);
        
        $text .= "<a data-toggle='modal' href='#".$id."' data-cache='false' data-target='#".$id."' class='e-tip' title='".LAN_MOREINFO."'>";
        $text .= $title;  
        $text .= '</a>';
        
        $text .= '

         <div id="'.$id.'" class="modal hide fade" tabindex="-1" role="dialog"  aria-hidden="true">
                <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
               <h4>'.$tp->toHtml($title,false,'TITLE').'</h4>
                </div>
                <div class="modal-body">
                <p>';
        
        $text .= $info;
       
                
        $text .= '</p>
                </div>
                <div class="modal-footer">
                <a href="#" data-dismiss="modal" class="btn btn-primary">Close</a>
                </div>
                </div>';           
        
        return $text;
        
    }
    
    
    
    
	
	public function moduleRows($data)
	{
		$text = '';
		$tp = e107::getParser();
		$frm = e107::getForm();
		
		if(empty($data))
		{
			return "
				<tr>
					<td colspan='2'>".LAN_EURL_EMPTY."</td>
				</tr>
			";
		}
		
        $PLUGINS_DIRECTORY = e107::getFolder("PLUGINS");
        $srch = array("{SITEURL}","{e_PLUGIN_ABS}");
        $repl = array(SITEURL,SITEURL.$PLUGINS_DIRECTORY);
        
		foreach ($data as $obj) 
		{
			$admin 		= $obj->config->admin();
			$section 	= vartrue($admin['labels'], array());
            $rowspan 	= count($obj->locations)+1;
            $module 	= $obj->module;
			$generate 	= vartrue($admin['generate'], array());
           
          /*
			$info .= "
                <tr>
                    <td rowspan='$rowspan'><a class='e-tip' style='display:block' title='".LAN_EURL_LOCATION.$path."'>
                    ".vartrue($section['name'], eHelper::labelize($obj->module))."
                    </a></td>
               </tr>
            ";
          */
            $opt = "";   
			$info = "<table class='table table-striped'>";
            
			foreach ($obj->locations as $index => $location) 
			{
				$objSub = $obj->defaultLocation != $location ? eDispatcher::getConfigObject($obj->module, $location) : false; 
				if($objSub) 
				{
					$admin = $objSub->admin();
					$section = vartrue($admin['labels'], array());
				} 
				elseif($obj->defaultLocation != $location) $section = array();
				
				$id = 'eurl-'.str_replace('_', '-', $obj->module).'-'.$index;
				
				$checked = varset($obj->current[$module]) == $location ? ' checked="checked"' : '';
				
				$path = eDispatcher::getConfigPath($module, $location, false);
				if(!is_readable($path))
				{
				    $path = str_replace('/url.php', '/', $tp->replaceConstants(eDispatcher::getConfigPath($module, $location, true), true)).' <em>('.LAN_EURL_LOCATION_NONE.')</em>';
                    $diz = LAN_EURL_DEFAULT;
                }
				else
				{
				    $path = $tp->replaceConstants(eDispatcher::getConfigPath($module, $location, true), true);
                    $diz  = (basename($path) != 'url.php' ) ? LAN_EURL_FRIENDLY : LAN_EURL_DEFAULT;
				}
				    

				$label = vartrue($section['label'], $index == 0 ? LAN_EURL_DEFAULT : eHelper::labelize(ltrim(strstr($location, '/'), '/')));
				$cssClass = $checked ? 'e-showme' : 'e-hideme';
				$cssClass = 'e-hideme'; // always hidden for now, some interface changes could come after pre-alpha

				 $exampleUrl = array();
				 if(!empty($section['examples']))
				 {
	                foreach($section['examples'] as $ex)
	                {
	                    $exampleUrl[] = str_replace($srch,$repl,$ex);

	                }
				 }
                 if(strpos($path,'noid')!==false)
                {
               //     $exampleUrl .= "  &nbsp; &Dagger;";    //XXX Add footer - denotes more CPU required. ?
                }
                
                $selected = varset($obj->current[$module]) == $location ? "selected='selected'" : '';
				$opt .= "<option value='{$location}' {$selected} >".$diz.": ".$exampleUrl[0]."</option>";

				$info .= "<tr><td>".$label."
					
					</td>
					<td><strong>".LAN_EURL_LOCATION."</strong>: ".$path."
                    <p>".vartrue($section['description'], LAN_EURL_PROFILE_INFO)."</p><small>".implode("<br />", $exampleUrl)."</small></td>
                    
                    
                    
                    </tr>
				";

			}

			$info .= "</table>";

			$title = vartrue($section['name'], eHelper::labelize($obj->module));
			
			$text .= "
                <tr>
                    <td>".$this->moreInfo($title, $info)."</td>
                    <td><select name='eurl_config[$module]' class='input-block-level'>".$opt."</select></td>
                    <td>";
		
			$bTable = ($admin['generate']['table']);
			$bInput = $admin['generate']['input'];
			$bOutput = $admin['generate']['output'];
			$bPrimary = $admin['generate']['primary'];
			
		
			$text .= (is_array($admin['generate'])) ? $frm->admin_button('rebuild['.$bTable.']', $bPrimary."::".$bInput."::".$bOutput,'delete',"Rebuild") : "";	  
				  

			$text .= "</td>
               </tr>";
		}

		
		
		
		
		
		
		
		
		/*
		For Miro - intuitive interface example. All configs are contained within one e_url.php file. 
		Root namespacing automatically calculated based on selection. 
		ie. choosing option 1 below will set root namespacing for news. 
		Known bug (example): 
		  News title: Nothing's Gonna Change my World!
		  Currently becomes: /Nothing%26%23039%3Bs%20Gonna%20Change%20my%20World%21
		 Should become: /nothings-gonna-change-my-world
		 Good SEF reference: http://davidwalsh.name/generate-search-engine-friendly-urls-php-function
		 
		 [Miro] Solution comes from the module itself, not related with URL assembling in anyway (as per latest Skype discussion)
		 */

		
		// Global On/Off Switch Example
		// [Miro] there is no reason of switch, everything could go through single entry point at any time, without a need of .htaccess (path info)
		// Control is coming per configuration file.
		$example = "
		<tr><td>Enable Search-Engine-Friendly URLs</td>
		<td><input type='checkbox' name='SEF-active' value='1' />
		</td></tr>";
		
		//Entry Example (Hidden unless the above global switch is active)
		$example .= "
		
		<tr><td>News</td>
					<td style='padding:0px'>
					<table style='width:600px;margin-left:0px'>
					<tr>
						<td><input type='radio' class='radio' name='example' />Default</td><td>/news.php?item.1</td>
					</tr>
					<tr>
						<td><input type='radio' class='radio' name='example' />News Namespace and News Title</td><td>/news/news-item-title</td>
					</tr>
					<tr>
						<td><input type='radio' class='radio' name='example' />Year and News Title</td><td>/2011/news-item-title</td>
					</tr>
					<tr>
						<td><input type='radio' class='radio' name='example' />Year/Month and News Title</td><td>/2011/08/news-item-title</td>
					</tr>
					<tr>
						<td><input type='radio' class='radio' name='example' />Year/Month/Day and News Title</td><td>/2011/08/27/news-item-title</td>
					</tr>
					<tr>
						<td><input type='radio' class='radio' name='example' />News Category and News Title</td><td>/news-category/news-item-title</td>
					</tr>
					";
					
			// For 0.8 Beta 
			$example .= "
					<tr>
						<td><input type='radio' class='radio' name='example' />Custom</td><td><input class='tbox' type='text' name='custom-news' value='' /></td>
						</tr>";
		
			$example .= "</table>";
					
		$example .= "</td>
					</tr>";
					

		return $text;
		
	}

	public function aliasesRows($currentAliases, $modules, $lanDef, $lans)
	{
		if(empty($modules))
		{
			return "
				<tr>
					<td colspan='3'>".LAN_EURL_EMPTY."</td>
				</tr>
			";
		}
		
		$text = '';
		$tp = e107::getParser();
		
		foreach ($modules as $module => $obj) 
		{
			$cfg = $obj->config->config();
			if(isset($cfg['config']['noSingleEntry']) && $cfg['config']['noSingleEntry']) continue;
			
			if($module == 'index')
			{
			$text .= "
				<tr>
					<td>
						".LAN_EURL_CORE_INDEX."
					</td>
					<td>
						".LAN_EURL_CORE_INDEX_INFO."
					</td>
					<td>
						".LAN_EURL_FORM_HELP_EXAMPLE.":<br /><strong>".e107::getUrl()->create('/', '', array('full' => 1))."</strong>
					</td>
				</tr>
				";
				continue;
			}
			$help = array();
			$admin = $obj->config->admin();
			$lan = $lanDef[0];
			$url = e107::getUrl()->create($module, '', array('full' => 1, 'encode' => 0));
			$defVal = isset($currentAliases[$lan]) && in_array($module, $currentAliases[$lan]) ? array_search($module, $currentAliases[$lan]) : $module; 
			$section = vartrue($admin['labels'], array());
			
			$text .= "
				<tr>
					<td>
						".vartrue($section['name'], ucfirst(str_replace('_', ' ', $obj->module)))."
						<div class='label-note'>
						".LAN_EURL_FORM_HELP_ALIAS_0." <strong>{$module}</strong><br />
						</div>
					</td>
					<td>
			";
			
			
			
			// default language		
			$text .= $this->text('eurl_aliases['.$lanDef[0].']['.$module.']', $defVal).' ['.$lanDef[1].']'.$this->help(LAN_EURL_FORM_HELP_DEFAULT);
			$help[] = '['.$lanDef[1].'] '.LAN_EURL_FORM_HELP_EXAMPLE.':<br /><strong>'.$url.'</strong>';
			
			if($lans)
			{
				foreach ($lans as $code => $lan) 
				{

					$url = e107::getUrl()->create($module, '', array('lan' => $code, 'full' => 1, 'encode' => 0)); 
					$defVal = isset($currentAliases[$code]) && in_array($module, $currentAliases[$code]) ? array_search($module, $currentAliases[$code]) : $module; 
					$text .= "<div class='spacer'><!-- --></div>";
					$text .= $this->text('eurl_aliases['.$code.']['.$module.']', $defVal).' ['.$lan.']'.$this->help(LAN_EURL_FORM_HELP_ALIAS_1.' <strong>'.$lan.'</strong>');
					$help[] = '['.$lan.'] '.LAN_EURL_FORM_HELP_EXAMPLE.':<br /><strong>'.$url.'</strong>';
				}
			}
			
			if(e107::getUrl()->router()->isMainModule($module))
			{
				$help = array(LAN_EURL_CORE_MAIN);
			}
			
			$text .= "
					</td>
					<td>
						".implode("<div class='spacer'><!-- --></div>", $help)."
					</td>
				</tr>
			";
		}

		return $text;
	}
}

class eurlAdminEmptyConfig extends eUrlConfig
{
	public function config()
	{
		return array();
	}
}

new eurl_admin();

require_once(e_ADMIN.'auth.php');

e107::getAdminUI()->runPage();

require_once(e_ADMIN.'footer.php');


