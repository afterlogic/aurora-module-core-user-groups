<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\CoreUserGroups\Models;

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
class Group extends \Aurora\System\Classes\Model
{
	public $GroupUsers = array();

	protected $table = 'core_user_groups';

	protected $fillable = [
		'Id',
		'TenantId',
		'Name',
		'IsDefault'
	];

	public function toResponseArray()
	{
		$aResponse = parent::toResponseArray();
		$aResponse['Id'] = $this->Id;

		$aArgs = ['Group' => $this];
		\Aurora\System\Api::GetModule('Core')->broadcastEvent(
			'CoreUserGroups::Group::ToResponseArray',
			$aArgs,
			$aResponse
		);

		return $aResponse;
	}
}
