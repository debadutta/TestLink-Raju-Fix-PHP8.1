{* 
TestLink Open Source Project - http://testlink.sourceforge.net/
@filesource planUrgency.tpl

Smarty template - manage test case urgency
         
*}
{$cfg_section=$smarty.template|basename|replace:".tpl":"" }
{config_load file="input_dimensions.conf" section=$cfg_section}

{$ownURL="lib/plan/planUrgency.php"}
{lang_get var="labels" 
          s='title_plan_urgency, th_testcase, th_urgency, urgency_low, urgency_medium, urgency_high,
             label_set_urgency_ts, btn_set_urgency_tc, urgency_description,testsuite_is_empty,
             priority, importance, execution_history, design,assigned_to'}

{include file="inc_head.tpl"}
{include file="bootstrap.inc.tpl"}
<body>

<h1 class="{#TITLE_CLASS#}">{$gui->pageTitle|escape}</h1>
<div class="workBack">

{if $gui->listTestCases != ''}
  <div class="groupBtn">
    <form method="post" action="{$ownURL}" id="set_urgency">
     <span>{$labels.label_set_urgency_ts}
      <input class="{#BUTTON_CLASS#}" type="submit" 
             name="high_urgency" id="high_urgency"
             value="{$labels.urgency_high}" />
      <input class="{#BUTTON_CLASS#}" type="submit" 
             name="medium_urgency" id="medium_urgency" 
             value="{$labels.urgency_medium}" />
      <input class="{#BUTTON_CLASS#}" type="submit" 
             name="low_urgency" id="low_urgency"
             value="{$labels.urgency_low}" />
      <input type="hidden" name="tplan_id" value="{$gui->tplan_id}" />
      <input type="hidden" name="id" value="{$gui->node_id}" />
      <input type="hidden" 
             name="form_token" id="form_token" 
             value="{$gui->formToken}" />
    </span>
    </form>
  </div>

{* ----------------------------------------------------------------- *}
  <form method="post" action="{$ownURL}" id="set_urgency_tc">
  <input type="hidden" name="tplan_id" value="{$gui->tplan_id}" />
  <input type="hidden" name="id" value="{$gui->node_id}" />
  <input type="hidden" name="form_token" id="form_token" value="{$gui->formToken}" />

  <table class="{#item_view_table#}" id="item_view">
    <thead class="{#item_view_thead#}">
      <tr>
        <th style="text-align: left;">{$labels.th_testcase}</th>
        <th style="text-align: left;">{$labels.assigned_to}</th>
        <th>{$labels.importance}</th>
        <th colspan="3">{$labels.th_urgency}</th>
        <th>{$labels.priority}</th>
      </tr>
    </thead>
    <tbody>
  {foreach item=itemSet from=$gui->listTestCases}
    {$start=true}

    {foreach item=res from=$itemSet}
       {$importance=$res.importance}
       {$urgencyCode=$res.urgency}
       {$priority=$res.priority}
   
      <tr>

      {if $start}
        {$start = false}
          <td style="text-align: left;">
            <img class="clickable" src="{$tlImages.history_small}"
                 onclick="javascript:openExecHistoryWindow({$res.testcase_id},undefined,{$gui->tproject});"
                 title="{$labels.execution_history}" />
            <img class="clickable" src="{$tlImages.edit_icon}"
                 onclick="javascript:openTCaseWindow({$res.testcase_id});"
                 title="{$labels.design}" />
              {$res.tcprefix|escape}{$res.tc_external_id}{$gsmarty_gui->title_separator_1}{$res.name|escape}
          </td>
          <td style="text-align: left;">
              {if $res.assigned_to != ''}
              <img src="{$tlImages.info_small}" title="{$res.first|escape} {$res.last|escape}"> {$res.assigned_to|escape} 
              {/if}
          </td>

          <td>{$gsmarty_option_importance.$importance}</td>
          <td><input type="radio"  
                     name="urgency[{$res.tcversion_id}]"
                     id="urgency[{$res.tcversion_id}]"  
                     value="{$smarty.const.HIGH}" 
                 {if $urgencyCode == $smarty.const.HIGH}  checked="checked"  {/if}  />
            <span style="vertical-align:middle;">{$labels.urgency_high}</span>
          </td>

          <td><input type="radio"  
                     name="urgency[{$res.tcversion_id}]" 
                     id="urgency[{$res.tcversion_id}]" 
                     value="{$smarty.const.MEDIUM}" 
                 {if $urgencyCode == $smarty.const.MEDIUM} checked="checked" {/if}/>
            <span style="vertical-align:middle;">{$labels.urgency_medium}</span>
          </td>
          
          <td><input type="radio"  
                     name="urgency[{$res.tcversion_id}]"
                     id="urgency[{$res.tcversion_id}]"
                     value="{$smarty.const.LOW}" 
                 {if $urgencyCode == $smarty.const.LOW} checked="checked" {/if}  />
            <span style="vertical-align:middle;">{$labels.urgency_low}</span>
          </td>
          <td>{$gsmarty_option_priority.$priority}</td>
      {else}
          <td>&nbsp;</td>
          <td style="text-align: left;">
             {if $res.assigned_to != ''}
               <img src="{$tlImages.info_small}" title="{$res.first|escape} {$res.last|escape}"> {$res.assigned_to|escape}
               <br> &nbsp;  {* dirty way to improve layout ;( *}
             {/if}
          </td>
          <td>&nbsp;</td>
          <td><span style="vertical-align:middle;">&nbsp;</span></td>
          <td><span style="vertical-align:middle;">&nbsp;</span></td>
          <td><span style="vertical-align:middle;">&nbsp;</span></td>
          <td>&nbsp;</td>
      {/if}  
      </tr>
    {/foreach}
  {/foreach}
   </tbody>
  </table>

  <div class="groupBtn">
    <input class="{#BUTTON_CLASS#}" type="submit" 
           name="doIt" id="doIt"
           value="{$labels.btn_set_urgency_tc}" />
  </div>
  </form>
{* -------------------------------------------------------------- *}
  <p>{$labels.urgency_description}</p>
{else}
  <p>{$labels.testsuite_is_empty}</p>
{/if}
</div>
</body>
</html>