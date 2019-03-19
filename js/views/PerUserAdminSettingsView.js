'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
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
	
	this.groups = ko.computed(function () {
		return _.map(Cache.groups(), function (oGroup) {
			return {
				iId: oGroup.Id,
				sName: oGroup.Name,
				/* Editable fields */
				checkedGroup: ko.observable(false)
				/*-- Editable fields */
			};
		});
	}, this);
	
	this.visible = ko.computed(function () {
		return this.entityType() === 'User' && this.groups().length > 0;
	}, this);
}

_.extendOwn(CPerUserAdminSettingsView.prototype, CAbstractSettingsFormView.prototype);

CPerUserAdminSettingsView.prototype.ViewTemplate = '%ModuleName%_PerUserAdminSettingsView';

/**
 * Runs after routing to this view.
 */
CPerUserAdminSettingsView.prototype.onRoute = function ()
{
	this.getGroupsOfUser();
	this.iAuthMode = Settings.AuthMode;
};

/**
 * Requests list of groups of current user.
 */
CPerUserAdminSettingsView.prototype.getGroupsOfUser = function ()
{
	if (Types.isPositiveNumber(this.iUserId))
	{
		Ajax.send(Settings.ServerModuleName, 'GetGroupsOfUser', {'UserId': this.iUserId}, function (oResponse) {
			if (oResponse.Result)
			{
				_.each(this.groups(), function (oGroup) {
					var bCheckedGroup = !!_.find(oResponse.Result, function (oGroupUser) {
						return oGroup.iId === oGroupUser.GroupId;
					});
					oGroup.checkedGroup(bCheckedGroup);
				}.bind(this));
			}
		}, this);
	}
};

/**
 * Saves group list fo current user.
 */
CPerUserAdminSettingsView.prototype.saveGroupsOfUser = function()
{
	this.isSaving(true);
	
	var
		aUserGroups = _.filter(this.groups(), function (oGroup) {
			return oGroup.checkedGroup();
		}),
		oParameters = {
			'UserId': this.iUserId,
			'GroupsIds': _.map(aUserGroups, function (oGroup) {
				return oGroup.iId;
			})
		}
	;
	
	Ajax.send(
		Settings.ServerModuleName,
		'SaveGroupsOfUser',
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
