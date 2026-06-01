<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use ReflectionClass;
use ReflectionNamedType;

trait JsonSerializableTrait {

	public function jsonSerialize(): array {
		$result = [];
		foreach ((new ReflectionClass($this))->getProperties() as $prop) {
			$attrs = $prop->getAttributes(JsonField::class);
			if (empty($attrs)) {
				continue;
			}
			$field = $attrs[0]->newInstance();
			if ($field->excludeFromJson) {
				continue;
			}
			$value = $prop->getValue($this);
			$serialized = self::serializeValue($value, $field);
			if ($serialized !== null) {
				$result[$field->key] = $serialized;
			}
		}
		return $result;
	}

	public static function fromArray(array $data): static {
		return static::hydrateFromArray($data);
	}

	protected static function hydrateFromArray(array $data): static {
		$ref = new ReflectionClass(static::class);
		$instance = $ref->newInstanceWithoutConstructor();
		foreach ($ref->getProperties() as $prop) {
			$attrs = $prop->getAttributes(JsonField::class);
			if (empty($attrs)) {
				continue;
			}
			$field = $attrs[0]->newInstance();
			if ($field->excludeFromJson) {
				$prop->setValue($instance, self::typeDefault($prop->getType()));
				continue;
			}
			$raw = $data[$field->key] ?? null;
			$value = self::deserializeValue($raw, $field);
			if ($value === null) {
				$value = self::typeDefault($prop->getType());
			}
			$prop->setValue($instance, $value);
		}
		return $instance;
	}

	private static function serializeValue(mixed $value, JsonField $field): mixed {
		if ($value === null || $value === '' || $value === []) {
			return null;
		}
		if ($field->isBytes) {
			return base64_encode($value);
		}
		if ($field->class !== null && $field->isArray) {
			$mapped = array_map(fn ($item) => $item->jsonSerialize(), $value);
			return empty($mapped) ? null : $mapped;
		}
		if ($field->class !== null) {
			$serialized = $value->jsonSerialize();
			if (empty($serialized)) {
				return $field->emptyAsObject ? (object)[] : null;
			}
			return $serialized;
		}
		return $value;
	}

	private static function deserializeValue(mixed $value, JsonField $field): mixed {
		if ($value === null || $value === '' || $value === []) {
			return null;
		}
		if ($field->isBytes) {
			$decoded = base64_decode((string)$value, true);
			return $decoded !== false ? $decoded : null;
		}
		if ($field->class !== null && $field->isArray) {
			$items = array_map(fn ($m) => ($field->class)::fromArray((array)$m), (array)$value);
			return empty($items) ? null : $items;
		}
		if ($field->class !== null) {
			return ($field->class)::fromArray((array)$value);
		}
		return $value;
	}

	private static function typeDefault(?\ReflectionType $type): mixed {
		if ($type === null || $type->allowsNull()) {
			return null;
		}
		if ($type instanceof ReflectionNamedType) {
			return match ($type->getName()) {
				'int', 'float' => 0,
				'bool'         => false,
				'array'        => [],
				'string'       => '',
				default        => null,
			};
		}
		return null;
	}
}
