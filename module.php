<?php
namespace Vytux\Webtrees_vytux_cousins;

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
use PDO;
use Fisharebest\Webtrees as webtrees;

class VytuxCousinsTabModule extends webtrees\AbstractModule implements webtrees\ModuleTabInterface {

	public function __construct() {
		parent::__construct('vytux_cousins');
	}
	
	// Extend WT_Module
	public function getTitle() {
		return /* I18N: Name of a module/tab on the individual page. */ webtrees\I18N::translate('Cousins');
	}

	// Extend WT_Module
	public function getDescription() {
		return /* I18N: Description of the "Facts and events" module */ webtrees\I18N::translate('A tab showing cousins of an individual.');
	}

	// Implement WT_Module_Tab
	public function defaultTabOrder() {
		return 10;
	}

	// Implement WT_Module_Tab
	public function isGrayedOut() {
		return false;
	}

	// Extend class WT_Module
	public function defaultAccessLevel() {
        return webtrees\Auth::PRIV_USER;
	}
	
	// Implement WT_Module_Tab
	public function getTabContent() {
		global $controller, $WT_TREE;
		global $TEXT_DIRECTION;
		
		$list_f          = array();
		$list_f2         = array();
		$list_f3         = array();
		$list_m          = array();
		$list_m2         = array();
		$list_m3         = array();
		$sql_f           = '';
		$sql_f2          = '';
		$sql_f3          = '';
		$sql_m           = '';
		$sql_m2          = '';
		$sql_m3          = '';
		$args_f          = '';
		$args_f2         = '';
		$args_f3         = '';
		$args_m          = '';
		$args_m2         = '';
		$args_m3         = '';
		$count_cousins_f = 0;
		$count_cousins_m = 0;
		$family          = '';
		
		$html = '<style type="text/css">
				#vytux_cousins {width:98%;}
				#vytux_cousins h3 {margin:10px 20px;}
				#vytux_cousins h4 {font-size:16px;font-weight:900;margin:0 0 5px;text-align:center;}
				#vytux_cousins h5 {margin:0;clear:both;padding:10px 0 2px 0;}
				#vytux_cousins_content {border-spacing:10px;display:inline-table;margin:0 10px;overflow:hidden;padding:0;width:98%;}
				#vytux_cousins .person_box, #vytux_cousins .person_boxF, #vytux_cousins .person_boxNN {clear:left;float:left;margin:1px 0;width:99%;!important;}
				#cousins_f, #cousins_m {border:1px solid grey;border-radius:5px;display:table-cell;padding:10px;width:45%;}
				.cousins_name {}
				.cousins_lifespan, .cousins_pedi {font-size:9px;padding:3px 5px 5px 5px;}
				.cousins_counter {margin:0 3px;font-size:80%;}
				.cousins_counter:after {content:". ";}
				</style>';

		$person   = $controller->getSignificantIndividual();
		$fullname = $controller->record->getFullName();
		$xref     = $controller->record->getXref();
		
		if ($person->getPrimaryChildFamily()) {
			$parentFamily = $person->getPrimaryChildFamily();
		} else {
			$html .= '<h3>'.webtrees\I18N::translate('No family available').'</h3>';
			return $html;
			exit;
		}
		
		if ($parentFamily->getHusband()) {
			$grandparentFamilyHusb = $parentFamily->getHusband()->getPrimaryChildFamily();
		} else {
			$grandparentFamilyHusb = '';
		}
		
		if ($parentFamily->getWife()) {
			$grandparentFamilyWife = $parentFamily->getWife()->getPrimaryChildFamily();
		} else {
			$grandparentFamilyWife = '';
		}
		
		//Lookup father's siblings
		$sql_f  = "SELECT l_to as xref, MIN(d_julianday1) as date, n_givn as name ";
		$sql_f .= "FROM `##link` ";
		$sql_f .= "LEFT JOIN `##dates` ON l_to = d_gid AND d_file = l_file AND d_fact = 'BIRT' ";
		$sql_f .= "LEFT JOIN `##name` ON l_to = n_id AND l_file = n_file AND n_type = 'NAME' ";
		$sql_f .= "WHERE l_file = :tree_id ";
		$sql_f .= "AND l_type LIKE 'CHIL' ";
		$sql_f .= "AND l_from LIKE :family_id ";
		$sql_f .= "GROUP BY xref ";
		$sql_f .= "ORDER BY -MIN(d_julianday1) DESC, n_givn ASC";
				
		$args_f['tree_id']   = $WT_TREE->getTreeId();
		$args_f['family_id'] = substr($grandparentFamilyHusb, 0, strpos($grandparentFamilyHusb, '@'));
		
		$rows = webtrees\Database::prepare($sql_f)->execute($args_f)->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			if ($row['xref'] != substr($parentFamily->getHusband(), 0, strpos($parentFamily->getHusband(), '@')))
				$list_f[] = $row['xref'];
		}
		
		//Lookup Aunt & Uncle's families (father's family)
		foreach ($list_f as $ids) {
			$sql_f2  = "SELECT l_from as xref ";
			$sql_f2 .= "FROM `##link` ";
			$sql_f2 .= "WHERE l_file = :tree_id ";
			$sql_f2 .= "AND (l_type LIKE 'HUSB' OR l_type LIKE 'WIFE') ";
			$sql_f2 .= "AND l_to LIKE :family_id";
			
			$args_f2['tree_id']   = $WT_TREE->getTreeId();
			$args_f2['family_id'] = $ids;
			
			$rows = webtrees\Database::prepare($sql_f2)->execute($args_f2)->fetchAll(PDO::FETCH_ASSOC);
			foreach ($rows as $row) {
				$list_f2[] = $row['xref'];
			}
		}
		
		//Lookup cousins (father's family)
		foreach ($list_f2 as $id2) {
			$sql_f3  = "SELECT l_to as xref, MIN(d_julianday1) as date, n_givn as name ";
			$sql_f3 .= "FROM `##link` ";
			$sql_f3 .= "LEFT JOIN `##dates` ON l_to = d_gid AND d_file = l_file AND d_fact = 'BIRT' ";
			$sql_f3 .= "LEFT JOIN `##name` ON l_to = n_id AND l_file = n_file AND n_type = 'NAME' ";
			$sql_f3 .= "WHERE l_file = :tree_id ";
			$sql_f3 .= "AND l_type LIKE 'CHIL' ";
			$sql_f3 .= "AND l_from LIKE :family_id ";
			$sql_f3 .= "GROUP BY xref ";
			$sql_f3 .= "ORDER BY -MIN(d_julianday1) DESC, n_givn ASC";
					
			$args_f3['tree_id']   = $WT_TREE->getTreeId();
			$args_f3['family_id'] = $id2;
			
			$rows  = webtrees\Database::prepare($sql_f3)->execute($args_f3)->fetchAll(PDO::FETCH_ASSOC);
			foreach ($rows as $row) {
				$list_f3[] = $row['xref'];
				$count_cousins_f ++;
			}
		}

		//Lookup mother's siblings
		$sql_m  = "SELECT l_to as xref, MIN(d_julianday1) as date, n_givn as name ";
		$sql_m .= "FROM `##link` ";
		$sql_m .= "LEFT JOIN `##dates` ON l_to = d_gid AND d_file = l_file AND d_fact = 'BIRT' ";
		$sql_m .= "LEFT JOIN `##name` ON l_to = n_id AND l_file = n_file AND n_type = 'NAME' ";
		$sql_m .= "WHERE l_file = :tree_id ";
		$sql_m .= "AND l_type LIKE 'CHIL' ";
		$sql_m .= "AND l_from LIKE :family_id ";
		$sql_m .= "GROUP BY xref ";
		$sql_m .= "ORDER BY -MIN(d_julianday1) DESC, n_givn ASC";
				
		$args_m['tree_id']   = $WT_TREE->getTreeId();
		$args_m['family_id'] = substr($grandparentFamilyWife, 0, strpos($grandparentFamilyWife, '@'));
		
		$rows = webtrees\Database::prepare($sql_m)->execute($args_m)->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			if ($row['xref'] != substr($parentFamily->getWife(), 0, strpos($parentFamily->getWife(), '@')))
				$list_m[] = $row['xref'];
		}
		
		//Lookup Aunt & Uncle's families (mother's family)
		foreach ($list_m as $ids) {
			$sql_m2  = "SELECT l_from as xref ";
			$sql_m2 .= "FROM `##link` ";
			$sql_m2 .= "WHERE l_file = :tree_id ";
			$sql_m2 .= "AND (l_type LIKE 'HUSB' OR l_type LIKE 'WIFE') ";
			$sql_m2 .= "AND l_to LIKE :family_id";
			
			$args_m2['tree_id']   = $WT_TREE->getTreeId();
			$args_m2['family_id'] = $ids;
			
			$rows = webtrees\Database::prepare($sql_m2)->execute($args_m2)->fetchAll(PDO::FETCH_ASSOC);
			foreach ($rows as $row) {
				$list_m2[] = $row['xref'];
			}
		}
		
		//Lookup cousins (mother's family)
		foreach ($list_m2 as $id2) {
			$sql_m3  = "SELECT l_to as xref, MIN(d_julianday1) as date, n_givn as name ";
			$sql_m3 .= "FROM `##link` ";
			$sql_m3 .= "LEFT JOIN `##dates` ON l_to = d_gid AND d_file = l_file AND d_fact = 'BIRT' ";
			$sql_m3 .= "LEFT JOIN `##name` ON l_to = n_id AND l_file = n_file AND n_type = 'NAME' ";
			$sql_m3 .= "WHERE l_file = :tree_id ";
			$sql_m3 .= "AND l_type LIKE 'CHIL' ";
			$sql_m3 .= "AND l_from LIKE :family_id ";
			$sql_m3 .= "GROUP BY xref ";
			$sql_m3 .= "ORDER BY -MIN(d_julianday1) DESC, n_givn ASC";
					
			$args_m3['tree_id']   = $WT_TREE->getTreeId();
			$args_m3['family_id'] = $id2;
			
			$rows = webtrees\Database::prepare($sql_m3)->execute($args_m3)->fetchAll(PDO::FETCH_ASSOC);
			foreach ($rows as $row) {
				$list_m3[] = $row['xref'];
				$count_cousins_m ++;
			}
		}
		
		$count_cousins  = $count_cousins_f + $count_cousins_m;
		$myParentFamily = $parentFamily->getXref();
		
		$html .= '<h3>' . webtrees\I18N::plural('%2$s has %1$d first cousin recorded', '%2$s has %1$d first cousins recorded', $count_cousins, $count_cousins, $fullname) . '</h3>';
		$html .= '<div id="vytux_cousins_content">';

		//List Cousins (father's family)
		$html .= '<div id="cousins_f">';
		$html .= '<h4>' . webtrees\I18N::translate('Father\'s family (%s)', $count_cousins_f) . '</h4>';
		$i = 0;
		$prev_fam_id = -1;
		foreach ($list_f3 as $id3) {
			$i++;
			$record = webtrees\Individual::getInstance($id3, $WT_TREE);
			if ($record->getPrimaryChildFamily()) {
				$primaryChildFamily = $record->getPrimaryChildFamily();
				$cousinParentFamily = substr($primaryChildFamily, 0, strpos($primaryChildFamily, '@'));
				if ( $cousinParentFamily == $myParentFamily )
					continue; // cannot be cousin to self
				$family = webtrees\Family::getInstance($cousinParentFamily, $WT_TREE);
				$tmp = array('M'=>'', 'F'=>'F', 'U'=>'NN');
				$isF = $tmp[$record->getSex()];
				$label = '';
				foreach ($record->getFacts('FAMC') as $fact) {
					if ($fact->getTarget() === $record->getPrimaryChildFamily()) {
						$pedi = $fact->getAttribute('PEDI');
						break;
					}
				}
				if (isset($pedi) && $pedi != 'birth') {
					$label = '<span class="cousins_pedi">' . webtrees\GedcomCodePedi::getValue($pedi, $record) . '</span>';
				}
				if ($cousinParentFamily != $prev_fam_id) {
					$prev_fam_id = $cousinParentFamily;
					$html .= '<h5>' . /* I18N: Do not translate. Already in webtrees core */ webtrees\I18N::translate('Parents') . '<a target="_blank" href="' . $family->getHtmlUrl() . '">&nbsp;' . $family->getFullName() . '</a></h5>';
					$i = 1;	
				}
			}
			$html .= '<div class="person_box' . $isF . '">';
			$html .= '<span class="cousins_counter">' . $i . '</span>';
			$html .= '<span class="cousins_name"><a target="_blank" href="' . $record->getHtmlUrl() . '">' . $record->getFullName() .'</a></span>';
			$html .= '<span class="cousins_lifespan" dir="' . $TEXT_DIRECTION . '">' . $record->getLifeSpan() . '</span>';
			$html .= '<span class="cousins_pedi">' . $label . '</span>';
			$html .= '</div>';
		}
		$html .= '</div>'; // close id="cousins_f"
		
		//List Cousins (mother's family)
		$prev_fam_id = -1;
		$html .= '<div id="cousins_m">';
		$html .= '<h4>' . webtrees\I18N::translate('Mother\'s family (%s)', $count_cousins_m) . '</h4>';
		$i = 0;
		foreach ($list_m3 as $id3) {
			$i++;
			$record = webtrees\Individual::getInstance($id3, $WT_TREE);
			if ($record->getPrimaryChildFamily()) {
				$primaryChildFamily = $record->getPrimaryChildFamily();
				$cousinParentFamily = substr($primaryChildFamily, 0, strpos($primaryChildFamily, '@'));
				if ( $cousinParentFamily == $myParentFamily )
					continue; // cannot be cousin to self
				$record = webtrees\Individual::getInstance($id3, $WT_TREE);
				$cousinParentFamily = substr($primaryChildFamily, 0, strpos($primaryChildFamily, '@'));
				$family = webtrees\Family::getInstance($cousinParentFamily, $WT_TREE);
				$tmp = array('M'=>'', 'F'=>'F', 'U'=>'NN');
				$isF = $tmp[$record->getSex()];
				$label = '';
				foreach ($record->getFacts('FAMC') as $fact) {
					if ($fact->getTarget() === $record->getPrimaryChildFamily()) {
						$pedi = $fact->getAttribute('PEDI');
						break;
					}
				}
				if (isset($pedi) && $pedi != 'birth') {
					$label = '<span class="cousins_pedi">' . webtrees\GedcomCodePedi::getValue($pedi, $record) . '</span>';
				}
				if ($cousinParentFamily != $prev_fam_id) {
					$prev_fam_id = $cousinParentFamily;
					$html .= '<h5>' . /* I18N: Do not translate. Already in webtrees core */ webtrees\I18N::translate('Parents') . '<a target="_blank" href="' . $family->getHtmlUrl() . '">&nbsp;' . $family->getFullName() . '</a></h5>';
					$i = 1;
				}
			}
			$html .= '<div class="person_box' . $isF . '">';
			$html .= '<span class="cousins_counter">' . $i . '</span>';
			$html .= '<span class="cousins_name"><a target="_blank" href="' . $record->getHtmlUrl() . '">' . $record->getFullName() . '</a></span>';
			$html .= '<span class="cousins_lifespan" dir="' . $TEXT_DIRECTION . '">' . $record->getLifeSpan() . '</span>';
			$html .= '<span class="cousins_pedi">' . $label . '</span>';
			$html .= '</div>';
		}
		$html .= '</div>'; // close id="cousins_m"
		$html .= '</div>'; // close div id="vytux_cousins_content"
		return $html;
	}

	// Implement WT_Module_Tab
	public function hasTabContent() {
		return true;
	}
	
	// Implement WT_Module_Tab
	public function canLoadAjax() {
		return false;
	}

	// Implement WT_Module_Tab
	public function getPreLoadContent() {
		return '';
	}
}

return new VytuxCousinsTabModule;