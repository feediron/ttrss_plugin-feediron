<?php
class RecipeManager{
	private $recipes = array();
	const recipes_location = "https://api.github.com/repos/m42e/ttrss_plugin-feediron/contents/recipes";
	const recipes_branch = "?rev=master";

	function __construct(){
	#	$this->loadAvailableRecipes();
	}

	public function loadAvailableRecipes(){
		$content = fetch_file_contents (self::recipes_location.self::recipes_branch);
		$data = json_decode($content);
		if(isset ($data['message'])){
				$this->recipes[$data['message']] = ''; 
		}else{
			foreach ($data as $file){
				$this->recipes[$file->name] = $file; 
			}
		}
	}

	public function getRecipes(){
		return array_keys($this->recipes);
	}

	public function getRecipe($recipename){
		$content = fetch_file_contents (self::recipes_location.'/'.$recipename.self::recipes_branch);
		$filedata = json_decode($content, true);
		
		if(isset ($filedata['message'])){
			return $filedata;
		}
		return json_decode(base64_decode($filedata['content']),true);
	}
}
