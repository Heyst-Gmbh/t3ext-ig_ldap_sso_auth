<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2007 Michael Gagnon <mgagnon@infoglobe.ca>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class tx_igldapssoauth_ldap for the 'ig_ldap_sso_auth' extension.
 *
 * @author	Michael Gagnon <mgagnon@infoglobe.ca>
 * @package	TYPO3
 * @subpackage	ig_ldap_sso_auth
 */
class tx_igldapssoauth_ldap {

	static public function connect(array $config = array()) {
		// Connect to ldap server.
		if (!tx_igldapssoauth_utility_Ldap::connect($config['host'], $config['port'], $config['protocol'], $config['charset'], $config['server'], $config['tls'])) {
			return FALSE;
		}

		// Bind to ldap server.
		if (!tx_igldapssoauth_utility_Ldap::bind($config['binddn'], $config['password'])) {
			tx_igldapssoauth_ldap::disconnect();
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Returns the corresponding DN if a given user is provided, otherwise FALSE.
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $basedn
	 * @param string $filter
	 * @return bool|string
	 */
	static public function valid_user($username = NULL, $password = NULL, $basedn = NULL, $filter = NULL) {

		// If user found on ldap server.
		if (tx_igldapssoauth_utility_Ldap::search($basedn, str_replace('{USERNAME}', $username, $filter), array('dn'))) {

			// Validate with password.
			if ($password) {

				// Bind DN of user with password.
				if (tx_igldapssoauth_utility_Ldap::bind(tx_igldapssoauth_utility_Ldap::get_dn(), $password)) {

					$dn = tx_igldapssoauth_utility_Ldap::get_dn();

					// Restore last LDAP binding
					$config = tx_igldapssoauth_config::getLdapConfiguration();
					tx_igldapssoauth_utility_Ldap::bind($config['binddn'], $config['password']);

					return $dn;
				}
				else {
					return FALSE;	// Password does not match
				}

				// If enable, SSO authentication without password.
			} elseif (!$password && tx_igldapssoauth_config::is_enable('CASAuthentication')) {

				return tx_igldapssoauth_utility_Ldap::get_dn();

			} else {

				// User invalid. Authentication failed.
				return FALSE;

			}

		}

		return FALSE;

	}

	static public function search($basedn = NULL, $filter = NULL, $attributes = array(), $first_entry = FALSE, $limit = 0) {
		$result = array();

		if (tx_igldapssoauth_utility_Ldap::search($basedn, $filter, $attributes, 0, $first_entry ? 1 : $limit)) {
			if ($first_entry) {
				$result = tx_igldapssoauth_utility_Ldap::get_first_entry();
				$result['dn'] = tx_igldapssoauth_utility_Ldap::get_dn();
				unset($result['count']);
			} else {
				$result = tx_igldapssoauth_utility_Ldap::get_entries();
			}
		}

		return $result;
	}

	static public function get_status() {
		return tx_igldapssoauth_utility_Ldap::get_status();
	}

	static public function disconnect() {
		tx_igldapssoauth_utility_Ldap::disconnect();
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
	static public function escapeDnForFilter($dn) {
		$escapeCharacters = array('(', ')', '\\');
		foreach ($escapeCharacters as $escapeCharacter) {
			$dn = str_replace($escapeCharacter, '\\' . $escapeCharacter, $dn);
		}
		return $dn;
	}
}

if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/ig_ldap_sso_auth/lib/class.tx_igldapssoauth_ldap.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/ig_ldap_sso_auth/lib/class.tx_igldapssoauth_ldap.php']);
}