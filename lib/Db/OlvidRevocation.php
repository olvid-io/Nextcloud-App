<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

class OlvidRevocation extends Entity {
	protected string $olvidId = '';
	protected int $timestamp = 0;
	protected int $revocationType = 0;
	protected string $signature = '';
	protected ?string $username = null;
	protected ?string $firstname = null;
	protected ?string $lastname = null;
	protected ?string $mail = null;
	protected ?string $position = null;
	protected ?string $company = null;
	protected ?string $fullSearchString = null;

	public function __construct() {
		$this->addType('olvidId', Types::STRING);
		$this->addType('timestamp', Types::BIGINT);
		$this->addType('revocationType', Types::INTEGER);
		$this->addType('signature', Types::TEXT);
		$this->addType('username', Types::STRING);
		$this->addType('firstname', Types::STRING);
		$this->addType('lastname', Types::STRING);
		$this->addType('mail', Types::STRING);
		$this->addType('position', Types::STRING);
		$this->addType('company', Types::STRING);
		$this->addType('fullSearchString', Types::STRING);
	}

	public function getOlvidId(): string {
		return $this->olvidId;
	}

	public function setOlvidId(string $olvidId): void {
		$this->olvidId = $olvidId;
		$this->markFieldUpdated('olvidId');
	}

	public function getTimestamp(): int {
		return $this->timestamp;
	}

	public function setTimestamp(int $timestamp): void {
		$this->timestamp = $timestamp;
		$this->markFieldUpdated('timestamp');
	}

	public function getRevocationType(): int {
		return $this->revocationType;
	}

	public function setRevocationType(int $revocationType): void {
		$this->revocationType = $revocationType;
		$this->markFieldUpdated('revocationType');
	}

	public function getSignature(): string {
		return $this->signature;
	}

	public function setSignature(string $signature): void {
		$this->signature = $signature;
		$this->markFieldUpdated('signature');
	}

	public function getUsername(): ?string {
		return $this->username;
	}

	public function setUsername(?string $username): void {
		$this->username = $username;
		$this->markFieldUpdated('username');
	}

	public function getFirstname(): ?string {
		return $this->firstname;
	}

	public function setFirstname(?string $firstname): void {
		$this->firstname = $firstname;
		$this->markFieldUpdated('firstname');
	}

	public function getLastname(): ?string {
		return $this->lastname;
	}

	public function setLastname(?string $lastname): void {
		$this->lastname = $lastname;
		$this->markFieldUpdated('lastname');
	}

	public function getMail(): ?string {
		return $this->mail;
	}

	public function setMail(?string $mail): void {
		$this->mail = $mail;
		$this->markFieldUpdated('mail');
	}

	public function getPosition(): ?string {
		return $this->position;
	}

	public function setPosition(?string $position): void {
		$this->position = $position;
		$this->markFieldUpdated('position');
	}

	public function getCompany(): ?string {
		return $this->company;
	}

	public function setCompany(?string $company): void {
		$this->company = $company;
		$this->markFieldUpdated('company');
	}

	public function getFullSearchString(): ?string {
		return $this->fullSearchString;
	}

	public function setFullSearchString(?string $fullSearchString): void {
		$this->fullSearchString = $fullSearchString;
		$this->markFieldUpdated('fullSearchString');
	}

	public function recomputeFullSearchString(): void {
		$parts = [$this->username, $this->firstname, $this->lastname, $this->position, $this->company];
		$this->setFullSearchString(mb_substr(implode(' ', array_filter($parts)), 0, 255));
	}

	public function __toString(): string {
		return 'OlvidRevocation{'
			. 'id=' . $this->getId()
			. ', olvidId=' . $this->olvidId
			. ', timestamp=' . $this->timestamp
			. ', revocationType=' . $this->revocationType
			. ', username=' . $this->username
			. ', firstname=' . $this->firstname
			. ', lastname=' . $this->lastname
			. ', mail=' . $this->mail
			. ', position=' . $this->position
			. ', company=' . $this->company
			. '}';
	}
}
