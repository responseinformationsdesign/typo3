<?php

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

namespace TYPO3\CMS\Install\Report;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Service\EnableFileService;
use TYPO3\CMS\Install\SystemEnvironment\ServerResponse\ServerResponseCheck;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;

/**
 * Provides a status report of the security of the install tool
 * @internal This class is only meant to be used within EXT:install and is not part of the TYPO3 Core API.
 */
class SecurityStatusReport implements StatusProviderInterface
{
    /**
     * Compiles a collection of system status checks as a status report.
     *
     * @return Status[]
     */
    public function getStatus()
    {
        $this->executeAdminCommand();
        return [
            'installToolProtection' => $this->getInstallToolProtectionStatus(),
            'serverResponseStatus' => GeneralUtility::makeInstance(ServerResponseCheck::class)->asStatus(),
        ];
    }

    /**
     * Checks for the existence of the ENABLE_INSTALL_TOOL file.
     *
     * @return Status An object representing whether ENABLE_INSTALL_TOOL exists
     */
    protected function getInstallToolProtectionStatus()
    {
        $enableInstallToolFile = Environment::getPublicPath() . '/' . EnableFileService::INSTALL_TOOL_ENABLE_FILE_PATH;
        $value = $this->getLanguageService()->getLL('status_disabled');
        $message = '';
        $severity = Status::OK;
        if (EnableFileService::installToolEnableFileExists()) {
            if (EnableFileService::isInstallToolEnableFilePermanent()) {
                $severity = Status::WARNING;
                $disableInstallToolUrl = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL') . '&adminCmd=remove_ENABLE_INSTALL_TOOL';
                $value = $this->getLanguageService()->sL('LLL:EXT:install/Resources/Private/Language/Report/locallang.xlf:status_enabledPermanently');
                $message = sprintf(
                    $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:warning.install_enabled'),
                    '<code style="white-space: nowrap;">' . $enableInstallToolFile . '</code>'
                );
                $message .= ' <a href="' . htmlspecialchars($disableInstallToolUrl) . '">' .
                    $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:warning.install_enabled_cmd') . '</a>';
            } else {
                if (EnableFileService::installToolEnableFileLifetimeExpired()) {
                    EnableFileService::removeInstallToolEnableFile();
                } else {
                    $severity = Status::NOTICE;
                    $disableInstallToolUrl = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL') . '&adminCmd=remove_ENABLE_INSTALL_TOOL';
                    $value = $this->getLanguageService()->sL('LLL:EXT:install/Resources/Private/Language/Report/locallang.xlf:status_enabledTemporarily');
                    $message = sprintf(
                        $this->getLanguageService()->sL('LLL:EXT:install/Resources/Private/Language/Report/locallang.xlf:status_installEnabledTemporarily'),
                        '<code style="white-space: nowrap;">' . $enableInstallToolFile . '</code>',
                        floor((@filemtime($enableInstallToolFile) + EnableFileService::INSTALL_TOOL_ENABLE_FILE_LIFETIME - time()) / 60)
                    );
                    $message .= ' <a href="' . htmlspecialchars($disableInstallToolUrl) . '">' .
                        $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:warning.install_enabled_cmd') . '</a>';
                }
            }
        }
        return GeneralUtility::makeInstance(
            Status::class,
            $this->getLanguageService()->sL('LLL:EXT:install/Resources/Private/Language/Report/locallang.xlf:status_installTool'),
            $value,
            $message,
            $severity
        );
    }

    /**
     * Executes commands like removing the Install Tool enable file.
     */
    protected function executeAdminCommand()
    {
        $command = GeneralUtility::_GET('adminCmd');
        switch ($command) {
            case 'remove_ENABLE_INSTALL_TOOL':
                EnableFileService::removeInstallToolEnableFile();
                break;
            default:
                // Do nothing
        }
    }

    protected function getLanguageService(): ?LanguageService
    {
        return $GLOBALS['LANG'] ?? null;
    }
}
