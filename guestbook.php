<?php
#########################################################################
#	Gaestebuch Script - YellaBook					                                #
#	http://www.YellaBook.de               						                    #
#	All rights by KnotheMedia.de                                    			#
#-----------------------------------------------------------------------#
#	I-Net: http://www.knothemedia.de                            					#
#########################################################################
/**
 * frontend for the guestbook
 * 
 * @date 2012-07-29
 * @version 1.0
 * 
 */

 
 
session_start();



/********************************** define constants *******************************/

define("PATH_TO_LANG", "guestbook/lang/");
define("PATH_TO_RES", "guestbook/res/");
define("PATH_TO_TEMPLATES", "guestbook/templates/");
define("PATH_TO_DATA", "guestbook/data/");





/********************************* include needed data ******************************/

require_once(PATH_TO_DATA."config.php");				// include config
require_once(PATH_TO_RES."class.template.php");			// include template-class
require_once(PATH_TO_RES."class.entries.php");			// include extries-class
require_once(PATH_TO_RES."class.config.php");			// include configuration-class
require_once(PATH_TO_RES."class.language.php");			// include language-class
require_once(PATH_TO_RES."class.securequestions.php");	// include securequestions-class




/********************************* include smiley-array ******************************/

require_once(PATH_TO_DATA."smileys.php");			// include smiley-array
$_SESSION['gbook']['smileys'] = $gb_smileys;



/************************************ get link to page ******************************/

$_SESSION['gbook']['link'] = str_replace(array("?", $_SERVER['QUERY_STRING']), array("", ""), basename($_SERVER['REQUEST_URI']));





/************************************ handle language ********************************/

// set language an get texts
$_SESSION['gbook']['language'] = LANGUAGE;
$language = new Language(PATH_TO_LANG, LANGUAGE);
if(!$language->get_texts()){
	echo $language->error[0];
	exit;
}

 
 
 
 
/************************************** set action ***********************************/

$action = "";


// set the action from get-vars
if(!isset($_POST['gb_action']) && isset($_GET['gb_action'])){
	$action = $_GET['gb_action'];
}
// set the action from post-vars
else if(isset($_POST['gb_action']) && !isset($_GET['gb_action'])){
	$action = $_POST['gb_action'];
}

 
 
 

 
 
/*********************************** action-handling ********************************/

$content = "";

switch($action){
	case "new":		$content = display_form(); break;
	case "check":	$content = check_form(); break;
	case "save":	$content = save_form(); break;
	case "mail":	$content = return_mail(); break;
	default:		$content = display_entries(); break;
}





/********************************* translate content *******************************/

// wrap template if file isn't included
if(basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)){
header('Content-type: text/html; charset=utf-8');
	$template = new Template(PATH_TO_TEMPLATES."body.html");
	$template->replace_marker("CONTENT", $content);
	$content = $template->get_content();
}

foreach($_SESSION['gbook']['texts'] as $key=>$value){
	$content = str_replace('###'.$key.'###', $value, $content);
}

echo $content;





/************************************* functions **********************************/

/**
 * display the entries and a page-browser
 *
 * @param string $message 	message for successful saving
 * @return string 			html-content
 */
function display_entries($message=""){

	
	// create needed objects
	$template = new Template(PATH_TO_TEMPLATES."list.html");
	$entry_obj = new Entries(PATH_TO_DATA, 1);

	
	
	// get last page and current page
	$entry_obj->entries_active%ENTRIES_PER_PAGE == 0 ?
		$last_page = $entry_obj->entries_active/ENTRIES_PER_PAGE :
		$last_page = floor($entry_obj->entries_active/ENTRIES_PER_PAGE) + 1;
	
	if(isset($_GET['page']) && is_numeric($_GET['page'])){
		$_GET['page'] = floor($_GET['page']);
	}
	if(!isset($_GET['page']) || ""==trim($_GET['page']) || !is_numeric($_GET['page']) || $_GET['page']>$last_page){
		$_GET['page'] = 1;
	}
	
	
	
	// create pagebrowser
	if(0<$entry_obj->entries_active){
		$template_pagebrowser = new Template(PATH_TO_TEMPLATES."pagebrowser.html");
		1<$_GET['page'] ?
			$link_prev = '<a href="'.htmlentities($_SESSION['gbook']['link'].'?page='.($_GET['page']-1)).'">###prev_page###</a>' :
			$link_prev = '###prev_page###';
		($_GET['page']+1) <= $last_page ?
			$link_next = '<a href="'.htmlentities($_SESSION['gbook']['link'].'?page='.($_GET['page']+1)).'">###next_page###</a>' :
			$link_next = '###next_page###';	
			
		$template_pagebrowser->replace_marker("PAGEBROWSER_ACTION", $_SESSION['gbook']['link']);
		$template_pagebrowser->replace_marker("ENTRIES_QTY", $entry_obj->entries_active);
		$template_pagebrowser->replace_marker("PAGES_QTY", $last_page);
		$template_pagebrowser->replace_marker("CURRENT_PAGE", $_GET['page']);
		$template_pagebrowser->replace_marker("PREV_PAGE_LINK", $link_prev);
		$template_pagebrowser->replace_marker("NEXT_PAGE_LINK", $link_next);
		$template->replace_marker("PAGEBROWSER", $template_pagebrowser->get_content());
	}
	else{
		$template->replace_marker("PAGEBROWSER", "");
	}
	
	
	
	// get entries
	$entry_arr = $entry_obj->get_entries(($_GET['page']*ENTRIES_PER_PAGE)-ENTRIES_PER_PAGE+1, ENTRIES_PER_PAGE, 1);
	
	
	// no entries
	if(0==count($entry_arr)){
		$template->replace_marker("ENTRIES", "###no_entries###");
	}
	
	// there are entries
	else{
	
		// create entry-list
		$entries = "";
		foreach($entry_arr as $entry){
		
			// get entry-template
			$template_entry = new Template(PATH_TO_TEMPLATES."entry.html");
			
			
			// insert entry-info
			$template_entry->replace_marker("ENTRY_NR", $entry['number']);
			$template_entry->replace_marker("NAME", $entry['name']);
			""==$entry['city'] ?
				$template_entry->remove_area("FROM") :
				$template_entry->replace_marker("CITY", $entry['city']);
			$template_entry->replace_marker("DATE", date("F j, Y", $entry['entry_date']));
			$template_entry->replace_marker("TIME", date("g:i a", $entry['entry_date']));
			""==$entry['email'] ?
				$template_entry->remove_area("EMAIL") :
				$template_entry->replace_marker("EMAIL", htmlentities($_SESSION['gbook']['link']."?gb_action=mail&mail=".$entry['id']."&chkt=".(time()+6324)."&page=".$_GET['page']));			
			""==$entry['web'] ? 
				$template_entry->remove_area("WEB") :
				$template_entry->replace_marker("WEB", $entry['web']);
			$text = $entry['message'];
			foreach($_SESSION['gbook']['smileys'] as $smiley_text => $smiley_image){
				$text = str_replace($smiley_text, '<img class="gb_smiley" src="guestbook/images/'.$smiley_image.'" width="28" height="19" alt="" />', $text);
			}
			$template_entry->replace_marker("TEXT", $text);
			if(""==$entry['comment']){
				$template_entry->remove_area("COMMENT");
			}
			else{
				$template_entry->replace_marker("COMMENT_DATE", date("F j, Y", $entry['comment_date']));
				$template_entry->replace_marker("COMMENT_TIME", date("g:i a", $entry['comment_date']));
				$comment = $entry['comment'];
				foreach($_SESSION['gbook']['smileys'] as $smiley_text => $smiley_image){
					$comment = str_replace($smiley_text, '<img class="gb_smiley" src="guestbook/images/'.$smiley_image.'" width="28" height="19" alt="" />', $comment);
				}
				$template_entry->replace_marker("COMMENT", $comment);
			}
	
			// store entry in list
			$entries .= $template_entry->get_content();
		}
		
		// fill template with entry-information
		$template->replace_marker("ENTRIES", $entries);
	}
	
	

	$template->replace_marker("LINK", htmlentities($_SESSION['gbook']['link']."?gb_action=new"));
	$template->replace_marker("MESSAGE", $message);
	return $template->get_content();
}






/**
 * display the form for a new entry
 *
 * @param string $errormessage 	errormessage
 * @return string 				html-content
 */
function display_form($errormessage=""){
	
	// create template
	$template = new Template(PATH_TO_TEMPLATES."entry_form.html");
	
	
	// create link for form
	$template->replace_marker("FORM_ACTION", $_SESSION['gbook']['link']);
	
	
	// backlink, errormessage, timestamp
	$template->replace_marker("BACK_LINK", $_SESSION['gbook']['link']);
	$template->replace_marker("ERRORS", $errormessage);
	$template->replace_marker("TIME", time());

	
	// set session-id
	$_SESSION['gbook']['new_entry']['sessid'] = session_id();
	$template->replace_marker("SID", session_id());
	
	
	// form-values
	isset($_SESSION['gbook']['new_entry']['name']) ?
		$template->replace_marker("NAME", $_SESSION['gbook']['new_entry']['name']) :
		$template->replace_marker("NAME", "");	
	isset($_SESSION['gbook']['new_entry']['city']) ?
		$template->replace_marker("CITY", $_SESSION['gbook']['new_entry']['city']) :
		$template->replace_marker("CITY", "");	
	isset($_SESSION['gbook']['new_entry']['email']) ?
		$template->replace_marker("EMAIL", $_SESSION['gbook']['new_entry']['email']) :
		$template->replace_marker("EMAIL", "");	
	isset($_SESSION['gbook']['new_entry']['web']) ?
		$template->replace_marker("WEB", $_SESSION['gbook']['new_entry']['web']) :
		$template->replace_marker("WEB", "");	
	isset($_SESSION['gbook']['new_entry']['message']) ?
		$template->replace_marker("MESSAGE", $_SESSION['gbook']['new_entry']['message']) :
		$template->replace_marker("MESSAGE", "");		
		
	
	// create-smiley-block
	$smileys = "";
	foreach($_SESSION['gbook']['smileys'] as $smiley_text => $smiley_image){
		$smiley_template = new Template(PATH_TO_TEMPLATES."smiley.html");
		$smiley_template->replace_marker("SMILEY_TEXT", $smiley_text);		
		$smiley_template->replace_marker("SMILEY_TEXTAREA_ID", 'gb_form_messagebox');		
		$smiley_template->replace_marker("SMILEY_IMAGE", 'guestbook/images/'.$smiley_image);		
		$smileys .= $smiley_template->get_content();
	}
	$template->replace_marker("SMILEYS", $smileys);		

	
	// insert max_length
	$template->replace_marker("MAX_NAME_CHARACTERS", MAX_NAME_CHARACTERS);		
	$template->replace_marker("MAX_CITY_CHARACTERS", MAX_CITY_CHARACTERS);		
	$template->replace_marker("MAX_EMAIL_CHARACTERS", MAX_EMAIL_CHARACTERS);		
	$template->replace_marker("MAX_WEB_CHARACTERS", MAX_WEB_CHARACTERS);		
	$template->replace_marker("MAX_MESSAGE_CHARACTERS", MAX_MESSAGE_CHARACTERS);		
	
	
	// insert securequestion
	if(ENABLE_SECUREQUESTION){
		// get subquestions
		$securequestion_obj = new Securequestions(PATH_TO_DATA);
		$securequestions = $securequestion_obj->get_securequestions();
		
		// get random subquestion
		$securequestion_id = array_rand($securequestions);
		$securequestion = $securequestions[$securequestion_id];
		
		// create div with the securequestion
		$securequestion_string = '<div class="gb_form_row gb_form_securequestion">
									<label>###spamprotection###<br/>###securequestion_info###<br/><span>'.$securequestion[LANGUAGE].'</span></label>
									<input type="hidden" name="securequestion_id" value="'.$securequestion_id.'" />
									<input type="text" name="securequestion_answer" value="" />
								</div>';

		$template->replace_marker("SECUREQUESTION", $securequestion_string);
	}
	else{
		$template->replace_marker("SECUREQUESTION", '');		
	}
	
	
	
	
	return $template->get_content();
}






/**
 * check the values of the form and display the form with errormessages, the preview or save the form
 *
 * @return string 				html-content
 */
function check_form(){
	
	// create entry-object and check the data
	$entry_obj = new Entries(PATH_TO_DATA);
	$entry_obj->check_new_entry($_POST);
	
	
	// errors -> create error-messages and display form with errors
	if(0<count($entry_obj->error)){
		$errormessage = '<p class="gb_error">';
		foreach($entry_obj->error as $error){
			$errormessage .= $error.'<br/>';
		}
		$errormessage .= '</p>';
		
		return display_form($errormessage);
	}
	
	
	// no errors -> dislay preview or save the entry
	else{
	
		// save the entry
		if(isset($_POST['save_without_preview'])){
			return save_form();
		}
		
		
		// display preview
		else{
		
			// create template
			$template = new Template(PATH_TO_TEMPLATES."preview.html");
			
					
			// create link for form and backlink
			$template->replace_marker("FORM_ACTION", htmlentities($_SESSION['gbook']['link']));
			$template->replace_marker("BACK_LINK", htmlentities($_SESSION['gbook']['link']."?gb_action=new"));
			

			// set session-id and time
			$_SESSION['gbook']['new_entry']['sessid'] = session_id();
			$template->replace_marker("SID", session_id());
			$template->replace_marker("TIME", time());
			
			
			// get entry-template
			$template_entry = new Template(PATH_TO_TEMPLATES."entry-preview.html");
			
			
			// insert entry-info
			$template_entry->replace_marker("ENTRY_NR", AUTO_INCREMENT);
			$template_entry->replace_marker("NAME", htmlentities(stripslashes($_POST['name']), ENT_COMPAT, 'UTF-8'));
			!isset($_POST['city']) || ""==$_POST['city'] ?
				$template_entry->remove_area("FROM") :
				$template_entry->replace_marker("CITY", htmlentities(stripslashes($_POST['city']), ENT_COMPAT, 'UTF-8'));
			$template_entry->replace_marker("DATE", date("F j, Y", time()));
			$template_entry->replace_marker("TIME", date("g:i a", time()));
			!isset($_POST['email']) || ""==$_POST['email'] ?
				$template_entry->remove_area("EMAIL") :
				$template_entry->replace_marker("EMAIL", htmlentities("mailto:").stripslashes($_POST['email']));			
			!isset($_POST['web']) || ""==$_POST['web'] ? 
				$template_entry->remove_area("WEB") :
				$template_entry->replace_marker("WEB", htmlentities(stripslashes(((false===strpos($_POST['web'], "http://") && false===strpos($_POST['web'], "https://")) ? "http://".$_POST['web'] : $_POST['web'])), ENT_COMPAT, 'UTF-8'));
			$text = nl2br(htmlentities(stripslashes($_POST['message']), ENT_COMPAT, 'UTF-8'));
			foreach($_SESSION['gbook']['smileys'] as $smiley_text => $smiley_image){
				$text = str_replace($smiley_text, '<img class="gb_smiley" src="guestbook/images/'.$smiley_image.'" width="28" height="19" alt="" />', $text);
			}
			$template_entry->replace_marker("TEXT", $text);
			$template_entry->remove_area("COMMENT");

			
			// insert entry
			$template->replace_marker("PREVIEW", $template_entry->get_content());
		
			return $template->get_content();
		}
	}
}






/**
 * try to save the new entry. display the form if there is an error else display the guestbook with a message
 *
 * @return string 				html-content
 */
function save_form(){
	
	// create entry-object and check the data
	$entry_obj = new Entries(PATH_TO_DATA, 1);
	$entry_obj->check_new_entry($_SESSION['gbook']['new_entry']);
	
	
	// errors -> create error-messages and display form with errors
	if(0<count($entry_obj->error)){
		$errormessage = '<p class="gb_error">';
		foreach($entry_obj->error as $error){
			$errormessage .= $error.'<br/>';
		}
		$errormessage .= '</p>';
		
		return display_form($errormessage);
	}
	
	
	// no errors -> save entry
	else{
	
		// could save the new entry
		if($entry_obj->new_entry($_SESSION['gbook']['new_entry'])){
			
			// display info for success and admin-mail
			if(ENABLE_ENTRIES){
				return display_entries('<p class="gb_message">###save_successful_with_adminmail###</p>');
			}
			// display info for success
			else{
				return display_entries('<p class="gb_message">###save_successful###</p>');
			}
		}
		
		// there was a problem when saving the entry
		else{
			return display_entries('<p class="gb_error">###save_problem###:<br/>'.$entry_obj->error[0].'</p>');
		}
	}
}






/**
 * redirect to mailto-mail if the timestamp is ok
 *
 * @return string 			html-content
 */
function return_mail(){
	
	// no bot
	if(isset($_GET['chkt']) && ""!=$_GET['chkt'] && (($_GET['chkt']-6324) <= (time()-2))){
		$entry_obj = new Entries(PATH_TO_DATA, 1);
		$entry_arr = $entry_obj->get_entries();
		foreach($entry_arr as $entry){
			if($entry['id'] == $_GET['mail']){
				//header("Location: mailto:".$entry['email']);
				$content = '<p>###email###: '.$entry['email'].'</p>
							<p><a href="'.$_SESSION['gbook']['link']."?page=".$_GET['page'].'">###back_to_guestbook###</a></p>';
							return $content;
			}
		}
	}
	
	// bot
	else{
		// redirect to the guestbook
		header("Location: ".$_SESSION['gbook']['link']);
		exit;
	}	
}


?>