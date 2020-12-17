'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
 * @constructor of object which stores user groups of every tenant.
 */
function CCache()
{
	this.selectedTenantId = ModulesManager.run('AdminPanelWebclient', 'getKoSelectedTenantId');
	this.groupsByTenants = ko.observable({});
	if (_.isFunction(this.selectedTenantId))
	{
		this.selectedTenantId.subscribe(function () {
			if (typeof this.groupsByTenants()[this.selectedTenantId()] === 'undefined')
			{
				Ajax.send(Settings.UserGroupsServerModuleName, 'GetGroups', { TenantId: this.selectedTenantId() });
			}
		}, this);
	}
	this.groups = ko.computed(function () {
		var aGroups = _.isFunction(this.selectedTenantId) ? this.groupsByTenants()[this.selectedTenantId()] : [];
		return _.isArray(aGroups) ? aGroups : [];
	}, this);
}

/**
 * Initializes Cache object.
 */
CCache.prototype.init = function ()
{
	App.subscribeEvent('AdminPanelWebclient::ConstructView::after', function (oParams) {
		if (oParams.Name === 'CSettingsView' && Types.isPositiveNumber(oParams.View.selectedTenant().Id))
		{
			Ajax.send(Settings.ServerModuleName, 'GetGroups', { 'TenantId': oParams.View.selectedTenant().Id });
		}
	}.bind(this));
	App.subscribeEvent('ReceiveAjaxResponse::after', this.onAjaxResponse.bind(this));
	App.subscribeEvent('SendAjaxRequest::before', this.onAjaxSend.bind(this));
};

/**
 * Obtains group.
 * @param {int} iId Group identifier.
 * @returns {object|undefined}
 */
CCache.prototype.getGroup = function (iId)
{
	return _.find(this.groups(), function (oGroup) {
		return oGroup.Id === iId;
	});
};

/**
 * Only Cache object knows if groups are empty or not received yet.
 * So error will be shown as soon as groups will be received from server if they are empty.
 * @returns Boolean
 */
CCache.prototype.showErrorIfGroupsEmpty = function ()
{
	var
		bGroupsEmptyOrUndefined = true,
		fShowErrorIfGroupsEmpty = function () {
			if (this.groups().length === 0)
			{
				Screens.showError(TextUtils.i18n('%MODULENAME%/ERROR_ADD_GROUP_FIRST'));
			}
			else
			{
				bGroupsEmptyOrUndefined = false;
			}
		}.bind(this)
	;
	
	if (_.isFunction(this.selectedTenantId))
	{
		if (typeof this.groupsByTenants()[this.selectedTenantId()] === 'undefined')
		{
			var fSubscription = this.groupsByTenants.subscribe(function () {
				fShowErrorIfGroupsEmpty();
				fSubscription.dispose();
				fSubscription = undefined;
			});
		}
		else
		{
			fShowErrorIfGroupsEmpty();
		}
	}
	
	return bGroupsEmptyOrUndefined;
};

/**
 * Catches GetGroup AJAX request. If Cache already has the group prevents AJAX request and provides the group.
 * @param {object} oParams AJAX request parameters.
 */
CCache.prototype.onAjaxSend = function (oParams)
{
	if (oParams.Module === Settings.ServerModuleName && oParams.Method === 'GetGroup')
	{
		var oGroup = this.getGroup(oParams.Parameters.Id);
		if (oGroup)
		{
			if (_.isFunction(oParams.ResponseHandler))
			{
				oParams.ResponseHandler.apply(oParams.Context, [{
					'Module': oParams.Module,
					'Method': oParams.Method,
					'Result': oGroup
				}, {
					'Module': oParams.Module,
					'Method': oParams.Method,
					'Parameters': oParams.Parameters
				}]);
				oParams.Continue = false;
			}
		}
	}
};

/**
 * Catches GetGroups AJAX response and places it to the Cache.
 * @param {object} oParams AJAX response paraeters.
 */
CCache.prototype.onAjaxResponse = function (oParams)
{
	if (oParams.Response.Module === Settings.ServerModuleName && oParams.Response.Method === 'GetGroups')
	{
		var
			sSearch = Types.pString(oParams.Request.Parameters.Search),
			iOffset = Types.pInt(oParams.Request.Parameters.Offset)
		;
		if (sSearch === '' && iOffset === 0)
		{
			var
				iTenantId = oParams.Request.Parameters.TenantId,
				aGroups = oParams.Response.Result && _.isArray(oParams.Response.Result.Items) ? oParams.Response.Result.Items : []
			;

			_.each(aGroups, function (oGroup) {
				oGroup.Id = Types.pInt(oGroup.Id);
			});

			this.groupsByTenants()[iTenantId] = aGroups;
			this.groupsByTenants.valueHasMutated();
		}
	}
};

module.exports = new CCache();
