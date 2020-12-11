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
 * @copyright Copyright (c) 2020, Afterlogic Corp.
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
		];
		$this->subscribeEvent('Core::DeleteTenant::after', array($this, 'onAfterDeleteTenant'));
		
		\Aurora\Modules\Core\Classes\User::extend(
			self::GetName(),
			[
				'GroupId' => array('int', 0),
			]
		);		
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
		
		foreach ($UsersIds as $iUserId)
		{
			$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($iUserId);
			if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
			{
				$oUser->{self::GetName() . '::GroupId'} = (int) $GroupId;
				$oUser->saveAttribute(self::GetName() . '::GroupId');
			}
		}
		
		return true;
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
		
		$oTenant = \Aurora\Modules\Core\Module::Decorator()->GetTenantUnchecked($TenantId);
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
			if ($this->getGroupsManager()->deleteGroup($iGroupId))
			{
				$aUsers = self::Decorator()->GetGroupUsers($iGroupId);
				$aGroupUsersIds = array_map(function ($oUser) {
					return $oUser['Id'];
				}, $aUsers);
				$aUsersIds = array_unique(array_merge($aUsersIds, $aGroupUsersIds));
			}
		}
		
		// Subscribers need this result
		return $aUsersIds;
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
	 * Obtains list of users for specified group.
	 * @param int $GroupId Group identifier.
	 * @return array|boolean
	 */
	public function GetGroupUsers($GroupId = 0, $TenantId = 0)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		if ($GroupId === 0)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		$aFilters = [self::GetName() . '::GroupId' => [$GroupId, '=']];
		$aUsers = \Aurora\Modules\Core\Module::Decorator()->GetUsers($TenantId, 0, 0, 'PublicId', \Aurora\System\Enums\SortOrder::ASC, '', $aFilters);
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
	 * @param array $GroupId Group identifier.
	 * @return boolean
	 */
	public function UpdateUserGroup($UserId, $GroupId)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		if ($UserId === 0)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($UserId);
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$oUser->{self::GetName() . '::GroupId'} = (int) $GroupId;
			$oUser->saveAttribute(self::GetName() . '::GroupId');
		}
		
		return true;
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
		
		if ($Id === 0)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		// Name cannot be changed anymore
		// Some extended props can be changed by subscribers
		return true;
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
}
