<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\CoreUserGroups\Classes;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2020, Afterlogic Corp.
 *
 * @property int $TenantId
 * @property string $Name
 *
 * @ignore
 * @package CoreUserGroups
 * @subpackage Classes
 */
class Group extends \Aurora\System\EAV\Entity
{
	public $GroupUsers = array();
	
	protected $aStaticMap = array(
		'TenantId' => array('int', 0, true),
		'Name' => array('string', '', true),
		'IsDefault' => array('bool', false, true),
	);

	public function toResponseArray()
	{
		$aResponse = parent::toResponseArray();
		$aResponse['Id'] = $this->EntityId;
		
		$aArgs = ['Group' => $this];
		\Aurora\System\Api::GetModule('Core')->broadcastEvent(
			'CoreUserGroups::Group::ToResponseArray',
			$aArgs,
			$aResponse
		);
		
		return $aResponse;
	}
}
