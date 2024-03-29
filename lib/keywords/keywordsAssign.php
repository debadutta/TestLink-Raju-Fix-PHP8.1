<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * @filesource  keywordsAssign.php
 * @package     TestLink
 * @copyright   2007-2019, TestLink community 
 * @link        http://www.testlink.org/
 * 
 *
**/
require_once("../../config.inc.php");
require_once("common.php");
require_once("opt_transfer.php");
testlinkInitPage($db,false,false,"checkRights");

$templateCfg = templateConfiguration();

list($args,$gui,$opt_cfg) = initScript($db);

$smarty = new TLSmarty();
$tproject_mgr = new testproject($db);
$tcase_mgr = new testcase($db);

$result = null;

// Important Development Notice
// option transfer do the magic on GUI, 
// analizing content of from->map and to->map, is able to populate
// each side as expected.
//
$opt_cfg->global_lbl = '';
$opt_cfg->additional_global_lbl = null;
$opt_cfg->from->lbl = lang_get('available_kword');
$opt_cfg->from->map = $tproject_mgr->get_keywords_map($args->tproject_id);


switch($args->edit) {

  case 'testsuite':
    $opt_cfg->to->lbl = lang_get('kword_to_be_assigned_to_testcases');
    $opt_cfg->to->map = null;

    // We are going to walk all test suites contained
    // in the selected container, and assign/remove keywords on each test case.
    $tsuite_mgr = new testsuite($db);
    $testsuite = $tsuite_mgr->get_by_id($args->id);
    $gui->keyword_assignment_subtitle = lang_get('test_suite') . TITLE_SEP . $testsuite['name'];

    $tcs = null;
    if($args->useFilteredSet) {
      $tcs = $args->tcaseSet;    
    } 

    // The checkbox on UX to assign only to selected test cases is ALWAYS Visible
    // This generates issue when NO filter was applied and the checkbox checked
    if ($tcs == null) {
      $tcs = $tsuite_mgr->get_testcases_deep($args->id,'only_id');
    } 
    

    if( ($loop2do = sizeof($tcs)) ) {
      $gui->can_do = 1;
      if ($args->assignToTestSuite) {
        $result = 'ok';
        
        $glOpt = array('output' => 'thin', 'active' => 1);
     
        for($idx = 0; $idx < $loop2do; $idx++) {
          $ltcv = $tcase_mgr->get_last_version_info($tcs[$idx],$glOpt);
          $latestActiveVersionID = $ltcv['tcversion_id'];
          $statusQuo = current($tcase_mgr->get_versions_status_quo($tcs[$idx],$latestActiveVersionID));
         
          $hasBeenExecuted = intval($statusQuo['executed']) > 0;
          if( $gui->canAddRemoveKWFromExecuted || 
              $hasBeenExecuted == false ) {
            if(is_null($args->keywordArray)) { 
              $tcase_mgr->deleteKeywords($tcs[$idx],$latestActiveVersionID);
            }
            else {
              $tcase_mgr->addKeywords($tcs[$idx],$latestActiveVersionID,$args->keywordArray);
            }             
          }
        }
      }
    }
  break;


  case 'testcase':
    $doRecall = true;
    $gui->can_do = 1;
    
    $tcName = $tcase_mgr->getName($args->id);
    $gui->keyword_assignment_subtitle = lang_get('test_case') . TITLE_SEP . 
                                        $tcName;

    // Now we work only on latest active version.
    // We also need to check if has been executed
    $glOpt = array('output' => 'thin', 'active' => 1);
    $ltcv = $tcase_mgr->get_last_version_info($args->id,$glOpt);
    $latestActiveVersionID = $ltcv['tcversion_id'];
    
    $statusQuo = current($tcase_mgr->get_versions_status_quo($args->id,$latestActiveVersionID));
    $gui->hasBeenExecuted = intval($statusQuo['executed']) > 0;

    if( $args->assignToTestCase && 
        ($gui->canAddRemoveKWFromExecuted || !$gui->hasBeenExecuted) ) {
      $result = 'ok';
      $tcase_mgr->setKeywords($args->id,$latestActiveVersionID,$args->keywordArray);
      $doRecall = !is_null($args->keywordArray);  
    }

    $opt_cfg->to->lbl = lang_get('assigned_kword');
    $opt_cfg->to->map = $doRecall ? 
      $tcase_mgr->get_keywords_map($args->id,$latestActiveVersionID,
                                   array('orderByClause' =>" ORDER BY keyword ASC ")) : null;
  break;
}


keywords_opt_transf_cfg($opt_cfg, $args->keywordList);


$smarty->assign('gui', $gui);
$smarty->assign('sqlResult', $result);
$smarty->assign('opt_cfg', $opt_cfg);
$smarty->display($templateCfg->template_dir . $templateCfg->default_template);


/**
 *
 */ 
function initScript(&$dbH) {

  $optCfg = opt_transf_empty_cfg();
  $optCfg->js_ot_name = 'ot';

  $args = init_args($optCfg);
  $gui = initializeGui($dbH,$args);

  return array($args,$gui,$optCfg);
}


/**
 *
 */ 
function init_args(&$opt_cfg) {
  $rl_html_name = $opt_cfg->js_ot_name . "_newRight";
  
  $iParams = 
    array("id" => array(tlInputParameter::INT_N),
          "edit" => array(tlInputParameter::STRING_N,0,100),
          "assigntestcase" => array(tlInputParameter::STRING_N,0,1),
          "assigntestsuite" => array(tlInputParameter::STRING_N,0,1),
          $rl_html_name => array(tlInputParameter::STRING_N),
          "caller" => array(tlInputParameter::STRING_N,0,6) );
    
  $pParams = R_PARAMS($iParams);
    
  list($context,$env) = initContext();
  $args = new stdClass();
  $args->tproject_id = $context->tproject_id;
  $args->tplan_id = $context->tplan_id;

  $args->id = $pParams["id"];
  $args->edit = $pParams["edit"];
  $args->caller = $pParams["caller"];

  $args->assignToTestCase = ($pParams["assigntestcase"] != "") ? 1 : 0;
  $args->assignToTestSuite = ($pParams["assigntestsuite"] != "") ? 1 : 0;
  $args->useFilteredSet = isset($_REQUEST['useFilteredSet']) ? 1 : 0;                     

  $args->form_token = isset($_REQUEST['form_token']) ? $_REQUEST['form_token'] : 0;
  $args->tcaseSet = isset($_SESSION['edit_mode']) && isset($_SESSION['edit_mode'][$args->form_token]['testcases_to_show']) ? 
                    $_SESSION['edit_mode'][$args->form_token]['testcases_to_show'] : null;

  $args->keywordArray = null;
  $args->keywordList = $pParams[$rl_html_name];
  if ($args->keywordList != "") { 
    $args->keywordArray = explode(",",$args->keywordList);
  }

  $args->user = $_SESSION['currentUser'];
  return $args;
}

/**
 *
 */
function initializeGui(&$dbH,&$argsObj) {
  list($add2args,$guiObj) = initUserEnv($dbH,$argsObj);

  $guiObj->can_do = 0;
  $guiObj->form_token = $argsObj->form_token;
  $guiObj->useFilteredSet = $argsObj->useFilteredSet;
  $guiObj->id = $argsObj->id;
  $guiObj->level = $argsObj->edit;
  $guiObj->keyword_assignment_subtitle = null;

  $guiObj->canAddRemoveKWFromExecuted = 
    $argsObj->user->hasRight($db,
    'testproject_add_remove_keywords_executed_tcversions') ||
    $argsObj->user->hasRight($db,'testproject_edit_executed_testcases');

  return $guiObj;
}


function checkRights(&$db,&$user) {
  return $user->hasRight($db,'keyword_assignment');
}