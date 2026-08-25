<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * A cached directory entry for a user enrolled on a trusted, Olvid-capable federated
 * Nextcloud instance. Populated by OlvidFederationSyncTask from that instance's
 * /federations/users export; never written to by anything else.
 *
 * @method int getServerId()
 * @method void setServerId(int $serverId)
 *
 * @method string getRemoteUserId()
 * @method void setRemoteUserId(string $remoteUserId)
 *
 * @method string getBytesIdentity()
 * @method void setBytesIdentity(string $bytesIdentity)
 *
 * @method string getSignedDetails()
 * @method void setSignedDetails(string $signedDetails)
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
 *
 * @method int getRegistrationTimestamp()
 * @method void setRegistrationTimestamp(int $registrationTimestamp)
 */
class OlvidFederatedUser extends Entity {
	protected int $serverId = 0;
	protected string $remoteUserId = '';
	protected string $bytesIdentity = '';
	protected string $signedDetails = '';
	protected ?string $firstname = null;
	protected ?string $lastname = null;
	protected ?string $position = null;
	protected ?string $company = null;
	protected ?string $fullSearchField = null;
	protected int $registrationTimestamp = 0;

	public function __construct() {
		$this->addType('serverId', Types::BIGINT);
		$this->addType('remoteUserId', Types::STRING);
		$this->addType('bytesIdentity', Types::BLOB);
		$this->addType('signedDetails', Types::TEXT);
		$this->addType('firstname', Types::STRING);
		$this->addType('lastname', Types::STRING);
		$this->addType('position', Types::STRING);
		$this->addType('company', Types::STRING);
		$this->addType('fullSearchField', Types::STRING);
		$this->addType('registrationTimestamp', Types::BIGINT);
	}

	/**
	 * The federated identity string as it appears (and was originally signed) in the
	 * home instance's JWT payload: `remoteUid@domain`.
	 */
	public function computeFederatedId(string $serverDomain): string {
		return $this->getRemoteUserId() . '@' . $serverDomain;
	}

	/**
	 * Unsigned representation for search/getKey responses — matches
	 * OlvidUser::computeJsonUserDetails()'s convention of stamping `timestamp` as "now"
	 * rather than any stored value (it's a response-freshness marker, not a cursor; the
	 * actual sync cursor is registrationTimestamp, kept separately).
	 */
	public function computeJsonUserDetails(string $serverDomain): JsonUserDetails {
		$details = new JsonUserDetails();
		$details->id = $this->computeFederatedId($serverDomain);
		$details->firstname = $this->getFirstname() ?? '';
		$details->lastname = $this->getLastname() ?? '';
		$details->position = $this->getPosition() ?? '';
		$details->company = $this->getCompany() ?? '';
		$details->base64Identity = base64_encode($this->getBytesIdentity());
		$details->timestamp = TimeUtil::currentTimeMillis();
		return $details;
	}
}
