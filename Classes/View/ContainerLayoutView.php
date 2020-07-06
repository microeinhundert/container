<?php
declare(strict_types = 1);

namespace B13\Container\View;

/*
 * This file is part of TYPO3 CMS-based extension "container" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\Container\Domain\Factory\ContainerFactory;
use B13\Container\Domain\Model\Container;
use TYPO3\CMS\Backend\View\PageLayoutView;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Versioning\VersionState;
use Psr\EventDispatcher\EventDispatcherInterface;

class ContainerLayoutView extends PageLayoutView
{
    /**
     * @var ContainerFactory
     */
    protected $containerFactory = null;

    /**
     * @var Container
     */
    protected $container = null;

    /**
     * ContainerLayoutView constructor.
     * @param EventDispatcherInterface|null $eventDispatcher
     * @param ContainerFactory|null $containerFactory
     */
    public function __construct(EventDispatcherInterface $eventDispatcher = null, ContainerFactory $containerFactory = null)
    {
        $this->containerFactory = $containerFactory ?? GeneralUtility::makeInstance(ContainerFactory::class);

        if (version_compare(TYPO3_branch, '10.3', '<')) {
            parent::__construct();
        } else {
            parent::__construct($eventDispatcher);
        }
    }

    /**
     * @param int $uid
     * @param int $colPos
     * @return string
     */
    public function renderContainerChildren(int $uid, int $colPos): string
    {

        $this->initWebLayoutModuleData();
        $this->initLabels();

        try {
            $container = $this->containerFactory->buildContainer($uid);
        } catch (\B13\Container\Domain\Factory\Exception $e) {
            return '';
        }
        $this->id = $container->getPid();
        $this->pageinfo = BackendUtility::readPageAccess($this->id, '');
        $this->container = $container;
        $content = $this->renderRecords($colPos);
        return $content;
    }

    /**
     * @return void
     */
    protected function initLabels(): void
    {
        $this->CType_labels = [];
        foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] as $val) {
            $this->CType_labels[$val[1]] = $this->getLanguageService()->sL($val[0]);
        }

        $this->itemLabels = [];
        foreach ($GLOBALS['TCA']['tt_content']['columns'] as $name => $val) {
            $this->itemLabels[$name] = $this->getLanguageService()->sL($val['label']);
        }
    }

    /**
     * @param int $colPos
     * @return string
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function buildNewContentElementWizardLinkTop(int $colPos): string
    {
        $containerRecord = $this->container->getContainerRecord();
        $urlParameters = [
            'id' => $containerRecord['pid'],
            'sys_language_uid' => $this->container->getLanguage(),
            'tx_container_parent' => $containerRecord['uid'],
            'colPos' => $colPos,
            'uid_pid' => $containerRecord['pid'],
            'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')
        ];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $url = (string)$uriBuilder->buildUriFromRoute('new_content_element_wizard', $urlParameters);
        return $url;
    }

    /**
     * @param array $currentRecord
     * @return string
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function buildNewContentElementWizardLinkAfterCurrent(array $currentRecord): string
    {
        $containerRecord = $this->container->getContainerRecord();
        $colPos = $currentRecord['colPos'];
        $target = -$currentRecord['uid'];
        $lang = $currentRecord['sys_language_uid'];
        $urlParameters = [
            'id' => $containerRecord['pid'],
            'sys_language_uid' => $lang,
            'colPos' => $colPos,
            'tx_container_parent' => $containerRecord['uid'],
            'uid_pid' => $target,
            'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')
        ];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $url = (string)$uriBuilder->buildUriFromRoute('new_content_element_wizard', $urlParameters);
        return $url;
    }

    /**
     * @return void
     */
    protected function initWebLayoutModuleData(): void
    {
        $webLayoutModuleData = BackendUtility::getModuleData([], [], 'web_layout');
        if (isset($webLayoutModuleData['tt_content_showHidden'])) {
            $this->tt_contentConfig['showHidden'] = $webLayoutModuleData['tt_content_showHidden'];
        }
    }

    /**
     * Creates the icon image tag for record from table and wraps it in a link which will trigger the click menu.
     *
     * @param string $table Table name
     * @param array $row Record array
     * @param string $enabledClickMenuItems Passthrough to wrapClickMenuOnIcon
     * @return string HTML for the icon
     */
    public function getIcon($table, $row, $enabledClickMenuItems = '')
    {
        if ($this->isLanguageEditable()) {
            return parent::getIcon($table, $row, $enabledClickMenuItems);
        } else {
            $toolTip = BackendUtility::getRecordToolTip($row, 'tt_content');
            $icon = '<span ' . $toolTip . '>' . $this->iconFactory->getIconForRecord($table, $row, Icon::SIZE_SMALL)->render() . '</span>';
            $this->counter++;
            // do not render click-menu
            return $icon;
        }
    }

    /**
     * @return bool
     */
    protected function isLanguageEditable(): bool
    {
        return $this->container->getLanguage() === 0 || !$this->container->isConnectedMode();
    }

    /**
     * @param int $colPos
     * @return string
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function renderNewContentButtonAtTop(int $colPos): string
    {
        // Add new content at the top most position
        $link = '';
        $content = '';
        if ($this->isContentEditable() && $this->isLanguageEditable()) {
            $url = $this->buildNewContentElementWizardLinkTop($colPos);
            $title = htmlspecialchars($this->getLanguageService()->getLL('newContentElement'));
            $link = '<a href="' . htmlspecialchars($url) . '" '
                . 'title="' . $title . '"'
                . 'data-title="' . $title . '"'
                . 'class="btn btn-default btn-sm t3js-toggle-new-content-element-wizard">'
                . $this->iconFactory->getIcon('actions-add', Icon::SIZE_SMALL)->render()
                . ' '
                . htmlspecialchars($this->getLanguageService()->getLL('content')) . '</a>';
        }

        if ($this->getBackendUser()->checkLanguageAccess($this->container->getLanguage())) {
            $content = '
                <div class="t3-page-ce t3js-page-ce" data-page="' . $this->container->getPid() . '" id="' . StringUtility::getUniqueId() . '">
                    <div class="t3js-page-new-ce t3-page-ce-wrapper-new-ce" id="colpos-' . $colPos . '-page-' . $this->container->getUid() . '-' . StringUtility::getUniqueId() . '">'
                . $link
                . '</div>
                    <div class="t3-page-ce-dropzone-available t3js-page-ce-dropzone-available"></div>
                </div>
                ';
        }
        return $content;
    }

    /**
     * @param array $row
     * @return string
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function renderNewContentButtonAfterContentElement(array $row): string
    {
        $url = $this->buildNewContentElementWizardLinkAfterCurrent($row);
        $title = htmlspecialchars($this->getLanguageService()->getLL('newContentElement'));
        return  '<a href="' . htmlspecialchars($url) . '" '
            . 'title="' . $title . '"'
            . 'data-title="' . $title . '"'
            . 'class="btn btn-default btn-sm t3js-toggle-new-content-element-wizard">'
            . $this->iconFactory->getIcon('actions-add', Icon::SIZE_SMALL)->render()
            . ' '
            . htmlspecialchars($this->getLanguageService()->getLL('content')) . '</a>';
    }

    /**
     * @param int $colPos
     * @return string
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     * @throws \TYPO3\CMS\Core\Exception\SiteNotFoundException
     */
    protected function renderRecords(int $colPos): string
    {
        $containerRecord = $this->container->getContainerRecord();
        $this->resolveSiteLanguages($containerRecord['pid']);
        $records = $this->container->getChildrenByColPos($colPos);
        $this->nextThree = 1;
        $this->generateTtContentDataArray($records);

        $content = '';
        $head = '';
        $currentLanguage = $containerRecord['sys_language_uid'];
        $id = $containerRecord['pid'];

        // Start wrapping div
        $content .= '<div data-colpos="' . $containerRecord['uid'] . '-' . $colPos . '" data-language-uid="' . $currentLanguage . '" class="t3js-sortable t3js-sortable-lang t3js-sortable-lang-' . $currentLanguage . ' t3-page-ce-wrapper">';
        $content .= $this->renderNewContentButtonAtTop($colPos);

        foreach ($records as $row) {
            if (is_array($row) && !VersionState::cast($row['t3ver_state'])->equals(VersionState::DELETE_PLACEHOLDER)) {
                $singleElementHTML = '<div class="t3-page-ce-dragitem" id="' . StringUtility::getUniqueId() . '">';
                // new is visible ... s. ContextMenuController
                $disableMoveAndNewButtons = !$this->isLanguageEditable();
                $singleElementHTML .= $this->tt_content_drawHeader(
                    $row,
                    $this->tt_contentConfig['showInfo'] ? 15 : 5,
                    $disableMoveAndNewButtons,
                    true,
                    $this->getBackendUser()->doesUserHaveAccess($this->pageinfo, Permission::CONTENT_EDIT)
                );

                $innerContent = '<div ' . ($row['_ORIG_uid'] ? ' class="ver-element"' : '') . '>'
                    . $this->tt_content_drawItem($row) . '</div>';
                $singleElementHTML .= '<div class="t3-page-ce-body-inner">' . $innerContent . '</div></div>'
                    . $this->tt_content_drawFooter($row);
                $isDisabled = $this->isDisabled('tt_content', $row);
                $statusHidden = $isDisabled ? ' t3-page-ce-hidden t3js-hidden-record' : '';
                $displayNone = !$this->tt_contentConfig['showHidden'] && $isDisabled ? ' style="display: none;"' : '';

                $singleElementHTML = '<div class="t3-page-ce t3js-page-ce t3js-page-ce-sortable ' . $statusHidden . '" id="element-tt_content-'
                    . $row['uid'] . '" data-table="tt_content" data-uid="' . $row['uid'] . '"' . $displayNone . '>' . $singleElementHTML . '</div>';

                $singleElementHTML .= '<div class="t3-page-ce" data-colpos="' . $containerRecord['uid'] . '-' . $colPos . '">';
                $singleElementHTML .= '<div class="t3js-page-new-ce t3-page-ce-wrapper-new-ce" id="colpos-' . $colPos . '-page-' . $id .
                    '-' . StringUtility::getUniqueId() . '">';
                // Add icon "new content element below"
                if (!$disableMoveAndNewButtons
                    && $this->isContentEditable()
                    && $this->getBackendUser()->checkLanguageAccess($currentLanguage)
                ) {
                    $singleElementHTML .= $this->renderNewContentButtonAfterContentElement($row);
                }
                $singleElementHTML .= '</div></div><div class="t3-page-ce-dropzone-available t3js-page-ce-dropzone-available"></div></div>';
                $content .= $singleElementHTML;
            }
        }
        $content .= '</div>';
        $colTitle = BackendUtility::getProcessedValue('tt_content', 'colPos', (string)$colPos);
        $head .= $this->tt_content_drawColHeader($colTitle);

        return $head . $content;
    }
}
