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

namespace TYPO3\CMS\RteCKEditor\Controller;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\BackendTemplateView;
use TYPO3\CMS\Recordlist\Controller\AbstractLinkBrowserController;

/**
 * Extended controller for link browser
 * @internal This is a specific Backend Controller implementation and is not considered part of the Public TYPO3 API.
 */
class BrowseLinksController extends AbstractLinkBrowserController
{
    protected string $editorId;

    /**
     * TYPO3 language code of the content language
     */
    protected string $contentsLanguage;
    protected ?LanguageService $contentLanguageService;
    protected array $buttonConfig = [];
    protected array $thisConfig = [];
    protected array $classesAnchorDefault = [];
    protected array $classesAnchorDefaultTitle = [];
    protected array $classesAnchorClassTitle = [];
    protected array $classesAnchorDefaultTarget = [];
    protected array $classesAnchorJSOptions = [];
    protected string $defaultLinkTarget = '';
    protected array $additionalAttributes = [];
    protected string $siteUrl = '';

    public function __construct(
        protected readonly LinkService $linkService,
        protected readonly Richtext $richtext,
        protected readonly LanguageServiceFactory $languageServiceFactory,
    ) {
    }

    /**
     * This is only used by RTE currently.
     */
    public function getConfiguration(): array
    {
        return $this->buttonConfig;
    }

    /**
     * @return array{act: string, P: array, editorId: string, contentsLanguage: string} Array of parameters which have to be added to URLs
     */
    public function getUrlParameters(array $overrides = null): array
    {
        return [
            'act' => $overrides['act'] ?? $this->displayedLinkHandlerId,
            'P' => $overrides['P'] ?? $this->parameters,
            'editorId' => $this->editorId,
            'contentsLanguage' => $this->contentsLanguage,
        ];
    }

    protected function initDocumentTemplate(): void
    {
        $this->pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction(
            JavaScriptModuleInstruction::create('TYPO3/CMS/RteCkeditor/RteLinkBrowser.js')
                ->invoke('initialize', $this->editorId)
        );
    }

    protected function getCurrentPageId(): int
    {
        return (int)$this->parameters['pid'];
    }

    protected function initVariables(ServerRequestInterface $request): void
    {
        parent::initVariables($request);
        $queryParameters = $request->getQueryParams();
        $this->siteUrl = $request->getAttribute('normalizedParams')->getSiteUrl();
        $this->currentLinkParts = $queryParameters['P']['curUrl'] ?? [];
        $this->editorId = $queryParameters['editorId'];
        $this->contentsLanguage = $queryParameters['contentsLanguage'];
        $this->contentLanguageService = $this->languageServiceFactory->create($this->contentsLanguage);
        $tcaFieldConf = [
            'enableRichtext' => true,
            'richtextConfiguration' => $this->parameters['richtextConfigurationName'] ?: null,
        ];
        $this->thisConfig = $this->richtext->getConfiguration(
            $this->parameters['table'],
            $this->parameters['fieldName'],
            (int)$this->parameters['pid'],
            $this->parameters['recordType'],
            $tcaFieldConf
        );
        $this->buttonConfig = $this->thisConfig['buttons']['link'] ?? [];
    }

    protected function initCurrentUrl(): void
    {
        if (empty($this->currentLinkParts)) {
            return;
        }
        if (!empty($this->currentLinkParts['url'])) {
            $data = $this->linkService->resolve($this->currentLinkParts['url']);
            $this->currentLinkParts['type'] = $data['type'];
            unset($data['type']);
            $this->currentLinkParts['url'] = $data;
            if (!empty($this->currentLinkParts['url']['parameters'])) {
                $this->currentLinkParts['params'] = '&' . $this->currentLinkParts['url']['parameters'];
            }
        }
        parent::initCurrentUrl();
    }

    protected function renderLinkAttributeFields(BackendTemplateView $view): string
    {
        // Processing the classes configuration
        if (!empty($this->buttonConfig['properties']['class']['allowedClasses'])) {
            $classesAnchorArray = is_array($this->buttonConfig['properties']['class']['allowedClasses'])
                ? $this->buttonConfig['properties']['class']['allowedClasses']
                : GeneralUtility::trimExplode(',', $this->buttonConfig['properties']['class']['allowedClasses'], true);
            // Collecting allowed classes and configured default values
            $classesAnchor = [
                'all' => [],
            ];

            if (is_array($this->thisConfig['classesAnchor'])) {
                $readOnlyTitle = $this->isReadonlyTitle();
                foreach ($this->thisConfig['classesAnchor'] as $label => $conf) {
                    if (in_array($conf['class'] ?? null, $classesAnchorArray, true)) {
                        $classesAnchor['all'][] = $conf['class'];
                        if ($conf['type'] === $this->displayedLinkHandlerId) {
                            $classesAnchor[$conf['type']][] = $conf['class'];
                            if (($this->buttonConfig[$conf['type']]['properties']['class']['default'] ?? null) === $conf['class']) {
                                $this->classesAnchorDefault[$conf['type']] = $conf['class'];
                                if (isset($conf['titleText'])) {
                                    $this->classesAnchorDefaultTitle[$conf['type']] = $this->contentLanguageService->sL(trim((string)$conf['titleText']));
                                }
                                if (isset($conf['target'])) {
                                    $this->classesAnchorDefaultTarget[$conf['type']] = trim((string)$conf['target']);
                                }
                            }
                        }
                        if ($readOnlyTitle && $conf['titleText']) {
                            $this->classesAnchorClassTitle[$conf['class']] = ($this->classesAnchorDefaultTitle[$conf['type']] = $this->contentLanguageService->sL(trim($conf['titleText'])));
                        }
                    }
                }
            }
            if (isset($this->linkAttributeValues['class'])
                && isset($classesAnchor[$this->displayedLinkHandlerId])
                && !in_array($this->linkAttributeValues['class'], $classesAnchor[$this->displayedLinkHandlerId], true)
            ) {
                unset($this->linkAttributeValues['class']);
            }
            // Constructing the class selector options
            foreach ($classesAnchorArray as $class) {
                if (
                    !in_array($class, $classesAnchor['all'])
                    || (
                        in_array($class, $classesAnchor['all'])
                        && isset($classesAnchor[$this->displayedLinkHandlerId])
                        && is_array($classesAnchor[$this->displayedLinkHandlerId])
                        && in_array($class, $classesAnchor[$this->displayedLinkHandlerId])
                    )
                ) {
                    $selected = '';
                    if (
                        (($this->linkAttributeValues['class'] ?? false) === $class)
                        || ($this->classesAnchorDefault[$this->displayedLinkHandlerId] ?? false) === $class
                    ) {
                        $selected = 'selected="selected"';
                    }
                    $classLabel = !empty($this->thisConfig['classes'][$class]['name'])
                        ? $this->getPageConfigLabel($this->thisConfig['classes'][$class]['name'], false)
                        : $class;
                    $classStyle = !empty($this->thisConfig['classes'][$class]['value'])
                        ? $this->thisConfig['classes'][$class]['value']
                        : '';
                    $title = $this->classesAnchorClassTitle[$class] ?? $this->classesAnchorDefaultTitle[$class] ?? '';

                    $this->classesAnchorJSOptions[$this->displayedLinkHandlerId] ??= '';
                    $this->classesAnchorJSOptions[$this->displayedLinkHandlerId] .= '<option ' . $selected . ' value="' . htmlspecialchars($class) . '"'
                        . ($classStyle ? ' style="' . htmlspecialchars($classStyle) . '"' : '')
                        . 'data-link-title="' . htmlspecialchars($title) . '"'
                        . '>' . htmlspecialchars($classLabel)
                        . '</option>';
                }
            }
            if (
                ($this->classesAnchorJSOptions[$this->displayedLinkHandlerId] ?? false)
                && !(
                    ($this->buttonConfig['properties']['class']['required'] ?? false)
                    || ($this->buttonConfig[$this->displayedLinkHandlerId]['properties']['class']['required'] ?? false)
                )
            ) {
                $selected = '';
                if (!($this->linkAttributeValues['class'] ?? false) && !($this->classesAnchorDefault[$this->displayedLinkHandlerId] ?? false)) {
                    $selected = 'selected="selected"';
                }
                $this->classesAnchorJSOptions[$this->displayedLinkHandlerId] = '<option ' . $selected . ' value=""></option>' . $this->classesAnchorJSOptions[$this->displayedLinkHandlerId];
            }
        }
        // Default target
        $this->defaultLinkTarget = ($this->classesAnchorDefault[$this->displayedLinkHandlerId] ?? false) && ($this->classesAnchorDefaultTarget[$this->displayedLinkHandlerId] ?? false)
            ? $this->classesAnchorDefaultTarget[$this->displayedLinkHandlerId]
            : ($this->buttonConfig[$this->displayedLinkHandlerId]['properties']['target']['default'] ?? $this->buttonConfig['properties']['target']['default'] ?? '');

        // todo: find new name for this option
        // Initializing additional attributes
        if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rte_ckeditor']['plugins']['TYPO3Link']['additionalAttributes'] ?? false) {
            $addAttributes = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rte_ckeditor']['plugins']['TYPO3Link']['additionalAttributes'], true);
            foreach ($addAttributes as $attribute) {
                $this->additionalAttributes[$attribute] = $this->linkAttributeValues[$attribute] ?? '';
            }
        }
        return parent::renderLinkAttributeFields($view);
    }

    /**
     * Localize a label obtained from Page TSConfig
     *
     * @param string $string The label to be localized
     * @param bool $JScharCode If it needs to be converted to an array of char numbers
     * @return string Localized string
     */
    protected function getPageConfigLabel(string $string, bool $JScharCode = true): string
    {
        if (!str_starts_with($string, 'LLL:')) {
            $label = $string;
        } else {
            $label = $this->getLanguageService()->sL(trim($string));
        }
        $label = str_replace(['\\\'', '"'], ['\'', '\\"'], $label);
        return $JScharCode ? GeneralUtility::quoteJSvalue($label) : $label;
    }

    protected function renderCurrentUrl(BackendTemplateView $view): void
    {
        $view->assign('removeCurrentLink', true);
        parent::renderCurrentUrl($view);
    }

    /**
     * @return string[]
     */
    protected function getAllowedItems(): array
    {
        $allowedItems = parent::getAllowedItems();

        $blindLinkOptions = isset($this->thisConfig['blindLinkOptions'])
            ? GeneralUtility::trimExplode(',', $this->thisConfig['blindLinkOptions'], true)
            : [];
        $allowedItems = array_diff($allowedItems, $blindLinkOptions);

        if (is_array($this->buttonConfig['options'] ?? null) && !empty($this->buttonConfig['options']['removeItems'])) {
            $allowedItems = array_diff($allowedItems, GeneralUtility::trimExplode(',', $this->buttonConfig['options']['removeItems'], true));
        }

        return $allowedItems;
    }

    /**
     * @return string[]
     */
    protected function getAllowedLinkAttributes(): array
    {
        $allowedLinkAttributes = parent::getAllowedLinkAttributes();

        $blindLinkFields = isset($this->thisConfig['blindLinkFields'])
            ? GeneralUtility::trimExplode(',', $this->thisConfig['blindLinkFields'], true)
            : [];
        $allowedLinkAttributes = array_diff($allowedLinkAttributes, $blindLinkFields);

        return $allowedLinkAttributes;
    }

    /**
     * Create an array of link attribute field rendering definitions
     *
     * @return string[]
     */
    protected function getLinkAttributeFieldDefinitions(): array
    {
        $fieldRenderingDefinitions = parent::getLinkAttributeFieldDefinitions();
        $fieldRenderingDefinitions['title'] = $this->getTitleField();
        $fieldRenderingDefinitions['class'] = $this->getClassField();
        $fieldRenderingDefinitions['target'] = $this->getTargetField();
        $fieldRenderingDefinitions['rel'] = $this->getRelField();
        if (empty($this->buttonConfig['queryParametersSelector']['enabled'])) {
            unset($fieldRenderingDefinitions['params']);
        }
        return $fieldRenderingDefinitions;
    }

    protected function getRelField(): string
    {
        if (empty($this->buttonConfig['relAttribute']['enabled'])) {
            return '';
        }

        $currentRel = '';
        if ($this->displayedLinkHandler === $this->currentLinkHandler
            && !empty($this->currentLinkParts)
            && isset($this->linkAttributeValues['rel'])
            && is_string($this->linkAttributeValues['rel'])
        ) {
            $currentRel = $this->linkAttributeValues['rel'];
        }

        return '
            <form action="" name="lrelform" id="lrelform" class="t3js-dummyform form-horizontal">
                 <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">' .
                        htmlspecialchars($this->getLanguageService()->getLL('linkRelationship')) .
                    '</label>
                    <div class="col-sm-9">
                        <input type="text" name="lrel" class="form-control" value="' . htmlspecialchars($currentRel) . '" />
                    </div>
                </div>
            </form>
            ';
    }

    protected function getTargetField(): string
    {
        $targetSelectorConfig = [];
        if (is_array($this->buttonConfig['targetSelector'] ?? null)) {
            $targetSelectorConfig = $this->buttonConfig['targetSelector'];
        }
        $target = !empty($this->linkAttributeValues['target']) ? $this->linkAttributeValues['target'] : $this->defaultLinkTarget;
        $lang = $this->getLanguageService();
        $targetSelector = '';

        $disabled = $targetSelectorConfig['disabled'] ?? false;
        if (!$disabled) {
            $targetSelector = '
						<select name="ltarget_type" class="t3js-targetPreselect form-select">
							<option value=""></option>
							<option value="_top">' . htmlspecialchars($lang->getLL('top')) . '</option>
							<option value="_blank">' . htmlspecialchars($lang->getLL('newWindow')) . '</option>
						</select>
			';
        }

        return '
				<form action="" name="ltargetform" id="ltargetform" class="t3js-dummyform form-horizontal">
                    <div class="row mb-3" ' . ($disabled ? ' style="display: none;"' : '') . '>
                        <label class="col-sm-3 col-form-label">' . htmlspecialchars($lang->getLL('target')) . '</label>
						<div class="col-sm-4">
							<input type="text" name="ltarget" class="t3js-linkTarget form-control"
							    value="' . htmlspecialchars($target) . '" />
						</div>
						<div class="col-sm-5">
							' . $targetSelector . '
						</div>
					</div>
				</form>
				';
    }

    protected function getTitleField(): string
    {
        if ($this->linkAttributeValues['title'] ?? null) {
            $title = $this->linkAttributeValues['title'];
        } else {
            $title = ($this->classesAnchorDefaultTitle[$this->displayedLinkHandlerId] ?? false) ?: '';
        }
        $readOnlyTitle = $this->isReadonlyTitle();

        if ($readOnlyTitle) {
            $currentClass = $this->linkAttributeFields['class'] ?? '';
            if (!$currentClass) {
                $currentClass = empty($this->classesAnchorDefault[$this->displayedLinkHandlerId]) ? '' : $this->classesAnchorDefault[$this->displayedLinkHandlerId];
            }
            $title = $this->classesAnchorClassTitle[$currentClass] ?? $this->classesAnchorDefaultTitle[$this->displayedLinkHandlerId] ?? '';
        }
        return '
                <form action="" name="ltitleform" id="ltitleform" class="t3js-dummyform form-horizontal">
                    <div class="row mb-3">
                        <label class="col-sm-3 col-form-label">
                            ' . htmlspecialchars($this->getLanguageService()->getLL('title')) . '
                         </label>
                         <div class="col-sm-9">
                                <input ' . ($readOnlyTitle ? 'disabled' : '') . ' type="text" name="ltitle" class="form-control t3js-linkTitle"
                                        value="' . htmlspecialchars($title) . '" />
                        </div>
                    </div>
                </form>
                ';
    }

    /**
     * Return html code for the class selector
     *
     * @return string the html code to be added to the form
     */
    protected function getClassField(): string
    {
        $selectClass = '';
        if (isset($this->classesAnchorJSOptions[$this->displayedLinkHandlerId])) {
            $selectClass = '
                <form action="" name="lclassform" id="lclassform" class="t3js-dummyform form-horizontal">
                    <div class="row mb-3">
                        <label class="col-sm-3 col-form-label">
                            ' . htmlspecialchars($this->getLanguageService()->getLL('class')) . '
                        </label>
                        <div class="col-sm-9">
                            <select name="lclass" class="t3js-class-selector form-select">
                                ' . $this->classesAnchorJSOptions[$this->displayedLinkHandlerId] . '
                            </select>
                        </div>
                    </div>
                </form>
            ';
        }
        return $selectClass;
    }

    /**
     * @return string[] Array of body-tag attributes
     */
    protected function getBodyTagAttributes(): array
    {
        $parameters = parent::getBodyTagAttributes();
        $parameters['data-site-url'] = $this->siteUrl;
        $parameters['data-default-link-target'] = $this->defaultLinkTarget;
        return $parameters;
    }

    protected function isReadonlyTitle(): bool
    {
        if (isset($this->buttonConfig[$this->displayedLinkHandlerId]['properties']['title']['readOnly'])) {
            return (bool)$this->buttonConfig[$this->displayedLinkHandlerId]['properties']['title']['readOnly'];
        }

        return (bool)($this->buttonConfig['properties']['title']['readOnly'] ?? false);
    }
}
