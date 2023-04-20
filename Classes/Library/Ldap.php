<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Causal\IgLdapSsoAuth\Library;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Causal\IgLdapSsoAuth\Utility\LdapUtility;

/**
 * Class Ldap for the 'ig_ldap_sso_auth' extension.
 *
 * @author        Xavier Perseguers <xavier@causal.ch>
 * @author        Michael Gagnon <mgagnon@infoglobe.ca>
 * @package       TYPO3
 * @subpackage    ig_ldap_sso_auth
 */
class Ldap
{
	/**
	 * Last bind diagnostic message
	 *
	 * @var string
	 */
	protected string $lastBindDiagnostic = '';

	/**
	 * LDAP utility
	 *
	 * @var \Causal\IgLdapSsoAuth\Utility\LdapUtility
	 */
	protected LdapUtility $ldapUtility;

	/**
	 * Ldap constructor.
	 */
	public function __construct()
	{
		// @todo Use DI here, but to do it right, we have to get rid of all the static methods, so...
		$this->ldapUtility = GeneralUtility::makeInstance(LdapUtility::class, GeneralUtility::makeInstance(\TYPO3\CMS\Core\Charset\CharsetConverter::class));
	}

	/**
	 * Destructor.
	 */
	public function __destruct()
	{
		$this->ldapUtility->disconnect();
	}

	/**
	 * Initializes a connection to the LDAP server.
	 *
	 * @param array $config
	 * @return bool
	 * @throws \Causal\IgLdapSsoAuth\Exception\UnresolvedPhpDependencyException when LDAP extension for PHP is not available
	 */
	public function connect(array $config = []): bool
	{
		$debugConfiguration = [
			'host' => $config['host'],
			'port' => $config['port'],
			'charset' => $config['charset'],
			'server' => $config['server'],
			'tls' => $config['tls'],
			'tlsReqcert' => $config['tlsReqcert'],
			'ssl' => $config['ssl'],
		];
		// Connect to ldap server.
		if (!$this->ldapUtility->connect(
			$config['host'],
			$config['port'],
			3,
			$config['charset'],
			Configuration::getServerType($config['server']),
			$config['tls'],
			$config['ssl'],
			$config['tlsReqcert']
		)) {
			// @extensionScannerIgnoreLine
			static::getLogger()->error('Cannot connect', $debugConfiguration);
			return false;
		}

		$debugConfiguration['binddn'] = $config['binddn'];
		$debugConfiguration['password'] = $config['password'] !== '' ? '••••••••••••' : '';

		// Bind to ldap server.
		if (!$this->ldapUtility->bind($config['binddn'], $config['password'])) {
			$status = $this->ldapUtility->getStatus();
			$this->lastBindDiagnostic = $status['bind']['diagnostic'];

			$message = 'Cannot bind to LDAP';
			if (!empty($this->lastBindDiagnostic)) {
				$message .= ': ' . $this->lastBindDiagnostic;
			}
			// @extensionScannerIgnoreLine
			static::getLogger()->error($message, $debugConfiguration);

			$this->disconnect();

			return false;
		}

		static::getLogger()->debug('Successfully connected', $debugConfiguration);

		return true;
	}

	/**
	 * Disconnects the LDAP server.
	 */
	public function disconnect(): void
	{
		$this->ldapUtility->disconnect();
	}

	/**
	 * Returns the corresponding DN if a given user is provided, otherwise false.
	 *
	 * @param string $username
	 * @param string $password User's password. If null password will not be checked
	 * @param string $baseDn
	 * @param string $filter
	 * @return bool|string
	 */
	public function validateUser($username = null, $password = null, $baseDn = null, $filter = null)
	{
		// If user found on ldap server.
		if ($this->ldapUtility->search(
			$baseDn,
			str_replace(
				'{USERNAME}',
				ldap_escape($username, '', LDAP_ESCAPE_FILTER),
				$filter
			),
			['dn']
		)) {
			// Validate with password.
			if ($password !== null) {
				// Bind DN of user with password.
				if (empty($password)) {
					$this->lastBindDiagnostic = 'Empty password provided!';

					return false;
				} elseif ($this->ldapUtility->bind($this->ldapUtility->getDn(), $password)) {
					$dn = $this->ldapUtility->getDn();

					// Restore last LDAP binding
					$config = Configuration::getLdapConfiguration();
					$this->ldapUtility->bind($config['binddn'], $config['password']);
					$this->lastBindDiagnostic = '';

					return $dn;
				} else {
					$status = $this->ldapUtility->getStatus();
					$this->lastBindDiagnostic = $status['bind']['diagnostic'];

					return false;    // Password does not match
				}
				// If enable, SSO authentication without password
			} elseif ($password === null && Configuration::getValue('SSOAuthentication')) {
				return $this->ldapUtility->getDn();
			} else {
				// User invalid. Authentication failed.
				return false;
			}
		}

		return false;
	}

	/**
	 * Searches LDAP entries satisfying some filter.
	 *
	 * @param string $baseDn
	 * @param string $filter
	 * @param array $attributes
	 * @param bool $firstEntry
	 * @param int $limit
	 * @param bool $continueLastSearch
	 * @return array
	 */
	public function search(
		$baseDn = null,
		$filter = null,
		$attributes = [],
		$firstEntry = false,
		$limit = 0,
		$continueLastSearch = false
	): array {
		$result = [];
		$timeLimit = 0;
		$dereferenceAliases = LDAP_DEREF_NEVER;

		if ($this->ldapUtility->search(
			$baseDn,
			$filter,
			$attributes,
			false,
			$firstEntry ? 1 : $limit,
			$timeLimit,
			$dereferenceAliases,
			$continueLastSearch
		)) {
			if ($firstEntry) {
				$result = $this->ldapUtility->getFirstEntry();
				$result['dn'] = $this->ldapUtility->getDn();
				unset($result['count']);
			} else {
				$result = $this->ldapUtility->getEntries();
			}
		}

		return $result;
	}

	/**
	 * Returns whether the last search result was partial.
	 *
	 * @return bool
	 */
	public function isPartialSearchResult(): bool
	{
		return $this->ldapUtility->hasMoreEntries();
	}

	/**
	 * Returns the LDAP status.
	 *
	 * @return array
	 */
	public function getStatus(): array
	{
		return $this->ldapUtility->getStatus();
	}

	/**
	 * Returns the last ldap_bind() diagnostic (may be empty).
	 *
	 * @return string
	 */
	public function getLastBindDiagnostic(): string
	{
		return $this->lastBindDiagnostic;
	}

	/**
	 * Escapes a string for use in a LDAP filter statement.
	 *
	 * To find the groups of a user by filtering the groups where the
	 * authenticated user is in the members list some characters
	 * in the users distinguished name can make the filter expression
	 * invalid.
	 *
	 * At the moment this problem was experienced with brackets which
	 * are also used in the filter, e.g.:
	 * (&(objectClass=group)(member={USERDN}))
	 *
	 * Additionally a single backslash (that is used for escaping special
	 * characters like commas) needs to be escaped. E.g.:
	 * CN=Lastname\, Firstname,DC=company,DC=tld needs to be escaped like:
	 * CN=Lastname\\, Firstname,DC=company,DC=tld
	 *
	 * @param string $dn
	 * @return string Escaped $dn
	 */
	public function escapeDnForFilter(string $dn): string
	{
		$escapeCharacters = ['\\', '(', ')'];
		foreach ($escapeCharacters as $escapeCharacter) {
			$dn = str_replace($escapeCharacter, '\\' . $escapeCharacter, $dn);
		}
		return $dn;
	}

	/**
	 * Returns a logger.
	 *
	 * @return \TYPO3\CMS\Core\Log\Logger
	 */
	protected static function getLogger(): \TYPO3\CMS\Core\Log\Logger
	{
		/** @var \TYPO3\CMS\Core\Log\Logger $logger */
		static $logger = null;
		if ($logger === null) {
			$logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
		}

		return $logger;
	}
}
