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
import Notification from"TYPO3/CMS/Backend/Notification.js";import AjaxRequest from"TYPO3/CMS/Core/Ajax/AjaxRequest.js";import Router from"TYPO3/CMS/Install/Router.js";import{AbstractInteractableModule}from"TYPO3/CMS/Install/Module/AbstractInteractableModule.js";class SystemInformation extends AbstractInteractableModule{initialize(t){this.currentModal=t,this.getData()}getData(){const t=this.getModalBody();new AjaxRequest(Router.getUrl("systemInformationGetData")).get({cache:"no-cache"}).then(async e=>{const o=await e.resolve();!0===o.success?t.empty().append(o.html):Notification.error("Something went wrong","The request was not processed successfully. Please check the browser's console and TYPO3's log.")},e=>{Router.handleAjaxError(e,t)})}}export default new SystemInformation;