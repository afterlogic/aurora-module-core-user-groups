'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	
	Cache = require('modules/%ModuleName%/js/Cache.js'),
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
 * @constructor of object that provides "Add to group" button. The button should be placed on Users screen.
 */
function CAddToGroupButtonView()
{
	this.hasCheckedEntities = ko.observable(false);
	this.checkedEntities = ko.observableArray([]);
	this.groups = Cache.groups;
}

CAddToGroupButtonView.prototype.ViewTemplate = '%ModuleName%_AddToGroupButtonView';

/**
 * Initializes object.
 * @param {object} koHasCheckedEntities Observable boolean which indicates if there are checked users on User screen.
 * @param {object} koCheckedEntities Observable list of checked users on User screen.
 */
CAddToGroupButtonView.prototype.init = function (koHasCheckedEntities, koCheckedEntities)
{
	this.hasCheckedEntities = koHasCheckedEntities;
	this.checkedEntities = koCheckedEntities;
};

/**
 * Adds list of checked users to specified group.
 * @param {int} iId Group identifier.
 */
CAddToGroupButtonView.prototype.addToGroup = function (iId)
{
	var aUsersIds = _.map(this.checkedEntities(), function (oEntity) {
		return oEntity.Id;
	});
	Screens.showLoading(TextUtils.i18n('%MODULENAME%/INFO_GROUP_USERS_ADDING_PLURAL', {}, null, aUsersIds.length));
	Ajax.send(Settings.ServerModuleName, 'AddToGroup', { 'GroupId': iId, UsersIds:  aUsersIds}, function (oResponse, oRequest) {
		Screens.hideLoading();
		if (oResponse.Result)
		{
			Screens.showReport(TextUtils.i18n('%MODULENAME%/REPORT_ADD_TO_GROUP_PLURAL', {}, null, aUsersIds.length));
		}
		else
		{
			Api.showErrorByCode(oResponse, TextUtils.i18n('%MODULENAME%/ERROR_ADD_TO_GROUP_PLURAL', {}, null, aUsersIds.length));
		}
	}, this);
};

module.exports = new CAddToGroupButtonView();
