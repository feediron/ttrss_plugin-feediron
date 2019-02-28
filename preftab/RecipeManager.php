<?php
class RecipeManager{
	private $recipes = array();
	private $recipes_location = array(array("url"=>"https://api.github.com/repos/m42e/ttrss_plugin-feediron/contents/recipes", "branch"=>"master"), array("url"=>"https://api.github.com/repos/mbirth/ttrss_plugin-af_feedmod/contents/mods", "branch"=>"master"));

	function __construct(){
		#	$this->loadAvailableRecipes();
	}

	public function loadAvailableRecipes(){
		foreach ($this->recipes_location as $rloc){
			$content = fetch_file_contents ($rloc['url'].'?rev='.$rloc['branch']);

			Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "content: $content");
			$data = json_decode($content, true);
			if(isset ($data['message'])){
				$this->recipes[$data['message']] = ''; 
			}else{
				foreach ($data as $file){
					$this->recipes[$file['name']] = $file['url']; 
				}
			}
		}
	}

	public function getRecipes(){
		return $this->recipes;
	}

	public function getRecipe($recipeurl){
		$content = fetch_file_contents ($recipeurl);
		Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Recipe url: $recipeurl");
		$filedata = json_decode($content, true);

		Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Recipe content: $content");
		if(isset ($filedata['message'])){
			return $filedata;
		}
		$data = preg_replace('/\n/', '', $filedata['content']);
		Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Recipe content: $data");
		Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, base64_decode($data));
		$data = preg_replace(preg_quote('/\\./'), '.', base64_decode($data));
		$obj =  json_decode(($data),true);
		Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, 'last error: '.json_last_error());
		return $obj;
	}
}
