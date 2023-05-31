<?php
defined('TYPO3') || die();

// Add new fields.
$tempColumns = [
    'tx_igldapssoauth_dn' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:ig_ldap_sso_auth/Resources/Private/Language/locallang_db.xlf:fe_groups.tx_igldapssoauth_dn',
        'config' => [
            'type' => 'input',
            'size' => 30,
        ]
    ],
];
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_groups', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('fe_groups', 'tx_igldapssoauth_dn');
$GLOBALS['TCA']['fe_groups']['columns']['title']['config']['size'] = 255;
$GLOBALS['TCA']['fe_groups']['columns']['title']['config']['max'] = 255;
