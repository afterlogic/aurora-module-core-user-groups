<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\CoreUserGroups;

/**
 * Provides user groups.
 * 
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
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
		$this->subscribeEvent('Core::DeleteTenant::after', array($this, 'onAfterDeleteTenant'));
		$this->subscribeEvent('Core::DeleteUser::before', array($this, 'onBeforeDeleteUser'));
	}
	
	public function getGroupsManager()
	{
		if ($this->oApiGroupsManager === null)
		{
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
		
		if ($GroupId === 0 || empty($UsersIds))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		return $this->getGroupsManager()->addToGroup($GroupId, $UsersIds);
	}
	
	/**
	 * Creates user group.
	 * @param int $TenantId Tenant identifier.
	 * @param string $Name Group name.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function CreateGroup($TenantId = 0, $Name = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantById($TenantId);
		if (!$oTenant || $Name === '')
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		return $this->getGroupsManager()->createGroup($TenantId, $Name);
	}
	
	/**
	 * Deletes Groups.
	 * @param int $IdList List of Group identifiers.
	 * @return array
	 */
	public function DeleteGroups($IdList)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		$aUsersIds = [];
		foreach ($IdList as $iGroupId)
		{
			$aGroupUsersIds = $this->getGroupsManager()->deleteGroup($iGroupId);
			$aUsersIds = array_unique(array_merge($aUsersIds, $aGroupUsersIds));
		}
		
		return $aUsersIds;
	}
	
	/**
	 * Obtains specified group.
	 * @param int $Id Group identifier.
	 * @return array|boolean
	 */
	public function GetGroup($Id)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		return $this->getGroupsManager()->getGroup($Id);
	}
	
	/**
	 * Obtains list of users for specified group.
	 * @param int $GroupId Group identifier.
	 * @return array|boolean
	 */
	public function GetGroupUsers($GroupId = 0)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		if ($GroupId === 0)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		$aGroupUserObjects = $this->getGroupsManager()->getGroupUserObjects($GroupId);
		$aUsersIds = [];
		foreach ($aGroupUserObjects as $oGroupUser)
		{
			$aUsersIds[] = $oGroupUser->UserId;
		}
		$aFilters = ['EntityId' => [$aUsersIds, 'IN']];
		$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
		$aUsers = [];
		if ($oCoreDecorator && !empty($aUsersIds))
		{
			$aUsers = $oCoreDecorator->GetUserList(0, 0, 'PublicId', \Aurora\System\Enums\SortOrder::ASC, '', $aFilters);
		}
		
		return $aUsers;
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
		
		if ($TenantId === 0)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		$aGroups = $this->getGroupsManager()->getGroups($TenantId, $Offset, $Limit, $Search);
		$iGroupsCount = $this->getGroupsManager()->getGroupsCount($TenantId, $Search);
		if (is_array($aGroups))
		{
			return [
				'Count' => $iGroupsCount,
				'Items' => $aGroups
			];
		}
		
		return false;
	}
	
	/**
	 * Obtains all groups of specified user.
	 * @param int $UserId User identifier.
	 * @return array|boolean
	 */
	public function GetGroupsOfUser($UserId = 0)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		if ($UserId === 0)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		return $this->getGroupsManager()->getGroupsOfUser($UserId);
	}
	
	public function GetGroupNamesOfUser($UserId = 0)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		if ($UserId === 0)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		$aGroupUsers = $this->getGroupsManager()->getGroupsOfUser($UserId);
		$aGroups = [];
		foreach ($aGroupUsers as $oGroupUser)
		{
			$oGroup = $this->getGroupsManager()->getGroup($oGroupUser->GroupId);
			$aGroups[] = $oGroup->Name;
		}
		
		return $aGroups;
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
		
		if ($GroupId === 0 && empty($UsersIds))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		return $this->getGroupsManager()->removeUsersFromGroup($GroupId, $UsersIds);
	}
	
	/**
	 * Saves list of groups for specified user.
	 * @param int $UserId User identifier.
	 * @param array $GroupsIds List of group identifiers.
	 * @return boolean
	 */
	public function SaveGroupsOfUser($UserId, $GroupsIds)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		if ($UserId === 0)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		return $this->getGroupsManager()->saveGroupsOfUser($UserId, $GroupsIds);
	}
	
	/**
	 * Updates user group.
	 * @param array $Data Group data.
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UpdateGroup($Data = [])
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		$iGroupId = $Data['Id'];
		$sName = $Data['Name'];
		if ($iGroupId === 0 || $sName === '')
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		return $this->getGroupsManager()->updateGroup($iGroupId, $sName);
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
		foreach ($aGroups as $oGropup)
		{
			$this->getGroupsManager()->deleteGroup($oGropup->EntityId);
		}
	}
	
	/**
	 * Removes all groups of user before its deleting.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onBeforeDeleteUser($aArgs, &$mResult)
	{
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		
		$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
		$oUser = $oCoreDecorator ? $oCoreDecorator->GetUser($aArgs['UserId']) : null;
		
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User && $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::TenantAdmin && $oUser->IdTenant === $oAuthenticatedUser->IdTenant)
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		}
		else
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);
		}
		
		$this->getGroupsManager()->removeAllUserGroups($oUser->EntityId);
	}
}
