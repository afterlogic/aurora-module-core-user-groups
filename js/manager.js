'use strict';

module.exports = function (oAppData) {
	var
		App = require('%PathToCoreWebclientModule%/js/App.js'),
				
		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
		
		ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),

		Cache = require('modules/%ModuleName%/js/Cache.js'),
		Settings = require('modules/%ModuleName%/js/Settings.js')
	;
	
	Settings.init(oAppData);
	Cache.init();
	
	if (ModulesManager.isModuleAvailable(Settings.ServerModuleName))
	{
		if (App.getUserRole() === Enums.UserRole.SuperAdmin)
		{
			return {
				/**
				 * Registers admin settings tabs before application start.
				 * 
				 * @param {Object} ModulesManager
				 */
				start: function (ModulesManager)
				{
					ModulesManager.run('AdminPanelWebclient', 'registerAdminPanelEntityType', [{
						Type: 'Group',
						ScreenHash: 'group',
						LinkTextKey: '%MODULENAME%/HEADING_GROUP_SETTINGS_TABNAME',
						EditView: require('modules/%ModuleName%/js/views/EditGroupView.js'),

						ServerModuleName: Settings.ServerModuleName,
						GetListRequest: 'GetGroups',
						GetRequest: 'GetGroup',
						CreateRequest: 'CreateGroup',
						UpdateRequest: 'UpdateGroup',
						DeleteRequest: 'DeleteGroups',

						NoEntitiesFoundText: TextUtils.i18n('%MODULENAME%/INFO_NO_ENTITIES_FOUND_GROUP'),
						ActionCreateText: TextUtils.i18n('%MODULENAME%/ACTION_CREATE_ENTITY_GROUP'),
						ReportSuccessCreateText: TextUtils.i18n('%MODULENAME%/REPORT_CREATE_ENTITY_GROUP'),
						ErrorCreateText: TextUtils.i18n('%MODULENAME%/ERROR_CREATE_ENTITY_GROUP'),
						CommonSettingsHeadingText: TextUtils.i18n('%MODULENAME%/HEADING_EDIT_GROUP'),
						ReportSuccessUpdate: TextUtils.i18n('%MODULENAME%/REPORT_UPDATE_ENTITY_GROUP'),
						ErrorUpdate: TextUtils.i18n('%MODULENAME%/ERROR_UPDATE_ENTITY_GROUP'),
						ActionDeleteText: TextUtils.i18n('%MODULENAME%/ACTION_DELETE_GROUP'),
						ConfirmDeleteLangConst: '%MODULENAME%/CONFIRM_DELETE_GROUP_PLURAL',
						ReportSuccessDeleteLangConst: '%MODULENAME%/REPORT_DELETE_ENTITIES_GROUP_PLURAL',
						ErrorDeleteLangConst: '%MODULENAME%/ERROR_DELETE_ENTITIES_GROUP_PLURAL'
					}]);
					ModulesManager.run('AdminPanelWebclient', 'changeAdminPanelEntityData', [{
						Type: 'User',
						AdditionalButtons: [{'ButtonView': require('modules/%ModuleName%/js/views/AddToGroupButtonView.js')}]
					}]);
					ModulesManager.run('AdminPanelWebclient', 'registerAdminPanelTab', [
						function(resolve) {
							require.ensure(
								['modules/%ModuleName%/js/views/PerUserAdminSettingsView.js'],
								function() {
									resolve(require('modules/%ModuleName%/js/views/PerUserAdminSettingsView.js'));
								},
								"admin-bundle"
							);
						},
						Settings.HashModuleName + '-user',
						TextUtils.i18n('%MODULENAME%/LABEL_SETTINGS_TAB_USERGROUPS')
					]);
				}
			};
		}
	}
	
	return null;
};
