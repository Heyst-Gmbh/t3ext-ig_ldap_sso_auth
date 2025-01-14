<?php
defined('TYPO3') || die();

// Add new fields.
$tempColumns = [
    'tx_igldapssoauth_dn' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:ig_ldap_sso_auth/Resources/Private/Language/locallang_db.xlf:fe_users.tx_igldapssoauth_dn',
        'config' => [
            'type' => 'input',
            'size' => 30,
        ]
    ],
];
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('fe_users', 'tx_igldapssoauth_dn');

// Remove password field for LDAP users.
$GLOBALS['TCA']['fe_users']['columns']['password']['displayCond'] = 'FIELD:tx_igldapssoauth_dn:REQ:false';

// Change size of title Field
$GLOBALS['TCA']['fe_users']['columns']['title']['config']['size'] = 255;
$GLOBALS['TCA']['fe_users']['columns']['title']['config']['max'] = 255;
$GLOBALS['TCA']['fe_users']['columns']['fax']['config']['size'] = 255;
$GLOBALS['TCA']['fe_users']['columns']['fax']['config']['max'] = 255;
