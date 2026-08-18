<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

enum GroupPermission: string {
	case GROUP_ADMIN = 'ga';
	case REMOTE_DELETE_ANYTHING = 'rd';
	case EDIT_OR_REMOTE_DELETE_OWN_MESSAGES = 'eo';
	case CHANGE_SETTINGS = 'cs';
	case SEND_MESSAGE = 'sm';

	/**
	 * @return string[] GroupPermission
	 */
	public static function getDefaultGroupPermissions(): array {
		return [
			GroupPermission::EDIT_OR_REMOTE_DELETE_OWN_MESSAGES->value,
			GroupPermission::SEND_MESSAGE->value,
		];
	}

	/**
	 * @param string[] $permissionStrings
	 * @return self[]
	 */
	public static function fromStrings(iterable $permissionStrings): array {
		$res = [];
		foreach ($permissionStrings as $permissionString) {
			$permission = self::tryFrom($permissionString);
			if ($permission !== null && !in_array($permission, $res, true)) {
				$res[] = $permission;
			}
		}
		return $res;
	}

	/**
	 * @return string[]
	 */
	public static function deserializePermissions(string $serializedPermissions): array {
		$permissionStrings = [];
		$startPos = 0;
		$length = strlen($serializedPermissions);
		for ($i = 0; $i < $length; $i++) {
			if ($serializedPermissions[$i] === "\x00") {
				$permissionStrings[] = substr($serializedPermissions, $startPos, $i - $startPos);
				$startPos = $i + 1;
			}
		}
		if ($startPos !== $length) {
			$permissionStrings[] = substr($serializedPermissions, $startPos);
		}
		return $permissionStrings;
	}

	/**
	 * @return self[]
	 */
	public static function deserializeKnownPermissions(string $serializedPermissions): array {
		return self::fromStrings(self::deserializePermissions($serializedPermissions));
	}

	/**
	 * @param string[] $permissionStrings
	 */
	public static function serializePermissionStrings(iterable $permissionStrings): string {
		$parts = [];
		foreach ($permissionStrings as $permissionString) {
			$parts[] = $permissionString;
		}
		return implode("\x00", $parts);
	}

	/**
	 * @param self[] $permissions
	 */
	public static function serializePermissions(iterable $permissions): string {
		$parts = [];
		foreach ($permissions as $permission) {
			$parts[] = $permission->value;
		}
		return implode("\x00", $parts);
	}
}
