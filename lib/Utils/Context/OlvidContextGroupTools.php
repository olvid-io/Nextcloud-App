<?php

namespace OCA\Olvid\Utils\Context;

use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCP\IGroup;
use OCP\IUser;

class OlvidContextGroupTools {
	/**
	 * @param IGroup $group
	 * @return string[]
	 */
	public function getGroupMemberIds(IGroup $nextcloudGroup): array {
		return array_map(fn (IUser $gm) => $gm->getUID(), $nextcloudGroup->getUsers());
	}

	/**
	 * @param Circle $circle
	 * @return string[]
	 */
	public function getCircleMemberIds(Circle $circle): array {
		return array_map(fn (Member $m) => $m->getUserId(), $circle->getMembers());
	}
}
