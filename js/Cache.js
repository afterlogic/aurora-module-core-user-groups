'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
 * @constructor of object which stores user groups of every tenant.
 */
function CCache()
{
	this.selectedTenantId = ModulesManager.run('AdminPanelWebclient', 'getKoSelectedTenantId');
	this.groupsByTenants = ko.observable({});
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
