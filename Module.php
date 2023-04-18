<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\CoreUserGroups;

use Aurora\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Provides user groups.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    /*
     * @var $oApiGroupsManager Managers\MailingLists
     */
    public $oApiGroupsManager = null;

    public function init()
    {
        $this->aErrors = [
            Enums\ErrorCodes::GroupAlreadyExists => $this->i18N('ERROR_GROUP_ALREADY_EXISTS'),
            Enums\ErrorCodes::CannotDeleteDefaultGroup => $this->i18N('ERROR_CANNOT_DELETE_DEFAULT_GROUP'),
        ];
        $this->subscribeEvent('Core::DeleteTenant::after', array($this, 'onAfterDeleteTenant'));
        $this->subscribeEvent('Core::GetUsers::before', array($this, 'onBeforeGetUsers'));
        //temporary removed setting a default user group when a user is created
        // $this->subscribeEvent('Core::CreateUser::after', array($this, 'onAfterCreateUser'));
    }

    /**
     *
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     *
     * @return Settings
     */
    protected function GetModuleSettings()
    {
        return $this->oModuleSettings;
    }

    public function getGroupsManager()
    {
        if ($this->oApiGroupsManager === null) {
            $this->oApiGroupsManager = new Managers\Groups\Manager($this);
        }

        return $this->oApiGroupsManager;
    }

    /**
     * Adds users to group.
     * @param int $GroupId Group identifier.
     * @param array $UsersIds List of user identifiers.
     * @return boolean
     */
    public function AddToGroup($GroupId = 0, $UsersIds = [])
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);

        if ($GroupId === 0 || empty($UsersIds)) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
        }

        foreach ($UsersIds as $iUserId) {
            self::Decorator()->UpdateUserGroup($iUserId, $GroupId);
        }

        return true; // If something goes wrong, an exception will be thrown.
    }

    /**
     * Creates user group.
     * @param int $TenantId Tenant identifier.
     * @param string $Name Group name.
     * @return int|false New group identifier
     * @throws \Aurora\System\Exceptions\ApiException
     */
    public function CreateGroup($TenantId = 0, $Name = '')
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);

        if ($Name === '') {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
        }

        // If $TenantId is 0, the new group is custom and belongs to some particular user.
        return $this->getGroupsManager()->createGroup($TenantId, $Name);
    }

    /**
     * Deletes Groups.
     * @param array $IdList List of Group identifiers.
     * @return array
     */
    public function DeleteGroups($TenantId, $IdList)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);

        $aUsersIds = [];
        foreach ($IdList as $iGroupId) {
            if ($this->getGroupsManager()->deleteGroup($iGroupId)) {
                $aUsers = self::Decorator()->GetGroupUsers($iGroupId);
                $aGroupUsersIds = array_map(function ($oUser) {
                    return $oUser['Id'];
                }, $aUsers);
                $aUsersIds = array_unique(array_merge($aUsersIds, $aGroupUsersIds));
            }
        }

        if (!empty($aUsersIds)) {
            $oDefaultGroup = $TenantId !== 0 ? self::Decorator()->GetDefaultGroup($TenantId) : null;
            if ($oDefaultGroup instanceof Models\Group) {
                self::Decorator()->AddToGroup($oDefaultGroup->Id, $aUsersIds);
            }
        }

        return true; // If something goes wrong, an exception will be thrown.
    }

    /**
     * Obtains specified group.
     * @param int $Id Group identifier.
     * @return array|boolean
     */
    public function GetGroup($Id)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        return $this->getGroupsManager()->getGroup($Id);
    }

    /**
     * Obtains default group for specified tenant.
     * If the tenant does not have a default group, the default is set to the first group.
     * @param int $TenantId
     * @return object|false
     */
    public function GetDefaultGroup($TenantId)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        return $this->getGroupsManager()->getDefaultGroup($TenantId);
    }

    /**
     * Obtains list of users for specified group.
     * @param int $GroupId Group identifier.
     * @return array|boolean
     */
    public function GetGroupUsers($GroupId = 0, $TenantId = 0)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);

        if ($GroupId === 0) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
        }

        $oFilter = User::where('Properties->' . self::GetName() . '::GroupId', $GroupId);
        $aUsers = \Aurora\Modules\Core\Module::Decorator()->GetUsers($TenantId, 0, 0, 'PublicId', \Aurora\System\Enums\SortOrder::ASC, '', $oFilter);
        return is_array($aUsers) && is_array($aUsers['Items']) ? $aUsers['Items'] : [];
    }

    /**
     * Obtains all Groups for specified tenant.
     * @param int $TenantId Tenant identifier.
     * @param int $Offset Offset of the list.
     * @param int $Limit Limit of the list.
     * @param string $Search Search string.
     * @return array|boolean
     * @throws \Aurora\System\Exceptions\ApiException
     */
    public function GetGroups($TenantId = 0, $Offset = 0, $Limit = 0, $Search = '')
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);

        if ($TenantId === 0) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
        }

        $aGroups = $this->getGroupsManager()->getGroups($TenantId, $Offset, $Limit, $Search);
        $iGroupsCount = $this->getGroupsManager()->getGroupsCount($TenantId, $Search);
        if (is_array($aGroups)) {
            return [
                'Count' => $iGroupsCount,
                'Items' => $aGroups
            ];
        }

        return false;
    }

    /**
     * Removes users from group.
     * @param int $GroupId Group identifier.
     * @param array $UsersIds List of user identifiers.
     * @return boolean
     */
    public function RemoveUsersFromGroup($GroupId, $UsersIds)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);

        if ($GroupId === 0 && empty($UsersIds)) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
        }

        return $this->getGroupsManager()->removeUsersFromGroup($GroupId, $UsersIds);
    }

    /**
     * Saves group for specified user.
     * @param int $UserId User identifier.
     * @param array $GroupId Group identifier.
     * @return boolean
     */
    public function UpdateUserGroup($UserId, $GroupId)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);

        if ($UserId === 0) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
        }

        $oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserWithoutRoleCheck($UserId);
        if ($oUser instanceof \Aurora\Modules\Core\Models\User) {
            $oUser->setExtendedProp(self::GetName() . '::GroupId', (int) $GroupId);
            $oUser->save();
        }

        return $oUser->{self::GetName() . '::GroupId'}; // If something goes wrong, an exception will be thrown.
    }

    /**
     * Saves group for specified user.
     * @param int $UserId User identifier.
     * @param int $TenantId Tenant identifier.
     * @param string $GroupName Group name is unique within one tenant.
     * @return array|boolean
     */
    public function UpdateUserGroupByName($UserId, $TenantId, $GroupName)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);

        $oGroup = $this->getGroupsManager()->getGroupByName($TenantId, $GroupName);

        if ($oGroup instanceof Models\Group) {
            return self::Decorator()->UpdateUserGroup($UserId, $oGroup->Id);
        } else {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
        }
    }

    /**
     * Updates user group.
     * @param int $Id
     * @return boolean
     * @throws \Aurora\System\Exceptions\ApiException
     */
    public function UpdateGroup($Id = 0)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);

        if ($Id === 0) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
        }

        // Name cannot be changed anymore
        // Some extended props can be changed by subscribers
        return true; // If something goes wrong, an exception will be thrown.
    }

    /**
     * Updates default user group.
     * @param int $TenantId
     * @param int $DefaultGroupId
     * @return boolean
     * @throws \Aurora\System\Exceptions\ApiException
     */
    public function ChangeDefaultGroup($TenantId = 0, $DefaultGroupId = 0)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);

        if ($TenantId === 0 || $DefaultGroupId === 0) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
        }

        return $this->getGroupsManager()->changeDefaultGroup($TenantId, $DefaultGroupId);
    }

    /**
     * Removes groups of tenant after its deleting.
     * @param array $aArgs
     * @param mixed $mResult
     */
    public function onAfterDeleteTenant($aArgs, &$mResult)
    {
        $iTenantId = $aArgs['TenantId'];
        $aGroups = $this->getGroupsManager()->getGroups($iTenantId);
        foreach ($aGroups as $oGropup) {
            $this->getGroupsManager()->deleteGroup($oGropup->Id);
        }
    }

    /**
     * Prepares filters to get users from the specified group(s).
     * @param array $aArgs
     * @param mixed $mResult
     */
    public function onBeforeGetUsers(&$aArgs, &$mResult)
    {
        // -2: in Custom groups
        // -1: all group, filters are not needed
        // 0: not in any group
        // > 0: some particular group
        if (isset($aArgs['GroupId']) && $aArgs['GroupId'] !== -1) {
            $oFilter = ($aArgs['Filters'] instanceof Builder) ? $aArgs['Filters'] : User::query();
            if ($aArgs['GroupId'] === -2) {
                $aGroups = \Aurora\Modules\CoreUserGroups\Module::Decorator()->GetGroups($aArgs['TenantId']);
                $aGroupsIds = array_map(function ($oGroup) {
                    return $oGroup->Id;
                }, $aGroups['Items']);
                $aGroupsIds[] = 0;
                $aArgs['Filters'] = $oFilter->whereNoIn('Properties->CoreUserGroups::GroupId', $aGroupsIds);
            } else {
                $aArgs['Filters'] = $oFilter->where('Properties->CoreUserGroups::GroupId', $aArgs['GroupId']);
            }
        }
    }

    /**
     * @param array $aArgs
     * @param mixed $mResult
     */
    public function onAfterCreateUser($aArgs, &$mResult)
    {
        if ($mResult) {
            $oDefaultGroup = self::Decorator()->GetDefaultGroup($aArgs['TenantId']);
            if ($oDefaultGroup instanceof Models\Group) {
                self::Decorator()->UpdateUserGroup($mResult, $oDefaultGroup->Id);
            }
        }
    }
}
