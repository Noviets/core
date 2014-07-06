<?php
 /*
 * Project:		EQdkp-Plus
 * License:		Creative Commons - Attribution-Noncommercial-Share Alike 3.0 Unported
 * Link:		http://creativecommons.org/licenses/by-nc-sa/3.0/
 * -----------------------------------------------------------------------
 * Began:		2013
 * Date:		$Date$
 * -----------------------------------------------------------------------
 * @author		$Author$
 * @copyright	2006-2013 EQdkp-Plus Developer Team
 * @link		http://eqdkp-plus.com
 * @package		eqdkp-plus
 * @version		$Rev$
 * 
 * $Id$
 */

if ( !defined('EQDKP_INC') ){
	header('HTTP/1.0 404 Not Found');exit;
}

include_once(registry::get_const('root_path').'core/html/html.aclass.php');

/*
 * available options
 * name			(string) 	name of the textarea
 * id			(string)	id of the field, defaults to a clean form of name if not set
 * value		
 * class		(string)	class for the field
 * readonly		(boolean)	field readonly?
 * size			(int)		size of the field
 * js			(string)	extra js which shall be injected into the field
 * spinner		(boolean)	make a spinner out of the field?
 * disabled		(boolean)	disabled field
 * autocomplete	(array)		if not empty: array containing the elements on which to autocomplete (not to use together with spinner)
 * colorpicker	(boolean) 	apply a colorpicker to this field
 */
class hfile extends html {

	protected static $type = 'file';
	
	public $name = '';
	public $readonly = false;
	public $class = 'input';
	public $inptype = '';
	
	
	protected $mimetypes = false;
	protected $numerate = false;
	protected $extensions = array();
	private $out = '';
	
	public function _construct() {
		$out = '<input type="'.self::$type.'" name="'.$this->name.'" ';
		if(empty($this->id)) $this->id = $this->cleanid($this->name);
		$out .= 'id="'.$this->id.'" ';
				
		if(isset($this->value)) $out .= 'value="'.$this->value.'" ';
		if(!empty($this->class)) $out .= 'class="'.$this->class.'" ';
		if(!empty($this->size)) $out .= 'size="'.$this->size.'" ';
		if($this->readonly) $out .= 'readonly="readonly" ';
		if(!empty($this->js)) $out.= $this->js.' ';
		$this->out = $out.' />';
	}
	
	public function _toString() {
		return $this->out;
	}
	
	public function inpval() {
		$tempname		= $_FILES[$this->name]['tmp_name'];
		$filename		= $_FILES[$this->name]['name'];
		$filetype		= $_FILES[$this->name]['type'];
		if ($tempname == '') return false;

		
		$fileEnding		= pathinfo($filename, PATHINFO_EXTENSION);
		if ($this->mimetypes){
			$mime = false;
			if(function_exists('finfo_open') && function_exists('finfo_file') && function_exists('finfo_close')){
				$finfo			= finfo_open(FILEINFO_MIME);
				$mime			= finfo_file($finfo, $tempname);
				finfo_close($finfo);
				
				$mime = array_shift(preg_split('/[; ]/', $mime));					
				if (!in_array($mime, $this->mimetypes)) return false;
			}elseif(function_exists('mime_content_type')){
				$mime			= mime_content_type( $tempname );
				$mime = array_shift(preg_split('/[; ]/', $mime));
				if (!in_array($mime, $this->mimetypes)) return false;
			}else{
				// try to get the extension... not really secure...			
				if (!in_array($fileEnding, $this->extensions)) return false;
			}			
			
		} else {
			
			if (!in_array($fileEnding, $this->extensions)) {
				return false;
			}
		}
		
		
		if($this->numerate){
			//Do no overwrite existing files
			$offset = 0;
			$files = array();
			$file = scandir($this->root_path.$this->folder);
			foreach($file as $this_file) {
				if( valid_folder($this_file) && !is_dir($this_file)) {
					$files[] = $this_file;
				}
			}
			
			$pathinfo = pathinfo($filename);
			$name = $pathinfo['filename'];
			
			$arrFiles = preg_grep('/^' . preg_quote($name, '/') . '.*\.' . preg_quote($fileEnding, '/') . '/', $files);
			
			foreach ($arrFiles as $strFile){
				if (preg_match('/_[0-9]+\.' . preg_quote($pathinfo['extension'], '/') . '$/', $strFile)){
					$strFile = str_replace('.' . $pathinfo['extension'], '', $strFile);
					$intValue = intval(substr($strFile, (strrpos($strFile, '_') + 1)));
					$offset = max($offset, $intValue);
				}
			}
			
			$filename = str_replace($name, $name . '_' . ++$offset, $filename);	
		}		

		if (isFilelinkInFolder(str_replace(registry::get_const('root_path'),"", $this->folder.$filename), str_replace(registry::get_const('root_path'),"", $this->folder))) {
			$this->pfh->FileMove($tempname, $this->folder.$filename, true);
		} else {
			unlink($tempname);
			return false;
		}

		return str_replace(registry::get_const('root_path'),"", $this->folder.$filename);		
	}
}
?>