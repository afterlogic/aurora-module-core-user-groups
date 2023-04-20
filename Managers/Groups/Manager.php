<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\CoreUserGroups\Managers\Groups;

use Aurora\Modules\Core\Models\User;
use Aurora\Modules\CoreUserGroups\Models\Group;
use Aurora\Modules\CoreUserGroups\Exceptions\Exception;
use Aurora\Modules\CoreUserGroups\Enums\ErrorCodes;
use Aurora\Modules\CoreUserGroups\Module;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package CoreUserGroups
 * @subpackage Managers
 *
 * @property Module $oModule
 */
class Manager extends \Aurora\System\Managers\AbstractManager
{
    /**
     * @param \Aurora\System\Module\AbstractModule $oModule
     */
    public function __construct(\Aurora\System\Module\AbstractModule $oModule = null)
    {
        parent::__construct($oModule);
    }

    /**
     * Creates group.
     * @param int $iTenantId Tenant identifier.
     * @param string $sName Group name.
     * @return boolean
     */
    public function createGroup($iTenantId, $sName)
    {
        // Groups without tenant are custom groups and they can have the same name
        $oGroupWithSameName = ($iTenantId !== 0) ? $this->getGroupByName($iTenantId, $sName) : false;

        if ($oGroupWithSameName !== false) {
            throw new Exception(ErrorCodes::GroupAlreadyExists);
        }

        $oGroup = new Group();
        $oGroup->TenantId = $iTenantId;
        $oGroup->Name = $sName;

        $oGroup->save();

        return $oGroup->Id;
    }

    /**
     * Changes default user group.
     * @param int $iTenantId
     * @param int $iDefaultGroupId Group identifier.
     * @return array
     */
    public function changeDefaultGroup($iTenantId, $iDefaultGroupId)
    {
        $aGroups = $this->getGroups($iTenantId);
        foreach ($aGroups as $oGroup) {
            if ($oGroup->Id === $iDefaultGroupId && !$oGroup->IsDefault) {
                $oGroup->IsDefault = true;
                $oGroup->save();
            }
            if ($oGroup->Id !== $iDefaultGroupId && $oGroup->IsDefault) {
                $oGroup->IsDefault = false;
                $oGroup->save();
            }
        }
        return true;
    }

    /**
     * Deletes group.
     * @param int $iGroupId Group identifier.
     * @return array
     */
    public function deleteGroup($iGroupId)
    {
        $mResult = false;
        $oGroup = $this->getGroup($iGroupId);
        if ($oGroup instanceof Group && $oGroup->IsDefault) {
            if ($oGroup->IsDefault) {
                throw new Exception(ErrorCodes::CannotDeleteDefaultGroup);
            }
            $mResult = $oGroup->delete();
        }

        return $mResult;
    }

    /**
     * Obtains default group for specified tenant.
     * If the tenant does not have a default group, the default is set to the first group.
     * @param int $iTenantId
     * @return object|false
     */
    public function getDefaultGroup($iTenantId)
    {
        if ($iTenantId === 0) {
            return null;
        }

        $oDefaultGroup = Group::firstWhere([['TenantId', '=', $iTenantId], ['IsDefault', '=', true]]);

        if (!($oDefaultGroup instanceof Group)) {
            $oDefaultGroup = Group::firstWhere('TenantId', $iTenantId);
            if ($oDefaultGroup instanceof Group) {
                $oDefaultGroup->IsDefault = true;
                $oDefaultGroup->save();
            }
        }

        return $oDefaultGroup;
    }

    /**
     * Obtains specified group.
     * @param int $iGroupId Group identifier.
     * @return \Aurora\Modules\CoreUserGroups\Models\Group|boolean
     */
    public function getGroup($iGroupId)
    {
        return Group::find($iGroupId);
    }

    /**
     * Obtains group with specified name and tenant identifier.
     * @param int $iTenantId Tenant identifier.
     * @param string $sGroupName Group name is unique within one tenant.
     * @return \Aurora\Modules\CoreUserGroups\Classes\Group|boolean
     */
    public function getGroupByName($iTenantId, $sGroupName)
    {
        return Group::firstWhere([['TenantId', '=', $iTenantId], ['Name', '=', $sGroupName]]);
    }

    /**
     * @param int $iTenantId Tenant identifier.
     * @param string $sSearch Search string.
     * @return int|false
     */
    public function getGroupsCount($iTenantId, $sSearch)
    {
        return Group::where([['TenantId', '=', $iTenantId], ['Name', 'LIKE', '%' . $sSearch . '%']])->count();
    }

    /**
     * Obtains all groups for specified tenant.
     * Checks if the tenant has a default group. If not, the default is set to the first group.
     * @param int $iTenantId Tenant identifier.
     * @param int $iOffset Offset of the list.
     * @param int $iLimit Limit of the list.
     * @param string $sSearch Search string.
     * @return array|boolean
     */
    public function getGroups($iTenantId, $iOffset = 0, $iLimit = 0, $sSearch = '')
    {
        $oQuery = Group::where([['TenantId', '=', $iTenantId], ['Name', 'LIKE', '%' . $sSearch . '%']]);
        if ($iOffset > 0) {
            $oQuery = $oQuery->offset($iOffset);
        }
        if ($iLimit > 0) {
            $oQuery = $oQuery->limit($iLimit);
        }
        $aGroups = $oQuery->get();
        if (count($aGroups) && $iTenantId > 0) {
            // The tenant must have at least one default group
            $oDefaultGroup = null;
            foreach ($aGroups as $oGroup) {
                if ($oGroup->IsDefault) {
                    $oDefaultGroup = $oGroup;
                    break;
                }
            }
            if ($oDefaultGroup === null) {
                $oDefaultGroup = $aGroups[0];
                $oDefaultGroup->IsDefault = true;
                $oDefaultGroup->save();
            }
        }

        return $aGroups;
    }

    /**
     * Removes users from group.
     * @param int $iGroupId Group identifier.
     * @param array $aUsersIds List of user identifiers.
     * @return boolean
     */
    public function removeUsersFromGroup($iGroupId, $aUsersIds)
    {
        User::where('Properties->' . Module::GetName() . '::GroupId', $iGroupId)
            ->whereIn('Id', $aUsersIds)
            ->update('Properies->' . Module::GetName() . '::GroupId', 0);

        return true;
    }
}
