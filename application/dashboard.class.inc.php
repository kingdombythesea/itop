<?php
// Copyright (C) 2010-2012 Combodo SARL
//
//   This file is part of iTop.
//
//   iTop is free software; you can redistribute it and/or modify	
//   it under the terms of the GNU Affero General Public License as published by
//   the Free Software Foundation, either version 3 of the License, or
//   (at your option) any later version.
//
//   iTop is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU Affero General Public License for more details.
//
//   You should have received a copy of the GNU Affero General Public License
//   along with iTop. If not, see <http://www.gnu.org/licenses/>

require_once(APPROOT.'application/dashboardlayout.class.inc.php');
require_once(APPROOT.'application/dashlet.class.inc.php');

/**
 * A user editable dashboard page
 *
 * @copyright   Copyright (C) 2010-2012 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
abstract class Dashboard
{
	protected $sTitle;
	protected $sLayoutClass;
	protected $aWidgetsData;
	protected $oDOMNode;
	protected $sId;
	protected $aCells;
	
	public function __construct($sId)
	{
		$this->sLayoutClass = null;
		$this->aCells = array();
		$this->oDOMNode = null;
		$this->sId = $sId;
	}
	
	public function FromXml($sXml)
	{
		$this->aCells = array(); // reset the content of the dashboard
		set_error_handler(array('Dashboard', 'ErrorHandler'));
		$oDoc = new DOMDocument();
		$oDoc->loadXML($sXml);
		restore_error_handler();
		$this->oDOMNode = $oDoc->getElementsByTagName('dashboard')->item(0);
		
		$oLayoutNode = $this->oDOMNode->getElementsByTagName('layout')->item(0);
		$this->sLayoutClass = $oLayoutNode->textContent;
		
		$oTitleNode = $this->oDOMNode->getElementsByTagName('title')->item(0);
		$this->sTitle = $oTitleNode->textContent;
		
		$oCellsNode = $this->oDOMNode->getElementsByTagName('cells')->item(0);
		$oCellsList = $oCellsNode->getElementsByTagName('cell');
		$aCellOrder = array();
		$iCellRank = 0;
		foreach($oCellsList as $oCellNode)
		{
			$aDashletList = array();
			$oCellRank =  $oCellNode->getElementsByTagName('rank')->item(0);
			if ($oCellRank)
			{
				$iCellRank = (float)$oCellRank->textContent;
			}
			$oDashletsNode = $oCellNode->getElementsByTagName('dashlets')->item(0);
			$oDashletList = $oDashletsNode->getElementsByTagName('dashlet');
			$iRank = 0;
			$aDashletOrder = array();
			foreach($oDashletList as $oDomNode)
			{
				$sDashletClass = $oDomNode->getAttribute('xsi:type');
				$oRank =  $oDomNode->getElementsByTagName('rank')->item(0);
				if ($oRank)
				{
					$iRank = (float)$oRank->textContent;
				}
				$sId = $oDomNode->getAttribute('id');
				$oNewDashlet = new $sDashletClass($sId);
				$oNewDashlet->FromDOMNode($oDomNode);
				$aDashletOrder[] = array('rank' => $iRank, 'dashlet' => $oNewDashlet);
			}
			usort($aDashletOrder, array(get_class($this), 'SortOnRank'));
			$aDashletList = array();
			foreach($aDashletOrder as $aItem)
			{
				$aDashletList[] = $aItem['dashlet'];
			}
			$aCellOrder[] = array('rank' => $iCellRank, 'dashlets' => $aDashletList);
		}
		usort($aCellOrder, array(get_class($this), 'SortOnRank'));
		foreach($aCellOrder as $aItem)
		{
			$this->aCells[] = $aItem['dashlets'];
		}
		
		
	}
	
	static function SortOnRank($aItem1, $aItem2)
	{
		return ($aItem1['rank'] > $aItem2['rank']) ? +1 : -1;
	}
	/**
	 * Error handler to turn XML loading warnings into exceptions
	 */
	public static function ErrorHandler($errno, $errstr, $errfile, $errline)
	{
		if ($errno == E_WARNING && (substr_count($errstr,"DOMDocument::loadXML()")>0))
		{
			throw new DOMException($errstr);
		}
		else
		{
			return false;
		}
	}

	public function ToXml()
	{
		$oDoc = new DOMDocument();
		$oDoc->formatOutput = true; // indent (must be loaded with option LIBXML_NOBLANKS)
		$oDoc->preserveWhiteSpace = true; // otherwise the formatOutput option would have no effect

		$oMainNode = $oDoc->createElement('dashboard');
		$oMainNode->setAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");
		$oDoc->appendChild($oMainNode);
		
		$oNode = $oDoc->createElement('layout', $this->sLayoutClass);
		$oMainNode->appendChild($oNode);

		$oNode = $oDoc->createElement('title', $this->sTitle);
		$oMainNode->appendChild($oNode);

		$oCellsNode = $oDoc->createElement('cells');
		$oMainNode->appendChild($oCellsNode);
		
		$iCellRank = 0;
		foreach ($this->aCells as $aCell)
		{
			$oCellNode = $oDoc->createElement('cell');
			$oCellNode->setAttribute('id', $iCellRank);
			$oCellsNode->appendChild($oCellNode);
			$oCellRank = $oDoc->createElement('rank', $iCellRank);
			$oCellNode->appendChild($oCellRank);
			$iCellRank++;
						
			$iDashletRank = 0;
			$oDashletsNode = $oDoc->createElement('dashlets');
			$oCellNode->appendChild($oDashletsNode);
			foreach ($aCell as $oDashlet)
			{
				$oNode = $oDoc->createElement('dashlet');
				$oDashletsNode->appendChild($oNode);
				$oNode->setAttribute('id', $oDashlet->GetID());
				$oNode->setAttribute('xsi:type', get_class($oDashlet));
				$oDashletRank = $oDoc->createElement('rank', $iDashletRank);
				$oNode->appendChild($oDashletRank);
				$iDashletRank++;
				$oDashlet->ToDOMNode($oNode);
			}
		}

		$sXml = $oDoc->saveXML();
		return $sXml;
	}

	public function FromParams($aParams)
	{
		$this->sLayoutClass = $aParams['layout_class'];
		$this->sTitle = $aParams['title'];
		
		foreach($aParams['cells'] as $aCell)
		{
			$aCellDashlets = array();
			foreach($aCell as $aDashletParams)
			{
				$sDashletClass = $aDashletParams['dashlet_class'];
				$sId = $aDashletParams['dashlet_id'];
				$oNewDashlet = new $sDashletClass($sId);
				
				$oForm = $oNewDashlet->GetForm();
				$oForm->SetParamsContainer($sId);
				$oForm->SetPrefix('');
				$aValues = $oForm->ReadParams();
				$oNewDashlet->FromParams($aValues);
				$aCellDashlets[] = $oNewDashlet;
			}
			$this->aCells[] = $aCellDashlets;
		}
		
	}
	
	public function Save()
	{
		
	}
	
	public function GetLayout()
	{
		return $this->sLayoutClass;
	}
	
	public function SetLayout($sLayoutClass)
	{
		$this->sLayoutClass = $sLayoutClass;
	}
	
	public function GetTitle()
	{
		return $this->sTitle;
	}
	
	public function SetTitle($sTitle)
	{
		$this->sTitle = $sTitle;
	}
		
	public function AddDashlet($oDashlet)
	{
		$sId = $this->GetNewDashletId();
		$oDashlet->SetId($sId);
		$this->aCells[] = array($oDashlet);
	}
	
	public function Render($oPage, $bEditMode = false, $aExtraParams = array())
	{
		$oPage->add('<h1>'.Dict::S($this->sTitle).'</h1>');
		$oLayout = new $this->sLayoutClass;
		$oLayout->Render($oPage, $this->aCells, $bEditMode, $aExtraParams);
		if (!$bEditMode)
		{
			$oPage->add_linked_script('../js/dashlet.js');
			$oPage->add_linked_script('../js/dashboard.js');
		}
	}
	
	public function RenderProperties($oPage)
	{
		// menu to pick a layout and edit other properties of the dashboard
		$oPage->add('<div class="ui-widget-content ui-corner-all"><div class="ui-widget-header ui-corner-all" style="text-align:center; padding: 2px;">'.Dict::S('UI:DashboardEdit:Properties').'</div>');
		$sUrl = utils::GetAbsoluteUrlAppRoot();
		
		$oPage->add('<div style="text-align:center">'.Dict::S('UI:DashboardEdit:Layout').'</div>');
		$oPage->add('<div id="select_layout" style="text-align:center">');
		foreach( get_declared_classes() as $sLayoutClass)
		{
			if (is_subclass_of($sLayoutClass, 'DashboardLayout'))
			{
				$oReflection = new ReflectionClass($sLayoutClass);
				if (!$oReflection->isAbstract())
				{
					$aCallSpec = array($sLayoutClass, 'GetInfo');
					$aInfo = call_user_func($aCallSpec);
					$sChecked = ($this->sLayoutClass == $sLayoutClass) ? 'checked' : '';
					$oPage->add('<input type="radio" name="layout_class" '.$sChecked.' value="'.$sLayoutClass.'" id="layout_'.$sLayoutClass.'"><label for="layout_'.$sLayoutClass.'"><img src="'.$sUrl.$aInfo['icon'].'" /></label>'); // title="" on either the img or the label does nothing !
				}
			}
		}
		$oPage->add('</div>');
		
		$oForm = new DesignerForm();
		$oField = new DesignerLongTextField('dashboard_title', Dict::S('UI:DashboardEdit:DashboardTitle'), $this->sTitle);
		$oForm->AddField($oField);
		$this->SetFormParams($oForm);
		$oForm->RenderAsPropertySheet($oPage, false, ':itop-dashboard');	
		
		$oPage->add('</div>');
		$oPage->add_ready_script(
<<<EOF
	$('#select_layout').buttonset();
	$('#select_layout input').click( function() {
		var sLayoutClass = $(this).val();
		$(':itop-dashboard').dashboard('option', {layout_class: sLayoutClass});
	} );
	$('#row_attr_dashboard_title').property_field('option', {parent_selector: ':itop-dashboard', auto_apply: false, 'do_apply': function() {
			var sTitle = $('#attr_dashboard_title').val();
			$(':itop-dashboard').dashboard('option', {title: sTitle});
			return true;
		}
	});
EOF
		);
	}
	
	public function RenderDashletsSelection($oPage)
	{
		// Toolbox/palette to drag and drop dashlets
		$oPage->add('<div class="ui-widget-content ui-corner-all"><div class="ui-widget-header ui-corner-all" style="text-align:center; padding: 2px;">'.Dict::S('UI:DashboardEdit:Dashlets').'</div>');
		$sUrl = utils::GetAbsoluteUrlAppRoot();

		$oPage->add('<div id="select_dashlet" style="text-align:center">');
		foreach( get_declared_classes() as $sDashletClass)
		{
			if (is_subclass_of($sDashletClass, 'Dashlet'))
			{
				$oReflection = new ReflectionClass($sDashletClass);
				if (!$oReflection->isAbstract())
				{
					$aCallSpec = array($sDashletClass, 'IsVisible');
					$bVisible = call_user_func($aCallSpec);
					if ($bVisible)
					{
						$aCallSpec = array($sDashletClass, 'GetInfo');
						$aInfo = call_user_func($aCallSpec);
						$oPage->add('<span dashlet_class="'.$sDashletClass.'" class="dashlet_icon ui-widget-content ui-corner-all" id="dashlet_'.$sDashletClass.'" title="'.$aInfo['label'].'" style="width:34px; height:34px; display:inline-block; margin:2px;"><img src="'.$sUrl.$aInfo['icon'].'" /></span>');
					}
				}
			}
		}
		$oPage->add('</div>');

		$oPage->add('</div>');
		$oPage->add_ready_script("$('.dashlet_icon').draggable({helper: 'clone', appendTo: 'body', zIndex: 10000, revert:'invalid'});");
		$oPage->add_ready_script("$('.layout_cell').droppable({accept:'.dashlet_icon', hoverClass:'dragHover'});");
	}
	
	public function RenderDashletsProperties($oPage)
	{
		// Toolbox/palette to edit the properties of each dashlet
		$oPage->add('<div class="ui-widget-content ui-corner-all"><div class="ui-widget-header ui-corner-all" style="text-align:center; padding: 2px;">'.Dict::S('UI:DashboardEdit:DashletProperties').'</div>');

		$oPage->add('<div id="dashlet_properties" style="text-align:center">');
		foreach($this->aCells as $aCell)
		{
			foreach($aCell as $oDashlet)
			{
				$sId = $oDashlet->GetID();
				$sClass = get_class($oDashlet);
				if ($oDashlet->IsVisible())
				{
					$oPage->add('<div class="dashlet_properties" id="dashlet_properties_'.$sId.'" style="display:none">');
					$oForm = $oDashlet->GetForm();
					$this->SetFormParams($oForm);
					$oForm->RenderAsPropertySheet($oPage, false, ':itop-dashboard');		
					$oPage->add('</div>');
				}
			}
		}
		$oPage->add('</div>');

		$oPage->add('</div>');
	}
	
	protected function GetNewDashletId()
	{
		$iNewId = 0;
		foreach($this->aCells as $aDashlets)
		{
			foreach($aDashlets as $oDashlet)
			{
				$iNewId = max($iNewId, (int)$oDashlet->GetID());
			}
		}
		return $iNewId + 1;
	}
	
	abstract protected function SetFormParams($oForm);
}

class RuntimeDashboard extends Dashboard
{
	protected $bCustomized;
	
	public function __construct($sId)
	{
		parent::__construct($sId);
		$this->bCustomized = false;
	}
		
	public function SetCustomFlag($bCustomized)
	{
		$this->bCustomized = $bCustomized;
	}
	
	protected function SetFormParams($oForm)
	{
		$oForm->SetSubmitParams(utils::GetAbsoluteUrlAppRoot().'pages/ajax.render.php', array('operation' => 'update_dashlet_property'));		
	}
	
	public function Save()
	{
		$sXml = $this->ToXml();
		$oUDSearch = new DBObjectSearch('UserDashboard');
		$oUDSearch->AddCondition('user_id', UserRights::GetUserId(), '=');
		$oUDSearch->AddCondition('menu_code', $this->sId, '=');
		$oUDSet = new DBObjectSet($oUDSearch);
		if ($oUDSet->Count() > 0)
		{
			// Assuming there is at most one couple {user, menu}!
			$oUserDashboard = $oUDSet->Fetch();
			$oUserDashboard->Set('contents', $sXml);
			
			$oUserDashboard->DBUpdate();
		}
		else
		{
			// No such customized dasboard for the current user, let's create a new record
			$oUserDashboard = new UserDashboard();
			$oUserDashboard->Set('user_id', UserRights::GetUserId());
			$oUserDashboard->Set('menu_code', $this->sId);
			$oUserDashboard->Set('contents', $sXml);
			
			$oUserDashboard->DBInsert();
		}		
	}
	
	public function Revert()
	{
		$oUDSearch = new DBObjectSearch('UserDashboard');
		$oUDSearch->AddCondition('user_id', UserRights::GetUserId(), '=');
		$oUDSearch->AddCondition('menu_code', $this->sId, '=');
		$oUDSet = new DBObjectSet($oUDSearch);
		if ($oUDSet->Count() > 0)
		{
			// Assuming there is at most one couple {user, menu}!
			$oUserDashboard = $oUDSet->Fetch();
			$oUserDashboard->DBDelete();
		}
	}
	
	public function Render($oPage, $bEditMode = false, $aExtraParams = array())
	{
		parent::Render($oPage, $bEditMode, $aExtraParams);
		if (!$bEditMode)
		{
			$sEditMenu = "<td><span id=\"DashboardMenu\"><ul><li><img src=\"../images/edit.png\"><ul>";
		
			$aActions = array();
			$oEdit = new JSPopupMenuItem('UI:Dashboard:Edit', Dict::S('UI:Dashboard:Edit'), "return EditDashboard('{$this->sId}')");
			$aActions[$oEdit->GetUID()] = $oEdit->GetMenuItem();

			if ($this->bCustomized)
			{
				$oRevert = new JSPopupMenuItem('UI:Dashboard:RevertConfirm', Dict::S('UI:Dashboard:Revert'),
												"if (confirm('".addslashes(Dict::S('UI:Dashboard:RevertConfirm'))."')) return RevertDashboard('{$this->sId}'); else return false");
				$aActions[$oRevert->GetUID()] = $oRevert->GetMenuItem();
			}
			utils::GetPopupMenuItems($oPage, iPopupMenuExtension::MENU_DASHBOARD_ACTIONS, $this, $aActions);
			$sEditMenu .= $oPage->RenderPopupMenuItems($aActions);
					

			$sEditMenu = addslashes($sEditMenu);
			//$sEditBtn = addslashes('<div style="display: inline-block; height: 55px; width:200px;vertical-align:center;line-height:60px;text-align:left;"><button onclick="EditDashboard(\''.$this->sId.'\');">Edit This Page</button></div>');
			$oPage->add_ready_script(
<<<EOF
	$('#logOffBtn').parent().before('$sEditMenu');
	$('#DashboardMenu>ul').popupmenu();
	
EOF
			);
			$oPage->add_script(
<<<EOF
function EditDashboard(sId)
{
	$.post(GetAbsoluteUrlAppRoot()+'pages/ajax.render.php', {operation: 'dashboard_editor', id: sId},
		function(data)
		{
			$('body').append(data);
		}
	);
	return false;
}
function RevertDashboard(sId)
{
	$.post(GetAbsoluteUrlAppRoot()+'pages/ajax.render.php', {operation: 'revert_dashboard', dashboard_id: sId},
		function(data)
		{
			$('body').append(data);
		}
	);
	return false;
}
EOF
			);
		}
	}
	
	public function RenderEditor($oPage)
	{
		$oPage->add('<div id="dashboard_editor">');
		$oPage->add('<div class="ui-layout-center">');
		$this->Render($oPage, true);
		$oPage->add('</div>');
		$oPage->add('<div class="ui-layout-east">');
		$this->RenderProperties($oPage);
		$this->RenderDashletsSelection($oPage);
		$this->RenderDashletsProperties($oPage);
		$oPage->add('</div>');
		$oPage->add('<div id="event_bus"/>'); // For exchanging messages between the panes, same as in the designer
		$oPage->add('</div>');
		
		$sDialogTitle = Dict::S('UI:DashboardEdit:Title');
		$sOkButtonLabel = Dict::S('UI:Button:Save');
		$sCancelButtonLabel = Dict::S('UI:Button:Cancel');
		
		$sId = addslashes($this->sId);
		$sLayoutClass = addslashes($this->sLayoutClass);
		$sTitle = addslashes($this->sTitle);
		$sUrl = utils::GetAbsoluteUrlAppRoot().'pages/ajax.render.php';

		$sExitConfirmationMessage = addslashes(Dict::S('UI:NavigateAwayConfirmationMessage'));
		$sCancelConfirmationMessage = addslashes(Dict::S('UI:CancelConfirmationMessage'));
		$sAutoApplyConfirmationMessage = addslashes(Dict::S('UI:AutoApplyConfirmationMessage'));
		
		$oPage->add_ready_script(
<<<EOF
window.bLeavingOnUserAction = false;

$('#dashboard_editor').dialog({
	height: $('body').height() - 50,
	width: $('body').width() - 50,
	modal: true,
	title: '$sDialogTitle',
	buttons: [
	{ text: "$sOkButtonLabel", click: function() {
		var oDashboard = $(':itop-dashboard').data('dashboard');
		if (oDashboard.is_dirty())
		{
			if (!confirm('$sAutoApplyConfirmationMessage'))
			{
				return;
			}
			else
			{
				oDashboard.apply_changes();
			}
		}
		window.bLeavingOnUserAction = true;
		oDashboard.save();
	} },
	{ text: "$sCancelButtonLabel", click: function() {
		var oDashboard = $(':itop-dashboard').data('dashboard');
		if (oDashboard.is_modified())
		{
			if (!confirm('$sCancelConfirmationMessage'))
			{
				return;
			}
		}
		window.bLeavingOnUserAction = true;
		$(this).dialog( "close" );
		$(this).remove();
	} },
	],
	close: function() { $(this).remove(); }
});

$('#dashboard_editor .ui-layout-center').dashboard({
	dashboard_id: '$sId', layout_class: '$sLayoutClass', title: '$sTitle',
	submit_to: '$sUrl', submit_parameters: {operation: 'save_dashboard'},
	render_to: '$sUrl', render_parameters: {operation: 'render_dashboard'},
	new_dashlet_parameters: {operation: 'new_dashlet'}
});

$('#select_dashlet').droppable({
	accept: '.dashlet',
	drop: function(event, ui) {
		$( this ).find( ".placeholder" ).remove();
		var oDashlet = ui.draggable;
		oDashlet.remove();
	},
});

$('#event_bus').bind('dashlet-selected', function(event, data){
		var sDashletId = data.dashlet_id;
		var sPropId = 'dashlet_properties_'+sDashletId;
		$('.dashlet_properties').each(function() {
			var sId = $(this).attr('id');
			var bShow = (sId == sPropId);
			if (bShow)
			{
				$(this).show();
			}
			else
			{
				$(this).hide();
			}
		});

	});
	
dashboard_prop_size = GetUserPreference('dashboard_prop_size', 350);
$('#dashboard_editor').layout({
	east: {
		minSize: 200,
		size: dashboard_prop_size,
		togglerLength_open: 0,
		togglerLength_closed: 0, 
		onresize_end: function(name, elt, state, options, layout)
		{
			if (state.isSliding == false)
			{
				SetUserPreference('dashboard_prop_size', state.size, true);
			}
		},
	}
});

window.onbeforeunload = function() {
	if (!window.bLeavingOnUserAction)
	{
		var oDashboard = $(':itop-dashboard').data('dashboard');
		if (oDashboard)
		{
			if (oDashboard.is_dirty())
			{
				return '$sExitConfirmationMessage';
			}	
			if (oDashboard.is_modified())
			{
				return '$sExitConfirmationMessage';
			}
		}	
	}
	// return nothing ! safer for IE
};
EOF
		);
		$oPage->add_ready_script("");
	}
	
	public static function GetDashletCreationForm($sOQL = null)
	{
		$oForm = new DesignerForm();
	
		// Get the list of all 'dashboard' menus in which we can insert a dashlet
		$aAllMenus = ApplicationMenu::ReflectionMenuNodes();
		$aAllowedDashboards = array();
		foreach($aAllMenus as $idx => $aMenu)
		{
			$oMenu = $aMenu['node'];
			$sParentId = $aMenu['parent'];
			if ($oMenu instanceof DashboardMenuNode)
			{
				$sMenuLabel = $oMenu->GetTitle();
				$sParentLabel = Dict::S('Menu:'.$sParentId);
				if ($sParentLabel != $sMenuLabel)
				{
					$aAllowedDashboards[$oMenu->GetMenuId()] = $sParentLabel.' - '.$sMenuLabel;
				}
				else
				{
					$aAllowedDashboards[$oMenu->GetMenuId()] = $sMenuLabel;
				}
			}
		}
		asort($aAllowedDashboards);
		
		$aKeys = array_keys($aAllowedDashboards); // Select the first one by default
		$sDefaultDashboard = $aKeys[0];
		$oField = new DesignerComboField('menu_id', Dict::S('UI:DashletCreation:Dashboard'), $sDefaultDashboard);
		$oField->SetAllowedValues($aAllowedDashboards);
		$oField->SetMandatory(true);
		$oForm->AddField($oField);
				
		// Get the list of possible dashlets that support a creation from
		// an OQL
		$aDashlets = array();
		foreach(get_declared_classes() as $sDashletClass)
		{
			if (is_subclass_of($sDashletClass, 'Dashlet'))
			{
				$oReflection = new ReflectionClass($sDashletClass);
				if (!$oReflection->isAbstract())
				{
					$aCallSpec = array($sDashletClass, 'CanCreateFromOQL');
					$bShorcutMode = call_user_func($aCallSpec);
					if ($bShorcutMode)
					{
						$aCallSpec = array($sDashletClass, 'GetInfo');
						$aInfo = call_user_func($aCallSpec);
						$aDashlets[$sDashletClass] = array('label' => $aInfo['label'], 'class' => $sDashletClass, 'icon' => $aInfo['icon']);
					}
				}
			}
		}
		
		$oSelectorField = new DesignerFormSelectorField('dashlet_class', Dict::S('UI:DashletCreation:DashletType'), '');
		$oForm->AddField($oSelectorField);
		foreach($aDashlets as $sDashletClass => $aDashletInfo)
		{
			$oSubForm = new DesignerForm();
			$oDashlet = new $sDashletClass(0);
			$oDashlet->GetPropertiesFieldsFromOQL($oSubForm, $sOQL);
			
			$oSelectorField->AddSubForm($oSubForm, $aDashletInfo['label'], $aDashletInfo['class']);
		}
		$oField = new DesignerBooleanField('open_editor', Dict::S('UI:DashletCreation:EditNow'), true);
		$oForm->AddField($oField);
		
		return $oForm;
	}
	
	public static function GetDashletCreationDlgFromOQL($oPage, $sOQL)
	{
		$oPage->add('<div id="dashlet_creation_dlg">');

		$oForm = self::GetDashletCreationForm($sOQL);

		$oForm->Render($oPage);
		$oPage->add('</div>');
		
		$sDialogTitle = Dict::S('UI:DashletCreation:Title');
		$sOkButtonLabel = Dict::S('UI:Button:Ok');
		$sCancelButtonLabel = Dict::S('UI:Button:Cancel');
		
		$oPage->add_ready_script(
<<<EOF
$('#dashlet_creation_dlg').dialog({
	width: 400,
	modal: true,
	title: '$sDialogTitle',
	buttons: [
	{ text: "$sOkButtonLabel", click: function() {
		var oForm = $(this).find('form');
		var sFormId = oForm.attr('id');
		var oParams = null;
		var aErrors = ValidateForm(sFormId, false);
		if (aErrors.length == 0)
		{
			oParams = ReadFormParams(sFormId);
		}
		oParams.operation = 'add_dashlet';
		var me = $(this);
		$.post(GetAbsoluteUrlAppRoot()+'pages/ajax.render.php', oParams, function(data) {
			me.dialog( "close" );
			me.remove();
			$('body').append(data);
		});
	} },
	{ text: "$sCancelButtonLabel", click: function() {
		$(this).dialog( "close" ); $(this).remove();
	} },
	],
	close: function() { $(this).remove(); }
});
EOF
		);
	}
}