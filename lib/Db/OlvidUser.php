<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Nextcloud user id
 * @method string getUserId()
 * @method void setUserId(string $userId)
 *
 * Olvid identity as bytes
 * @method string|null getBytesIdentity()
 * @method void setBytesIdentity(string|null $bytesIdentity)
 *
 * @method string|null getApiKey()
 * @method void setApiKey(string|null $apiKey)
 *
 * @method string|null getNonce()
 * @method void setNonce(string|null $nonce)
 *
 * @method string|null getMagicToken()
 * @method void setMagicToken(string|null $magicToken)
 *
 * @method int|null getMagicTokenExpiration()
 * @method void setMagicTokenExpiration(int|null $magicTokenExpiration)
 *
 * @method int|null getSessionRevokedBefore()
 * @method void setSessionRevokedBefore(int|null $sessionRevokedBefore)
 *
 * @method string|null getSignedDetails()
 * @method void setSignedDetails(string|null $signedDetails)
 *
 * @method string|null getFirstname()
 * @method void setFirstname(string|null $firstname)
 *
 * @method string|null getLastname()
 * @method void setLastname(string|null $lastname)
 *
 * @method string|null getPosition()
 * @method void setPosition(string|null $position)
 *
 * @method string|null getCompany()
 * @method void setCompany(string|null $company)
 *
 * @method string|null getFullSearchField()
 * @method void setFullSearchField(string|null $fullSearchField)
 */
class OlvidUser extends Entity {
	protected string $userId = '';
	protected ?string $bytesIdentity = null;
	protected ?string $apiKey = null;
	protected ?string $nonce = null;
	protected ?string $magicToken = null;
	protected ?int $magicTokenExpiration = null;
	protected ?int $sessionRevokedBefore = null;
	protected ?string $signedDetails = null;
	protected ?string $firstname = null;
	protected ?string $lastname = null;
	protected ?string $position = null;
	protected ?string $company = null;
	protected ?string $fullSearchField = null;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('bytesIdentity', Types::BLOB);
		$this->addType('apiKey', Types::STRING);
		$this->addType('nonce', Types::STRING);
		$this->addType('magicToken', Types::STRING);
		$this->addType('magicTokenExpiration', Types::BIGINT);
		$this->addType('sessionRevokedBefore', Types::BIGINT);
		$this->addType('signedDetails', Types::STRING);
		$this->addType('firstname', Types::STRING);
		$this->addType('lastname', Types::STRING);
		$this->addType('position', Types::STRING);
		$this->addType('company', Types::STRING);
		$this->addType('fullSearchField', Types::STRING);
	}

	public static function create(string $userId): OlvidUser {
		$user = new OlvidUser();
		$user->setUserId($userId);
		return $user;
	}

	public function hasIdentity(): bool {
		return $this->bytesIdentity !== null;
	}

	/**
	 * @param string $fallbackName: pass nextcloud Display Name, it is used if user do not set its Olvid details
	 * @return JsonUserDetails
	 */
	public function computeJsonUserDetails(string $fallbackName): JsonUserDetails {
		// get user details from attributes
		$firstname = trim($this->getFirstname() ?? '');
		$lastname = trim($this->getLastname() ?? '');
		$position = trim($this->getPosition() ?? '');
		$company = trim($this->getCompany() ?? '');

		// fallback: if user does not set any of firstname and lastname we use display name as a first name
		if (!$firstname && !$lastname) {
			$firstname = $fallbackName;
		}

		$base64Identity = base64_encode($this->getBytesIdentity() ?? '');
		// set identity to null and not to an empty string, else android think we already have an identity on server ...
		if (!$base64Identity) {
			$base64Identity = null;
		}
		$details = new JsonUserDetails();
		$details->id = $this->getUserId();
		$details->firstname = $firstname;
		$details->lastname = $lastname;
		$details->position = $position;
		$details->company = $company;
		$details->identity = $base64Identity;
		$details->timestamp = TimeUtil::currentTimeMillis();
		return $details;
	}

	public function jsonSerialize(): array {
		return [
			'userId' => $this->getUserId(),
			'bytesIdentity' => base64_encode($this->getBytesIdentity() ?? ''),
			'apiKey' => $this->getApiKey(),
			'nonce' => $this->getNonce(),
			'magicToken' => $this->getMagicToken(),
			'magicTokenExpiration' => $this->getMagicTokenExpiration(),
			'sessionRevokedBefore' => $this->getSessionRevokedBefore(),
			'signedDetails' => $this->getSignedDetails(),
			'firstname' => $this->getFirstname(),
			'lastname' => $this->getLastname(),
			'position' => $this->getPosition(),
			'company' => $this->getCompany(),
			'fullSearchField' => $this->getFullSearchField()
		];
	}

	public function __toString(): string {
		return 'OlvidUser{'
			. 'id=' . $this->getId()
			. ', userId=' . $this->userId
			. ', bytesIdentity=' . $this->bytesIdentity
			. ', apiKey=' . $this->apiKey
			. ', nonce=' . $this->nonce
			. ', magicToken=' . $this->magicToken
			. ', magicTokenExpiration=' . $this->magicTokenExpiration
			. ', sessionRevokedBefore=' . $this->sessionRevokedBefore
			. ', signedDetails=' . $this->signedDetails
			. ', firstname=' . $this->firstname
			. ', lastname=' . $this->lastname
			. ', position=' . $this->position
			. ', company=' . $this->company
			. ', fullSearchField=' . $this->fullSearchField
			. '}';
	}
}
