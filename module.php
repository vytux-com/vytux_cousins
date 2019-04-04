<?php
namespace Vytux\WebtreesModules\VytuxCousins;
/*
 * webtrees - vytux_cousins tab based on simpl_cousins
 *
 * Copyright (C) 2013 Vytautas Krivickas and vytux.com. All rights reserved. 
 *
 * Copyright (C) 2013 Nigel Osborne and kiwtrees.net. All rights reserved.
 *
 * webtrees: Web based Family History software
 * Copyright (C) 2013 webtrees development team.
 *
 * Derived from PhpGedView
 * Copyright (C) 2002 to 2010  PGV Development Team.  All rights reserved.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\GedcomCode\GedcomCodePedi;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;

/**
 * vytux_cousins module
 */
class VytuxCousinsTabModule extends AbstractModule implements ModuleTabInterface, ModuleCustomInterface {
    use ModuleCustomTrait;
    use ModuleTabTrait;

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        return /* I18N: Name of a module/tab on the individual page. */ I18N::translate('Cousins');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        return /* I18N: Description of the "Facts and events" module */ I18N::translate('A tab showing cousins of an individual.');
    }

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName(): string
    {
        return 'Vytautas Krivickas';
    }

    /**
     * The version of this module.
     *
     * @return string
     */
    public function customModuleVersion(): string
    {
        return '2.0.0';
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://vytux.com/main/projects/webtrees/vytux_cousins/';
    }

    /**
     * Where to get support for this module.  Perhaps a github respository?
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://vytux.com/main/contact-us/';
    }

    /**
     * The default position for this tab.  It can be changed in the control panel.
     *
     * @return int
     */
    public function defaultTabOrder(): int
    {
        return 10;
    }

    /**
     * Is this tab empty? If so, we don't always need to display it.
     *
     * @param Individual $individual
     *
     * @return bool
     */
    public function hasTabContent(Individual $individual): bool
    {
        return true;
    }

    /**
     * A greyed out tab has no actual content, but may perhaps have
     * options to create content.
     *
     * @param Individual $individual
     *
     * @return bool
     */
    public function isGrayedOut(Individual $individual): bool
    {
        return false;
    }

    private function getCousins(Individual $individual): object
    {
        $cousinsObj = (object)[];
        $cousinsObj->self = $individual;
        $cousinsObj->fathersCousinCount = 0;
        $cousinsObj->mothersCousinCount = 0;
        $cousinsObj->allCousinCount = 0;
        $cousinsObj->fatherCousins = [];
        $cousinsObj->motherCousins = [];
        if ($individual->primaryChildFamily()) {
            $cousinsObj->father = $individual->primaryChildFamily()->husband();
            if (($cousinsObj->father) && ($cousinsObj->father->primaryChildFamily())) {
                foreach ($cousinsObj->father->primaryChildFamily()->children() as $sibling) {
                    if ($sibling !== $cousinsObj->father) {
                        foreach ($sibling->spouseFamilies() as $fam) {
                            foreach ($fam->children() as $child) {
                                $cousinsObj->fatherCousins[] = $child;
                                $cousinsObj->fathersCousinCount++;
                            }
                        }
                    }
                } 
            }

            $cousinsObj->mother = $individual->primaryChildFamily()->wife();
            if (($cousinsObj->mother) && ($cousinsObj->mother->primaryChildFamily())) {
                foreach ($cousinsObj->mother->primaryChildFamily()->children() as $sibling) {
                    if ($sibling !== $cousinsObj->mother) {
                        foreach ($sibling->spouseFamilies() as $fam) {
                            foreach ($fam->children() as $child) {
                                $cousinsObj->motherCousins[] = $child;
                                $cousinsObj->mothersCousinCount++;
                            }
                        }
                    }
                } 
            }

            $cousinsObj->allCousinCount = sizeof(array_unique(array_merge($cousinsObj->fatherCousins,$cousinsObj->motherCousins)));
        }

        return $cousinsObj;
    }

    /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * A label for a parental family group
     *
     * @param Family $family
     *
     * @return string
     */
    public function getChildLabel(Individual $individual): string
    {
        if (preg_match('/\n1 FAMC @' . $individual->primaryChildFamily()->xref() . '@(?:\n[2-9].*)*\n2 PEDI (.+)/', $individual->gedcom(), $match)) {
            // A specified pedigree
            return GedcomCodePedi::getValue($match[1],$individual->getInstance($individual->xref(),$individual->tree()));
        }

        // Default (birth) pedigree
        return GedcomCodePedi::getValue('',$individual->getInstance($individual->xref(),$individual->tree()));
    }


    /**
     * @return string
     */
    public function css(): string
    {
        return $this->assetUrl('css/vytux_cousins.css');
    }

    /** {@inheritdoc} */
    public function getTabContent(Individual $individual): string
    {
        return view($this->name() . '::tab', [
            'cousins_obj'	=> $this->getCousins($individual),
            'cousins_css'	=> $this->css(),
            'module_obj'    => $this,
        ]);	
    }

    /** {@inheritdoc} */
    public function canLoadAjax(): bool
    {
        return false;
    }

    /**
     *  Constructor.
     */
    public function __construct()
    {
        // IMPORTANT - the constructor is called on *all* modules, even ones that are disabled.
        // It is also called before the webtrees framework is initialised, and so other components
        // will not yet exist.
    }

    /**
     *  Boostrap.
     *
     * @param UserInterface $user A user (or visitor) object.
     * @param Tree|null     $tree Note that $tree can be null (if all trees are private).
     */
    public function boot(UserInterface $user, ?Tree $tree): void
    {
        // Here is also a good place to register any views (templates) used by the module.
        // This command allows the module to use: view($this->name() . '::', 'fish')
        // to access the file ./resources/views/fish.phtml
        View::registerNamespace($this->name(), __DIR__ . '/resources/views/');
    }

    
}

return new VytuxCousinsTabModule;
