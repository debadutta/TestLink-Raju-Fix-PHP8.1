<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later.
 *
 * Used when creating and/or editing a container (Test project, Test suite)
 *
 * @filesource  containerEdit.php
 * @package     TestLink
 * @copyright   2005-2022, TestLink community
 * @link        http://www.testlink.org
 *
 */
require_once("../../config.inc.php");
require_once("common.php");
require_once("opt_transfer.php");
require_once("web_editor.php");
require_once('event_api.php');
$editorCfg=getWebEditorCfg('design');
require_once(require_web_editor($editorCfg['type']));

testlinkInitPage($db);
$tree_mgr = new tree($db);
$tproject_mgr = new testproject($db);
$tsuite_mgr = new testsuite($db);
$tcase_mgr = new testcase($db);

$template_dir = 'testcases/';
$refreshTree = false;


list($args,$opt_cfg) = init_args($db,$tproject_mgr,$tsuite_mgr);
list($add2args,$gui) = initUserEnv($db,$args);


$level = isset($args->level) ? $args->level : $args->containerType;

$gui_cfg = config_get('gui');
$smarty = new TLSmarty();
$smarty->assign('editorType',$editorCfg['type']);

list($a_tpl, $a_actions) = initTPLActions();
$a_init_opt_transfer = [
  'edit_testsuite' => 1,
  'new_testsuite'  => 1,
  'add_testsuite'  => 1,
  'update_testsuite' => 1
];

$the_tpl = null;
$action = null;
$get_c_data = null;
$init_opt_transfer = null;


// 20121222 -franciscom
// Need this trick because current implementation of Ext.ux.requireSessionAndSubmit()
// discards the original submit button
if( isset($_REQUEST['doAction']) ) {
  $_POST[$_REQUEST['doAction']] = $_REQUEST['doAction'];
}
$action = isset($_REQUEST['doAction']) ? $_REQUEST['doAction'] : null;

foreach ($a_actions as $the_key => $the_val) {
  if (isset($_POST[$the_key]) ) {
    $the_tpl = isset($a_tpl[$the_key]) ? $a_tpl[$the_key] : null;
    $init_opt_transfer = isset($a_init_opt_transfer[$the_key])?1:0;
  
    $action = $the_key;
    $get_c_data = $the_val;
    $level = is_null($level) ? 'testsuite' : $level;
    break;
  }
}
$args->action = $action;

$smarty->assign('level', $level);
$smarty->assign('page_title',lang_get('container_title_' . $level));

if($init_opt_transfer) {
  $opt_cfg = initializeOptionTransfer($tproject_mgr,$tsuite_mgr,$args,$action);
}

// create  web editor objects
list($oWebEditor,$webEditorHtmlNames,$webEditorTemplateKey) = initWebEditors($action,$level,$editorCfg);
if($get_c_data) {
  $name_ok = 1;
  $c_data = getValuesFromPost($webEditorHtmlNames);
  if($name_ok && !check_string($c_data['container_name'],$g_ereg_forbidden)) {
    $msg = $args->l10n['string_contains_bad_chars'];
    $name_ok = 0;
  }

  if($name_ok && ($c_data['container_name'] == "")) {
    $msg = $args->l10n['warning_empty_testsuite_name'];
    $name_ok = 0;
  }
}

$doIt = true;
switch($action) {
  case 'fileUpload':
  case 'deleteFile':
  case 'edit_testsuite':
  case 'new_testsuite':
  case 'delete_testsuite':
  case 'move_testsuite_viewer':
  case 'reorder_testsuites':
  case 'update_testsuite':
  case 'do_move':
  case 'do_copy':
  case 'do_move_tcase_set':
  case 'do_copy_tcase_set':
  case 'do_copy_tcase_set_ghost':
  case 'add_testsuite':
  case 'delete_testcases':
  case 'do_delete_testcases':
  case 'reorder_testcases':
  case 'reorder_testsuites_alpha':
  case 'reorder_testproject_testsuites_alpha':
  case 'doBulkSet':
  case 'addKeyword':
  case 'addKeywordTSDeep':
  case 'removeKeyword':
    $doIt = ('yes' == $args->grants->testcase_mgmt);
  break;
}

if( $doIt ) {
  switch($action) {
    case 'fileUpload':
      switch($level) {
        case 'testsuite':
          $uploadOp = fileUploadManagement($db,$args->testsuiteID,
                        $args->fileTitle,
                        $tsuite_mgr->getAttachmentTableName());
          $gui = initializeGui($tsuite_mgr,$args->testsuiteID,$args);
          $gui->refreshTree = 0;
          $gui->uploadOp = $uploadOp;
          $tsuite_mgr->show($smarty,$gui,$template_dir,$args->testsuiteID,null,null);
        break;

        case 'testproject':
          $uploadOp = fileUploadManagement($db,$args->tproject_id,
                         $args->fileTitle,
                         $tproject_mgr->getAttachmentTableName());
          $gui = initializeGui($tproject_mgr,$args->tproject_id,$args);
          $gui->refreshTree = 0;
          $gui->uploadOp = $uploadOp;
          $tproject_mgr->show($smarty,$gui,$template_dir,$args->tproject_id,null,null);
        break;
      }
    break;

    case 'deleteFile':
      deleteAttachment($db,$args->file_id);
      switch($level) {
        case 'testsuite':
          $gui = initializeGui($tsuite_mgr,$args->testsuiteID,$args);
          $gui->refreshTree = 0;
          $tsuite_mgr->show($smarty,$gui,$template_dir,$args->testsuiteID,null,null);
        break;  

        case 'testproject':
          $gui = initializeGui($tproject_mgr,$args->tproject_id,$args);
          $gui->refreshTree = 0;
          $tproject_mgr->show($smarty,$gui,$template_dir,$args->tproject_id,null,null);
        break;  
      }  
    break;

    case 'edit_testsuite':
    case 'new_testsuite':
      list($add2args,$gui) = initUserEnv($db,$args);

      $gui->containerType = $level;
      $gui->refreshTree = $args->refreshTree;
      $gui->containerID = $args->containerID;
      $gui->hasKeywords = (count((array)$opt_cfg->from->map) > 0) || (count((array)$opt_cfg->to->map) > 0);

      cancelActionURL($gui,$args);

      keywords_opt_transf_cfg($opt_cfg, $args->assigned_keyword_list);
      $smarty->assign('opt_cfg', $opt_cfg);
      $smarty->assign('level', $level);
      $smarty->assign('gui', $gui);
      $tsuite_mgr->viewer_edit_new($smarty,$template_dir,$webEditorHtmlNames,
                                   $oWebEditor,$action,$args->containerID, 
                                   $args->testsuiteID,null,
                                   $webEditorTemplateKey);
    break;

    case 'delete_testsuite':
      $refreshTree = deleteTestSuite($smarty,$args,$tsuite_mgr,$tree_mgr,$tcase_mgr,$level);
    break;

    case 'move_testsuite_viewer':
      $gui = moveTestSuiteViewer($smarty,$tproject_mgr,$args);
    break;

    case 'move_testcases_viewer':
      $gui = moveTestCasesViewer($db,$smarty,$tproject_mgr,$tree_mgr,$args);
    break;

    case 'testcases_table_view':
      $cf = null;
      $cf_map = $tcase_mgr->get_linked_cfields_at_design(0,null,null,null,$args->tproject_id);    
      if(!is_null($cf_map)) {
        $cfOpt = [
          'addCheck' => true, 
          'forceOptional' => true
        ];
        $cf = $tcase_mgr->cfield_mgr->html_table_inputs($cf_map,'',null,$cfOpt);
      }

      $gui = moveTestCasesViewer($db,$smarty,$tproject_mgr,$tree_mgr,$args,null,$cf);
    break;


    case 'reorder_testsuites':
      $ret = reorderTestSuiteViewer($smarty,$tree_mgr,$args);
      $level = is_null($ret) ? $level : $ret;
    break;

    case 'do_move':
      moveTestSuite($smarty,$template_dir,$tproject_mgr,$args);
    break;

    case 'do_copy':
      copyTestSuite($smarty,$template_dir,$tsuite_mgr,$args);
    break;

    case 'update_testsuite':
      if ($name_ok) {
        $msg = updateTestSuite($tsuite_mgr,$args,$c_data,$_REQUEST);
      }
      $gui = initializeGui($tsuite_mgr,$args->testsuiteID,$args);
      $tsuite_mgr->show($smarty,$gui,$template_dir,
                        $args->testsuiteID,null,$msg);
    break;

    case 'add_testsuite':
      $messages = null;
      $op['status'] = 0;
      if ($name_ok) {
        $op = addTestSuite($tsuite_mgr,$args,$c_data,$_REQUEST);
        $messages = [
          'result_msg' => $op['messages']['msg'],
          'user_feedback' => $op['messages']['user_feedback']
        ];
      }
           
      // $userInput is used to maintain data filled by user if there is
      // a problem with test suite name.
      $userInput = $op['status'] ? null : $_REQUEST;

      list($add2args,$gui) = initUserEnv($db,$args);
      $gui->containerType = $level;
      $gui->refreshTree = $args->refreshTree;
      $gui->containerID = $args->containerID;
      cancelActionURL($gui,$args);
      
      $assignedKeywords = $op['status'] ? "" : $args->assigned_keyword_list;
      keywords_opt_transf_cfg($opt_cfg, $assignedKeywords);
      $smarty->assign('opt_cfg', $opt_cfg);
      $smarty->assign('level', $level);
      $smarty->assign('gui', $gui);

      $tsuite_mgr->viewer_edit_new($smarty,$template_dir,$webEditorHtmlNames, 
                                   $oWebEditor, $action,$args->containerID, null,
                                   $messages,$webEditorTemplateKey,$userInput);
    break;


    case 'do_move_tcase_set':
      moveTestCases($smarty,$template_dir,$tsuite_mgr,$tree_mgr,$args);
    break;

    case 'do_copy_tcase_set':
    case 'do_copy_tcase_set_ghost':
      $args->stepAsGhost = ($action == 'do_copy_tcase_set_ghost');
      $op = copyTestCases($smarty,$template_dir,$tsuite_mgr,$tcase_mgr,$args);

      $refreshTree = $op['refreshTree'];
      $gui = moveTestCasesViewer($db,$smarty,$tproject_mgr,$tree_mgr,$args,$op['userfeedback']);
    break;


    case 'delete_testcases':
      $args->refreshTree = false;
      deleteTestCasesViewer($db,$smarty,$tproject_mgr,$tree_mgr,$tsuite_mgr,$tcase_mgr,$args);
    break;

    case 'do_delete_testcases':
      $args->refreshTree = true;
      doDeleteTestCases($db,$args->tcaseSet,$tcase_mgr);
      deleteTestCasesViewer($db,$smarty,$tproject_mgr,$tree_mgr,$tsuite_mgr,
                            $tcase_mgr,$args,
                            lang_get('all_testcases_have_been_deleted'));
    break;

    case 'reorder_testcases':
      reorderTestCasesByCriteria($args,$tsuite_mgr,$tree_mgr);
      $gui = initializeGui($tsuite_mgr,$args->testsuiteID,$args);
      $gui->refreshTree = true;
      $tsuite_mgr->show($smarty,$gui,$template_dir,$args->testsuiteID,null,null);
      exit();
    break;


    case 'reorder_testsuites_alpha':
      reorderTestSuitesDictionary($args,$tree_mgr,$args->testsuiteID);
      $gui = initializeGui($tsuite_mgr,$args->testsuiteID,$args);
      $gui->refreshTree = true;
      $tsuite_mgr->show($smarty,$gui,$template_dir,$args->testsuiteID,null,null);
      exit();
    break;

    case 'reorder_testproject_testsuites_alpha':
      reorderTestSuitesDictionary($args,$tree_mgr,$args->tproject_id);
      $gui = initializeGui($tproject_mgr,$args->tproject_id,$args);
      $gui->refreshTree = true;
      $tproject_mgr->show($smarty,$gui,$template_dir,$args->tproject_id,null,null);
      exit();
    break;

    case 'doBulkSet':
      $args->refreshTree = true;
      doBulkSet($db,$args,$args->tcaseSet,$tcase_mgr);    

      $cf = null;
      $cf_map = $tcase_mgr->get_linked_cfields_at_design(0,null,null,null,$args->tproject_id);    
      if(!is_null($cf_map)) {
        $cfOpt = array('addCheck' => true, 'forceOptional' => true);
        $cf = $tcase_mgr->cfield_mgr->html_table_inputs($cf_map,'',null,$cfOpt);
      }
      $gui = moveTestCasesViewer($db,$smarty,$tproject_mgr,$tree_mgr,$args,null,$cf);
    break;

    case 'addKeyword':
      $tsuite_mgr->addKeywords($args->item_id,$args->free_keywords);    
      showTestSuite($smarty,$args,$tsuite_mgr,$template_dir);
      exit();
    break;

    case 'addKeywordTSDeep':
      $tsuite_mgr->addKeywordsDeep($args->item_id,$args->free_keywords);    
      showTestSuite($smarty,$args,$tsuite_mgr,$template_dir);
      exit();
    break;

    case 'removeKeyword': 
      $tsuite_mgr->deleteKeywordByLinkID($args->kw_link_id);
      showTestSuite($smarty,$args,$tsuite_mgr,$template_dir);
      exit();
    break;


    default:
      trigger_error("containerEdit.php - No correct GET/POST data", E_USER_ERROR);
    break;
  }
}


if($the_tpl) {
  $gui->tprojOpt = $args->tprojOpt;
  $smarty->assign('gui',$gui);
  $smarty->assign('refreshTree',$refreshTree && $args->refreshTree);

  $smarty->display($template_dir . $the_tpl);
}


/**
 *
 *
 */
function getValuesFromPost($akeys2get) {
  $akeys2get[] = 'container_name';
  $c_data = array();
  foreach($akeys2get as $key) {
    $c_data[$key] = isset($_POST[$key]) ? strings_stripSlashes($_POST[$key]) : null;
  }
  return $c_data;
}

/*
 function:

args :

returns:

*/
function build_del_testsuite_warning_msg(&$tree_mgr,&$tcase_mgr,&$testcases,$tsuite_id)
{
    $msg = null;
    $msg['warning'] = null;
    $msg['link_msg'] = null;
    $msg['delete_msg'] = null;

    if(!is_null($testcases)) {
        $show_warning = 0;
        $delete_msg = '';
        $verbose = array();
        $msg['link_msg'] = array();

        $status_warning = array('linked_and_executed' => 1,
                        'linked_but_not_executed' => 1,
                        'no_links' => 0);

        $delete_notice = array('linked_and_executed' => lang_get('delete_notice'),
                        'linked_but_not_executed' => '',
                        'no_links' => '');

        $getOptions = array('addExecIndicator' => true);
        foreach($testcases as $the_key => $elem) {
            $verbose[] = $tree_mgr->get_path($elem['id'],$tsuite_id);
            $xx = $tcase_mgr->get_exec_status($elem['id'],null,$getOptions);
            $status = 'no_links';
            if(!is_null($xx)) {
                $status = $xx['executed'] ? 'linked_and_executed' : 'linked_but_not_executed';
            }
            $msg['link_msg'][] = $status;

            if($status_warning[$status]) {
                $show_warning = 1;
                $msg['delete_msg'] = $delete_notice[$status];
            }
        }

        $idx = 0;
        if($show_warning) {
            $msg['warning'] = array();
            foreach($verbose as $the_key => $elem) {
                $msg['warning'][$idx] = '';
                $bSlash = false;
                foreach($elem as $tkey => $telem) {
                    if ($bSlash) {
                        $msg['warning'][$idx] .= "\\";
                    }
                    $msg['warning'][$idx] .= $telem['name'];
                    $bSlash = true;
                }
                $idx++;
            }
        }
        else {
            $msg['link_msg'] = null;
            $msg['warning'] = null;
        }
    }
    return $msg;
}


/*
 function:

args :

returns:

*/
function init_args(&$dbHandler,&$tprojectMgr,&$tsuiteMgr)
{
  $args = new stdClass();
  list($context,$env) = initContext();

  $_REQUEST = strings_stripSlashes($_REQUEST);

  // Option Transfer configuration
  $optTCfg = new stdClass();
  $optTCfg->js_ot_name = 'ot';
  $rl_html_name = $optTCfg->js_ot_name . "_newRight";
  $args->assigned_keyword_list = isset($_REQUEST[$rl_html_name])? $_REQUEST[$rl_html_name] : "";


  $k2null = [
    'kw_link_id',
    'item_id',
    'containerID',
    'testsuiteID',
    'parent_tsuite_id',
    'objectID'
  ];
  foreach ($k2null as $prop) {
    $args->$prop = isset($_REQUEST[$prop]) ? intval($_REQUEST[$prop]) : null;    
  }

  if (null == $args->containerID) {
    $args->containerID = $args->objectID;
  }

  $k2c = [
    'objectType',
    'containerType',
    'testsuiteName',
    'free_keywords'
  ];
  foreach ($k2c as $prop) {
    $args->$prop = isset($_REQUEST[$prop]) ? $_REQUEST[$prop] : null; 
  }

  // check against whitelist
  if( null != $args->containerType ) {
    $whiteList = array('testproject' => 'OK','testsuite' => 'OK');
    if(!is_null($args->containerType) && !isset($whiteList[$args->containerType])) {
      $args->containerType = null;  
    }      
  }

  // When Deleting Test suite - container ID is not set
  if( is_null($args->containerID) ) {
    $args->containerType = is_null($args->containerType) ? 'testproject' : $args->containerType;
  }

  if( null == $args->containerType ) {
    throw new Exception("Error No Container Type", 1);    
  }

  $args->tsuite_name = null;
  switch( $args->containerType ) {
    case 'testproject':
      $args->tproject_id = $args->containerID;
    break;

    case 'testsuite':
      $nodeID = !is_null($args->testsuiteID) ? $args->testsuiteID : $args->containerID;

      if( isset($_REQUEST['tproject_id']) ) {
        $args->tproject_id = intval($_REQUEST['tproject_id']);
      }
      if( isset($_REQUEST['tprojectID']) ) {
        $args->tproject_id = intval($_REQUEST['tprojectID']);
      }    
  
      if( intval($args->tproject_id) == 0 ) {
        $args->tproject_id = $tsuiteMgr->getTestProjectFromTestSuite($nodeID,null);
      }
      $hipo = $tsuiteMgr->tree_manager->get_node_hierarchy_info($nodeID);
      $args->tsuite_name = $hipo['name'];
    break;
  }

  if( intval($args->tproject_id) == 0 ) {
    if( isset($_REQUEST['tproject_id']) ) {
      $args->tproject_id = intval($_REQUEST['tproject_id']);
    }

    if( isset($_REQUEST['tprojectID']) ) {
      $args->tproject_id = intval($_REQUEST['tprojectID']);
    }    
  }

  // very ugly
  $args->level = null; 
  if( isset($_REQUEST['delete_testsuite']) ) {
    $args->level = $args->objectType; 
  }


  $info = $tprojectMgr->get_by_id($args->tproject_id,['output' => 'name']);
  $args->tprojectName = $info['name'];
  $args->tprojOpt = $tprojectMgr->getOptions($args->tproject_id);

  $args->userID = isset($_SESSION['userID']) ? intval($_SESSION['userID']) : 0;
  $args->file_id = isset($_REQUEST['file_id']) ? intval($_REQUEST['file_id']) : 0;
  $args->fileTitle = isset($_REQUEST['fileTitle']) ? trim($_REQUEST['fileTitle']) : '';


  $k2l = array('tc_status','importance','execution_type');
  foreach($k2l as $kv) {
    $args->$kv = isset($_REQUEST[$kv]) ? intval($_REQUEST[$kv]) : -1;
  }  

  $args->user = $_SESSION['currentUser'];

  $args->grants = new stdClass();
  $args->grants->delete_executed_testcases = 
    $args->user->hasRight($dbHandler,'testproject_delete_executed_testcases',$args->tproject_id);

  $args->grants->testcase_mgmt = 
    $args->user->hasRight($dbHandler,'mgt_modify_tc',$args->tproject_id);


  $keys2loop=array('nodes_order' => null, 'tcaseSet' => null,
                   'target_position' => 'bottom', 'doAction' => '');
  foreach($keys2loop as $key => $value) {
    $args->$key = isset($_REQUEST[$key]) ? $_REQUEST[$key] : $value;
  }

  $args->bSure = (isset($_REQUEST['sure']) && ($_REQUEST['sure'] == 'yes'));


  $keys2loop = array('copyKeywords' => 0,'copyRequirementAssignments' => 0);
  foreach($keys2loop as $key => $value) {
    $args->$key = isset($_REQUEST[$key]) ? intval($_REQUEST[$key]) : $value;
  }

  $ks = 'setting_refresh_tree_on_action';
  $args->refreshTree = isset($_SESSION[$ks]) ? $_SESSION[$ks] : 0;

  $args->treeFormToken = isset($_REQUEST['form_token']) ? $_REQUEST['form_token'] : 0;
  $args->testCaseSet = null;

  if($args->treeFormToken >0) {  
    $mode = 'edit_mode';
    $sdata = isset($_SESSION[$mode]) && isset($_SESSION[$mode][$args->treeFormToken]) ? 
             $_SESSION[$mode][$args->treeFormToken] : null;
    $args->testCaseSet = isset($sdata['testcases_to_show']) ? $sdata['testcases_to_show'] : null;
  }

  $dummy = ($sortCriteria = config_get('testcase_reorder_by')) == 'NAME' ? '_alpha' : 
           '_externalid';
  $lbl2init = array('warning_empty_testsuite_name' => null,
                    'string_contains_bad_chars' => null,
                    'container_title_testsuite' => null,
                    'btn_reorder_testcases' => 'btn_reorder_testcases' . $dummy);
  $args->l10n = init_labels($lbl2init);

  return array($args,$optTCfg);
}


/*
 function:

args:

returns:

*/
function writeCustomFieldsToDB(&$db,$tprojectID,$tsuiteID,&$hash)
{
  $ENABLED = 1;
  $NO_FILTERS = null;

  $cfield_mgr = new cfield_mgr($db);
  $cf_map = $cfield_mgr->get_linked_cfields_at_design($tprojectID,$ENABLED,
                                                      $NO_FILTERS,'testsuite');
  $cfield_mgr->design_values_to_db($hash,$tsuiteID,$cf_map);
}


/**
 * function: deleteTestSuite
 *
 *returns: true -> refresh tree
 *         false -> do not refresh
 *
 */
function deleteTestSuite(&$smartyObj,&$argsObj,&$tsuiteMgr,&$treeMgr,&$tcaseMgr,$level)
{

  $feedback_msg = '';
  $system_message = '';
  $testcase_cfg = config_get('testcase_cfg');
  $can_delete = 1;

  if($argsObj->bSure) {
    $tsuite = $tsuiteMgr->get_by_id($argsObj->objectID);
    $tsuiteMgr->delete_deep($argsObj->objectID);
    $tsuiteMgr->deleteKeywords($argsObj->objectID);
    $smartyObj->assign('objectName', $tsuite['name']);
    $doRefreshTree = true;
    $feedback_msg = 'ok';
    $um = sprintf(lang_get('testsuite_deleted'),$tsuite['name']);
    $smartyObj->assign('user_feedback',$um);
  }
  else {
    $doRefreshTree = false;

    // Get test cases present in this testsuite and all children
    $testcases = $tsuiteMgr->get_testcases_deep($argsObj->testsuiteID);
    $map_msg['warning'] = null;
    $map_msg['link_msg'] = null;
    $map_msg['delete_msg'] = null;

    if(is_null($testcases) || count($testcases) == 0) {
      $can_delete = 1;
    }
    else {
      $map_msg = build_del_testsuite_warning_msg($treeMgr,$tcaseMgr,$testcases,$argsObj->testsuiteID);
      if( in_array('linked_and_executed', (array)$map_msg['link_msg']) ) {
        $can_delete = $argsObj->grants->delete_executed_testcases;
      }
    }

    $system_message = '';
    if(!$can_delete && !$argsObj->grants->delete_executed_testcases) {
      $system_message = lang_get('system_blocks_tsuite_delete_due_to_exec_tc');
    }

    // prepare to show the delete confirmation page
    $smartyObj->assign('can_delete',$can_delete);
    $smartyObj->assign('objectID',$argsObj->testsuiteID);
    $smartyObj->assign('objectType','testsuite');
    $smartyObj->assign('objectName', $argsObj->tsuite_name);
    $smartyObj->assign('delete_msg',$map_msg['delete_msg']);
    $smartyObj->assign('warning', $map_msg['warning']);
    $smartyObj->assign('link_msg', $map_msg['link_msg']);
  }
  $smartyObj->assign('system_message', $system_message);
  $smartyObj->assign('page_title', lang_get('delete') . " " . lang_get('container_title_' . $level));
  $smartyObj->assign('sqlResult',$feedback_msg);

  return $doRefreshTree;
}

/**
 * function: addTestSuite
 *
 * returns: map with messages and status
 *
 */
function addTestSuite(&$tsuiteMgr,&$argsObj,$container,&$hash) {
  $new_order = null;
  $ret = $tsuiteMgr->create($argsObj->containerID,
            $container['container_name'],
            $container['details'],
            $new_order,config_get('check_names_for_duplicates'),'block');
   
  $op['messages']= array('msg' => $ret['msg'], 'user_feedback' => '');
  $op['status']=$ret['status_ok'];

  if ($ret['status_ok']) {
    $op['messages']['user_feedback'] = lang_get('testsuite_created');
    if($op['messages']['msg'] != 'ok') {
        $op['messages']['user_feedback'] = $op['messages']['msg'];
    }

    if(trim($argsObj->assigned_keyword_list) != "") {
      $tsuiteMgr->addKeywords($ret['id'],explode(",",$argsObj->assigned_keyword_list));
    }
    writeCustomFieldsToDB($tsuiteMgr->db,$argsObj->tproject_id,$ret['id'],$hash);

    // Send Events to plugins 
    $ctx = array('id' => $ret['id'],
                 'name' => $container['container_name'],
                 'details' => $container['details']);
    event_signal('EVENT_TEST_SUITE_CREATE', $ctx);
  }
  return $op;
}

/*
 function: moveTestSuiteViewer
prepares smarty variables to display move testsuite viewer

args:

returns: -

*/
function  moveTestSuiteViewer(&$smartyObj,&$tprojectMgr,$argsObj) {
    $testsuites = $tprojectMgr->gen_combo_test_suites($argsObj->tproject_id,[$argsObj->testsuiteID => 'exclude']);

    // Added the Test Project as the FIRST Container where is possible to copy
    $testsuites = [$argsObj->tproject_id => $argsObj->tprojectName] + $testsuites;

    // original container (need to comment this better)
    $smartyObj->assign('old_containerID', $argsObj->tproject_id);
    $smartyObj->assign('containers', $testsuites);
    $smartyObj->assign('objectID', $argsObj->testsuiteID);
    $smartyObj->assign('object_name', $argsObj->tsuite_name);
    $smartyObj->assign('top_checked','checked=checked');
    $smartyObj->assign('bottom_checked','');

    return initializeGui($tprojectMgr,$argsObj->tproject_id,$argsObj);
}


/*
 function: reorderTestSuiteViewer
prepares smarty variables to display reorder testsuite viewer

args:

returns: -

*/
function  reorderTestSuiteViewer(&$smartyObj,&$treeMgr,$argsObj)
{
    $level = null;
    $oid = is_null($argsObj->testsuiteID) ? $argsObj->containerID : $argsObj->testsuiteID;
    $children = $treeMgr->get_children($oid, ["testplan" => "exclude_me",
                                              "requirement_spec"  => "exclude_me"]);
    if (!sizeof($children)) {  
      $children = null;
    }
    $object_info = $treeMgr->get_node_hierarchy_info($oid);
    $object_name = $object_info['name'];

    $smartyObj->assign('arraySelect', $children);
    $smartyObj->assign('objectID', $oid);
    $smartyObj->assign('object_name', $object_name);

    if($oid == $argsObj->tproject_id) {
      $level = 'testproject';
      $smartyObj->assign('level', $level);
      $smartyObj->assign('page_title',lang_get('container_title_' . $level));
    }

    return $level;
}


/**
 * function: updateTestSuite
 *
 */
function updateTestSuite(&$tsuiteMgr,&$argsObj,$container,&$hash) {
  $msg = 'ok';
  $ret = $tsuiteMgr->update($argsObj->testsuiteID,$container['container_name'],$container['details']);
  if($ret['status_ok']) {
    $tsuiteMgr->deleteKeywords($argsObj->testsuiteID);
    if(trim($argsObj->assigned_keyword_list) != "") {
      $tsuiteMgr->addKeywords($argsObj->testsuiteID,explode(",",$argsObj->assigned_keyword_list));
    }
    writeCustomFieldsToDB($tsuiteMgr->db,$argsObj->tproject_id,$argsObj->testsuiteID,$hash);

    /* Send events to plugins */
    $ctx = array('id' => $argsObj->testsuiteID,'name' => $container['container_name'],'details' => $container['details']);
    event_signal('EVENT_TEST_SUITE_UPDATE', $ctx);
  }
  else
  {
    $msg = $ret['msg'];
  }
  return $msg;
}

/*
 function: copyTestSuite

args:

returns:

*/
function copyTestSuite(&$smartyObj,$template_dir,&$tsuiteMgr,$argsObj) {
  $guiObj = new stdClass();
  $guiObj->btn_reorder_testcases = $argsObj->l10n['btn_reorder_testcases'];;

  $exclude_node_types=array('testplan' => 1, 'requirement' => 1, 'requirement_spec' => 1);
     
  $options = [];
  $options['check_duplicate_name'] = config_get('check_names_for_duplicates');
  $options['action_on_duplicate_name'] = config_get('action_on_duplicate_name');
  $options['copyKeywords'] = $argsObj->copyKeywords;
  $options['copyRequirements'] = $argsObj->copyRequirementAssignments;


  // copy_to($source,$destination,...)
  $op = $tsuiteMgr->copy_to($argsObj->objectID, $argsObj->containerID, $argsObj->userID,$options);

  if( $op['status_ok'] ) {
    $tsuiteMgr->tree_manager->change_child_order($argsObj->containerID,$op['id'],
                                                 $argsObj->target_position,$exclude_node_types);


    // get info to provide feedback
    $dummy = $tsuiteMgr->tree_manager->get_node_hierarchy_info(array($argsObj->objectID, $argsObj->containerID));

    $msgk = $op['name_changed'] ? 'tsuite_copied_ok_name_changed' : 'tsuite_copied_ok';
    $guiObj->user_feedback = sprintf(lang_get($msgk),$dummy[$argsObj->objectID]['name'],
                                     $dummy[$argsObj->containerID]['name'],$op['name']);
  }
  $guiObj->refreshTree = $op['status_ok'] && $argsObj->refreshTree;
  $guiObj->attachments = getAttachmentInfosFrom($tsuiteMgr,$argsObj->objectID);
  $guiObj->id = $argsObj->objectID;
  $guiObj->tproject_id = $argsObj->tproject_id;
  $guiObj->treeFormToken = $guiObj->form_token = $argsObj->treeFormToken;
  
  
  $guiObj->direct_link = $tsuiteMgr->buildDirectWebLink((object)['basehref' => $_SESSION['basehref'],
                                                                 'id' => $guiObj->id,
                                                                 'tproject_id' => $guiObj->tproject_id]);

  $tsuiteMgr->show($smartyObj,$guiObj,$template_dir,$argsObj->objectID,null,'ok');

  return $op['status_ok'];
}

/*
 function: moveTestSuite

args:

returns:

*/
function moveTestSuite(&$smartyObj,$template_dir,&$tprojectMgr,$argsObj)
{
    $exclude_node_types=array('testplan' => 1, 'requirement' => 1, 'requirement_spec' => 1);

    $tprojectMgr->tree_manager->change_parent($argsObj->objectID,$argsObj->containerID);
    $tprojectMgr->tree_manager->change_child_order($argsObj->containerID,$argsObj->objectID,
                    $argsObj->target_position,$exclude_node_types);

    $guiObj = new stdClass();
    $guiObj->id = $argsObj->tproject_id;
    $guiObj->refreshTree = $argsObj->refreshTree;

    $tprojectMgr->show($smartyObj,$guiObj,$template_dir,$argsObj->tproject_id,null,'ok');
    exit();
}


/*
 function: initializeOptionTransfer

args:

returns: option transfer configuration

*/
function initializeOptionTransfer(&$tprojectMgr,&$tsuiteMgr,$argsObj,$doAction) {
  $opt_cfg = opt_transf_empty_cfg();
  $opt_cfg->js_ot_name='ot';
  $opt_cfg->global_lbl='';
  $opt_cfg->from->lbl=lang_get('available_kword');
  $opt_cfg->from->map = $tprojectMgr->get_keywords_map($argsObj->tproject_id);
  $opt_cfg->to->lbl=lang_get('assigned_kword');

  if($doAction=='edit_testsuite') {
    $opt_cfg->to->map = $tsuiteMgr->get_keywords_map($argsObj->testsuiteID," ORDER BY keyword ASC ");
  }
  return $opt_cfg;
}


/*
 function: moveTestCasesViewer
prepares smarty variables to display move testcases viewer

args:

returns: 

*/
function moveTestCasesViewer(&$dbHandler,&$smartyObj,&$tprojectMgr,&$treeMgr,
                             $argsObj,$feedback='',$cf=null)
{
  $gui = new stdClass();
  $gui->treeFormToken = $gui->form_token = $argsObj->treeFormToken; 


  $tables = $tprojectMgr->getDBTables(['nodes_hierarchy','node_types','tcversions']);
  $testcase_cfg = config_get('testcase_cfg');
  $glue = $testcase_cfg->glue_character;

  $containerID = isset($argsObj->testsuiteID) ? $argsObj->testsuiteID : $argsObj->objectID;
  $dummy = $treeMgr->get_node_hierarchy_info($containerID);
  $gui->tsuiteName = $dummy['name'];

  $dummy = $treeMgr->get_node_hierarchy_info($argsObj->tproject_id);
  $gui->tprojectName = $dummy['name'];
  

 
  // I'have discovered that exclude selected testsuite branch is not good
  // when you want to move lots of testcases from one testsuite to it's children
  // testsuites. (in this situation tree drag & drop is not ergonomic).
  $testsuites = $tprojectMgr->gen_combo_test_suites($argsObj->tproject_id);
  $tcasePrefix = $tprojectMgr->getTestCasePrefix($argsObj->tproject_id) . $glue;

  // While testing with PostGres have found this behaivour:
  // No matter is UPPER CASE has used on field aliases, keys on hash returned by
  // ADODB are lower case.
  // Accessing this keys on Smarty template using UPPER CASE fails.
  // Solution: have changed case on Smarty to lower case.
  //
  $sqlA = " SELECT MAX(TCV.version) AS lvnum, NHTC.node_order, NHTC.name," . 
          " NHTC.id, TCV.tc_external_id AS tcexternalid" .
          " FROM {$tables['nodes_hierarchy']} NHTC " .
          " JOIN {$tables['nodes_hierarchy']} NHTCV " . 
          " ON NHTCV.parent_id = NHTC.id " .
          " JOIN {$tables['tcversions']} TCV ON TCV.id = NHTCV.id " .
          " JOIN {$tables['node_types']} NT " . 
          " ON NT.id = NHTC.node_type_id AND NT.description='testcase'" .
          " WHERE NHTC.parent_id = " . intval($containerID);

  if( !is_null($argsObj->testCaseSet) ) {
    $sqlA .= " AND NHTC.id IN (" . implode(',', $argsObj->testCaseSet). ")";
  }        

  $sqlA .=" GROUP BY NHTC.id,TCV.tc_external_id,NHTC.name,NHTC.node_order ";

  $sqlB = " SELECT SQLA.id AS tcid, SQLA.name AS tcname," . 
          " SQLA.node_order AS tcorder, SQLA.tcexternalid," . 
          " MTCV.summary,MTCV.status,MTCV.importance,MTCV.execution_type," .
          " MTCV.id AS tcversion_id FROM ($sqlA) SQLA " .
          " JOIN {$tables['nodes_hierarchy']} MNHTCV ON MNHTCV.parent_id = SQLA.id " .
          " JOIN {$tables['tcversions']} MTCV ON MTCV.id = MNHTCV.id AND MTCV.version = SQLA.lvnum";
  $orderClause = " ORDER BY TCORDER,TCNAME";        

  $children = $dbHandler->get_recordset($sqlB . $orderClause);

  // check if operation can be done
  $user_feedback = $feedback;
  if(!is_null($children) && (sizeof($children) > 0) && sizeof($testsuites)) {
    $op_ok = true;
  } else {
    $children = null;
    $op_ok = false;
    $user_feedback = lang_get('no_testcases_available_or_tsuite');
  }



  $dummy = getConfigAndLabels('testCaseStatus','code');
  $gui->domainTCStatus = array(-1 => '') + $dummy['lbl'];
  $gui->domainTCImportance = array(-1 => '', 
                                   HIGH => lang_get('high_importance'), 
                                   MEDIUM => lang_get('medium_importance'), 
                                   LOW => lang_get('low_importance'));

  $dummy = getConfigAndLabels('execution_type','code');
  $gui->domainTCExecType = array(-1 => '') + $dummy['lbl'];

  $gui->testCasesTableView = 0;
  if(($argsObj->action == 'testcases_table_view') || 
     ($argsObj->action == 'doBulkSet')) {
    $gui->testCasesTableView = 1;
  }  
  $gui->cf = $cf;
  
  $gui->tcprefix = $tcasePrefix;
  $gui->testcases = $children;

  $smartyObj->assign('gui', $gui);
  $smartyObj->assign('op_ok', $op_ok);
  $smartyObj->assign('user_feedback', $user_feedback);
  
  //check if is needed
  $smartyObj->assign('old_containerID', $argsObj->tproject_id); 
  $smartyObj->assign('containers', $testsuites);
  $smartyObj->assign('objectID', $containerID);
  // $smartyObj->assign('object_name', $containerName);
  $smartyObj->assign('top_checked','checked=checked');
  $smartyObj->assign('bottom_checked','');

  return $gui;
}


/*
 function: copyTestCases
copy a set of choosen test cases.

args:

returns: -

*/
function copyTestCases(&$smartyObj,$template_dir,&$tsuiteMgr,&$tcaseMgr,$argsObj)
{
  $op = array('refreshTree' => false, 'userfeedback' => '');
  if( ($qty=sizeof($argsObj->tcaseSet)) > 0)
  {
    $msg_id = $qty == 1 ? 'one_testcase_copied' : 'testcase_set_copied';
    $op['userfeedback'] = sprintf(lang_get($msg_id),$qty);

    $copyOpt = array(
      'check_duplicate_name' => config_get('check_names_for_duplicates'),
      'action_on_duplicate_name' => config_get('action_on_duplicate_name'),
      'stepAsGhost' => $argsObj->stepAsGhost);
    $copyOpt['copy_also'] = 
      array('keyword_assignments' => $argsObj->copyKeywords,
            'requirement_assignments' => 
              $argsObj->copyRequirementAssignments);

    $copy_op =array();
    foreach($argsObj->tcaseSet as $key => $tcaseid) {
      $copy_op[$tcaseid] = $tcaseMgr->copy_to($tcaseid, $argsObj->containerID, $argsObj->userID, $copyOpt);
    }
    $guiObj = new stdClass();
    $guiObj->attachments = getAttachmentInfosFrom($tsuiteMgr,$argsObj->objectID);
    $guiObj->id = $argsObj->objectID;
    $guiObj->refreshTree = true;
    $op['refreshTree'] = true;
  }
  return $op;
}


/*
 function: moveTestCases
move a set of choosen test cases.

args:

returns: -

*/
function moveTestCases(&$smartyObj,$template_dir,&$tsuiteMgr,&$treeMgr,$argsObj,$lbl)
{
    if(sizeof($argsObj->tcaseSet) > 0)
    {
        $status_ok = $treeMgr->change_parent($argsObj->tcaseSet,$argsObj->containerID);
        $user_feedback= $status_ok ? '' : lang_get('move_testcases_failed');

        // objectID - original container
        $guiObj = new stdClass();
        $guiObj->attachments = getAttachmentInfosFrom($tsuiteMgr,$argsObj->objectID);
        $guiObj->id = $argsObj->objectID;
        $guiObj->refreshTree = true;
        $guiObj->btn_reorder_testcases = $lbl['btn_reorder_testcases'];

        $tsuiteMgr->show($smartyObj,$guiObj,$template_dir,$argsObj->objectID,null,$user_feedback);
    }
}


/**
 * initWebEditors
 *
 */
function initWebEditors($action,$itemType,$editorCfg) {
  $webEditorKeys = array('testsuite' => array('details'));
  $itemTemplateKey=null;

  switch($action) {
    case 'new_testsuite':
    case 'add_testsuite':
    case 'edit_testsuite':
      $accessKey = 'testsuite';
    break;

    default:
      $accessKey = '';
    break;
  }


  switch($itemType) {
    case 'testproject': 
    case 'testsuite':
      $itemTemplateKey = 'testsuite_template';
      $accessKey = 'testsuite';
    break;
  }

  $oWebEditor = array();
  $htmlNames = '';
  if( isset($webEditorKeys[$accessKey]) ) {  
    $htmlNames = $webEditorKeys[$accessKey];
    foreach ($htmlNames as $key) {
      $oWebEditor[$key] = web_editor($key,$_SESSION['basehref'],$editorCfg);
    }
  }
  return array($oWebEditor,$htmlNames,$itemTemplateKey);
}




/*
 function: deleteTestCasesViewer
prepares smarty variables to display move testcases viewer

args:

returns: -

@internal revisions
20110402 - franciscom - BUGID 4322: New Option to block delete of executed test cases.
*/
function deleteTestCasesViewer(&$dbHandler,&$smartyObj,&$tprojectMgr,&$treeMgr,&$tsuiteMgr,
                               &$tcaseMgr,$argsObj,$feedback = null)
{

    $guiObj = new stdClass();
    $guiObj->main_descr = lang_get('delete_testcases');
    $guiObj->system_message = '';


    $tables = $tprojectMgr->getDBTables(array('nodes_hierarchy','node_types','tcversions'));
    $testcase_cfg = config_get('testcase_cfg');
    $glue = $testcase_cfg->glue_character;

    $containerID = isset($argsObj->testsuiteID) ? $argsObj->testsuiteID : $argsObj->objectID;
    $containerName = $argsObj->tsuite_name;
    if( is_null($containerName) ) {
      $dummy = $treeMgr->get_node_hierarchy_info($argsObj->objectID);
      $containerName = $dummy['name'];
    }

    $guiObj->testCaseSet = $tsuiteMgr->get_children_testcases($containerID);
    $guiObj->exec_status_quo = null;
    $tcasePrefix = $tprojectMgr->getTestCasePrefix($argsObj->tproject_id);
    $hasExecutedTC = false;

    if( !is_null($guiObj->testCaseSet) && count($guiObj->testCaseSet) > 0)
    {
      foreach($guiObj->testCaseSet as &$child)
      {
        $external = $tcaseMgr->getExternalID($child['id'],null,$tcasePrefix);
        $child['external_id'] = $external[0];
              
        // key level 1 : Test Case Version ID
        // key level 2 : Test Plan  ID
        // key level 3 : Platform ID
        $getOptions = array('addExecIndicator' => true);
        $dummy = $tcaseMgr->get_exec_status($child['id'],null,$getOptions);
        $child['draw_check'] = $argsObj->grants->delete_executed_testcases || (!$dummy['executed']);

        $hasExecutedTC = $hasExecutedTC || $dummy['executed'];
        unset($dummy['executed']);
        $guiObj->exec_status_quo[] = $dummy;
      }
    }
    // Need to understand if platform column has to be displayed on GUI
    if( !is_null($guiObj->exec_status_quo) )
    {
      // key level 1 : Test Case Version ID
      // key level 2 : Test Plan  ID
      // key level 3 : Platform ID

      $itemSet = array_keys($guiObj->exec_status_quo);
      foreach($itemSet as $mainKey)
      {
        $guiObj->display_platform[$mainKey] = false;
        if(!is_null($guiObj->exec_status_quo[$mainKey]) )
        {
          $versionSet = array_keys($guiObj->exec_status_quo[$mainKey]);
          $stop = false;
          foreach($versionSet as $version_id)
          {
            $tplanSet = array_keys($guiObj->exec_status_quo[$mainKey][$version_id]);
            foreach($tplanSet as $tplan_id)
            {
              if( ($guiObj->display_platform[$mainKey] = !isset($guiObj->exec_status_quo[$mainKey][$version_id][$tplan_id][0])) )
              {
                $stop = true;
                break;
              }
            }
       
            if($stop)
            {
              break;
            }
          }
        }
      }
    }
    // check if operation can be done
    $guiObj->user_feedback = $feedback;
    if(!is_null($guiObj->testCaseSet) && (sizeof($guiObj->testCaseSet) > 0) )
    {
      $guiObj->op_ok = true;
      $guiObj->user_feedback = '';
    }
    else
    {
      $guiObj->children = null;
      $guiObj->op_ok = false;
      $guiObj->user_feedback = is_null($guiObj->user_feedback) ? lang_get('no_testcases_available') : $guiObj->user_feedback;
    }

    if(!$argsObj->grants->delete_executed_testcases && $hasExecutedTC)
    {
      $guiObj->system_message = lang_get('system_blocks_delete_executed_tc');
    }

    $guiObj->objectID = $containerID;
    $guiObj->object_name = $containerName;
    $guiObj->refreshTree = $argsObj->refreshTree;

    $smartyObj->assign('gui', $guiObj);

    return $guiObj;
}


/*
 function: doDeleteTestCasesViewer
prepares smarty variables to display move testcases viewer

args:

returns: -

*/
function doDeleteTestCases(&$dbHandler,$tcaseSet,&$tcaseMgr)
{
  if( count($tcaseSet) > 0 )
  {
    foreach($tcaseSet as $victim)
    {
      $tcaseMgr->delete($victim);
    }
  }
}


/**
 *
 *
 */
function reorderTestCasesByCriteria($argsObj,&$tsuiteMgr,&$treeMgr) {
  $sortCriteria = config_get('testcase_reorder_by');
  $pfn = ($sortCriteria == 'NAME') ? 'reorderTestCasesDictionary' : 'reorderTestCasesByExtID';
  $pfn($argsObj,$tsuiteMgr,$treeMgr);
}


/**
 *
 *
 */
function reorderTestCasesDictionary($argsObj,&$tsuiteMgr,&$treeMgr)
{
  $tcaseSet = (array)$tsuiteMgr->get_children_testcases($argsObj->testsuiteID,'simple');
  if( ($loop2do = count($tcaseSet)) > 0 )
  {
    for($idx=0; $idx < $loop2do; $idx++)
    {
      $a2sort[$tcaseSet[$idx]['id']] = strtolower($tcaseSet[$idx]['name']);
    }
    natsort($a2sort);
    $a2sort = array_keys($a2sort);
    $treeMgr->change_order_bulk($a2sort);
  }
}


/**
 *
 *
 */
function reorderTestCasesByExtID($argsObj,&$tsuiteMgr,&$treeMgr)
{
    $tables = $tsuiteMgr->getDBTables(array('nodes_hierarchy','testsuites','tcversions'));

    $sql = " SELECT DISTINCT NHTC.id,TCV.tc_external_id " .
                    " FROM {$tables['nodes_hierarchy']} NHTC " .
                    " JOIN {$tables['nodes_hierarchy']} NHTCV ON NHTCV.parent_id = NHTC.id " .
                    " JOIN {$tables['tcversions']} TCV ON TCV.id = NHTCV.id " .
                    " JOIN {$tables['testsuites']} TS ON NHTC.parent_id = TS.id " .
                    " WHERE TS.id = " . intval($argsObj->testsuiteID) .
                    " ORDER BY tc_external_id ASC";

    $tcaseSet = $tsuiteMgr->db->fetchColumnsIntoMap($sql,'tc_external_id','id');
    $treeMgr->change_order_bulk($tcaseSet);
}



/**
 *
 *
 */
function reorderTestSuitesDictionary($args,$treeMgr,$parent_id)
{
  $exclude_node_types = array('testplan' => 1, 'requirement' => 1, 'testcase' => 1, 'requirement_spec' => 1);
  $itemSet = (array)$treeMgr->get_children($parent_id,$exclude_node_types);
  if( ($loop2do = count($itemSet)) > 0 )
  {
    for($idx=0; $idx < $loop2do; $idx++)
    {
      $a2sort[$itemSet[$idx]['id']] = strtolower($itemSet[$idx]['name']);
    }
    natsort($a2sort);
    $a2sort = array_keys($a2sort);
    $treeMgr->change_order_bulk($a2sort);
  }
}

/**
 *
 */
function initializeGui(&$objMgr,$id,$argsObj,$lbl=null) {

  list($add2args,$guiObj) = initUserEnv($objMgr->db,$argsObj);

  $labels = $lbl;
  if( null == $labels ) {
    $labels = $argsObj->l10n;
  }

  $guiObj->id = $id;
  $guiObj->user = $argsObj->user;
  $guiObj->tproject_id = $argsObj->tproject_id;
  $guiObj->refreshTree = $argsObj->refreshTree;
  $guiObj->btn_reorder_testcases = $labels['btn_reorder_testcases'];
  $guiObj->page_title = $labels['container_title_testsuite'];
  $guiObj->attachments = getAttachmentInfosFrom($objMgr,$id);
  $guiObj->form_token = $argsObj->treeFormToken;

  $guiObj->fileUploadURL = $_SESSION['basehref'] . $objMgr->getFileUploadRelativeURL($id);

  if( $objMgr->my_node_type == $objMgr->node_types_descr_id['testsuite'] ) {
    $guiObj->direct_link = $objMgr->buildDirectWebLink((object)['basehref' => $_SESSION['basehref'],
                                                                'id' => $guiObj->id,
                                                                'tproject_id' => $guiObj->tproject_id]);
  }  
  return $guiObj;
}

/**
 *
 *
 */
function doBulkSet(&$dbHandler,$argsObj,$tcaseSet,&$tcaseMgr)
{
  if( count($tcaseSet) > 0 ) {
    
    $k2s = [
       'tc_status' => 'setStatus',
       'importance' => 'setImportance',
       'execution_type' => 'setExecutionType'
    ];
    
    foreach($tcaseSet as $tcversion_id => $tcase_id) {
      foreach($k2s as $attr => $m2c) {
        if($argsObj->$attr >0) {
          $tcaseMgr->$m2c($tcversion_id,$argsObj->$attr);
        }  
      } 
    }

    // second round, on Custom Fields
    $cf_map = $tcaseMgr->cfield_mgr->get_linked_cfields_at_design($argsObj->tproject_id,ENABLED,NO_FILTER_SHOW_ON_EXEC,'testcase');
    $valuesFromUX = [];
    if( !is_null($cf_map) ) {
      // get checkboxes from $_REQUEST
      $k2i = array_keys($_REQUEST);
      $cfDefinition = null;
      foreach($k2i as $val) { 
        if(strpos($val,'check_custom_field_') !== FALSE) {
          $cfid = end(explode('_',$val));
          $cfDefinition[$cfid] = $cf_map[$cfid];

          $accessToValue = str_replace('check_custom_field_','custom_field_',$val);
          $valuesFromUX[$accessToValue] = $_REQUEST[$accessToValue];
        }  
      }
      
      if(!is_null($cfDefinition)) {
        foreach($tcaseSet as $tcversion_id => $tcase_id) {
          $tcaseMgr->cfield_mgr->design_values_to_db($valuesFromUX,$tcversion_id,$cfDefinition);
        }
      }  
    }  
  }
}


/**
 *
 */
function initTPLActions() {

  $tpl = [
    'move_testsuite_viewer' => 'containerMove.tpl',
    'delete_testsuite' => 'containerDelete.tpl',
    'move_testcases_viewer' => 'containerMoveTC.tpl',
    'testcases_table_view' => 'containerMoveTC.tpl',
    'do_copy_tcase_set' => 'containerMoveTC.tpl',
    'do_copy_tcase_set_ghost' => 'containerMoveTC.tpl',
    'delete_testcases' =>  'containerDeleteTC.tpl',
    'do_delete_testcases' =>  'containerDeleteTC.tpl',
    'doBulkSet' => 'containerMoveTC.tpl'
  ];

  $actions = [
    'edit_testsuite' => 0,
    'new_testsuite' => 0,
    'delete_testsuite' => 0,
    'do_move' => 0,
    'do_copy' => 0,
    'reorder_testsuites' => 1,
    'do_testsuite_reorder' => 0,
    'add_testsuite' => 1,
    'move_testsuite_viewer' => 0,
    'update_testsuite' => 1,
    'move_testcases_viewer' => 0,
    'do_move_tcase_set' => 0,
    'testcases_table_view' => 0,
    'do_copy_tcase_set' => 0, 
    'do_copy_tcase_set_ghost' => 0, 
    'del_testsuites_bulk' => 0,
    'delete_testcases' => 0,
    'do_delete_testcases' => 0, 
    'reorder_testcases' => 0,
    'reorder_testsuites_alpha' => 0, 
    'reorder_testproject_testsuites_alpha' => 0,
    'doBulkSet' => 0
  ];

  return [$tpl,$actions];
}

/**
 *
 */
function showTestSuite(&$tplEngine,&$argsO,&$tsuiteMgr,$tplDir) {
  if( null == $argsO->tproject_id ) {
    $argsO->tproject_id = $tsuiteMgr->getTestproject($argsO->item_id); 
  }
  $gui = initializeGui($tsuiteMgr,$argsO->item_id,$argsO,$argsO->l10n);
  $gui->refreshTree = 0;
  $tsuiteMgr->show($tplEngine,$gui,$tplDir,$argsO->item_id,null,null);
  exit();
}

/**
 *
 */
function cancelActionURL(&$gui,&$args) 
{

  $gui->cancelActionJS = 'location.href=fRoot+' . 
                         "'lib/testcases/archiveData.php?id=" . intval($args->containerID);


  if (property_exists($args, 'tplan_id') ) {
   $gui->cancelActionJS .= "&tplan_id={$args->tplan_id}";
  }

  if (property_exists($args, 'tproject_id') ) {
    $gui->cancelActionJS .= "&tproject_id={$args->tproject_id}";
  }

  $pkg = isset($args->level) ? $args->level : $args->containerType;
  switch($pkg) {
    case 'testproject':
      $gui->cancelActionJS .= "&edit=testproject&level=testproject'";
    break;  

    case 'testsuite':
      $gui->cancelActionJS .= "&edit=testsuite&level=testsuite&containerType=testsuite'";
    break;  
  }  
}
