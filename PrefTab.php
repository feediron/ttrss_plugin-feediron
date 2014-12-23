<?php

class Feediron_PrefTab{
	public static function get_pref_tab($json_conf){
		$tab = '';
		$rm = new RecipeManager();
		$rm->loadAvailableRecipes();

		$tab .= '<div data-dojo-type="dijit/layout/TabContainer" style="width: 100%;" doLayout="false">';
		$tab .= '<div data-dojo-type="dijit/layout/ContentPane" title="Configuration" data-dojo-props="selected:true" id="config">';
		print self::get_form_start('save');
		print self::get_script(' notify_info(transport.responseJSON.message); dojo.query("#json_conf").attr("value",transport.responseJSON.json_conf); dojo.query("#json_error").attr("innerHTML", "").attr("class",""); ','dojo.query("#json_error").attr("innerHTML", transport.responseJSON.json_error).attr("class","error");');

		$tab .= '<textarea dojoType="dijit.form.SimpleTextarea" id="json_conf" name="json_conf" style="font-size: 12px; width: 99%; height: 400px;">'.$json_conf.'</textarea>';

		$tab .= '<p /><button dojoType="dijit.form.Button" type="submit">'.__("Save").'</button>';

		$tab .= '</form>';
		$tab .= self::get_form_start('add');
		$tab .= self::get_script('notify_info(transport.responseJSON.message); dojo.query("#json_conf").attr("value",transport.responseJSON.json_conf); ');

		$tab .= '<label for="addrecipe">'.__("Add recipe").': </label>';
		$tab .= '<select dojoType="dijit.form.Select" name="addrecipe">';
		foreach($rm->getRecipes() as $recipe){
			$tab .= '<option value="'.$recipe.'">'.$recipe.'</option>';
		}
		$tab .= '</select>&nbsp;';
		$tab .= '<button dojoType="dijit.form.Button" type="submit">'.__("Add").'</button>';
		$tab .= '</form><p /><div id="json_error"></div>';
		$tab .= __("Save after adding config!").'<br />';
		$tab .= '</div>';
		$tab .= '<div data-dojo-type="dijit/layout/ContentPane" title="Testing" data-dojo-props="selected:true" id="testing">';
		$tab .= self::get_form_start('test');

		$tab .= self::get_script('notify_info("Updated"); dojo.query("#test_url").attr("innerHTML", "<pre>"+transport.responseJSON.url+"</pre>"); dojo.query("#test_result").attr("innerHTML", transport.responseJSON.content); dojo.query("#test_log").attr("innerHTML", transport.responseJSON.log.join("\n"));');

		$tab .= __("Save before you test!").'<br />';

		$tab .= '<table width="100%"><tr><td>';
		$tab .= '<input dojoType="dijit.form.TextBox" name="test_url" style="font-size: 12px; width: 99%;" />';
		$tab .= '</td></tr></table>';
		$tab .= '<p><button dojoType="dijit.form.Button" type="submit">'.__("Test").'</button> <input id="verbose" dojoType="dijit.form.CheckBox" name="verbose" /><label for="verbose">'.__("Show every step").'</label> </p>';
		$tab .= '</form>';
		$tab .= '<div data-dojo-type="dijit/layout/TabContainer" style="width: 100%;" doLayout="false">';
		$tab .= '<div data-dojo-type="dijit/layout/ContentPane" title="log" data-dojo-props="selected:true" id="test_log"></div>';
		$tab .= '<div data-dojo-type="dijit/layout/ContentPane" title="result" data-dojo-props="selected:true" id="test_result"></div>';
		$tab .= '<div data-dojo-type="dijit/layout/ContentPane" title="url" data-dojo-props="selected:true" id="test_url"></div>';
		$tab .= '</div>';
		$tab .= '</div>';
		$tab .= '</div>';
		return $tab;
	}	
	private static function get_script($successaction,$failaction=''){
		$script = '<script type="dojo/method" event="onSubmit" args="evt">
			evt.preventDefault();
			dojo.query("#test_result").attr("innerHTML", "");
			new Ajax.Request("backend.php", {
				parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						if (transport.responseJSON.success == false){
							notify_error(transport.responseJSON.errormessage);
							'.$failaction.'
						}else{
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
