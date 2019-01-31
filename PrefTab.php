<?php

class Feediron_PrefTab{
	public static function get_pref_tab($json_conf, $test_conf){
		$tab = '';
		$rm = new RecipeManager();
		$rm->loadAvailableRecipes();

		$tab .= '<div data-dojo-type="dijit/layout/TabContainer" style="width: 100%; height:100%">';
		/* Config tab */
		$tab .= '<div data-dojo-type="dijit/layout/ContentPane" title="Configuration" data-dojo-props="selected:true" id="config">';
		$tab .= '<h3>Configuration</h3>';
		$tab .= '<a href="https://github.com/m42e/ttrss_plugin-feediron/blob/master/README.md">Configuration help</a>';
		$tab .= self::get_form_start('save');
		$tab .= self::get_script('transport.responseJSON.message','dojo.query("#json_conf").attr("value",transport.responseJSON.json_conf); dojo.query("#json_error").attr("innerHTML", "").attr("class",""); ','dojo.query("#json_error").attr("innerHTML", transport.responseJSON.json_error).attr("class","error");');

		$tab .= '<textarea dojoType="dijit.form.SimpleTextarea" class="dijitTextBox dijitTextArea" id="json_conf" name="json_conf" style="font-size: 12px; width: 99%; height: 400px;">'.$json_conf.'</textarea>';

		$tab .= '<p /><button dojoType="dijit.form.Button" type="submit">'.__("Save").'</button>';
		$tab .= '</form>';


		$tab .= '<h3>Add predefined rules</h3>';
		$tab .= self::get_form_start('add');
		$tab .= self::get_script('transport.responseJSON.message','dojo.query("#json_conf").attr("value",transport.responseJSON.json_conf); ');

		$tab .= '<label for="addrecipe">'.__("Add recipe").': </label>';
		$tab .= '<select dojoType="dijit.form.Select" class="dijit dijitReset dijitInline dijitLeft dijitDownArrowButton dijitSelect dijitValidationTextBox" name="addrecipe">';
		foreach($rm->getRecipes() as $key => $recipe){
			$tab .= '<option value="'.$recipe.'">'.$key.'</option>';
		}
		$tab .= '</select>&nbsp;';
		$tab .= '<button dojoType="dijit.form.Button" type="submit">'.__("Add").'</button>';
		$tab .= '</form><p /><div id="json_error"></div><br />';
		$tab .= __("Save after adding config!").'<br />';

		$tab .= '<h3>Export rules</h3>';
		$tab .= self::get_form_start('export');
		$tab .= self::get_script('transport.responseJSON.message','console.log(transport); dojo.query("#json_export").attr("innerHTML",transport.responseJSON.json_export);
		dojo.query("#json_export_wrapper").attr("class","notice");');

		$tab .= '<label for="recipe">'.__("Export").': </label>';
		$tab .= '<select dojoType="dijit.form.Select" class="dijit dijitReset dijitInline dijitLeft dijitDownArrowButton dijitSelect dijitValidationTextBox" name="recipe">';
		foreach(json_decode($json_conf,true) as $key => $config){
			if($key != 'debug'){
				$tab .= '<option value="'.$key.'">'.(isset($config['name'])?$config['name']:$key).'</option>';
			}
		}
		$tab .= '</select>&nbsp;';
		$tab .= '<button dojoType="dijit.form.Button" type="submit">'.__("Export").'</button>';
		$tab .= '</form><p />';
		$tab .= '<div id="json_export_wrapper"><pre id="json_export"></pre></div>';
		$tab .= '</div>';

		/* Testing tab */
		$tab .= '<div data-dojo-type="dijit/layout/ContentPane" title="Testing" data-dojo-props="selected:true" id="testing">';
		$tab .= self::get_form_start('test');

		$tab .= self::get_script('"Updated"','dojo.query("#test_url").attr("innerHTML", "<pre>"+transport.responseJSON.url+"</pre>"); dojo.query("#test_result").attr("innerHTML", transport.responseJSON.content); dojo.query("#test_log").attr("innerHTML", transport.responseJSON.log.join("\n")); dojo.query("#test_conf").attr("value", transport.responseJSON.config);');

		$tab .= __("Save before you test!").'<br />';
		$tab .= '<table width="100%">';
		$tab .= '<tr><td>';
		$tab .= 'URL:';
		$tab .= '</td></tr>';
		$tab .= '<tr><td>';
		$tab .= '<input dojoType="dijit.form.TextBox" name="test_url" style="font-size: 12px; width: 99%;" />';
		$tab .= '</td></tr>';
		$tab .= '<tr><td>';
		$tab .= 'Config (optional, will override default configuration):<button dojoType="dijit.form.Button" type="button">'.__("Restore last config").'<script type="dojo/on" event="click" args="evt">
			evt.preventDefault();
			dojo.query("#test_conf").attr("value", '.json_encode($test_conf).');
		</script>
			</button>';
		$tab .= '</td></tr>';
		$tab .= '<tr><td><textarea dojoType="dijit.form.SimpleTextarea" id="test_conf" name="test_conf" style="font-size: 12px; width: 99%; height: 150px;"></textarea>';
		$tab .= '</td></tr>';
		$tab .= '</table>';

		$tab .= '<p><button dojoType="dijit.form.Button" type="submit">'.__("Test").'</button> <input id="verbose" dojoType="dijit.form.CheckBox" name="verbose" /><label for="verbose">'.__("Show every step").'</label> </p>';
		$tab .= '</form>';
		$tab .= '<div data-dojo-type="dijit/layout/TabContainer" style="width: 100%; height: 75%">';
		$tab .= '<div data-dojo-type="dijit/layout/ContentPane" title="log" data-dojo-props="selected:true" id="test_log"></div>';
		$tab .= '<div data-dojo-type="dijit/layout/ContentPane" title="result" data-dojo-props="selected:true" id="test_result"></div>';
		$tab .= '<div data-dojo-type="dijit/layout/ContentPane" title="url" data-dojo-props="selected:true" id="test_url"></div>';
		$tab .= '</div>';
		$tab .= '</div>';
		$tab .= '</div>';
		return $tab;
	}
	private static function get_script($notification, $successaction,$failaction=''){
		$script = '<script type="dojo/method" event="onSubmit" args="evt">
			evt.preventDefault();
			dojo.query("#test_result").attr("innerHTML", "");
			new Ajax.Request("backend.php", {
				parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						if (transport.responseJSON.success == false){
							try {
								notify_error(transport.responseJSON.errormessage);
							} catch(err) {
								Notify.error(transport.responseJSON.errormessage);
							}
							'.$failaction.'
						}else{
							try {
								notify_info('.$notification.');
							} catch(err) {
								Notify.info('.$notification.');
							}
							'.$successaction.'
						}
					}
				}
			);
		</script>';
		return $script;
	}
	private static function get_form_start($method){
		$form = '<form dojoType="dijit.form.Form">';
		$form .='<input dojoType="dijit.form.TextBox" style="display : none" name="op" value="pluginhandler">';
		$form .= '<input dojoType="dijit.form.TextBox" style="display : none" name="method" value="'.$method.'">';
		$form .= '<input dojoType="dijit.form.TextBox" style="display : none" name="plugin" value="feediron">';
		return $form;
	}
}
