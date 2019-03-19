<?php
/**
 * This code is licensed under AfterLogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\UserGroups\Managers\Groups;

/**
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package UserGroups
 * @subpackage Managers
 */
class Manager extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * @var \Aurora\System\Managers\Eav
	 */
	public $oEavManager = null;
	
	/**
	 * @param \Aurora\System\Module\AbstractModule $oModule
	 */
	public function __construct(\Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct($oModule);
		
		$this->oEavManager = \Aurora\System\Managers\Eav::getInstance();
	}

	/**
	 * Adds users to group.
	 * @param int $iGroupId Group identifier.
	 * @param array $aUsersIds List of user identifiers.
	 * @return boolean
	 */
	public function addToGroup($iGroupId, $aUsersIds)
	{
		foreach ($aUsersIds as $iUserId)
		{
			$aFilters = [
				'$AND' => [
					'GroupId' => [$iGroupId, '='],
					'UserId' => [$iUserId, '=']
				]
			];
			
			$aGroupUser = $this->oEavManager->getEntities(\Aurora\Modules\UserGroups\Classes\GroupUser::class, array(), 0, 0, $aFilters);

			if (empty($aGroupUser))
			{
				$oUserGroup = new \Aurora\Modules\UserGroups\Classes\GroupUser(\Aurora\Modules\UserGroups\Module::GetName());
				$oUserGroup->GroupId = $iGroupId;
				$oUserGroup->UserId = $iUserId;
				$this->oEavManager->saveEntity($oUserGroup);
			}
		}
		
		return true;
	}
	
	/**
	 * Creates group.
	 * @param int $iTenantId Tenant identifier.
	 * @param string $sName Group name.
	 * @return boolean
	 */
	public function createGroup($iTenantId, $sName)
	{
		$oGroup = new \Aurora\Modules\UserGroups\Classes\Group(\Aurora\Modules\UserGroups\Module::GetName());
		$oGroup->TenantId = $iTenantId;
		$oGroup->Name = $sName;
		
		$this->oEavManager->saveEntity($oGroup);
		
		return $oGroup->EntityId;
	}
	
	/**
	 * Deletes group.
	 * @param int $iGroupId Group identifier.
	 * @return boolean
	 */
	public function deleteGroup($iGroupId)
	{
		$bResult = false;
		$oGroup = $this->getGroup($iGroupId);
		if ($oGroup)
		{
			$aGroupUserObjects = $this->getGroupUserObjects($iGroupId);
			$aIdsToDelete = [$iGroupId];
			foreach ($aGroupUserObjects as $oGroupUser)
			{
				$aIdsToDelete[] = $oGroupUser->EntityId;
			}
			return $this->oEavManager->deleteEntities($aIdsToDelete);
		}
		return $bResult;
	}
	
	/**
	 * Obtains specified group.
	 * @param int $iGroupId Group identifier.
	 * @return \Aurora\Modules\UserGroups\Classes\Group|boolean
	 */
	public function getGroup($iGroupId)
	{
		return $this->oEavManager->getEntity($iGroupId, \Aurora\Modules\UserGroups\Classes\Group::class);
	}
	
	/**
	 * Obtains list of group-user binding objects.
	 * @param int $iGroupId Group identifier.
	 * @return array|boolean
	 */
	public function getGroupUserObjects($iGroupId)
	{
		$aFilters = ['GroupId' => [$iGroupId, '=']];
		return $this->oEavManager->getEntities( \Aurora\Modules\UserGroups\Classes\GroupUser::class, array(), 0, 0, $aFilters);
	}
	
	/**
	 * Obtains all groups for specified tenant.
	 * @param int $iTenantId Tenant identifier.
	 * @return array|boolean
	 */
	public function getGroups($iTenantId)
	{
		$iOffset = 0;
		$iLimit = 0;
		$aFilters = ['TenantId' => [$iTenantId, '=']];
		$sOrderBy = 'Name';
		$iOrderType = \Aurora\System\Enums\SortOrder::ASC;
		
		return $this->oEavManager->getEntities(
			\Aurora\Modules\UserGroups\Classes\Group::class,
			array(),
			$iOffset,
			$iLimit,
			$aFilters,
			$sOrderBy,
			$iOrderType
		);
	}
	
	/**
	 * Obtains all groups of specified user.
	 * @param int $iUserId User identifier.
	 * @return array|boolean
	 */
	public function getGroupsOfUser($iUserId)
	{
		$aFilters = ['UserId' => [$iUserId, '=']];
		return $this->oEavManager->getEntities( \Aurora\Modules\UserGroups\Classes\GroupUser::class, array(), 0, 0, $aFilters);
	}
	
	/**
	 * Removes users from group.
	 * @param int $iGroupId Group identifier.
	 * @param array $aUsersIds List of user identifiers.
	 * @return boolean
	 */
	public function removeUsersFromGroup($iGroupId, $aUsersIds)
	{
		$aFilters = [
			'GroupId' => [$iGroupId, '=']
		];
		$aGroupUser = $this->oEavManager->getEntities(\Aurora\Modules\UserGroups\Classes\GroupUser::class, array(), 0, 0, $aFilters);
		
		$aGroupUserToDelete = [];
		foreach ($aGroupUser as $oGroupUser)
		{
			$iKey = array_search($oGroupUser->UserId, $aUsersIds);
			if ($iKey !== false)
			{
				$aGroupUserToDelete[] = $oGroupUser->EntityId;
			}
		}
		
		$this->oEavManager->deleteEntities($aGroupUserToDelete);
		
		return true;
	}
	
	/**
	 * Saves list of groups for specified user.
	 * @param int $iUserId User identifier.
	 * @param array $aGroupsIds List of group identifiers.
	 * @return boolean
	 */
	public function saveGroupsOfUser($iUserId, $aGroupsIds)
	{
		$aFilters = [
			'UserId' => [$iUserId, '=']
		];
		$aGroupUser = $this->oEavManager->getEntities(\Aurora\Modules\UserGroups\Classes\GroupUser::class, array(), 0, 0, $aFilters);
		
		$aGroupUserToDelete = [];
		foreach ($aGroupUser as $oGroupUser)
		{
			$iKey = array_search($oGroupUser->GroupId, $aGroupsIds);
			if ($iKey === false)
			{
				$aGroupUserToDelete[] = $oGroupUser->EntityId;
			}
			else
			{
				unset($aGroupsIds[$iKey]);
			}
		}
		
		$this->oEavManager->deleteEntities($aGroupUserToDelete);

		foreach ($aGroupsIds as $iGroupId)
		{
			$oUserGroup = new \Aurora\Modules\UserGroups\Classes\GroupUser(\Aurora\Modules\UserGroups\Module::GetName());
			$oUserGroup->GroupId = $iGroupId;
			$oUserGroup->UserId = $iUserId;
			$this->oEavManager->saveEntity($oUserGroup);
		}
		
		return true;
	}
	
	/**
	 * Updates group.
	 * @param int $iGroupId Group identifier.
	 * @param int $sName New group name.
	 * @return boolean
	 */
	public function updateGroup($iGroupId, $sName)
	{
		$oGroup = $this->getGroup($iGroupId);
		$oGroup->Name = $sName;
		
		return $this->oEavManager->saveEntity($oGroup);
	}
}
