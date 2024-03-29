<?php
/** 
 *  TestLink Open Source Project - http://testlink.sourceforge.net/
 * 
 *  @filesource   archiveData.php
 * 
 *  Allows you to show test suites, test cases.
 *
 *  USE CASES
 *  1. Launched from tree navigator on Test Specification feature.
 *
 *  2. Search option on Navigation Bar.
 *     In this Use Case, user can try to search for test cases that 
 *     DO NOT BELONG
 *     to current setted Test Project.
 *     System try to get Test Project analising user provided data 
 *    (test case identification)
 *
 */

require_once('../../config.inc.php');
require_once('common.php');
testlinkInitPage($db);

$smarty = new TLSmarty();
$smarty->tlTemplateCfg = $tplCfg = templateConfiguration();

$cfg = [
  'testcase' => config_get('testcase_cfg'),
  'testcase_reorder_by' => config_get('testcase_reorder_by'),
  'spec' => config_get('spec_cfg')
];

list($args,$gui,$grants) = initializeEnv($db);

// User right at test project level has to be done
// we need to use requested item to get its right Test Project
// We will start with Test Cases ONLY
switch($args->feature) {
  case 'testproject':
  case 'testsuite':
    $item_mgr = new $args->feature($db);
    $gui->id = $args->id;
    $gui->user = $args->user;
    if($args->feature == 'testproject') {
      $gui->id = $args->id = $args->tproject_id;
      $item_mgr->show($smarty,$gui, $tplCfg->template_dir,$args->id);
    } else {
      $gui->direct_link = $item_mgr->buildDirectWebLink($args);
      $gui->attachments = getAttachmentInfosFrom($item_mgr,$args->id);
      $item_mgr->show($smarty,$gui,$tplCfg->template_dir,$args->id,
                      array('show_mode' => $args->show_mode));
    }
    break;
    
  case 'testcase':
    try {
      processTestCase($db,$smarty,$args,$gui,$grants,$cfg);
    }
    catch (Exception $e) {
      echo $e->getMessage();
    }
  break;

  default:
    tLog('Argument "edit" has invalid value: ' . $args->feature , 'ERROR');
    trigger_error($_SESSION['currentUser']->login.'> Argument "edit" has invalid value.', E_USER_ERROR);
  break;
}



/**
 * 
 *
 */
function init_args(&$dbHandler) {
  $_REQUEST = strings_stripSlashes($_REQUEST);

  list($context,$env) = initContext();
  
  $iParams = array("edit" => array(tlInputParameter::STRING_N,0,50),
                   "id" => array(tlInputParameter::INT_N),
                   "tcase_id" => array(tlInputParameter::INT_N),
                   "tcversion_id" => array(tlInputParameter::INT_N),
                   "tplan_id" => array(tlInputParameter::INT_N),
                   "targetTestCase" => array(tlInputParameter::STRING_N,0,24),
                   "show_path" => array(tlInputParameter::INT_N),
                   "show_mode" => array(tlInputParameter::STRING_N,0,50),
                   "tcasePrefix" => array(tlInputParameter::STRING_N,0,16),
                   "tcaseExternalID" => array(tlInputParameter::STRING_N,0,16),
                   "tcaseVersionNumber" => array(tlInputParameter::INT_N),
                   "add_relation_feedback_msg" => array(tlInputParameter::STRING_N,0,255),
                   "caller" => array(tlInputParameter::STRING_N,0,10),
                   "tproject_id" => array(tlInputParameter::INT_N),
                   "containerType" => array(tlInputParameter::STRING_N,0,12));


  $args = new stdClass();
  R_PARAMS($iParams,$args);
  $args->form_token = $context->form_token;
  $args->user_id = $context->userID;
  $args->user = $context->user;
  $args->basehref = $_SESSION['basehref'];

  $tprojectMgr = new testproject($dbHandler);
  switch ($args->containerType) {
    case 'testproject':
      if ($args->tproject_id == 0) {
        $args->tproject_id = $args->id;
      }
    break;
  }

  $cfg = config_get('testcase_cfg');

  // ---------------------------
  // whitelist
  $wl = array_flip(array('testcase','testproject','testsuite'));
  $args->edit = trim($args->edit);

  if (!isset($wl[$args->edit])) {
    tLog('Argument "edit" has invalid value: ' . $args->edit , 'ERROR');
    trigger_error($_SESSION['currentUser']->login . 
                  '> Argument "edit" has invalid value.', E_USER_ERROR);
  }
  // ---------------------------
  
  $args->feature = $args->edit;
  $args->tcaseTestProject = null;
  $args->viewerArgs = null;

  $args->automationEnabled = 0;
  $args->requirementsEnabled = 0;
  $args->testPriorityEnabled = 0;
  $args->tcasePrefix = trim($args->tcasePrefix);

  // For more information about the data accessed in session here, see the comment
  // in the file header of lib/functions/tlTestCaseFilterControl.class.php.
  $args->refreshTree = getSettingFromFormNameSpace('edit_mode','setting_refresh_tree_on_action');

  // Try to understan how this script was called.
  switch($args->caller) {
    case 'navBar':
      systemWideTestCaseSearch($dbHandler,$args,$cfg->glue_character);
    break;

    case 'openTCW':
      // all data come in
      // tcaseExternalID   DOM-22
      // tcaseVersionNumber  1
      // trick for systemWideTestCaseSearch
      $args->targetTestCase = $args->tcaseExternalID; 
      systemWideTestCaseSearch($dbHandler,$args,$cfg->glue_character);
    break;

    default:
      if (!$args->tcversion_id) {
        $args->tcversion_id = testcase::ALL_VERSIONS;
      }
    break;
  }


  // used to manage goback  
  if(intval($args->tcase_id) > 0) {
    $args->feature = 'testcase';
    $args->id = intval($args->tcase_id);
  }
    
  switch($args->feature) {
    case 'testsuite':
      $args->viewerArgs = null;
      $_SESSION['setting_refresh_tree_on_action'] = ($args->refreshTree) ? 1 : 0;
    break;
     
    case 'testcase':
      $args->viewerArgs = [
        'action' => '', 
        'msg_result' => '', 
        'user_feedback' => '',
        'disable_edit' => 0, 
        'refreshTree' => 0,
        'add_relation_feedback_msg' => $args->add_relation_feedback_msg
      ];
            
      $args->id = is_null($args->id) ? 0 : $args->id;
      $args->tcase_id = $args->id;

      if( is_null($args->tcaseTestProject) && $args->id > 0 ) {
        $args->tcaseTestProject = $tprojectMgr->getByChildID($args->id);
      }
    break;
  }

  if(is_null($args->tcaseTestProject)) {  
    $args->tcaseTestProject = $tprojectMgr->get_by_id($args->tproject_id);
  }

  $args->requirementsEnabled = $args->tcaseTestProject['opt']->requirementsEnabled;
  $args->automationEnabled = $args->tcaseTestProject['opt']->automationEnabled;
  $args->testPriorityEnabled = $args->tcaseTestProject['opt']->testPriorityEnabled;

  // get code tracker config and object to manage TestLink - CTS integration
  $args->ctsCfg = null;
  $args->cts = null;

  unset($tprojectMgr);
  if( ($args->codeTrackerEnabled = intval($args->tcaseTestProject['code_tracker_enabled'])) ) {
    $ct_mgr = new tlCodeTracker($dbHandler);
    $args->ctsCfg = $ct_mgr->getLinkedTo($args->tproject_id);
    $args->cts = $ct_mgr->getInterfaceObject($args->tproject_id);

    unset($ct_mgr);
  }

  //echo '<pre>'; var_dump($args); echo '</pre>'; //die();
  return $args;
}



/**
 * 
 *
 */
function initializeEnv(&$dbHandler)
{
  $args = init_args($dbHandler);
  list($add2args,$gui) = initUserEnv($dbHandler,$args);

  $grant2check = testcase::getStandardGrantsNames();
  $grants = new stdClass();
  foreach($grant2check as $right) {
    $grants->$right = $_SESSION['currentUser']->hasRight($dbHandler,$right,$args->tproject_id);
    $gui->$right = $grants->$right;
  }

  $gui->modify_tc_rights = $gui->mgt_modify_tc;

  $gui->form_token = $args->form_token;
  $gui->tproject_id = $args->tproject_id;
  $gui->tplan_id = $args->tplan_id;

  $gui->page_title = lang_get('container_title_' . $args->feature);
  $gui->requirementsEnabled = $args->requirementsEnabled; 
  $gui->automationEnabled = $args->automationEnabled; 
  $gui->testPriorityEnabled = $args->testPriorityEnabled;
  $gui->codeTrackerEnabled = $args->codeTrackerEnabled;
  $gui->cts = $args->cts;
  $gui->show_mode = $args->show_mode;
  $lblkey = config_get('testcase_reorder_by') == 'NAME' ? '_alpha' : '_externalid';
  $gui->btn_reorder_testcases = lang_get('btn_reorder_testcases' . $lblkey);

  // has sense only when we work on test case
  $dummy = testcase::getLayout();
  $gui->tableColspan = $dummy->tableToDisplayTestCaseSteps->colspan;

  $gui->platforms = null;
  $gui->loadOnCancelURL = '';
  $gui->attachments = null;
  $gui->direct_link = null;
  $gui->steps_results_layout = config_get('spec_cfg')->steps_results_layout;
  $gui->bodyOnUnload = "storeWindowSize('TCEditPopup')";
  $gui->viewerArgs = $args->viewerArgs;


  return [$args,$gui,$grants];
}


/**
 *
 *
 */
function systemWideTestCaseSearch(&$dbHandler,&$argsObj,$glue)
{
  // Attention: 
  // this algorithm has potential flaw (IMHO) because we can find the glue character
  // in situation where it's role is not this.
  // Anyway i will work on this in the future (if I've time)
  //
  if (strpos($argsObj->targetTestCase,$glue) === false) {
    // We suppose user was lazy enough to do not provide prefix,
    // then we will try to help him/her
    $argsObj->targetTestCase = $argsObj->tcasePrefix . $argsObj->targetTestCase;
  }

  if( !is_null($argsObj->targetTestCase) ) {
    // parse to get JUST prefix, find the last glue char.
    // This useful because from navBar, user can request search of test cases that belongs
    // to test project DIFFERENT to test project setted in environment
    if( ($gluePos = strrpos($argsObj->targetTestCase, $glue)) !== false) {
      $tcasePrefix = substr($argsObj->targetTestCase, 0, $gluePos);
    }

    $tprojectMgr = new testproject($dbHandler);
    $argsObj->tcaseTestProject = $tprojectMgr->get_by_prefix($tcasePrefix);

    $tcaseMgr = new testcase($dbHandler);
    $argsObj->tcase_id = $tcaseMgr->getInternalID($argsObj->targetTestCase);
    $dummy = $tcaseMgr->get_basic_info($argsObj->tcase_id,array('number' => $argsObj->tcaseVersionNumber));
    if(!is_null($dummy)) {
      $argsObj->tcversion_id = $dummy[0]['tcversion_id'];
    }
  }
}

/**
 *
 */
function getSettingFromFormNameSpace($mode,$setting)
{
  $form_token = isset($_REQUEST['form_token']) ? $_REQUEST['form_token'] : 0;
  $sd = isset($_SESSION[$mode]) && isset($_SESSION[$mode][$form_token]) ? $_SESSION[$mode][$form_token] : null;
  
  $rtSetting = isset($sd[$setting]) ? $sd[$setting] : 0;
  return $rtSetting;
}

/**
 *
 *
 */ 
function processTestCase(&$dbHandler,$tplEngine,$args,&$gui,$grants,$cfg) {
  $get_path_info = false;
  $item_mgr = new testcase($dbHandler);


  // has sense only when we work on test case
  $dummy = testcase::getLayout();

  $gui->showAllVersions = true;
  $gui->tableColspan = $dummy->tableToDisplayTestCaseSteps->colspan;
  $gui->viewerArgs['refresh_tree'] = 'no';
  $gui->path_info = null;
  $gui->platforms = null;
  $gui->loadOnCancelURL = '';
  $gui->attachments = null;
  $gui->direct_link = null;
  $gui->steps_results_layout = $cfg['spec']->steps_results_layout;
  $gui->bodyOnUnload = "storeWindowSize('TCEditPopup')";

  $tprj = new testproject($dbHandler);
  $gui->tprojOpt = $tprj->getOptions($args->tproject_id);
    
  if( ($args->caller == 'navBar') && !is_null($args->targetTestCase) && strcmp($args->targetTestCase,$args->tcasePrefix) != 0) {

    $args->id = $item_mgr->getInternalID($args->targetTestCase);
    $args->tcversion_id = testcase::ALL_VERSIONS;

    // I've added $args->caller, in order to make clear the logic, 
    // because some actions need to be done ONLY
    // when we have arrived to this script because user has requested 
    // a search from navBar.
    // Before we have trusted the existence of certain variables 
    // (do not think this old kind of approach is good).
    //
    // why strcmp($args->targetTestCase,$args->tcasePrefix) ?
    // because in navBar targetTestCase is initialized with testcase prefix 
    // to provide some help to user
    // then if user request search without adding nothing, 
    // we will not be able to search.
    //
    // From navBar we want to allow ONLY to search for ONE and ONLY ONE test case ID.
    //
    $gui->showAllVersions = true;
    $gui->viewerArgs['show_title'] = 'no';
    $gui->viewerArgs['display_testproject'] = 1;
    $gui->viewerArgs['display_parent_testsuite'] = 1;
    if( !($get_path_info = ($args->id > 0)) ) {
      $gui->warning_msg = $args->id == 0 ? lang_get('testcase_does_not_exists') : lang_get('prefix_does_not_exists');
    }
  }

  // because we can arrive here from a User Search Request, 
  // if args->id == 0 => nothing found
  if( $args->id > 0 ) {
    if( $get_path_info || $args->show_path ) {
      $gui->path_info = $item_mgr->tree_manager->get_full_path_verbose($args->id);
    }
    $platform_mgr = new tlPlatform($dbHandler,$args->tproject_id);
    $opx = [
      'enable_on_design' => true
    ];
    $gui->platforms = $platform_mgr->getAllAsMap($opx);
    $gui->direct_link = $item_mgr->buildDirectWebLink($args);
    $gui->id = $args->id;

    $identity = new stdClass();
    $identity->id = $args->id;
    $identity->tproject_id = $args->tproject_id;
    $identity->version_id = intval($args->tcversion_id);

    $gui->showAllVersions = ($identity->version_id == 0);

    // Since 1.9.18, other entities (attachments, keywords, etc)
    // are related to test case versions, then the choice is to provide
    // in identity an specific test case version.
    // If nothing has been received on args, we will get latest active.
    //
    $latestTCVersionID = $identity->version_id;
    if( $latestTCVersionID == 0 ) {
      $tcvSet = $item_mgr->getAllVersionsID($args->id);
    } else {
      $tcvSet = array( $latestTCVersionID );
    }

    foreach( $tcvSet as $tcvx ) {
      $gui->attachments[$tcvx] = 
        getAttachmentInfosFrom($item_mgr,$tcvx);
    }

    try {
      $item_mgr->show($tplEngine,$gui,$identity,$grants);
    }
    catch (Exception $e) {
      echo $e->getMessage();
    }
    exit();
  }
  else {
    $tplCfg = templateConfiguration();
    
    // need to initialize search fields
    $xbm = $item_mgr->getTcSearchSkeleton();
    $xbm->warning_msg = lang_get('no_records_found');
    $xbm->pageTitle = lang_get('caption_search_form');
    $xbm->tableSet = null;
    $xbm->doSearch = false;
    $xbm->tproject_id = $args->tproject_id;


    $xbm->filter_by['requirement_doc_id'] = $gui->tprojOpt->requirementsEnabled; 
    $xbm->keywords = $tprj->getKeywords($args->tproject_id);
    $xbm->filter_by['keyword'] = !is_null($xbm->keywords);

    // 
    $cfMgr = new cfield_mgr($dbHandler);
    $xbm->design_cf = $cfMgr->get_linked_cfields_at_design($args->tproject_id,
                              cfield_mgr::ENABLED,null,'testcase');

    $xbm->filter_by['design_scope_custom_fields'] = !is_null($xbm->design_cf);

    $tplEngine->assign('gui',$xbm);
    $tplEngine->display($tplCfg->template_dir . 'tcSearchResults.tpl');
  }  
}
