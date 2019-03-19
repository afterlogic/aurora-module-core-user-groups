'use strict';

var
	_ = require('underscore'),
	$ = require('jquery'),
	ko = require('knockout'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	
	Popups = require('%PathToCoreWebclientModule%/js/Popups.js'),
	AlertPopup = require('%PathToCoreWebclientModule%/js/popups/AlertPopup.js'),
	ConfirmPopup = require('%PathToCoreWebclientModule%/js/popups/ConfirmPopup.js'),
	
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
 * @constructor of object that allows to create/edit group.
 */
function CEditGroupView()
{
	this.sHeading = TextUtils.i18n('%MODULENAME%/HEADING_CREATE_GROUP');
	this.id = ko.observable(0);
	this.name = ko.observable('');
	
	this.users = ko.observableArray([]);
	this.usersLoading = ko.observable(false);
	
	App.broadcastEvent('%ModuleName%::ConstructView::after', {'Name': this.ViewConstructorName, 'View': this});
}

CEditGroupView.prototype.ViewTemplate = '%ModuleName%_EditGroupView';
CEditGroupView.prototype.ViewConstructorName = 'CEditGroupView';

/**
 * Returns array with all settings values wich is used for indicating if there were changes on the page.
 * @returns {Array} Array with all settings values.
 */
CEditGroupView.prototype.getCurrentValues = function ()
{
	return [
		this.id(),
		this.name()
	];
};

/**
 * Clears all fields values.
 */
CEditGroupView.prototype.clearFields = function ()
{
	this.id(0);
	this.name('');
	this.users([]);
};

/**
 * Parses entity to edit.
 * @param {int} iEntityId Entity identifier.
 * @param {object} oResult Entity data from server.
 */
CEditGroupView.prototype.parse = function (iEntityId, oResult)
{
	if (oResult)
	{
		this.id(iEntityId);
		this.name(oResult.Name);
		this.getGroupUsers();
	}
	else
	{
		this.clearFields();
	}
};

/**
 * Checks if data is valid before its saving.
 * @returns {boolean}
 */
CEditGroupView.prototype.isValidSaveData = function ()
{
	var
		bValidUserName = $.trim(this.name()) !== ''
	;
	if (!bValidUserName)
	{
		Screens.showError(TextUtils.i18n('%MODULENAME%/ERROR_GROUP_NAME_EMPTY'));
		return false;
	}
	return true;
};

/**
 * Obtains parameters for saving on the server.
 * @returns {object}
 */
CEditGroupView.prototype.getParametersForSave = function ()
{
	return {
		Id: this.id(),
		Name: $.trim(this.name())
	};
};

/**
 * Saves entity by pressing enter in the field input.
 * @param {array} aParents
 * @param {object} oRoot
 */
CEditGroupView.prototype.saveEntity = function (aParents, oRoot)
{
	_.each(aParents, function (oParent) {
		if (oParent.constructor.name === 'CEntitiesView' && _.isFunction(oParent.createEntity))
		{
			oParent.createEntity();
		}
		if (oParent.constructor.name === 'CCommonSettingsPaneView' && _.isFunction(oParent.save))
		{
			oParent.save(oRoot);
		}
	});
};

/**
 * Requests users for the group.
 */
CEditGroupView.prototype.getGroupUsers = function ()
{
	if (Types.isPositiveNumber(this.id()))
	{
		this.usersLoading(true);
		Ajax.send(Settings.ServerModuleName, 'GetGroupUsers', {'GroupId': this.id()}, function (oResponse) {
			this.usersLoading(false);
			if (oResponse.Result)
			{
				var aUsers = [];
				_.each(oResponse.Result, function (oUser) {
					if (oUser && oUser.Id)
					{
						oUser.checkedUser = ko.observable(false);
						aUsers.push(oUser);
					}
				});
				this.users(aUsers);
			}
		}, this);
	}
};

/**
 * Confirms removing of selected users from the group.
 */
CEditGroupView.prototype.removeSelectedFromGroup = function ()
{
	var aCheckedUserId = [];
	_.each(this.users(), function (oUser) {
		if (oUser.checkedUser())
		{
			aCheckedUserId.push(oUser.Id);
		}
	});
	if (aCheckedUserId.length === 0)
	{
		Popups.showPopup(AlertPopup, [TextUtils.i18n('%MODULENAME%/WARNING_NO_USER_SELECTED_TO_REMOVE')]);
	}
	else
	{
		Popups.showPopup(ConfirmPopup, [TextUtils.i18n('%MODULENAME%/CONFIRM_REMOVE_USERS_FROM_GROUP_PLURAL', {}, null, aCheckedUserId.length), 
			_.bind(function (bOk) {
				if (bOk)
				{
					this.confirmedRemoveSelectedFromGroup(aCheckedUserId);
				}
			}, this)
		]);
	}
};

/**
 * Removes selected users from the group.
 * @param {array} aCheckedUserId List of checked users.
 */
CEditGroupView.prototype.confirmedRemoveSelectedFromGroup = function (aCheckedUserId)
{
	Ajax.send(Settings.ServerModuleName, 'RemoveUsersFromGroup', {'GroupId': this.id(), 'UsersIds': aCheckedUserId}, function (oResponse) {
		if (!oResponse.Result)
		{
			Api.showErrorByCode(oResponse, TextUtils.i18n('%MODULENAME%/ERROR_REMOVE_FROM_GROUP_PLURAL', {}, null, aCheckedUserId.length));
		}
		else
		{
			Screens.showReport(TextUtils.i18n('%MODULENAME%/REPORT_REMOVE_FROM_GROUP_PLURAL', {}, null, aCheckedUserId.length));
		}
		this.getGroupUsers();
	}, this);
};

/**
 * Highlights Users tab in tabsbar.
 */
CEditGroupView.prototype.onUsersInfoClick = function ()
{
	$('.tabsbar .item.admin.user').removeClass('recivedAnim');
	setTimeout(function () {
		$('.tabsbar .item.admin.user').addClass('recivedAnim');
	});
};

module.exports = new CEditGroupView();
