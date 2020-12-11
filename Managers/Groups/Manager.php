<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\CoreUserGroups\Managers\Groups;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2020, Afterlogic Corp.
 *
 * @package CoreUserGroups
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
	 * Creates group.
	 * @param int $iTenantId Tenant identifier.
	 * @param string $sName Group name.
	 * @return boolean
	 */
	public function createGroup($iTenantId, $sName)
	{
		$oGroupWithSameName = $this->getGroupByName($iTenantId, $sName);
		
		if ($oGroupWithSameName !== false)
		{
			throw new \Aurora\Modules\CoreUserGroups\Exceptions\Exception(\Aurora\Modules\CoreUserGroups\Enums\ErrorCodes::GroupAlreadyExists);
		}
		
		$oGroup = new \Aurora\Modules\CoreUserGroups\Classes\Group(\Aurora\Modules\CoreUserGroups\Module::GetName());
		$oGroup->TenantId = $iTenantId;
		$oGroup->Name = $sName;
		
		$this->oEavManager->saveEntity($oGroup);
		
		return $oGroup->EntityId;
	}
	
	/**
	 * Deletes group.
	 * @param int $iGroupId Group identifier.
	 * @return array
	 */
	public function deleteGroup($iGroupId)
	{
		return $this->oEavManager->deleteEntities([$iGroupId]);
	}
	
	/**
	 * Obtains specified group.
	 * @param int $iGroupId Group identifier.
	 * @return \Aurora\Modules\CoreUserGroups\Classes\Group|boolean
	 */
	public function getGroup($iGroupId)
	{
		return $this->oEavManager->getEntity($iGroupId, \Aurora\Modules\CoreUserGroups\Classes\Group::class);
	}
	
	/**
	 * Obtains group with specified name and tenant identifier.
	 * @param int $iTenantId Tenant identifier.
	 * @param string $sGroupName Group name is unique within one tenant.
	 * @return \Aurora\Modules\CoreUserGroups\Classes\Group|boolean
	 */
	public function getGroupByName($iTenantId, $sGroupName)
	{
		$aFilters = [
			'TenantId' => [$iTenantId, '='],
			'Name' => [$sGroupName, '=']
		];
		
		$aGroups = $this->oEavManager->getEntities(
			\Aurora\Modules\CoreUserGroups\Classes\Group::class,
			array(),
			0,
			0,
			$aFilters
		);
		
		return count($aGroups) > 0 ? $aGroups[0] : false;
	}
	
	/**
	 * @param int $iTenantId Tenant identifier.
	 * @param string $sSearch Search string.
	 * @return int|false
	 */
	public function getGroupsCount($iTenantId, $sSearch)
	{
		$aFilters = [
			'TenantId' => [$iTenantId, '='],
			'Name' => ['%' . $sSearch . '%', 'LIKE'],
		];
		
		return $this->oEavManager->getEntitiesCount(
			\Aurora\Modules\CoreUserGroups\Classes\Group::class,
			$aFilters
		);
	}
	
	/**
	 * Obtains all groups for specified tenant.
	 * @param int $iTenantId Tenant identifier.
	 * @param int $iOffset Offset of the list.
	 * @param int $iLimit Limit of the list.
	 * @param string $sSearch Search string.
	 * @return array|boolean
	 */
	public function getGroups($iTenantId, $iOffset = 0, $iLimit = 0, $sSearch = '')
	{
		$aFilters = [
			'TenantId' => [$iTenantId, '='],
			'Name' => ['%' . $sSearch . '%', 'LIKE']
		];
		$sOrderBy = 'Name';
		$iOrderType = \Aurora\System\Enums\SortOrder::ASC;
		
		return $this->oEavManager->getEntities(
			\Aurora\Modules\CoreUserGroups\Classes\Group::class,
			array(),
			$iOffset,
			$iLimit,
			$aFilters,
			$sOrderBy,
			$iOrderType
		);
	}
	
	/**
	 * Removes users from group.
	 * @param int $iGroupId Group identifier.
	 * @param array $aUsersIds List of user identifiers.
	 * @return boolean
	 */
	public function removeUsersFromGroup($iGroupId, $aUsersIds)
	{
		$aAndFilter = [];
		$aAndFilter[\Aurora\Modules\CoreUserGroups\Module::GetName() . '::GroupId'] = [$iGroupId, '='];
		$aAndFilter['EntityId'] = [$aUsersIds, 'IN'];
		$aFilters = [
			'$AND' => $aAndFilter
		];
		$aUsers = $this->oEavManager->getEntities(\Aurora\Modules\Core\Classes\User::class, array(), 0, 0, $aFilters);
		
		foreach ($aUsers as $oUser)
		{
			$oUser->{\Aurora\Modules\CoreUserGroups\Module::GetName() . '::GroupId'} = 0;
			$oUser->saveAttribute(\Aurora\Modules\CoreUserGroups\Module::GetName() . '::GroupId');
		}
		
		return true;
	}
}
