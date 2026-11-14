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
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Elements\PedigreeLinkageType;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Localization\Translation;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * vytux_cousins module
 */
class VytuxCousinsTabModule extends AbstractModule implements ModuleTabInterface, ModuleCustomInterface
{
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
        return '2.2.2';
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/vytux-com/vytux_cousins/master/latest.txt';
    }

    /**
     * Where to get support for this module.  Perhaps a github respository?
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/vytux-com/vytux_cousins/';
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

    private const MAX_DEGREE = 5;

    private function maxDegree(): int
    {
        $degree = (int) Session::get('vytux_cousins_degree', 1);

        return max(1, min(self::MAX_DEGREE, $degree));
    }

    /**
     * Label for a cousin of a given type, degree and sex; empty for plain first cousins.
     */
    public function cousinLabel(string $type, int $degree, string $sex = 'U'): string
    {
        if ($type === 'half') {
            $message = match ($degree) {
                1       => 'half cousin',
                2       => 'second half cousin',
                3       => 'third half cousin',
                4       => 'fourth half cousin',
                default => 'fifth half cousin',
            };
        } else {
            $message = match ($degree) {
                1       => '',
                2       => 'second cousin',
                3       => 'third cousin',
                4       => 'fourth cousin',
                default => 'fifth cousin',
            };
        }

        if ($message === '') {
            return '';
        }

        // Prefer a gendered translation (e.g. Lithuanian pusbrolis/pusseserė), falling back to the neutral one.
        $context = match ($sex) {
            'M'     => 'MALE',
            'F'     => 'FEMALE',
            default => '',
        };
        if ($context !== '') {
            $gendered = I18N::translateContext($context, $message);
            if ($gendered !== $message) {
                return $gendered;
            }
        }

        return I18N::translate($message);
    }

    private function degreeChoiceLabel(int $degree): string
    {
        return match ($degree) {
            1       => I18N::translate('First cousins'),
            2       => I18N::translate('Up to second cousins'),
            3       => I18N::translate('Up to third cousins'),
            4       => I18N::translate('Up to fourth cousins'),
            default => I18N::translate('Up to fifth cousins'),
        };
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
        $cousinsObj->fatherTypes = [];
        $cousinsObj->motherTypes = [];
        $cousinsObj->options = [];
        foreach ($this->cousinTypes() as $key => $type) {
            if ($type['optional']) {
                $cousinsObj->options[$key] = ['label' => $type['label'], 'enabled' => $this->isTypeEnabled($key)];
            }
        }
        $cousinsObj->degree = $this->maxDegree();
        $cousinsObj->degreeChoices = [];
        foreach (range(1, self::MAX_DEGREE) as $degree) {
            $cousinsObj->degreeChoices[$degree] = $this->degreeChoiceLabel($degree);
        }
        $cousinsObj->showTypeLabels = count($this->enabledCousinTypes()) > 1 || $cousinsObj->degree > 1;
        if ($individual->childFamilies()->first()) {
            $cousinsObj->father = $individual->childFamilies()->first()->husband();
            $cousinsObj->mother = $individual->childFamilies()->first()->wife();

            $cousinsObj->fatherCousins = $this->cousinsViaParent($individual, $cousinsObj->father, $cousinsObj->fatherTypes);
            $cousinsObj->motherCousins = $this->cousinsViaParent($individual, $cousinsObj->mother, $cousinsObj->motherTypes);
            $cousinsObj->fathersCousinCount = count($cousinsObj->fatherCousins);
            $cousinsObj->mothersCousinCount = count($cousinsObj->motherCousins);

            // A cousin related through both parents is listed on both sides but counted once overall.
            $cousinsObj->allCousinCount = count($cousinsObj->fatherTypes + $cousinsObj->motherTypes);
        }

        return $cousinsObj;
    }

    /**
     * Cousin types (degrees from first to MAX_DEGREE cousins are handled separately, see cousinsViaParent()).
     * To add a new type, add an entry here:
     * 'siblings' returns the relatives of a parent whose children count as cousins of this type.
     * Types with 'optional' => true get a check-box on the tab; the choice is kept in the session.
     *
     * @return array<string,array{label:string,optional:bool,siblings:callable}>
     */
    private function cousinTypes(): array
    {
        return [
            'first' => [
                'label'    => I18N::translate('First cousins'),
                'optional' => false,
                'siblings' => fn (Individual $parent): array => $this->fullSiblings($parent),
            ],
            'half'  => [
                'label'    => I18N::translate('Include half cousins'),
                'optional' => true,
                'siblings' => fn (Individual $parent): array => $this->halfSiblings($parent),
            ],
        ];
    }

    private function isTypeEnabled(string $key): bool
    {
        $type = $this->cousinTypes()[$key];

        return !$type['optional'] || Session::get('vytux_cousins_include_' . $key) === '1';
    }

    /**
     * @return array<string,array{label:string,optional:bool,siblings:callable}>
     */
    private function enabledCousinTypes(): array
    {
        return array_filter($this->cousinTypes(), fn (string $key): bool => $this->isTypeEnabled($key), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Saves the check-box choices and returns the refreshed tab content.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getCousinsAction(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $tree  = Validator::attributes($request)->tree();

        $individual = Registry::individualFactory()->make((string) ($query['xref'] ?? ''), $tree);
        $individual = Auth::checkIndividualAccess($individual);

        // Persist the new choices, but roll them back if the tab cannot be rendered with them.
        $keys     = array_map(fn (string $key): string => 'vytux_cousins_include_' . $key, array_keys($this->cousinTypes()));
        $keys[]   = 'vytux_cousins_degree';
        $previous = array_combine($keys, array_map(fn (string $key): mixed => Session::get($key), $keys));

        foreach ($this->cousinTypes() as $key => $type) {
            if ($type['optional'] && isset($query['include_' . $key])) {
                Session::put('vytux_cousins_include_' . $key, $query['include_' . $key] === '1' ? '1' : '0');
            }
        }

        if (isset($query['max_degree'])) {
            Session::put('vytux_cousins_degree', max(1, min(self::MAX_DEGREE, (int) $query['max_degree'])));
        }

        try {
            return response($this->getTabContent($individual));
        } catch (Throwable $exception) {
            foreach ($previous as $key => $value) {
                $value === null ? Session::forget($key) : Session::put($key, $value);
            }

            throw $exception;
        }
    }

    /**
     * Nth cousins are the descendants, N generations down, of the siblings of the
     * individual's ancestor N-1 generations above the parent.
     *
     * @param Individual                                    $self
     * @param Individual|null                               $parent
     * @param array<string,array{type:string,degree:int}>   $types  xref => type and degree; filled with the cousins found via this parent (lowest degree, first type wins)
     *
     * @return array<Individual>
     */
    private function cousinsViaParent(Individual $self, ?Individual $parent, array &$types): array
    {
        $cousins = [];

        if ($parent === null) {
            return $cousins;
        }

        $walker    = $this->walker();
        $ancestors = [$parent->xref() => $parent];

        for ($degree = 1; $degree <= $this->maxDegree(); $degree++) {
            foreach ($this->enabledCousinTypes() as $key => $type) {
                foreach ($ancestors as $ancestor) {
                    foreach ($type['siblings']($ancestor) as $sibling) {
                        foreach ($walker->descendants($sibling, $degree) as $child) {
                            if ($child !== $self && !isset($cousins[$child->xref()])) {
                                $cousins[$child->xref()] = $child;
                                $types[$child->xref()] = ['type' => $key, 'degree' => $degree];
                            }
                        }
                    }
                }
            }

            $ancestors = $walker->parentsOf($ancestors);
        }

        return array_values($cousins);
    }

    private function walker(): CousinWalker
    {
        return new CousinWalker(
            // Like the tab itself (father/mother) and fullSiblings()/halfSiblings(), only each person's
            // first parent family is followed, so birth and adoptive lines are never mixed.
            static function (Individual $person): array {
                $family = $person->childFamilies()->first();

                return array_values(array_filter([$family?->husband(), $family?->wife()]));
            },
            static function (Individual $person): array {
                $children = [];
                foreach ($person->spouseFamilies() as $fam) {
                    foreach ($fam->children() as $child) {
                        $children[] = $child;
                    }
                }

                return $children;
            },
            static fn (Individual $person): string => $person->xref(),
        );
    }

    /**
     * @return array<Individual>
     */
    private function fullSiblings(Individual $parent): array
    {
        $family = $parent->childFamilies()->first();

        if ($family === null) {
            return [];
        }

        return $family->children()->filter(fn (Individual $i): bool => $i !== $parent)->all();
    }

    /**
     * Children of the parent's other marriages, sharing only one parent.
     *
     * @return array<Individual>
     */
    private function halfSiblings(Individual $parent): array
    {
        $family = $parent->childFamilies()->first();

        if ($family === null) {
            return [];
        }

        $full = array_map(fn (Individual $i): string => $i->xref(), $this->fullSiblings($parent));
        $half = [];

        foreach ($family->spouses() as $grandparent) {
            foreach ($grandparent->spouseFamilies() as $other) {
                if ($other === $family) {
                    continue;
                }
                foreach ($other->children() as $child) {
                    if ($child !== $parent && !in_array($child->xref(), $full, true)) {
                        $half[$child->xref()] = $child;
                    }
                }
            }
        }

        return array_values($half);
    }

    /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return dirname(__DIR__) . '/resources/';
    }

    /**
     * A label for a parental family group
     *
     * @param Individual $individual
     *
     * @return string
     */
    public function getChildLabel(Individual $individual): string
    {
        $fact = $individual->facts(['FAMC'])->first();
        if ($fact instanceof Fact) {
            $pedi = $fact->attribute('PEDI');
        } else {
            $pedi = '';
        }

        if ($pedi !== '' && $pedi !== PedigreeLinkageType::VALUE_BIRTH) {
            $pedigree  = Registry::elementFactory()->make('INDI:FAMC:PEDI')->value($pedi, $individual->tree());
        } else {
            $pedigree  = Registry::elementFactory()->make('INDI:FAMC:PEDI')->value('', $individual->tree());
        }

        return $pedigree;
    }

    /**
     * @return ResponseInterface
     */
    function getCssAction(): ResponseInterface
    {
        return response(
            file_get_contents($this->resourcesFolder() . 'css/vytux_cousins.css'),
            200,
            ['content-type' => 'text/css']
        );
    }

    /** {@inheritdoc} */
    public function getTabContent(Individual $individual): string
    {
        return view(
            $this->name() . '::tab',
            [
                'cousins_obj'   => $this->getCousins($individual),
                'cousins_css'   => route('module', ['module' => $this->name(), 'action' => 'Css']),
                'module_obj'    => $this,
                'tab_url'       => route('module', ['module' => $this->name(), 'action' => 'Cousins', 'tree' => $individual->tree()->name(), 'xref' => $individual->xref()]),
            ]
        );
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
    public function boot(): void
    {
        // Here is also a good place to register any views (templates) used by the module.
        // This command allows the module to use: view($this->name() . '::', 'fish')
        // to access the file ./resources/views/fish.phtml
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }

    /**
     * Additional/updated translations.
     *
     * @param string $language
     *
     * @return string[]
     */
    public function customTranslations(string $language): array
    {
        // Here we are using an array for translations.
        // If you had .MO files, you could use them with:
        // return (new Translation('path/to/file.mo'))->asArray();
        switch ($language) {
            case 'da':
                return $this->danishTranslations();

            case 'fi':
                return $this->finnishTranslations();

            case 'fr':
            case 'fr-CA':
                return $this->frenchTranslations();

            case 'he':
                return $this->hebrewTranslations();

            case 'lt':
                return $this->lithuanianTranslations();

            case 'nb':
                return $this->norwegianBokmålTranslations();

            case 'nl':
                return $this->dutchTranslations();

            case 'nn':
                return $this->norwegianNynorskTranslations();

            case 'sv':
                return $this->swedishTranslations();

            case 'cs':
                return $this->czechTranslations();

            case 'de':
                return $this->germanTranslations();

            case 'ru':
                return $this->russianTranslations();
                                     
            case 'zh-Hans':
                return $this->hansTranslations();
                
            case 'zh-Hant':
                return $this->hantTranslations();

            default:
                return [];
        }
    }

    /**
     * @return array
     */
    protected function lithuanianTranslations(): array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => 'Pusbroliai / Pusseserės',
            'A tab showing cousins of an individual.' => 'Lapas rodantis asmens pusbrolius ir pusseseres.',
            'No family available' => 'Šeima nerasta',
            'Father\'s family (%s)' => 'Tėvo šeima (%s)',
            'Mother\'s family (%s)' => 'Motinos šeima (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s turi %1$d įrašyta pirmos eilės pusbrolį/pusseserę'
                . I18N::PLURAL . '%2$s turi %1$d įrašytus pirmos eilės pusbrolius/pusseseres'
                . I18N::PLURAL . '%2$s turi %1$d įrašytų pirmos eilės pusbrolių/pusseserių',
            'Include half cousins' => 'Įtraukti pusbrolius/pusseseres per vieną bendrą senelį',
            'half cousin' => 'pusbrolis/pusseserė per vieną bendrą senelį',
            'Cousin degree' => 'Pusbrolių/pusseserių eilė',
            'First cousins' => 'Pirmos eilės pusbroliai/pusseserės',
            'Up to second cousins' => 'Iki antros eilės pusbrolių/pusseserių',
            'Up to third cousins' => 'Iki trečios eilės pusbrolių/pusseserių',
            'Up to fourth cousins' => 'Iki ketvirtos eilės pusbrolių/pusseserių',
            'Up to fifth cousins' => 'Iki penktos eilės pusbrolių/pusseserių',
            'second cousin' => 'antros eilės pusbrolis/pusseserė',
            'third cousin' => 'trečios eilės pusbrolis/pusseserė',
            'fourth cousin' => 'ketvirtos eilės pusbrolis/pusseserė',
            'fifth cousin' => 'penktos eilės pusbrolis/pusseserė',
            'second half cousin' => 'antros eilės pusbrolis/pusseserė per vieną bendrą protėvį',
            'third half cousin' => 'trečios eilės pusbrolis/pusseserė per vieną bendrą protėvį',
            'fourth half cousin' => 'ketvirtos eilės pusbrolis/pusseserė per vieną bendrą protėvį',
            'fifth half cousin' => 'penktos eilės pusbrolis/pusseserė per vieną bendrą protėvį',
            'MALE' . Translation::CONTEXT_SEPARATOR . 'half cousin' => 'pusbrolis per vieną bendrą senelį',
            'FEMALE' . Translation::CONTEXT_SEPARATOR . 'half cousin' => 'pusseserė per vieną bendrą senelį',
            'MALE' . Translation::CONTEXT_SEPARATOR . 'second cousin' => 'antros eilės pusbrolis',
            'FEMALE' . Translation::CONTEXT_SEPARATOR . 'second cousin' => 'antros eilės pusseserė',
            'MALE' . Translation::CONTEXT_SEPARATOR . 'third cousin' => 'trečios eilės pusbrolis',
            'FEMALE' . Translation::CONTEXT_SEPARATOR . 'third cousin' => 'trečios eilės pusseserė',
            'MALE' . Translation::CONTEXT_SEPARATOR . 'fourth cousin' => 'ketvirtos eilės pusbrolis',
            'FEMALE' . Translation::CONTEXT_SEPARATOR . 'fourth cousin' => 'ketvirtos eilės pusseserė',
            'MALE' . Translation::CONTEXT_SEPARATOR . 'fifth cousin' => 'penktos eilės pusbrolis',
            'FEMALE' . Translation::CONTEXT_SEPARATOR . 'fifth cousin' => 'penktos eilės pusseserė',
            'MALE' . Translation::CONTEXT_SEPARATOR . 'second half cousin' => 'antros eilės pusbrolis per vieną bendrą protėvį',
            'FEMALE' . Translation::CONTEXT_SEPARATOR . 'second half cousin' => 'antros eilės pusseserė per vieną bendrą protėvį',
            'MALE' . Translation::CONTEXT_SEPARATOR . 'third half cousin' => 'trečios eilės pusbrolis per vieną bendrą protėvį',
            'FEMALE' . Translation::CONTEXT_SEPARATOR . 'third half cousin' => 'trečios eilės pusseserė per vieną bendrą protėvį',
            'MALE' . Translation::CONTEXT_SEPARATOR . 'fourth half cousin' => 'ketvirtos eilės pusbrolis per vieną bendrą protėvį',
            'FEMALE' . Translation::CONTEXT_SEPARATOR . 'fourth half cousin' => 'ketvirtos eilės pusseserė per vieną bendrą protėvį',
            'MALE' . Translation::CONTEXT_SEPARATOR . 'fifth half cousin' => 'penktos eilės pusbrolis per vieną bendrą protėvį',
            'FEMALE' . Translation::CONTEXT_SEPARATOR . 'fifth half cousin' => 'penktos eilės pusseserė per vieną bendrą protėvį',
            '%2$s has %1$d cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d cousins recorded' => '%2$s turi %1$d įrašytą pusbrolį/pusseserę'
                . I18N::PLURAL . '%2$s turi %1$d įrašytus pusbrolius/pusseseres'
                . I18N::PLURAL . '%2$s turi %1$d įrašytų pusbrolių/pusseserių',
        ];
    }

    /**
     * @return array
     */
    protected function germanTranslations(): array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => 'Cousins und Cousinen',
            'A tab showing cousins of an individual.' => 'Ein Reiter, der Cousins und Cousinen der Person anzeigt.',
            'No family available' => 'Es gibt keine Familie',
            'Father\'s family (%s)' => 'Väterlicherseits (%s)',
            'Mother\'s family (%s)' => 'Mütterlicherseits (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s hat einen Cousin oder eine Cousine ersten Grades'
                . I18N::PLURAL . '%2$s hat %1$d Cousins oder Cousinen ersten Grades',
        ];
    }

    /**
     * @return array
     */
    protected function danishTranslations(): array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => 'Fætre og kusiner',
            'A tab showing cousins of an individual.' => 'En fane der viser en persons fætre og kusiner.',
            'No family available' => 'Ingen familie tilgængelig',
            'Father\'s family (%s)' => 'Fars familie (%s)',
            'Mother\'s family (%s)' => 'Mors familie (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s har %1$d registreret fætter eller kusine'
                . I18N::PLURAL . '%2$s har %1$d registrerede fætre eller kusiner',
        ];
    }

    /**
     * @return array
     */
    protected function frenchTranslations(): array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => 'Cousins',
            'A tab showing cousins of an individual.' => 'Onglet montrant les cousins d\'un individu.',
            'No family available' => 'Pas de famille disponible',
            'Father\'s family (%s)' => 'Famille paternelle (%s)',
            'Mother\'s family (%s)' => 'Famille maternelle (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s a %1$d cousin germain connu'
                . I18N::PLURAL . '%2$s a %1$d cousins germains connus',
        ];
    }

    /**
     * @return array
     */
    protected function finnishTranslations(): array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => 'Serkut',
            'A tab showing cousins of an individual.' => 'Välilehti joka näyttää henkilön serkut.',
            'No family available' => 'Perhe puuttuu',
            'Father\'s family (%s)' => 'Isän perhe (%s)',
            'Mother\'s family (%s)' => 'Äidin perhe (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s:llä on %1$d serkku sivustolla'
                . I18N::PLURAL . '%2$s:lla on %1$d serkkua sivustolla',
        ];
    }

    /**
     * @return array
     */
    protected function hebrewTranslations(): array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => 'בני דודים',
            'A tab showing cousins of an individual.' => 'חוצץ המראה בני דוד של אדם.',
            'No family available' => 'משפחה חסרה',
            'Father\'s family (%s)' => 'משפחת האב (%s)',
            'Mother\'s family (%s)' => 'משפחת האם (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => 'ל%2$s יש בן דוד אחד מדרגה ראשונה'
                . I18N::PLURAL . 'ל%2$s יש %1$d בני דודים מדרגה ראשונה',
        ];
    }

    /**
     * @return array
     */
    protected function norwegianBokmålTranslations(): array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => 'Søskenbarn',
            'A tab showing cousins of an individual.' => 'Fane som viser en persons søskenbarn.',
            'No family available' => 'Ingen familie tilgjengelig',
            'Father\'s family (%s)' => 'Fars familie (%s)',
            'Mother\'s family (%s)' => 'Mors familie (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s har %1$d registrert søskenbarn'
                . I18N::PLURAL . '%2$s har %1$d registrerte søskenbarn',
            'Include half cousins' => 'Inkluder halvsøskenbarn',
            'half cousin' => 'halvsøskenbarn',
			'%2$s has %1$d cousin recorded'
			    . I18N::PLURAL . '%2$s has %1$d cousins recorded' => '%2$s har %1$d registrert søskenbarn'
                . I18N::PLURAL . '%2$s har %1$d registrerte søskenbarn',
        ];
    }

    /**
     * @return array
     */
    protected function norwegianNynorskTranslations(): array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => 'Syskenborn',
            'A tab showing cousins of an individual.' => 'Fane som syner ein person sine syskenborn.',
            'No family available' => 'Ingen familie tilgjengeleg',
            'Father\'s family (%s)' => 'Fars familie (%s)',
            'Mother\'s family (%s)' => 'Mors familie (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s har %1$d registrert syskenbarn'
                . I18N::PLURAL . '%2$s har %1$d registrerte syskenborn',
            'Include half cousins' => 'Inkluder halvsyskenborn',
            'half cousin' => 'halvsyskenbarn',
			'%2$s has %1$d cousin recorded'
			    . I18N::PLURAL . '%2$s has %1$d cousins recorded' => '%2$s har %1$d registrert syskenbarn'
                . I18N::PLURAL . '%2$s har %1$d registrerte syskenborn',
        ];
    }

    /**
     * @return array
     */
    protected function dutchTranslations(): array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => 'Neven en nichten',
            'A tab showing cousins of an individual.' => 'Een tabblad dat de neven en nichten van een persoon toont.',
            'No family available' => 'Geen familie beschikbaar',
            'Father\'s family (%s)' => 'Vader\'s familie (%s)',
            'Mother\'s family (%s)' => 'Moeder\'s familie (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s heeft %1$d volle neef of nicht geregistreerd'
                . I18N::PLURAL . '%2$s heeft %1$d volle neven en nichten geregistreerd',
            'Include half cousins' => 'Halfneven/-nichten opnemen',
            'half cousin' => 'halfneef/-nicht',
            '%2$s has %1$d cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d cousins recorded' => '%2$s heeft %1$d neef of nicht geregistreerd'
                . I18N::PLURAL . '%2$s heeft %1$d neven en nichten geregistreerd',
        ];
    }

    /**
     * @return array
     */
    protected function swedishTranslations(): array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => 'Kusiner',
            'A tab showing cousins of an individual.' => 'En flik som visar en persons kusiner.',
            'No family available' => 'Familj saknas',
            'Father\'s family (%s)' => 'Faderns familj (%s)',
            'Mother\'s family (%s)' => 'Moderns familj (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s har %1$d registrerad kusin'
                . I18N::PLURAL . '%2$s har %1$d registrerade kusiner',
        ];
    }

    /**
     * @return array
     */
    protected function czechTranslations(): array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => 'Bratranci',
            'A tab showing cousins of an individual.' => 'Panel zobrazující bratrance osoby.',
            'No family available' => 'Rodina chybí',
            'Father\'s family (%s)' => 'Otcova rodina (%s)',
            'Mother\'s family (%s)' => 'Matčina rodina (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s má %1$d bratrance'
                . I18N::PLURAL . '%2$s má %1$d bratrance'
                . I18N::PLURAL . '%2$s má %1$d bratranců',
        ];
    }

    /**
     * @return array
     */
    protected function russianTranslations(): array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => '🇺🇦 Двоюродные братья и сёстры 🇺🇦',
            'A tab showing cousins of an individual.' => 'Вкладка с двоюродными братьями и сёстрами человека.',
            'No family available' => 'Нет доступных семей',
            'Father\'s family (%s)' => 'По отцу (%s)',
            'Mother\'s family (%s)' => 'По матери (%s)',
            '%2$s has %1$d first cousin recorded' 
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s имеет %1$d двоюродного брата или сестру записанным' 
                . I18N::PLURAL . '%2$s имеет %1$d двоюродных братьев или сестёр записанными' 
                . I18N::PLURAL . '%2$s имеет %1$d двоюродных братьев или сестёр записанными',
        ];
    }
    
    /**
     * @return array
     */
    protected function hansTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => '表亲',
            'A tab showing cousins of an individual.' => '显示个人表亲的标签。',
            'No family available' => '没有堂表亲记录',
            'Father\'s family (%s)' => '父亲的家庭 (%s)',
            'Mother\'s family (%s)' => '母亲的家庭 (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s有%1$d个第一代堂表亲',
        ];
    }
	 /**
     * @return array
     */
    protected function hantTranslations() : array
    {
        // Note the special characters used in plural and context-sensitive translations.
        return [
            'Cousins' => '表親',
            'A tab showing cousins of an individual.' => '显示个人堂表親的标签。',
            'No family available' => '没有堂表親记录',
            'Father\'s family (%s)' => '父親的家庭 (%s)',
            'Mother\'s family (%s)' => '母親的家庭 (%s)',
            '%2$s has %1$d first cousin recorded'
                . I18N::PLURAL . '%2$s has %1$d first cousins recorded' => '%2$s 有 %1$d 個第一代堂表親',
        ];
    }
};
