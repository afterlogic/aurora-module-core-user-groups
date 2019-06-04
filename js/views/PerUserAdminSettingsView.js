'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	CAbstractSettingsFormView = ModulesManager.run('AdminPanelWebclient', 'getAbstractSettingsFormViewClass'),
	
	Cache = require('modules/%ModuleName%/js/Cache.js'),
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
* @constructor of object which is used to manage user groups at the user level.
*/
function CPerUserAdminSettingsView()
{
	CAbstractSettingsFormView.call(this, Settings.ServerModuleName);
	
	this.entityType = ko.observable('');
	
	this.iUserId = 0;
	
	/* Editable fields */
	this.selectedGroup = ko.observable(0);
	/*-- Editable fields */
	
	this.groups = ko.computed(function () {
		return _.map(Cache.groups(), function (oGroup) {
			return {
				iId: oGroup.Id,
				sName: oGroup.Name
			};
		});
	}, this);
	
	this.visible = ko.computed(function () {
		return this.entityType() === 'User' && this.groups().length > 0;
	}, this);
	
	App.subscribeEvent('ReceiveAjaxResponse::after', _.bind(function (oParams) {
		if (oParams.Request.Module === 'AdminPanelWebclient'
			&& oParams.Request.Method === 'GetEntity'
			&& oParams.Request.Parameters.Type === 'User'
			&& oParams.Request.Parameters.Id === this.iUserId
			&& oParams.Response.Result
			&& oParams.Response.Result.EntityId === this.iUserId)
		{
			this.selectedGroup(Types.pString(oParams.Response.Result['CoreUserGroups::GroupId']));
		}
		
		if (oParams.Request.Module === 'CoreUserGroups'
			&& oParams.Request.Method === 'AddToGroup'
			&& _.indexOf(oParams.Request.Parameters.UsersIds, this.iUserId) !== -1
			&& oParams.Response.Result)
		{
			this.selectedGroup(Types.pString(oParams.Request.Parameters.GroupId));
		}
	}, this));
}

_.extendOwn(CPerUserAdminSettingsView.prototype, CAbstractSettingsFormView.prototype);

CPerUserAdminSettingsView.prototype.ViewTemplate = '%ModuleName%_PerUserAdminSettingsView';

/**
 * Updates group identifier of user.
 */
CPerUserAdminSettingsView.prototype.updateUserGroup = function()
{
	this.isSaving(true);
	
	var
		oParameters = {
			'UserId': this.iUserId,
			'GroupId': Types.pInt(this.selectedGroup())
		}
	;
	
	Ajax.send(
		Settings.ServerModuleName,
		'UpdateUserGroup',
		oParameters,
		function (oResponse, oRequest) {
			this.isSaving(false);
			if (!oResponse.Result)
			{
				Api.showErrorByCode(oResponse, TextUtils.i18n('COREWEBCLIENT/ERROR_SAVING_SETTINGS_FAILED'));
			}
			else
			{
				Screens.showReport(TextUtils.i18n('COREWEBCLIENT/REPORT_SETTINGS_UPDATE_SUCCESS'));
			}
		},
		this
	);
};

/**
 * Sets access level for the view via entity type and entity identifier.
 * This view is visible only for User entity type.
 * 
 * @param {string} sEntityType Current entity type.
 * @param {number} iEntityId Indentificator of current intity.
 */
CPerUserAdminSettingsView.prototype.setAccessLevel = function (sEntityType, iEntityId)
{
	this.entityType(sEntityType);
	if (this.iUserId !== iEntityId)
	{
		this.iUserId = iEntityId;
	}
};

module.exports = new CPerUserAdminSettingsView();
