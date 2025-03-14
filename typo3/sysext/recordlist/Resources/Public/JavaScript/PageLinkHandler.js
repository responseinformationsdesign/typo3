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
import LinkBrowser from"TYPO3/CMS/Recordlist/LinkBrowser.js";import RegularEvent from"TYPO3/CMS/Core/Event/RegularEvent.js";class PageLinkHandler{constructor(){this.linkPageByTextfield=()=>{let e=document.getElementById("luid").value;if(!e)return;const t=parseInt(e,10);isNaN(t)||(e="t3://page?uid="+t),LinkBrowser.finalizeFunction(e)},new RegularEvent("click",(e,t)=>{e.preventDefault(),LinkBrowser.finalizeFunction(t.getAttribute("href"))}).delegateTo(document,"a.t3js-pageLink"),new RegularEvent("click",(e,t)=>{e.preventDefault(),LinkBrowser.finalizeFunction(document.body.dataset.currentLink)}).delegateTo(document,"input.t3js-linkCurrent"),new RegularEvent("click",(e,t)=>{e.preventDefault(),this.linkPageByTextfield()}).delegateTo(document,"input.t3js-pageLink")}}export default new PageLinkHandler;