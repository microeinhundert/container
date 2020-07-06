<?php
declare(strict_types = 1);

namespace B13\Container\Hooks;

/*
 * This file is part of TYPO3 CMS-based extension "container" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Backend\Wizard\NewContentElementWizardHookInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class WizardItems implements NewContentElementWizardHookInterface
{
    public function manipulateWizardItems(&$wizardItems, &$parentObject)
    {
        $parent = (int)GeneralUtility::_GP('tx_container_parent');
        if ($parent > 0) {
            foreach ($wizardItems as $key => $wizardItem) {
                $wizardItems[$key]['tt_content_defValues']['tx_container_parent'] = $parent;
                $wizardItems[$key]['params'] .= '&defVals[tt_content][tx_container_parent]=' . $parent;
            }
        }
    }
}
