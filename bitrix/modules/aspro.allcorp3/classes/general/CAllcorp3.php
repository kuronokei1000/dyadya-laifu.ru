<?
/**
 * Allcorp3 module
 * @copyright 2020 Aspro
 */

if(!defined('ALLCORP3_MODULE_ID'))
	define('ALLCORP3_MODULE_ID', 'aspro.allcorp3');

use \Bitrix\Main\Application,
	\Bitrix\Main\Type\Collection,
	\Bitrix\Main\Loader,
	\Bitrix\Main\IO\File,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

// initialize module parametrs list and default values
include_once __DIR__.'/../../parametrs.php';
include_once __DIR__.'/../../presets.php';
include_once __DIR__.'/../../thematics.php';

class CAllcorp3{
	const partnerName	= 'aspro';
	const solutionName	= 'allcorp3';
	const templateName	= 'aspro-allcorp3';
	const moduleID		= ALLCORP3_MODULE_ID;
	const wizardID		= 'aspro:allcorp3';
	const devMode		= false; // set to false before release

	public static $arParametrsList = array();
	public static $arThematicsList = array();
	public static $arPresetsList = array();
	private static $arMetaParams = array();

	public static function isPageSpeedTest(){
		return isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Lighthouse') !== false;
	}

	public static function checkIndexBot(){
		static $result;

		if(!isset($result)){
			$result = self::isPageSpeedTest() && Option::get(self::moduleID, 'USE_PAGE_SPEED_OPTIMIZATION', 'Y', SITE_ID) === 'Y';
		}

		return $result;
	}

	public function checkModuleRight($reqRight = 'R', $bShowError = false){
		global $APPLICATION;

		if($APPLICATION->GetGroupRight(self::moduleID) < $reqRight){
			if($bShowError){
				$APPLICATION->AuthForm(GetMessage('ALLCORP3_ACCESS_DENIED'));
			}
			return false;
		}

		return true;
	}

	public static function ClearSomeComponentsCache($SITE_ID = false){
		if(!strlen($SITE_ID)){
			$SITE_ID = SITE_ID;
		}

		CBitrixComponent::clearComponentCache('bitrix:news.list', $SITE_ID);
		CBitrixComponent::clearComponentCache('bitrix:news.detail', $SITE_ID);
	}

	public static function AjaxAuth(){
		if(!defined('ADMIN_SECTION') && isset($_REQUEST['auth_service_id']) && $_REQUEST['auth_service_id'])
		{
			if($_REQUEST['auth_service_id']):
				global $APPLICATION, $CACHE_MANAGER;?>
				<?$APPLICATION->IncludeComponent(
					"bitrix:system.auth.form",
					"popup",
					array(
						"PROFILE_URL" => "",
						"SHOW_ERRORS" => "Y",
						"POPUP_AUTH" => "Y"
					)
				);?>
			<?endif;?>
		<?}
	}

	public static function GetSections($arItems, $arParams)
	{
		$arSections = array(
			'PARENT_SECTIONS' => array(),
			'CHILD_SECTIONS' => array(),
			'ALL_SECTIONS' => array(),
		);
		if (is_array($arItems) && $arItems) {
			$arSectionsIDs = array();
			foreach ($arItems as $arItem) {
				if ($SID = $arItem['IBLOCK_SECTION_ID']) {
					$arSectionsIDs[] = $SID;
				}
			}
			if ($arSectionsIDs) {
				$arCacheParams = array(
					'TAG' => CAllcorp3Cache::GetIBlockCacheTag($arParams['IBLOCK_ID']),
					'GROUP' => array('ID'),
					'MULTI' => 'N'
				);
				if($arParams['SEF_URL_TEMPLATES'] && $arParams['SEF_URL_TEMPLATES']['section']){
					$arCacheParams['URL_TEMPLATE'] = $arParams['SEF_URL_TEMPLATES']['section'];
				}

				$arSections['ALL_SECTIONS'] = CAllcorp3Cache::CIBLockSection_GetList(
					array(
						'SORT' => 'ASC',
						'NAME' => 'ASC',
						'CACHE' => $arCacheParams,
					),
					array('ID' => $arSectionsIDs)
				);

				$bCheckRoot = false;
				foreach ($arSections['ALL_SECTIONS'] as $key => $arSection) {
					if ($arSection['DEPTH_LEVEL'] > 1) {
						$bCheckRoot = true;
						$arSections['CHILD_SECTIONS'][$key] = $arSection;
						unset($arSections['ALL_SECTIONS'][$key]);

						$arFilter = array('IBLOCK_ID'=>$arSection['IBLOCK_ID'], '<=LEFT_BORDER' => $arSection['LEFT_MARGIN'], '>=RIGHT_BORDER' => $arSection['RIGHT_MARGIN'], 'DEPTH_LEVEL' => 1);
						$arSelect = array('ID', 'SORT', 'IBLOCK_ID', 'NAME', 'SECTION_PAGE_URL');

						$arCacheParams = array(
							'TAG' => CAllcorp3Cache::GetIBlockCacheTag($arParams['IBLOCK_ID']),
							'MULTI' => 'N',
						);
						if($arParams['SEF_URL_TEMPLATES'] && $arParams['SEF_URL_TEMPLATES']['section']){
							$arCacheParams['URL_TEMPLATE'] = $arParams['SEF_URL_TEMPLATES']['section'];
						}

						$arParentSection = CAllcorp3Cache::CIBLockSection_GetList(
							array(
								'SORT' => 'ASC',
								'NAME' => 'ASC',
								'CACHE' => $arCacheParams,
							),
							$arFilter,
							false,
							$arSelect
						);

						$arSections['ALL_SECTIONS'][$arParentSection['ID']]['SECTION'] = $arParentSection;
						$arSections['ALL_SECTIONS'][$arParentSection['ID']]['CHILD_IDS'][$arSection['ID']] = $arSection['ID'];

						$arSections['PARENT_SECTIONS'][$arParentSection['ID']] = $arParentSection;
					} else {
						$arSections['ALL_SECTIONS'][$key]['SECTION'] = $arSection;
						$arSections['PARENT_SECTIONS'][$key] = $arSection;
					}
				}

				if ($bCheckRoot) {
					// get root sections
					$arFilter = array('IBLOCK_ID' => $arParams['IBLOCK_ID'], 'ACTIVE' => 'Y', 'DEPTH_LEVEL' => 1, 'ID' => array_keys($arSections['ALL_SECTIONS']));
					$arSelect = array('ID', 'SORT', 'IBLOCK_ID', 'NAME');

					$arCacheParams = array(
						'TAG' => CAllcorp3Cache::GetIBlockCacheTag($arParams['IBLOCK_ID'])
					);
					if($arParams['SEF_URL_TEMPLATES'] && $arParams['SEF_URL_TEMPLATES']['section']){
						$arCacheParams['URL_TEMPLATE'] = $arParams['SEF_URL_TEMPLATES']['section'];
					}

					$arRootSections = CAllcorp3Cache::CIBLockSection_GetList(
						array(
							'SORT' => 'ASC',
							'NAME' => 'ASC',
							'CACHE' => $arCacheParams,
						),
						$arFilter,
						false,
						$arSelect
					);

					foreach ($arRootSections as $arSection) {
						$arSections['ALL_SECTIONS']['SORTED'][$arSection['ID']] = $arSections['ALL_SECTIONS'][$arSection['ID']];
						unset($arSections['ALL_SECTIONS'][$arSection['ID']]);
					}
					foreach ($arSections['ALL_SECTIONS']['SORTED'] as $key => $arSection) {
						$arSections['ALL_SECTIONS'][$key] = $arSection;
					}
					unset($arSections['ALL_SECTIONS']['SORTED']);
				}
			}
		}
		return $arSections;
	}

	public static function getMenuChildsExt($arParams, &$aMenuLinksExt, $bMenu = false)
	{
		if ($handler = \Aspro\Functions\CAsproAllcorp3::getCustomFunc(__FUNCTION__)) {
			call_user_func_array($handler, [$arParams, &$aMenuLinksExt, $bMenu]);
			return;
		}

		$catalog_id = \Bitrix\Main\Config\Option::get('aspro.allcorp3', 'CATALOG_IBLOCK_ID', CAllcorp3Cache::$arIBlocks[SITE_ID]['aspro_allcorp3_catalog']['aspro_allcorp3_catalog'][0]);
		$bIsCatalog = $arParams['IBLOCK_ID'] == $catalog_id;

		$arParams['CATALOG_IBLOCK_ID'] = $catalog_id;
		$arParams['IS_CATALOG_IBLOCK'] = $bIsCatalog;

		foreach(GetModuleEvents(ALLCORP3_MODULE_ID, 'BeforeAsproGetMenuChildsExt', true) as $arEvent) // event for manipulation store quantity block
			ExecuteModuleEventEx($arEvent, array($arParams, &$aMenuLinksExt, $bMenu));

		$arSectionFilter = array(
			'IBLOCK_ID' => $arParams['IBLOCK_ID'],
			'ACTIVE' => 'Y',
			'GLOBAL_ACTIVE' => 'Y',
			'ACTIVE_DATE' => 'Y',
			'<DEPTH_LEVEL' => \Bitrix\Main\Config\Option::get("aspro.allcorp3", "MAX_DEPTH_MENU", 2),
		);
		$arSectionSelect = array(
			'ID',
			'SORT',
			'ACTIVE',
			'IBLOCK_ID',
			'NAME',
			'SECTION_PAGE_URL',
			'DEPTH_LEVEL',
			'IBLOCK_SECTION_ID',
			'PICTURE',
			'UF_REGION',
			'UF_TOP_SEO',
			'UF_SECTION_ICON',
			'UF_ICON',
			'UF_TRANSPARENT_PICTURE',
		);

		if($bIsCatalog) {
			// $arSectionFilter = array_merge($arSectionFilter, array(  ));
			$arSectionSelect = array_merge($arSectionSelect, array( 'UF_MENU_BANNER', 'UF_MENU_BRANDS', 'UF_CATALOG_ICON', ));
		}

		if(array_key_exists('SECTION_FILTER', $arParams) && $arParams['SECTION_FILTER']) {
			$arSectionFilter = array_merge($arSectionFilter, $arParams['SECTION_FILTER']);
		}
		if(array_key_exists('SECTION_SELECT', $arParams) && $arParams['SECTION_SELECT']) {
			$arSectionSelect = array_merge($arSectionSelect, $arParams['SECTION_SELECT']);
		}

		if($arParams['MENU_PARAMS']['MENU_SHOW_SECTIONS'] == 'Y')
		{
			$arSections = CAllcorp3Cache::CIBlockSection_GetList(array('SORT' => 'ASC', 'NAME' => 'ASC', 'CACHE' => array('TAG' => CAllcorp3Cache::GetIBlockCacheTag($arParams['IBLOCK_ID']), 'MULTI' => 'Y')), $arSectionFilter, false, $arSectionSelect);
			$arSectionsByParentSectionID = CAllcorp3Cache::GroupArrayBy($arSections, array('MULTI' => 'Y', 'GROUP' => array('IBLOCK_SECTION_ID')));
		}

		if(!$bIsCatalog) {
			if($arParams['MENU_PARAMS']['MENU_SHOW_ELEMENTS'] == 'Y'){
				$arElementFilter = array(
					'IBLOCK_ID' => $arParams['IBLOCK_ID'],
					'ACTIVE' => 'Y',
					'SECTION_GLOBAL_ACTIVE' => 'Y',
					'ACTIVE_DATE' => 'Y',
					'INCLUDE_SUBSECTIONS' => 'Y',
				);
				$arElementSelect = array(
					'ID',
					'SORT',
					'ACTIVE',
					'IBLOCK_ID',
					'NAME',
					'DETAIL_PAGE_URL',
					'DEPTH_LEVEL',
					'IBLOCK_SECTION_ID',
					'PROPERTY_LINK_REGION',
				);

				if(array_key_exists('ELEMENT_FILTER', $arParams) && $arParams['ELEMENT_FILTER']) {
					$arSectionFilter = array_merge($arSectionFilter, $arParams['ELEMENT_FILTER']);
				}
				if(array_key_exists('ELEMENT_SELECT', $arParams) && $arParams['ELEMENT_SELECT']) {
					$arSectionSelect = array_merge($arSectionSelect, $arParams['ELEMENT_SELECT']);
				}

				$arItems = CAllcorp3Cache::CIBlockElement_GetList(array('SORT' => 'ASC', 'NAME' => 'ASC', 'CACHE' => array('TAG' => CAllcorp3Cache::GetIBlockCacheTag($arParams['IBLOCK_ID']), 'MULTI' => 'Y')), $arElementFilter, false, false, $arElementSelect);

				/*filter by region*/
				global $arRegion;
				if($arItems)
				{
					foreach($arItems as $key => $arItem)
					{
						$arTmpProp = array();
						$rsPropRegion = CIBlockElement::GetProperty($arItem['IBLOCK_ID'], $arItem['ID'], array('sort' => 'asc'), Array('CODE'=>'LINK_REGION'));
						while($arPropRegion = $rsPropRegion->Fetch())
						{
							if($arPropRegion['VALUE'])
								$arTmpProp[] = $arPropRegion['VALUE'];
						}
						$arItems[$key]['LINK_REGION'] = $arTmpProp;
					}
				}

				if($arParams['MENU_PARAMS']['MENU_SHOW_SECTIONS'] == 'Y'){
					$arItemsBySectionID = CAllcorp3Cache::GroupArrayBy($arItems, array('MULTI' => 'Y', 'GROUP' => array('IBLOCK_SECTION_ID')));
				}
				else{
					$arItemsRoot = CAllcorp3Cache::CIBlockElement_GetList(array('SORT' => 'ASC', 'NAME' => 'ASC', 'CACHE' => array('TAG' => CAllcorp3Cache::GetIBlockCacheTag($arParams['IBLOCK_ID']), 'MULTI' => 'Y')), array('IBLOCK_ID' => $arParams['IBLOCK_ID'], 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y', 'SECTION_ID' => 0));
					$arItems = array_merge((array)$arItems, (array)$arItemsRoot);
				}
			}
		}

		foreach(GetModuleEvents(ALLCORP3_MODULE_ID, 'OnAsproGetMenuChildsExt', true) as $arEvent) // event for manipulation store quantity block
			ExecuteModuleEventEx($arEvent, array($arParams, &$aMenuLinksExt, $bMenu));

		if($arSections) {
			CAllcorp3::getSectionChilds(false, $arSections, $arSectionsByParentSectionID, $arItemsBySectionID, $aMenuLinksExt, $bMenu);
		}

		if(!$bIsCatalog) {
			if($arItems && $arParams['MENU_PARAMS']['MENU_SHOW_SECTIONS'] != 'Y'){
				foreach($arItems as $arItem){
					$arExtParam = array('FROM_IBLOCK' => 1, 'DEPTH_LEVEL' => 1, 'SORT' => $arItem['SORT']);
					if(isset($arItem['LINK_REGION']))
						$arExtParam['LINK_REGION'] = $arItem['LINK_REGION'];
					$aMenuLinksExt[] = array($arItem['NAME'], $arItem['DETAIL_PAGE_URL'], array(), $arExtParam);
				}
			}
		}

		foreach(GetModuleEvents(ALLCORP3_MODULE_ID, 'AfterAsproGetMenuChildsExt', true) as $arEvent) // event for manipulation store quantity block
			ExecuteModuleEventEx($arEvent, array($arParams, &$aMenuLinksExt, $bMenu));
	}

	public static function replaceMenuChilds(&$arResult, $arParams)
	{
		global $arTheme;

		$replaceType = $arTheme['MEGA_MENU_TYPE']['DEPENDENT_PARAMS']['REPLACE_TYPE']['VALUE'];
		$arMegaLinks = $arMegaItems = array();

		$menuIblockId = CAllcorp3Cache::$arIBlocks[SITE_ID]['aspro_allcorp3_catalog']['aspro_allcorp3_megamenu'][0];
		if($menuIblockId){
			$arMenuSections = CAllcorp3Cache::CIblockSection_GetList(
				array(
					'SORT' => 'ASC',
					'NAME' => 'ASC',
					'CACHE' => array(
						'TAG' => CAllcorp3Cache::GetIBlockCacheTag($menuIblockId),
						'GROUP' => array('DEPTH_LEVEL'),
						'MULTI' => 'Y',
					)
				),
				array(
					'ACTIVE' => 'Y',
					'GLOBAL_ACTIVE' => 'Y',
					'IBLOCK_ID' => $menuIblockId,
					'<=DEPTH_LEVEL' => $arParams['MAX_LEVEL'],
				),
				false,
				array(
					'ID',
					'NAME',
					'IBLOCK_SECTION_ID',
					'DEPTH_LEVEL',
					'PICTURE',
					'UF_MENU_LINK',
					'UF_MEGA_MENU_LINK',
					'UF_CATALOG_ICON',
					'UF_TRANSPARENT_PICTURE',
				)
			);

			ksort($arMenuSections);

			if($arMenuSections){
				$cur_page = $GLOBALS['APPLICATION']->GetCurPage(true);
				$cur_page_no_index = $GLOBALS['APPLICATION']->GetCurPage(false);
				$some_selected = false;
				$bMultiSelect = $arParams['ALLOW_MULTI_SELECT'] === 'Y';

				foreach($arMenuSections as $depth => $arLinks){
					foreach($arLinks as $arLink){
						$url = trim($arLink['UF_MEGA_MENU_LINK']);
						$url = $url ? $url : trim($arLink['UF_MENU_LINK']);
						if(
							(
								$depth == 1 &&
								strlen($url)
							) ||
							$depth > 1
						){
							$arMegaItem = array(
								'TEXT' => htmlspecialcharsbx($arLink['NAME']),
								'NAME' => htmlspecialcharsbx($arLink['NAME']),
								'LINK' => strlen($url) ? $url : 'javascript:;',
								'SECTION_PAGE_URL' => strlen($url) ? $url : 'javascript:;',
								'SELECTED' => false,
								'PARAMS' => array(
									'SORT' => $arLink['SORT'],
									'PICTURE' => $arLink['PICTURE'],
									'ICON' => $arLink['UF_CATALOG_ICON'],
									'TRANSPARENT_PICTURE' => $arLink['UF_TRANSPARENT_PICTURE'],
								),
								'CHILD' => array(),
							);

							if( $arLink['PICTURE'] ) {
								$arMegaItem['IMAGES']['src'] = CFile::GetPath($arLink['PICTURE']);
							}

							$arMegaItems[$arLink['ID']] =& $arMegaItem;

							if($depth > 1){
								if(
									strlen($url) &&
									($bMultiSelect || !$some_selected)
								){
									$arMegaItem['SELECTED'] = CMenu::IsItemSelected($url, $cur_page, $cur_page_no_index);
								}

								if($arMegaItems[$arLink['IBLOCK_SECTION_ID']]){
									$arMegaItems[$arLink['IBLOCK_SECTION_ID']]['IS_PARENT'] = 1;
									$arMegaItems[$arLink['IBLOCK_SECTION_ID']]['CHILD'][] =& $arMegaItems[$arLink['ID']];
								}
							}
							else{
								$arMegaLinks[] =& $arMegaItems[$arLink['ID']];
							}

							unset($arMegaItem);
						}
					}
				}
			}
		}

		if($arMegaLinks){
			foreach($arResult as $key => $arItem){
				foreach($arMegaLinks as $arLink){
					if($arItem['LINK'] == $arLink['LINK']){
						if($replaceType == 'REPLACE') {
							if($arResult[$key]['PARAMS']['MEGA_MENU_CHILDS']){
								array_splice($arResult, $key, 1, $arLink['CHILD']);
							}
							else{
								$arResult[$key]['CHILD'] =& $arLink['CHILD'];
								$arResult[$key]['IS_PARENT'] = boolval($arLink['CHILD']);
							}
						} else {
							if($arResult[$key]['PARAMS']['MEGA_MENU_CHILDS']){
								$arLink['CHILD'] = self::CompareMenuItems($arResult[$key]['CHILD'], $arLink['CHILD']);
								array_splice($arResult, $key, 1, $arLink['CHILD']);
							}
							else{
								$arResult[$key]['CHILD'] = self::CompareMenuItems($arResult[$key]['CHILD'], $arLink['CHILD']);
								$arResult[$key]['IS_PARENT'] = boolval($arResult[$key]['CHILD']);
							}
						}
					}
				}
			}
		}
	}

	public static function CompareMenuItems($parentMenu, $childMenu)
	{
		$arMenuEnd = $childMenu;
		foreach($parentMenu as &$parentLink) {
			foreach($childMenu as $childKey => $childLink) {
				if($childLink['LINK'] == $parentLink['LINK']) {
					$parentLink['NAME'] = $parentLink['TEXT'] = $childLink['NAME'];

					if($childLink['PARAMS']['PICTURE'] && isset($parentLink['PARAMS']['PICTURE'])) {
						$parentLink['PARAMS']['PICTURE'] = $childLink['PARAMS']['PICTURE'];
					}

					if($childLink['PARAMS']['SORT'] && isset($parentLink['PARAMS']['SORT'])) {
						$parentLink['PARAMS']['SORT'] = $childLink['PARAMS']['SORT'];
					}

					if($childLink['CHILD']) {
						if($parentLink['CHILD']) {
							$parentLink['CHILD'] = self::CompareMenuItems($parentLink['CHILD'], $childLink['CHILD']);
						} else {
							$parentLink['CHILD'] = $childLink['CHILD'];
						}
					}
					unset($arMenuEnd[$childKey]);

					if($parentLink['CHILD'] && count($parentLink['CHILD']) > 1) {
						\Bitrix\Main\Type\Collection::sortByColumn(
							$parentLink['CHILD'],
							'PARAMS',
							function($params) {
								$result = isset($params['SORT']) ? $params['SORT'] : 500;
								return $result;
							}
						);
					}
				}
			}
		}

		if($arMenuEnd) {
			$parentMenu = array_merge($parentMenu, $arMenuEnd);
		}
		\Bitrix\Main\Type\Collection::sortByColumn(
			$parentMenu,
			'PARAMS',
			function($params) {
				$result = isset($params['SORT']) ? $params['SORT'] : 500;
				return $result;
			}
		);
		unset($parentLink);

		return $parentMenu;
	}

	public static function getChainNeighbors($curSectionID, $chainPath)
	{
		static $arSections, $arSectionsIDs, $arSubSections;
		$arResult = array();

		if($arSections === NULL){
			$arSections = $arSectionsIDs = $arSubSections = array();
			$IBLOCK_ID = false;
			$nav = CIBlockSection::GetNavChain(false, $curSectionID, array("ID", "IBLOCK_ID", "IBLOCK_SECTION_ID", "SECTION_PAGE_URL"));
			while($ar = $nav->GetNext()){
				$arSections[] = $ar;
				$arSectionsIDs[] = ($ar["IBLOCK_SECTION_ID"] ? $ar["IBLOCK_SECTION_ID"] : 0);
				$IBLOCK_ID = $ar["IBLOCK_ID"];
			}

			if($arSectionsIDs){
				$arSubSectionsFilter = array("ACTIVE" => "Y", "GLOBAL_ACTIVE" => "Y", "IBLOCK_ID" => $IBLOCK_ID, "SECTION_ID" => $arSectionsIDs);
				$resSubSection = CIBlockSection::GetList(array('SORT' => 'ASC'), self::makeSectionFilterInRegion($arSubSectionsFilter), false, array("ID", "NAME", "IBLOCK_SECTION_ID", "SECTION_PAGE_URL"));
				while($arSubSection = $resSubSection->GetNext()){
					$arSubSection["IBLOCK_SECTION_ID"] = ($arSubSection["IBLOCK_SECTION_ID"] ? $arSubSection["IBLOCK_SECTION_ID"] : 0);
					$arSubSections[$arSubSection["IBLOCK_SECTION_ID"]][] = $arSubSection;
				}

				if(in_array(0, $arSectionsIDs)){
					$arSubSectionsFilter = array("ACTIVE" => "Y", "GLOBAL_ACTIVE" => "Y", "IBLOCK_ID" => $IBLOCK_ID, "SECTION_ID" => false);
					$resSubSection = CIBlockSection::GetList(array('SORT' => 'ASC'), self::makeSectionFilterInRegion($arSubSectionsFilter), false, array("ID", "NAME", "IBLOCK_SECTION_ID", "SECTION_PAGE_URL"));
					while($arSubSection = $resSubSection->GetNext()){
						$arSubSections[$arSubSection["IBLOCK_SECTION_ID"]][] = $arSubSection;
					}
				}
			}
		}

		if($arSections && strlen($chainPath)){
			foreach($arSections as $arSection){
				if($arSection["SECTION_PAGE_URL"] == $chainPath){
					if($arSubSections[$arSection["IBLOCK_SECTION_ID"]]){
						foreach($arSubSections[$arSection["IBLOCK_SECTION_ID"]] as $arSubSection){
							if($curSectionID !== $arSubSection["ID"]){
								$arResult[] = array("NAME" => $arSubSection["NAME"], "LINK" => $arSubSection["SECTION_PAGE_URL"]);
							}
						}
					}
					break;
				}
			}
		}

		return $arResult;
	}

	public static function ShowPageType($type = 'indexblocks', $subtype = '', $template = '', $bRestart = '', $bLoadAjax = false)
	{
		global $APPLICATION, $arTheme;
		global $is404, $arSite, $isMenu, $isForm, $isBlog, $isCabinet, $isIndex, $bActiveTheme, $isCatalog;

		$path = $_SERVER['DOCUMENT_ROOT'].SITE_TEMPLATE_PATH.'/page_blocks/'.$type.'_';
		$file = null;
		$bShowBlock = $type === 'header_counter' ;

		if ((is_array($arTheme) && $arTheme) || $bShowBlock) {
			switch($type):
				case 'mainpage':
					if($bRestart && $subtype)
					{
						static::checkRestartBuffer(true, $subtype);
						$GLOBALS["NavNum"]=0;
					}

					$path = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].'/'.SITE_DIR.'include/'.$type.'/components/'.$subtype.'/');
					$file = $path.$template.'.php';
					break;
				case 'search_title_component':
					$path = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].'/'.SITE_DIR.'include/footer/');
					$file = $path.'search.title.php';
					break;
				case 'basket_component':
					$path = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].'/'.SITE_DIR.'include/footer/');
					$file = $path.'basket.php';
					break;
				case 'auth_component':
					$path = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].'/'.SITE_DIR.'include/footer/');
					$file = $path.'auth.php';
					break;
				case 'header_counter':
					$bPageSpeedTest = self::isPageSpeedTest();
					if(!$bPageSpeedTest){
						$path = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].'/'.SITE_DIR.'include/header/');
						$file = $path.'body_above_counter.php';
					}
					break;
				case 'bottom_counter':
					$bPageSpeedTest = self::isPageSpeedTest();
					if(!$bPageSpeedTest){
						$path = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].'/'.SITE_DIR.'include/');
						$file = $path.'invis-counter.php';
					}
					break;
				case 'page_width':
					$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/width-'.$arTheme['PAGE_WIDTH']['VALUE'].'.css');
					break;
				case 'left_block':
					$file = $path.$arTheme['LEFT_BLOCK']['VALUE'].'.php';
					break;
				case 'h1_style':
					if($arTheme['H1_STYLE']['VALUE']=='Normal')
						$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/h1-normal.css');
					else
						$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/h1-bold.css');
					break;
				case 'footer':
					$file = $path.(isset($arTheme['FOOTER_TYPE']['VALUE']) && $arTheme['FOOTER_TYPE']['VALUE'] ? $arTheme['FOOTER_TYPE']['VALUE'] : $arTheme['FOOTER_TYPE']).'.php';
					if (defined('ERROR_404')) {
						$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/error_404.css');
					}
					break;
				case 'mega_menu':
					$file = $path.'1'.'.php';
					break;
				case 'header':
					$file = $path.$arTheme['HEADER_TYPE']['VALUE'].'.php';
					break;
				case 'header_fixed':
					$file = $path.$arTheme['TOP_MENU_FIXED']['DEPENDENT_PARAMS']['HEADER_FIXED']['VALUE'].'.php';
					break;
				case 'header_mobile':
					$file = $path.$arTheme['HEADER_MOBILE']['VALUE'].'.php';
					break;
				case 'header_mobile_menu':
					$file = $path.$arTheme['HEADER_MOBILE_MENU']['VALUE'].'.php';
					break;
				case 'page_title':
					$file = $path.$arTheme['PAGE_TITLE']['VALUE'].'.php';
					break;
				case 'eyed_component':
					$path = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].'/'.SITE_DIR.'include/header/');
					$file = $path.'eyed.php';
					break;
				default:
					global $arMainPageOrder;
					if(isset($arTheme['INDEX_TYPE']['SUB_PARAMS'][$arTheme['INDEX_TYPE']['VALUE']]))
					{
						$order = $arTheme["SORT_ORDER_INDEX_TYPE_".$arTheme["INDEX_TYPE"]["VALUE"]];
						if ($order) {
							$arMainPageOrder = explode(",", $order);
							if (
								$arDiff = array_diff(
									array_keys($arTheme['INDEX_TYPE']['SUB_PARAMS'][$arTheme['INDEX_TYPE']['VALUE']]),
									$arMainPageOrder
								)
							) {
								$arMainPageOrder += $arDiff;
							}
						} else {
							$arMainPageOrder = array_keys($arTheme['INDEX_TYPE']['SUB_PARAMS'][$arTheme['INDEX_TYPE']['VALUE']]);
						}
					}

					foreach(GetModuleEvents(ALLCORP3_MODULE_ID, 'OnAsproShowPageType', true) as $arEvent) // event for manipulation arMainPageOrder
						ExecuteModuleEventEx($arEvent, array($arTheme, &$arMainPageOrder));

					$path = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].'/'.SITE_DIR.$type);
					$file = $path.'_'.$arTheme['INDEX_TYPE']['VALUE'].'.php';
					break;
			endswitch;

			if($type === 'footer'){
				@include_once(str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].'/'.SITE_DIR.'include/footer/above_footer.php'));
			}

			if($file){
				@include_once $file;
			}

			if($type === 'footer'){
				@include_once(str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].'/'.SITE_DIR.'include/footer/under_footer.php'));
				$checkProperty = \Aspro\Functions\CAsproAllcorp3::checkProperty($APPLICATION->GetProperty('NAME_BUTTON_DOWNLOAD'), $APPLICATION->GetProperty('URL_BUTTON_DOWNLOAD'));
				if($checkProperty){
					$GLOBALS['APPLICATION']->AddViewContent('cowl_buttons', $checkProperty);
				}
			}

			if($type === 'header'){
				@include_once(str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].'/'.SITE_DIR.'include/header/under_header.php'));
			}

			if ($bRestart && $subtype) {
				static::checkRestartBuffer(true, $subtype);
			}
		}
	}

	public static function ShowLogo($arParams = array()){
		global $arSite;
		$arTheme = self::GetFrontParametrsValues(SITE_ID);

		$arParams = is_array($arParams) ? $arParams : array();
		$isWhite = array_key_exists('IS_WHITE', $arParams) && $arParams['IS_WHITE'];

		$text = '<a class="menu-light-icon-fill banner-light-icon-fill" href="'.SITE_DIR.'">';
		$logoOptionCode = 'LOGO_IMAGE'.($isWhite ? '_WHITE' : '');

		$arImg = unserialize(Option::get(ALLCORP3_MODULE_ID, $logoOptionCode, serialize(array()))) ?: ($isWhite ? unserialize(Option::get(ALLCORP3_MODULE_ID, 'LOGO_IMAGE', serialize(array()))) : array());

		if($arImg)
			$text .= '<img src="'.CFile::GetPath($arImg[0]).'" alt="'.$arSite["SITE_NAME"].'" title="'.$arSite["SITE_NAME"].'" data-src="" />';
		elseif(self::checkContentFile(SITE_DIR.'/include/logo_svg.php')){
			$text .= File::getFileContents($_SERVER['DOCUMENT_ROOT'].SITE_DIR.'/include/logo_svg.php');
			preg_match_all("(<.*class=[\"\']fill-ignore[\"\'].*>)",
			$text,
			$arMatch);
			foreach ($arMatch[0] as $match){
				if(strpos($match, 'fill=') !== false){
					$replace = str_replace('fill=', 'color=', $match);
					$text = str_replace($match, $replace, $text);
				}
			}
		}
		else
			$text .= '<img src="'.$arTheme[$logoOptionCode].'" alt="'.$arSite["SITE_NAME"].'" title="'.$arSite["SITE_NAME"].'" data-src="" />';
		$text .= '</a>';

		return $text;
	}

	public static function getLogoStub() {
		static $result;
		if (!isset($result)) {
			$result = self::ShowLogo();
		}
		return $result;
	}
	
	public static function ShowBufferedLogo() {
		static $iCalledID;
		++$iCalledID;
		$bComposite = self::IsCompositeEnabled();
		// if composite is enabled than show default dark logo in stub
		if ($bComposite) {
			$id = 'header-buffered-logo'.$iCalledID;
			Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID($id);
		}
		$GLOBALS['APPLICATION']->AddBufferContent([__CLASS__, 'GetBufferedLogo']);
		if ($bComposite) {
			Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID($id, self::getLogoStub());
		}
	}
	
	public static function GetBufferedLogo() {
		$logoColor = $GLOBALS['APPLICATION']->GetPageProperty('HEADER_LOGO') ?: 'dark';
		return self::ShowLogo(['IS_WHITE' => $logoColor === 'light']);
	}
	
	public static function ShowBufferedFixedLogo() {
		static $iCalledID;
		++$iCalledID;
		
		$bComposite = self::IsCompositeEnabled();
		
		// if composite is enabled than show default dark logo in stub
		if ($bComposite) {
			$id = 'header-buffered-fixed-logo'.$iCalledID;
			Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID($id);
		}
		$GLOBALS['APPLICATION']->AddBufferContent([__CLASS__, 'GetBufferedFixedLogo']);
		if ($bComposite) {
			Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID($id, self::getLogoStub());
		}
	}
	
	public static function GetBufferedFixedLogo() {
		$logoColor = $GLOBALS['APPLICATION']->GetPageProperty('HEADER_FIXED_LOGO') ?: 'dark';
		return self::ShowLogo(['IS_WHITE' => $logoColor === 'light']);
	}
	
	public static function ShowBufferedMobileLogo() {
		static $iCalledID;
		++$iCalledID;
		$bComposite = self::IsCompositeEnabled();
		// if composite is enabled than show default dark logo in stub
		if ($bComposite) {
			$id = 'header-buffered-mobile-logo'.$iCalledID;
			Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID($id);
		}
		$GLOBALS['APPLICATION']->AddBufferContent([__CLASS__, 'GetBufferedMobileLogo']);
		if ($bComposite) {
			Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID($id, self::getLogoStub());
		}
	}
	
	public static function GetBufferedMobileLogo() {
		$logoColor = $GLOBALS['APPLICATION']->GetPageProperty('HEADER_MOBILE_LOGO') ?: 'dark';
		return self::ShowLogo(['IS_WHITE' => $logoColor === 'light']);
	}

	public static function setLogoColor($arOptions = [], $siteID = SITE_ID) {
		global $APPLICATION;
		$arTheme = self::GetFrontParametrsValues($siteID);

		$arDefaultOptions = [
			'LOGO_POSITION' => $arTheme['HEADER_LOGO_POSITION_'.$arTheme['HEADER_TYPE']],
			'HEADER_MARGIN' => $arTheme['HEADER_MARGIN_'.$arTheme['HEADER_TYPE']],
			
			'PREFER_COLOR' => '',
		];
		$arConfig = array_merge($arDefaultOptions, $arOptions);
		
		$arConfig['MENU_COLOR'] = isset($arConfig['LOGO_POSITION']) && $arConfig['LOGO_POSITION'] === 'TOP' 
			? $arTheme['TOP_MENU_COLOR']
			: $arTheme['MENU_COLOR'];
		

		if ($handler = \Aspro\Functions\CAsproAllcorp3::getCustomFunc(__FUNCTION__)) {
			return call_user_func_array($handler, [$arConfig]);
		}

		if ($arConfig['LOGO_POSITION'] === 'LEFT') {
			return;
		}

		$arDarkColor = ['DARK', 'COLORED'];
		$transparentColor = 'LIGHT BG_NONE';

		$isLogoOnTop = $arConfig['LOGO_POSITION'] === 'TOP';
		$menuLogoPermanentLight = in_array($arConfig['MENU_COLOR'], $arDarkColor);

		$isLogoRowTransparent = (
			$arConfig['MENU_COLOR'] === $transparentColor
			|| ($isLogoOnTop && $arConfig['HEADER_MARGIN'] === 'Y')
			|| (!$isLogoOnTop && $arConfig['HEADER_MARGIN'] !== 'Y')
		);
		
		if ($isLogoRowTransparent) {
			if ($arConfig['PREFER_COLOR']) {
				$APPLICATION->SetPageProperty('HEADER_LOGO', $arConfig['PREFER_COLOR']);
			}
		} elseif ($menuLogoPermanentLight) {
			$APPLICATION->SetPageProperty('HEADER_LOGO', 'light');
		}
	}

	public static function setMobileLogoColor($arOptions = [], $siteID = SITE_ID) {
		global $APPLICATION;

		$arTheme = self::GetFrontParametrsValues($siteID);
		$arDefaultOptions = [
			'HEADER_MOBILE_COLOR' => $arTheme['HEADER_MOBILE_COLOR_'.$arTheme['HEADER_MOBILE']],
		];
		$arConfig = array_merge($arDefaultOptions, $arOptions);

		if ($handler = \Aspro\Functions\CAsproAllcorp3::getCustomFunc(__FUNCTION__)) {
			return call_user_func_array($handler, [$arConfig]);
		}

		$arDarkColor = ['DARK', 'COLORED'];
		if (in_array($arConfig['HEADER_MOBILE_COLOR'], $arDarkColor)) {
			$APPLICATION->SetPageProperty('HEADER_MOBILE_LOGO', 'light');
		}
	}

	public static function showIconSvg($class = 'phone', $path, $title = '', $class_icon = '', $show_wrapper = true){
		$text ='';
		if(self::checkContentFile($path))
		{
			static $svg_call;
			$iSvgID = ++$svg_call;
			if($show_wrapper)
				$text = '<i class="svg inline '.$class_icon.' svg-inline-'.$class.'" aria-hidden="true" '.($title ? 'title="'.$title.'"' : '').'>';

				$text .= str_replace('markID', $iSvgID, File::getFileContents($_SERVER['DOCUMENT_ROOT'].$path));

			if($show_wrapper)
				$text .= '</i>';
		}

		return $text;
	}

	public static function showSpriteIconSvg($path, $class = '', $arOptions = []){
		$width = ($arOptions['WIDTH'] ? 'width="'.$arOptions['WIDTH'].'"' : '');
		$height = ($arOptions['HEIGHT'] ? 'height="'.$arOptions['HEIGHT'].'"' : '');
		return '<i class="svg inline '.$class.'" aria-hidden="true"><svg '.$width.' '.$height.'><use xlink:href="'.$path.'"></use></svg></i>';
	}

	public static function GetBackParametrsValues($SITE_ID, $SITE_DIR = '', $bFromStatic = true){
		static $arCacheValues, $arWebForms;

		$SITE_ID = strlen($SITE_ID) ? $SITE_ID : (defined('SITE_ID') ? SITE_ID : '');
		$SITE_DIR = strlen($SITE_DIR) ? $SITE_DIR : (defined('SITE_DIR') ? SITE_DIR : '');

		if(!isset($arCacheValues)){
			$arCacheValues = $arWebForms = array();
		}

		if(!isset($arCacheValues[$SITE_ID])){
			$arCacheValues[$SITE_ID] = $arWebForms[$SITE_ID] = array();
		}

		if($bFromStatic){
			$arValues =& $arCacheValues[$SITE_ID];
		}
		else{
			$arValues = array();
		}

		if(!$arValues){
			$arDefaultValues = $arNestedValues = array();
			$bNestedParams = false;

			// get site template
			$arTemplate = self::GetSiteTemplate($SITE_ID);

			// add custom values for INDEX_PAGE
			if(isset(self::$arParametrsList['INDEX_PAGE']['OPTIONS']['INDEX_TYPE']['LIST']))
			{
				// get site dir
				$arSite = CSite::GetByID($SITE_ID)->Fetch();
				$siteDir = str_replace('//', '/', $arSite['DIR']).'/';
				if($arPageBlocks = self::GetIndexPageBlocks($_SERVER['DOCUMENT_ROOT'].$siteDir, 'indexblocks_', ''))
				{
					foreach($arPageBlocks as $page => $value)
					{
						$value_ = str_replace('indexblocks_', '', $value);
						if(!isset(self::$arParametrsList['INDEX_PAGE']['OPTIONS']['INDEX_TYPE']['LIST'][$value_]))
						{
							self::$arParametrsList['INDEX_PAGE']['OPTIONS']['INDEX_TYPE']['LIST'][$value_] = array(
								'TITLE' => $value,
								'HIDE' => 'Y',
								'IS_CUSTOM' => 'Y',
							);
						}
					}
					if(!self::$arParametrsList['INDEX_PAGE']['OPTIONS']['INDEX_TYPE']['DEFAULT'])
					{
						self::$arParametrsList['INDEX_PAGE']['OPTIONS']['INDEX_TYPE']['DEFAULT'] = key(self::$arParametrsList['INDEX_PAGE']['OPTIONS']['INDEX_TYPE']['LIST']);
					}
				}
			}

			// add form values for web_forms section
			if(isset(self::$arParametrsList['WEB_FORMS']['OPTIONS']) && defined('ADMIN_SECTION')){
				if(!$arWebForms[$SITE_ID]){
					if($arWebForms[$SITE_ID] = \Aspro\Allcorp3\Property\ListWebForms::getWebForms(array($SITE_ID))){
						if(isset(self::$arParametrsList['WEB_FORMS']['OPTIONS']['EXPRESS_BUTTON_FORM'])){
							// add form`s list
							self::$arParametrsList['WEB_FORMS']['OPTIONS']['EXPRESS_BUTTON_FORM']['LIST'] = array_merge($arWebForms[$SITE_ID]['MERGE'], $arWebForms[$SITE_ID]['FORM'], $arWebForms[$SITE_ID]['IBLOCK']);

							foreach($arWebForms[$SITE_ID] as $type => $arWebFormsOfType){
								if($arWebFormsOfType){
									self::$arParametrsList['WEB_FORMS']['OPTIONS']['EXPRESS_BUTTON_FORM']['GROUPPED_LIST'][] = array(
										'TITLE' => Loc::getMessage('EXPRESS_BUTTON_FORM_'.$type),
										'LIST' => $arWebFormsOfType,
									);
								}
							}
						}

						// add form`s options
						$arFormsOptions = array();
						foreach($arWebForms[$SITE_ID] as $type => $arWebFormsOfType){
							foreach($arWebFormsOfType as $code => $name){
								$file_name = $code.'_FORM';
								$code = strtoupper($code).'_FORM';
								$arFormsOptions[$code] = array(
									'TITLE' => $name,
									'TYPE' => 'selectbox',
									'LIST' => array(
										'COMPLEX' => GetMessage('USE_IBLOCK_BITRIX_FORM'),
										'CRM' => GetMessage('USE_CRM_FORM'),
									),
									'GROUP_BLOCK' => 'FORMS_OPTIONS_GROUP',
									'DEFAULT' => 'COMPLEX',
									'DEPENDENT_PARAMS' => array(
										'CRM_SCRIPT' => array(
											'TITLE' => GetMessage('CRM_SCRIPT_TITLE'),
											'TO_TOP' => 'Y',
											'TYPE' => 'includefile',
											'NO_EDITOR' => 'Y',
											'INCLUDEFILE' => '#SITE_DIR#include/form/b24/'.$file_name.'.php',
											'CONDITIONAL_VALUE' => 'CRM',
											'THEME' => 'N',
										),
										'BGFILE'.$code => array(
											'TITLE' => GetMessage('T_FORM_BGFILE'),
											'TO_TOP' => 'Y',
											'TYPE' => 'file',
											'CONDITIONAL_VALUE' => 'COMPLEX',
											'DEFAULT' => serialize(array()),
											'THEME' => 'N',
										),
										'BGFILE'.$code.'_ACTIVE' => array(
											'TITLE' => GetMessage('T_FORM_BGFILE_ACTIVE'),
											'TO_TOP' => 'Y',
											'TYPE' => 'file',
											'CONDITIONAL_VALUE' => 'COMPLEX',
											'DEFAULT' => serialize(array()),
											'THEME' => 'N',
										),
									),
								);
							}
						}

						self::$arParametrsList['WEB_FORMS']['OPTIONS'] += $arFormsOptions;
					}
				}
			}

			if($arTemplate && $arTemplate['PATH']){
				// add custom values for PAGE_TILE
				if(isset(self::$arParametrsList['MAIN']['OPTIONS']['PAGE_TITLE']))
					self::Add2OptionCustomPageBlocks(self::$arParametrsList['MAIN']['OPTIONS']['PAGE_TITLE'], $arTemplate['PATH'].'/page_blocks/', 'page_title_');

				// add custom values for LEFT_BLOCK
				if(isset(self::$arParametrsList['MAIN']['OPTIONS']['LEFT_BLOCK']))
					self::Add2OptionCustomPageBlocks(self::$arParametrsList['MAIN']['OPTIONS']['LEFT_BLOCK'], $arTemplate['PATH'].'/page_blocks/', 'left_block_');

				// add custom values for TOP_MENU_FIXED
				if(isset(self::$arParametrsList['HEADER']['OPTIONS']['TOP_MENU_FIXED']['DEPENDENT_PARAMS']['HEADER_FIXED']))
					self::Add2OptionCustomPageBlocks(self::$arParametrsList['HEADER']['OPTIONS']['TOP_MENU_FIXED']['DEPENDENT_PARAMS']['HEADER_FIXED'], $arTemplate['PATH'].'/page_blocks/', 'header_fixed_');

				// add custom values for HEADER_TYPE
				if(isset(self::$arParametrsList['HEADER']['OPTIONS']['HEADER_TYPE']))
					self::Add2OptionCustomPageBlocks(self::$arParametrsList['HEADER']['OPTIONS']['HEADER_TYPE'], $arTemplate['PATH'].'/page_blocks/', 'header_custom', 'custom');

				// add custom values for FOOTER_TYPE
				if(isset(self::$arParametrsList['FOOTER']['OPTIONS']['FOOTER_TYPE']))
					self::Add2OptionCustomPageBlocks(self::$arParametrsList['FOOTER']['OPTIONS']['FOOTER_TYPE'], $arTemplate['PATH'].'/page_blocks/', 'footer_custom', 'custom');

				// add custom values for HEADER_MOBILE
				if(isset(self::$arParametrsList['MOBILE']['OPTIONS']['HEADER_MOBILE']))
					self::Add2OptionCustomPageBlocks(self::$arParametrsList['MOBILE']['OPTIONS']['HEADER_MOBILE'], $arTemplate['PATH'].'/page_blocks/', 'header_mobile_custom', 'custom');

				// add custom values for HEADER_MOBILE_MENU
				if(isset(self::$arParametrsList['MOBILE']['OPTIONS']['HEADER_MOBILE_MENU']))
					self::Add2OptionCustomPageBlocks(self::$arParametrsList['MOBILE']['OPTIONS']['HEADER_MOBILE_MENU'], $arTemplate['PATH'].'/page_blocks/', 'header_mobile_menu_');

				// add custom values for BLOG_PAGE
				if(isset(self::$arParametrsList['SECTION']['OPTIONS']['BLOG_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocks(self::$arParametrsList['SECTION']['OPTIONS']['BLOG_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/blog');
				}

				// add custom values for PROJECTS_PAGE
				if(isset(self::$arParametrsList['PROJECT_PAGE']['OPTIONS']['PROJECTS_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocks(self::$arParametrsList['PROJECT_PAGE']['OPTIONS']['PROJECTS_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/projects');
				}
				// add custom values for PROJECTS_PAGE_DETAIL
				if(isset(self::$arParametrsList['PROJECT_PAGE']['OPTIONS']['PROJECTS_PAGE_DETAIL'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['PROJECT_PAGE']['OPTIONS']['PROJECTS_PAGE_DETAIL'], $arTemplate['PATH'].'/components/bitrix/news/projects');
				}

				// add custom values for NEWS_PAGE
				if(isset(self::$arParametrsList['SECTION']['OPTIONS']['NEWS_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocks(self::$arParametrsList['SECTION']['OPTIONS']['NEWS_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/news');
				}

				// add custom values for SALE_PAGE
				if(isset(self::$arParametrsList['SECTION']['OPTIONS']['SALE_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocks(self::$arParametrsList['SECTION']['OPTIONS']['SALE_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/sale');
				}

				// add custom values for STAFF_PAGE
				if(isset(self::$arParametrsList['SECTION']['OPTIONS']['STAFF_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocks(self::$arParametrsList['SECTION']['OPTIONS']['STAFF_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/staff');
				}

				// add custom values for PARTNERS_PAGE
				if(isset(self::$arParametrsList['SECTION']['OPTIONS']['PARTNERS_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocks(self::$arParametrsList['SECTION']['OPTIONS']['PARTNERS_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/partners');
				}

				// add custom values for VACANCY_PAGE
				if(isset(self::$arParametrsList['SECTION']['OPTIONS']['VACANCY_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocks(self::$arParametrsList['SECTION']['OPTIONS']['VACANCY_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/vacancy');
				}

				// add custom values for LICENSES_PAGE
				if(isset(self::$arParametrsList['SECTION']['OPTIONS']['LICENSES_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocks(self::$arParametrsList['SECTION']['OPTIONS']['LICENSES_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/licenses');
				}

				// add custom values for DOCUMENTS_PAGE
				if(isset(self::$arParametrsList['SECTION']['OPTIONS']['DOCUMENTS_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocks(self::$arParametrsList['SECTION']['OPTIONS']['DOCUMENTS_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/docs');
				}

				// add custom values for GALLERY_PAGE
				if(isset(self::$arParametrsList['GALLERY_PAGE']['OPTIONS']['GALLERY_LIST_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocks(self::$arParametrsList['GALLERY_PAGE']['OPTIONS']['GALLERY_LIST_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/gallery');
				}

				// add custom values for GALLERY_PAGE detail
				if(isset(self::$arParametrsList['GALLERY_PAGE']['OPTIONS']['GALLERY_DETAIL_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['GALLERY_PAGE']['OPTIONS']['GALLERY_DETAIL_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/gallery');
				}

				// add custom values for LANDING_PAGE
				if(isset(self::$arParametrsList['SECTION']['OPTIONS']['LANDINGS_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocks(self::$arParametrsList['SECTION']['OPTIONS']['LANDINGS_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/landings');
				}

				// add custom values for BRAND_PAGE
				if(isset(self::$arParametrsList['SECTION']['OPTIONS']['BRANDS_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocks(self::$arParametrsList['SECTION']['OPTIONS']['BRANDS_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/brands');
				}

				// add custom values for CATALOG_PAGE_DETAIL
				if(isset(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['CATALOG_PAGE_DETAIL'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['CATALOG_PAGE_DETAIL'], $arTemplate['PATH'].'/components/bitrix/catalog/main');
				}

				// add custom values for SECTIONS_TYPE_VIEW_CATALOG
				if(isset(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['SECTIONS_TYPE_VIEW_CATALOG'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['SECTIONS_TYPE_VIEW_CATALOG'], $arTemplate['PATH'].'/components/bitrix/catalog/main', 'SECTIONS');
				}

				// add custom values for SECTION_TYPE_VIEW_CATALOG
				if(isset(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['SECTION_TYPE_VIEW_CATALOG'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['SECTION_TYPE_VIEW_CATALOG'], $arTemplate['PATH'].'/components/bitrix/catalog/main', 'SUBSECTIONS');
				}

				// add custom values for ELEMENTS_CATALOG_PAGE
				if(isset(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['ELEMENTS_CATALOG_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['ELEMENTS_CATALOG_PAGE'], $arTemplate['PATH'].'/components/bitrix/catalog/main', 'ELEMENTS');
				}

				// add custom values for ELEMENTS_TABLE_TYPE_VIEW
				if(isset(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['ELEMENTS_TABLE_TYPE_VIEW'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['ELEMENTS_TABLE_TYPE_VIEW'], $arTemplate['PATH'].'/components/bitrix/catalog/main', 'ELEMENTS_TABLE');
				}

				// add custom values for ELEMENTS_LIST_TYPE_VIEW
				if(isset(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['ELEMENTS_LIST_TYPE_VIEW'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['ELEMENTS_LIST_TYPE_VIEW'], $arTemplate['PATH'].'/components/bitrix/catalog/main', 'ELEMENTS_LIST');
				}

				// add custom values for ELEMENTS_PRICE_TYPE_VIEW
				if(isset(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['ELEMENTS_PRICE_TYPE_VIEW'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['CATALOG_PAGE']['OPTIONS']['ELEMENTS_PRICE_TYPE_VIEW'], $arTemplate['PATH'].'/components/bitrix/catalog/main', 'ELEMENTS_PRICE');
				}

				// add custom values for SECTIONS_TYPE_VIEW
				if(isset(self::$arParametrsList['SERVICES_PAGE']['OPTIONS']['SECTIONS_TYPE_VIEW_SERVICES'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['SERVICES_PAGE']['OPTIONS']['SECTIONS_TYPE_VIEW_SERVICES'], $arTemplate['PATH'].'/components/bitrix/news/services', 'SECTIONS');
				}
				// add custom values for SECTION_TYPE_VIEW
				if(isset(self::$arParametrsList['SERVICES_PAGE']['OPTIONS']['SECTION_TYPE_VIEW_SERVICES'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['SERVICES_PAGE']['OPTIONS']['SECTION_TYPE_VIEW_SERVICES'], $arTemplate['PATH'].'/components/bitrix/news/services', 'SUBSECTIONS');
				}
				// add custom values for ELEMENTS_PAGE
				if(isset(self::$arParametrsList['SERVICES_PAGE']['OPTIONS']['ELEMENTS_PAGE_SERVICES'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['SERVICES_PAGE']['OPTIONS']['ELEMENTS_PAGE_SERVICES'], $arTemplate['PATH'].'/components/bitrix/news/services', 'ELEMENTS');
				}
				// add custom values for ELEMENT_PAGE_DETAIL
				if(isset(self::$arParametrsList['SERVICES_PAGE']['OPTIONS']['SERVICES_PAGE_DETAIL'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['SERVICES_PAGE']['OPTIONS']['SERVICES_PAGE_DETAIL'], $arTemplate['PATH'].'/components/bitrix/news/services');
				}

				// add custom values for ELEMENTS_PROJECT_PAGE
				if(isset(self::$arParametrsList['PROJECTS']['OPTIONS']['ELEMENTS_PROJECT_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['PROJECTS']['OPTIONS']['ELEMENTS_PROJECT_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/projects', 'ELEMENTS');
				}

				// add custom values for ELEMENT_PAGE_DETAIL project
				if(isset(self::$arParametrsList['PROJECTS']['OPTIONS']['ELEMENT_PROJECT_PAGE_DETAIL'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['PROJECTS']['OPTIONS']['ELEMENT_PROJECT_PAGE_DETAIL'], $arTemplate['PATH'].'/components/bitrix/news/projects');
				}

				// add custom values for ELEMENT_PAGE_DETAIL brand
				if(isset(self::$arParametrsList['SECTION']['OPTIONS']['BRANDS_DETAIL_PAGE'])){
					self::Add2OptionCustomComponentTemplatePageBlocksElement(self::$arParametrsList['SECTION']['OPTIONS']['BRANDS_DETAIL_PAGE'], $arTemplate['PATH'].'/components/bitrix/news/brands');
				}
			}

			if(self::$arParametrsList && is_array(self::$arParametrsList))
			{
				foreach(self::$arParametrsList as $blockCode => $arBlock)
				{
					if($arBlock['OPTIONS'] && is_array($arBlock['OPTIONS']))
					{
						foreach($arBlock['OPTIONS'] as $optionCode => $arOption)
						{
							if($arOption['TYPE'] !== 'note' && $arOption['TYPE'] !== 'includefile'){
								if($arOption['TYPE'] === 'array'){
									$itemsKeysCount = Option::get(self::moduleID, $optionCode, '0', $SITE_ID);
									if($arOption['OPTIONS'] && is_array($arOption['OPTIONS'])){
										for($itemKey = 0, $cnt = $itemsKeysCount + 1; $itemKey < $cnt; ++$itemKey){
											$_arParameters = array();
											$arOptionsKeys = array_keys($arOption['OPTIONS']);
											foreach($arOptionsKeys as $_optionKey){
												$arrayOptionItemCode = $optionCode.'_array_'.$_optionKey.'_'.$itemKey;
												$arValues[$arrayOptionItemCode] = Option::get(self::moduleID, $arrayOptionItemCode, '', $SITE_ID);
												$arDefaultValues[$arrayOptionItemCode] = $arOption['OPTIONS'][$_optionKey]['DEFAULT'];
											}
										}
									}
									$arValues[$optionCode] = $itemsKeysCount;
									$arDefaultValues[$optionCode] = 0;
								}
								else{
									$arDefaultValues[$optionCode] = $arOption['DEFAULT'];
									$arValues[$optionCode] = Option::get(self::moduleID, $optionCode, $arOption['DEFAULT'], $SITE_ID);

									\Aspro\Functions\CAsproAllcorp3Admin::getBackParams($arValues, $arDefaultValues, $arOption, $SITE_ID);

									if(isset($arOption['SUB_PARAMS']) && $arOption['SUB_PARAMS']) //get nested params default value
									{
										if($arOption['TYPE'] == 'selectbox' && (isset($arOption['LIST'])) && $arOption['LIST'])
										{
											$bNestedParams = true;
											$arNestedValues[$optionCode] = $arOption['LIST'];
											foreach($arOption['LIST'] as $key => $value)
											{
												if($arOption['SUB_PARAMS'][$key])
												{
													foreach($arOption['SUB_PARAMS'][$key] as $key2 => $arSubOptions)
													{
														//set special options for index components
														if(isset($arSubOptions['INDEX_BLOCK_OPTIONS']))
														{
															if(isset($arSubOptions['INDEX_BLOCK_OPTIONS']['TOP']) && $arSubOptions['INDEX_BLOCK_OPTIONS']['TOP']) {
																foreach($arSubOptions['INDEX_BLOCK_OPTIONS']['TOP'] as $topOptionKey => $topOption) {
																	$code_tmp = $topOptionKey.'_'.$key2.'_'.$key;
																	$arDefaultValues[$code_tmp] = $topOption;
																	$arValues[$code_tmp] = Option::get(self::moduleID, $code_tmp, $topOption, $SITE_ID);
																}
															}
															if(isset($arSubOptions['INDEX_BLOCK_OPTIONS']['BOTTOM']) && $arSubOptions['INDEX_BLOCK_OPTIONS']['BOTTOM']) {
																foreach($arSubOptions['INDEX_BLOCK_OPTIONS']['BOTTOM'] as $bottomOptionKey => $bottomOption) {
																	$code_tmp = $bottomOptionKey.'_'.$key2.'_'.$key;
																	$arDefaultValues[$code_tmp] = $bottomOption['DEFAULT'];
																	$arValues[$code_tmp] = Option::get(self::moduleID, $code_tmp, $bottomOption['DEFAULT'], $SITE_ID);
																}
															}
														}
														$arDefaultValues[$key.'_'.$key2] = $arSubOptions['DEFAULT'];

														//set default template index components
														if(isset($arSubOptions['TEMPLATE']) && $arSubOptions['TEMPLATE'])
														{
															$code_tmp = $key.'_'.$key2.'_TEMPLATE';
															$arDefaultValues[$code_tmp] = $arSubOptions['TEMPLATE']['DEFAULT'];
															$arValues[$code_tmp] = Option::get(self::moduleID, $code_tmp, $arSubOptions['TEMPLATE']['DEFAULT'], $SITE_ID);

															if( isset($arSubOptions['TEMPLATE']['LIST']) ) {
																foreach($arSubOptions['TEMPLATE']['LIST'] as $templateKey => $template) {
																	if($template['ADDITIONAL_OPTIONS'])
																	{
																		foreach($template['ADDITIONAL_OPTIONS'] as $additionalOptionKey => $additionalOption) {
																			$code_tmp = $key.'_'.$key2.'_'.$additionalOptionKey.'_'.$templateKey;

																			$arDefaultValues[$code_tmp] = $additionalOption['DEFAULT'];
																			$arValues[$code_tmp] = Option::get(self::moduleID, $code_tmp, $additionalOption['DEFAULT'], $SITE_ID);
																		}
																	}
																}
															}
														}
													}

													//sort order prop for main page
													$param = 'SORT_ORDER_'.$optionCode.'_'.$key;
													$arDefaultValues[$param] = implode(',', array_keys($arOption['SUB_PARAMS'][$key]));
													$arValues[$param] = Option::get(self::moduleID, $param, $arDefaultValues[$param], $SITE_ID);
													if(!$arValues[$param]){
														$arValues[$param] = $arDefaultValues[$param];
													}
												}
											}
										}
									}

									if(isset($arOption['DEPENDENT_PARAMS']) && $arOption['DEPENDENT_PARAMS']) //get dependent params default value
									{
										foreach($arOption['DEPENDENT_PARAMS'] as $key => $arSubOption)
										{
											$arDefaultValues[$key] = $arSubOption['DEFAULT'];
											$arValues[$key] = Option::get(self::moduleID, $key, $arSubOption['DEFAULT'], $SITE_ID);
										}
									}
								}
							}
						}
					}
				}
			}

			if($arNestedValues && $bNestedParams) //get nested params bd value
			{
				foreach($arNestedValues as $key => $arAllValues)
				{
					$arTmpValues = array();
					foreach($arAllValues as $key2 => $arOptionValue)
					{
						$arTmpValues = unserialize(Option::get(self::moduleID, 'NESTED_OPTIONS_'.$key.'_'.$key2, serialize(array()), $SITE_ID));
						if($arTmpValues)
						{
							foreach($arTmpValues as $key3 => $value)
							{
								$arValues[$key2.'_'.$key3] = $value;
							}
						}
					}

				}
			}

			if($arValues && is_array($arValues))
			{
				foreach($arValues as $optionCode => $arOption)
				{
					if(!isset($arDefaultValues[$optionCode]))
						unset($arValues[$optionCode]);
				}
			}

			if($arDefaultValues && is_array($arDefaultValues))
			{
				foreach($arDefaultValues as $optionCode => $arOption)
				{
					if(!isset($arValues[$optionCode]))
						$arValues[$optionCode] = $arOption;
				}
			}

			foreach($arValues as $key => $value)
			{
				if(
					$key == 'LOGO_IMAGE' ||
					$key == 'LOGO_IMAGE_LIGHT' ||
					$key == 'LOGO_IMAGE_WHITE' ||
					$key == 'FAVICON_IMAGE' ||
					$key == 'APPLE_TOUCH_ICON_IMAGE'
				){
					$arValue = unserialize(Option::get(self::moduleID, $key, serialize(array()), $SITE_ID));
					$arValue = (array)$arValue;
					$fileID = $arValue ? current($arValue) : false;

					if($key === 'FAVICON_IMAGE'){
						if($fileID){
							$faviconFile = CFIle::GetPath($fileID);
							$file_ext = pathinfo($faviconFile, PATHINFO_EXTENSION);
							$fav_ext = $file_ext ? $file_ext : 'ico';
							$arValues[$key] = str_replace('//', '/', $SITE_DIR.'/favicon.'.$file_ext);
						} else {
							$arValues[$key] = str_replace('//', '/', $SITE_DIR.'/favicon.ico');
						}
					}

					if($fileID)
					{
						if($key !== 'FAVICON_IMAGE')
							$arValues[$key] = CFIle::GetPath($fileID);
					}
					else
					{
						if($key === 'APPLE_TOUCH_ICON_IMAGE')
							$arValues[$key] = str_replace('//', '/', $SITE_DIR.'/include/apple-touch-icon.png');
						elseif($key === 'LOGO_IMAGE')
							$arValues[$key] = str_replace('//', '/', $SITE_DIR.'/logo.png');
					}

					if(!file_exists(str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].$arValues[$key]))){
						$arValues[$key] = '';
					}

				}
			}

			if(!defined('ADMIN_SECTION'))
			{
				// replace #SITE_DIR#
				if($arValues && is_array($arValues))
				{
					foreach($arValues as $optionCode => $arOption)
					{
						if(!is_array($arOption))
							$arValues[$optionCode] = str_replace('#SITE_DIR#', $SITE_DIR, $arOption);
					}
				}
			}
		}

		return $arValues;
	}

	public static function GetFrontParametrsValues($SITE_ID, $SITE_DIR = '', $bFromStatic = true){
		$SITE_ID = strlen($SITE_ID) ? $SITE_ID : (defined('SITE_ID') ? SITE_ID : '');
		$SITE_DIR = strlen($SITE_DIR) ? $SITE_DIR : (defined('SITE_DIR') ? SITE_DIR : '');

		$arBackParametrs = self::GetBackParametrsValues($SITE_ID, $SITE_DIR, $bFromStatic);
		$arValues = (array)$arBackParametrs;

		if (
			isset($_SESSION['THEME']) && is_array($_SESSION['THEME']) &&
			isset($_SESSION['THEME'][$SITE_ID]) && is_array($_SESSION['THEME'][$SITE_ID])
		) {
			if ($arBackParametrs['THEME_SWITCHER'] === 'Y') {
				$arValues = array_merge($arValues, (array)$_SESSION['THEME'][$SITE_ID]);
			} else {
				if (isset($_SESSION['THEME'][$SITE_ID]['THEME_VIEW_COLOR'])) {
					$arValues['THEME_VIEW_COLOR'] = $_SESSION['THEME'][$SITE_ID]['THEME_VIEW_COLOR'];
				}
			}
		}

		if($arValues['REGIONALITY_SEARCH_ROW'] == 'Y' && $arValues['REGIONALITY_VIEW'] != 'POPUP_REGIONS')
			$arValues['REGIONALITY_VIEW'] = 'POPUP_REGIONS';

		// global flag for OnEndBufferContentHandler
		$GLOBALS['_USE_LAZY_LOAD_ALLCORP3_'] = $arValues['USE_LAZY_LOAD'] === 'Y';

		return $arValues;
	}

	public static function GetFrontParametrValue($optionCode, $SITE_ID = SITE_ID, $bStatic = true){
		static $arFrontParametrs;

		if(!isset($arFrontParametrs) || !$bStatic)
			$arFrontParametrs = self::GetFrontParametrsValues($SITE_ID);

		return $arFrontParametrs[$optionCode];
	}

	public static function checkVersionModule($version = '1.0.0', $module="catalog"){
		if($info = \CModule::CreateModuleObject($module))
		{
			if(!CheckVersion($version, $info->MODULE_VERSION))
				return true;
		}
		return false;
	}

	public static function showAllAdminRows($optionCode, $arTab, $arOption, $module_id, $arPersonTypes, $optionsSiteID, $arDeliveryServices, $arPaySystems, $arCurrency, $arOrderPropertiesByPerson, $bSearchMode){
		if(array_key_exists($optionCode, $arTab["OPTIONS"]) || $arOption["TYPE"] == 'note' || $arOption["TYPE"] == 'includefile')
		{
			$arControllerOption = CControllerClient::GetInstalledOptions(self::moduleID);
			if($optionCode === "ONECLICKBUY_PERSON_TYPE"){
				$arOption['LIST'] = $arPersonTypes[$arTab["SITE_ID"]];
			}
			elseif($optionCode === "ONECLICKBUY_DELIVERY"){
				$arOption['LIST'] = $arDeliveryServices[$arTab["SITE_ID"]];
			}
			elseif($optionCode === "ONECLICKBUY_PAYMENT"){
				$arOption['LIST'] = $arPaySystems;
			}
			elseif($optionCode === "ONECLICKBUY_CURRENCY"){
				$arOption['LIST'] = $arCurrency;
			}
			elseif($optionCode === "ONECLICKBUY_PROPERTIES" || $optionCode === "ONECLICKBUY_REQUIRED_PROPERTIES"){
				$arOption['LIST'] = $arOrderPropertiesByPerson[Option::get(self::moduleID, 'ONECLIKBUY_PERSON_TYPE', ($arPersonTypes ? key($arPersonTypes[$arTab["SITE_ID"]]) : ''), $arTab["SITE_ID"])];
			}

			$searchClass = '';
			if($bSearchMode)
			{
				if(isset($arOption["SEARCH_FIND"]) && $arOption["SEARCH_FIND"]) {
					$searchClass = 'visible_block';
				}
			}

			if($arOption['TYPE'] === 'array')
			{
				$itemsCount = Option::get(self::moduleID, $optionCode, 0, $optionsSiteID);
				if($arOption['OPTIONS'] && is_array($arOption['OPTIONS']))
				{
					$arOptionsKeys = array_keys($arOption['OPTIONS']);
					$newItemHtml = '';
					?>
					<div class="title"><?=$arOption["TITLE"]?></div>
					<div class="item array <?=($itemsCount ? '' : 'empty_block');?> js_block" data-class="<?=$optionCode;?>" data-search="<?=$searchClass;?>">
						<div >
							<div class="aspro-admin-item">
								<div class="wrapper has_title no_drag">
									<div class="inner_wrapper">
										<?foreach($arOptionsKeys as $_optionKey):?>
											<div class="inner">
												<div class="title_wrapper"><div class="subtitle"><?=$arOption['OPTIONS'][$_optionKey]['TITLE']?></div></div>
												<?=self::ShowAdminRow(
													$optionCode.'_array_'.$_optionKey.'_#INDEX#',
													$arOption['OPTIONS'][$_optionKey],
													$arTab,
													$arControllerOption
												);?>
											</div>
										<?endforeach;?>
									</div>
								</div>
								<?for($itemIndex = 0; $itemIndex <= $itemsCount; ++$itemIndex):?>
									<?$bNew = $itemIndex == $itemsCount;?>
									<?if($bNew):?><?ob_start();?><?endif;?>
										<div class="wrapper">
											<div class="inner_wrapper">
												<?foreach($arOptionsKeys as $_optionKey):?>
													<div class="inner">
														<?=self::ShowAdminRow(
															$optionCode.'_array_'.$_optionKey.'_'.($bNew ? '#INDEX#' : $itemIndex),
															$arOption['OPTIONS'][$_optionKey],
															$arTab,
															$arControllerOption
														);?>
													</div>
												<?endforeach;?>
												<div class="remove" title="<?=Loc::getMessage("REMOVE_ITEM")?>"></div>
												<div class="drag" title="<?=Loc::getMessage("TRANSFORM_ITEM")?>"></div>
											</div>
										</div>
									<?if($bNew):?><?$newItemHtml = ob_get_clean();?><?endif;?>
								<?endfor;?>
							</div>
							<div class="new-item-html" style="display:none;"><?=str_replace('no_drag', '', $newItemHtml)?></div>
							<div>
								<a href="javascript:;" class="adm-btn adm-btn-save adm-btn-add"><?=GetMessage('OPTIONS_ADD_BUTTON_TITLE')?></a>
							</div>
						</div>
					</div>
				<?}
			}
			else
			{
				if($arOption["TYPE"] == 'note')
				{
					if($optionCode === 'CONTACTS_EDIT_LINK_NOTE')
					{
						$contactsHref = str_replace('//', '/', $arTab['SITE_DIR'].'/contacts/?bitrix_include_areas=Y');
						$arOption["TITLE"] = GetMessage('CONTACTS_OPTIONS_EDIT_LINK_NOTE', array('#CONTACTS_HREF#' => $contactsHref));
					}
					?>
					<div class="notes-block visible_block1" data-option_code="<?=$optionCode;?>">
						<div align="center">
							<?=BeginNote('align="center" name="'.htmlspecialcharsbx($optionCode)."_".$optionsSiteID.'"');?>
							<?=($arOption["TITLE"] ? $arOption["TITLE"] : $arOption["NOTE"])?>
							<?=EndNote();?>
						</div>
					</div>
					<?
				}
				else
				{
					$optionName = $arOption["TITLE"];
					$optionType = $arOption["TYPE"];
					$optionList = $arOption["LIST"];
					$optionDefault = $arOption["DEFAULT"];
					$optionVal = $arTab["OPTIONS"][$optionCode];
					$optionSize = $arOption["SIZE"];
					$optionCols = $arOption["COLS"];
					$optionRows = $arOption["ROWS"];
					$optionChecked = $optionVal == "Y" ? "checked" : "";
					$optionDisabled = isset($arControllerOption[$optionCode]) || array_key_exists("DISABLED", $arOption) && $arOption["DISABLED"] == "Y" ? "disabled" : "";
					$optionSup_text = array_key_exists("SUP", $arOption) ? $arOption["SUP"] : "";
					$optionController = isset($arControllerOption[$optionCode]) ? "title='".GetMessage("MAIN_ADMIN_SET_CONTROLLER_ALT")."'" : "";
					$style = "";
					$bHideOption = $arOption['CHECK_COUNT'] === 'Y' && (
						!isset($arOption['LIST']) 
						|| !is_array($arOption['LIST']) 
						|| count($arOption['LIST']) <= 1
					);
					?>
					<div class="item js_block <?=$optionType;?> <?=((isset($arOption["WITH_HINT"]) && $arOption["WITH_HINT"] == "Y") ? 'with-hint' : '');?> <?=((isset($arOption["BIG_BLOCK"]) && $arOption["BIG_BLOCK"] == "Y") ? 'big-block' : '');?>" data-class="<?=$optionCode;?>" data-search="<?=$searchClass;?>">
						<?if($arOption["HIDDEN"] != "Y" && !$bHideOption):?>
							<div data-optioncode="<?=$optionCode;?>" <?=$style;?> class="js_block1">

								<div class="inner_wrapper <?=($optionType == "checkbox" ? "checkbox" : "");?>">
									<?=self::ShowAdminRow($optionCode, $arOption, $arTab, $arControllerOption);?>
								</div>
								<?if(isset($arOption["IMG"]) && $arOption["IMG"]):?>
									<div class="img"><img src="<?=$arOption["IMG"];?>" alt="<?=$arOption["TITLE"];?>" title="<?=$arOption["TITLE"];?>"></div>
								<?endif;?>
							</div>
						<?endif;?>
						<?if(isset($arOption['SUB_PARAMS']) && $arOption['SUB_PARAMS'] && (isset($arOption['LIST']) && $arOption['LIST'])): //nested params?>
							<?foreach($arOption['LIST'] as $key => $value):?>
								<?foreach((array)$arOption['SUB_PARAMS'][$key] as $key2 => $arValue)
								{
									if(isset($arValue['VISIBLE']) && $arValue['VISIBLE'] == 'N')
										unset($arOption['SUB_PARAMS'][$key][$key2]);
								}
								if($arOption['SUB_PARAMS'][$key]):?>
									<div class="parent-wrapper js-sub block_<?=$key.'_'.$optionsSiteID;?>" <?=($optionVal == $key ? "style='display:block;'" : "")?>>
										<?$param = "SORT_ORDER_".$optionCode."_".$key;?>

										<?
										/* get custom blocks */
										$arIndexTemplate = array();
										$arNewOptions = \Aspro\Functions\CAsproAllcorp3::getCustomBlocks($optionsSiteID);

										if ($arNewOptions) {
											$arOption['SUB_PARAMS'][$key] += $arNewOptions;
											foreach ($arNewOptions as $keyOption => $arNewOption) {
												$fieldTemplate = $key.'_'.$keyOption.'_TEMPLATE';
												if (!$arTab['OPTIONS'][$fieldTemplate]) {

													$arTab['OPTIONS'][$fieldTemplate] = $arNewOption['TEMPLATE']['DEFAULT'];
													$arTab['OPTIONS'][$fieldTemplate] = Option::get(self::moduleID, $fieldTemplate, $arNewOption['TEMPLATE']['DEFAULT'], $optionsSiteID);
												}
												$fieldKey = $key.'_'.$keyOption;
												if (!$arTab['OPTIONS'][$fieldKey]) {
													$arTmpValues = unserialize(Option::get(self::moduleID, 'NESTED_OPTIONS_'.$optionCode.'_'.$key, serialize(array()), $optionsSiteID));

													if ($arTmpValues && $arTmpValues[$keyOption]) {
														$arTab['OPTIONS'][$fieldKey] = $arTmpValues[$keyOption];
													} else {
														$arTab['OPTIONS'][$fieldKey] = $arNewOption['DEFAULT'];
													}
												}
											}
										}
										/* */
										?>

										<div data-parent='<?=$optionCode."_".$arTab["SITE_ID"]?>' class="block <?=$key?> title" <?=($key == $arTab["OPTIONS"][$optionCode] ? "style='display:block'" : "style='display:none'");?>>
											<?if($arOption['SUB_PARAMS'][$key]):?><div><?=GetMessage('SUB_PARAMS');?></div><?endif;?>
										</div>
										<div class="aspro-admin-item" data-key="<?=$key;?>" data-site="<?=$optionsSiteID;?>">
											<?if ($arTab['OPTIONS'][$param]) {
												$arOrder = explode(",", $arTab['OPTIONS'][$param]);
												$arTmp = array();

												if (
													$arDiff = array_diff(
														array_keys($arOption['SUB_PARAMS'][$key]),
														$arOrder
													)
												) {
													$arOrder += $arDiff;
												}

												foreach ($arOrder as $name) {
													$arTmp[$name] = $arOption['SUB_PARAMS'][$key][$name];
												}
												$arOption['SUB_PARAMS'][$key] = $arTmp;
												unset($arTmp);
											}?>



											<?foreach((array)$arOption['SUB_PARAMS'][$key] as $key2 => $arValue):
												if($arValue['VISIBLE'] != 'N'):?>
													<?if (!$arValue) continue;?>
													<div data-parent='<?=$optionCode."_".$arTab["SITE_ID"]?>' class="block sub <?=$key?> <?=($arValue['DRAG'] == 'N' ? 'no_drag' : '');?>" <?=($key == $arTab["OPTIONS"][$optionCode] ? "style='display:block'" : "style='display:none'");?>>
														<div class="inner_wrapper <?=($arValue["TYPE"] == "checkbox" ? "checkbox" : "");?>">
															<?=self::ShowAdminRow($key.'_'.$key2, $arValue, $arTab, $arControllerOption);?>
															<?if($arValue['INDEX_BLOCK_OPTIONS'] && $arValue['INDEX_BLOCK_OPTIONS']['TOP']):?>
																<div class="index-block-top-options">
																	<?foreach($arValue['INDEX_BLOCK_OPTIONS']['TOP'] as $topOptionKey => $topOption):?>
																		<?$index_block_option = $topOptionKey.'_'.$key2.'_'.$key;?>
																		<?$index_block_value = Option::get(self::moduleID, $index_block_option, $arValue['INDEX_BLOCK_OPTIONS']['TOP'][$topOptionKey], $arTab["SITE_ID"]);?>
																		<?$index_block_option .= '_'.$arTab["SITE_ID"]?>
																		<div class="index-block-top-options__inner">
																			<div class="index-block-top-options__value">
																				<input type="checkbox" id="<?=$index_block_option?>" name="<?=$index_block_option?>" value="Y" <?=($index_block_value == 'Y' ? "checked" : "");?> class="adm-designed-checkbox">
																				<label class="adm-designed-checkbox-label" for="<?=$index_block_option?>" title=""></label>
																			</div>
																			<div class="title_wrapper index-block-top-options__title">
																				<div class="subtitle">
																					<label for="<?=$index_block_option?>"><?=Loc::getMessage($topOptionKey."_BLOCK")?></label>
																				</div>
																			</div>
																		</div>
																	<?endforeach;?>
																</div>
															<?endif;?>
															<?if($arValue['DRAG'] != 'N'):?>
																<div class="drag" title="<?=Loc::getMessage("TRANSFORM_ITEM")?>"></div>
															<?endif;?>

															<?if($arValue['INDEX_BLOCK_OPTIONS'] && $arValue['INDEX_BLOCK_OPTIONS']['BOTTOM']):?>
																<div class="index-block-bottom-options">
																	<?foreach($arValue['INDEX_BLOCK_OPTIONS']['BOTTOM'] as $bottomOptionKey => $bottomOption):?>
																		<div class="index-block-bottom-options__item">
																			<?$index_block_option = $bottomOptionKey.'_'.$key2.'_'.$key;?>
																			<?$index_block_value = Option::get(self::moduleID, $index_block_option, $arValue['INDEX_BLOCK_OPTIONS']['BOTTOM'][$bottomOptionKey]['DEFAULT'], $arTab["SITE_ID"]);?>

																			<?if (!$arTab['OPTIONS'][$index_block_option]) {
																				$arTab['OPTIONS'][$index_block_option] = $index_block_value;
																			}?>

																			<?=self::ShowAdminRow($index_block_option, $bottomOption, $arTab, $arControllerOption);?>
																		</div>
																	<?endforeach;?>
																</div>
															<?endif;?>
														</div>
													</div>
												<?endif;?>
												<?
												if(isset($arValue['TEMPLATE']) && $arValue['TEMPLATE'])
												{
													$code_tmp = $key2.'_TEMPLATE';
													$arIndexTemplate[$code_tmp] = $arValue['TEMPLATE'];
												}
												?>
											<?endforeach;?>
										</div>
										<input type="hidden" name="<?=$param.'_'.$arTab["SITE_ID"];?>" value="<?=$arTab["OPTIONS"][$param]?>" />
									</div>
									<?//show template index components?>
									<?if($arIndexTemplate):?>
										<div class="template-wrapper js-sub block_<?=$key.'_'.$optionsSiteID;?>" data-key="<?=$key;?>" data-site="<?=$optionsSiteID;?>" <?=($key == $arTab["OPTIONS"][$optionCode] ? "style='display:block'" : "style='display:none'");?>>
											<div class="title"><?=Loc::getMessage("FRONT_TEMPLATE_GROUP")?></div>
											<div class="sub-block item">
												<?foreach($arIndexTemplate as $key2 => $arValue):?>
													<div data-parent='<?=$optionCode."_".$arTab["SITE_ID"]?>' class="block <?=$key?>" <?=($key == $arTab["OPTIONS"][$optionCode] ? "style='display:block'" : "style='display:none'");?>>
														<?=self::ShowAdminRow($key.'_'.$key2, $arValue, $arTab, $arControllerOption);?>
													</div>
												<?endforeach;?>
											</div>
										</div>
									<?endif;?>
								<?endif;?>
							<?endforeach;?>
						<?endif;?>
						<?if(isset($arOption['DEPENDENT_PARAMS']) && $arOption['DEPENDENT_PARAMS']): //dependent params?>
							<?foreach($arOption['DEPENDENT_PARAMS'] as $key => $arValue):?>
								<?
								$searchClass = "";
								if($bSearchMode)
								{
									if(isset($arValue["SEARCH_FIND"]) && $arValue["SEARCH_FIND"])
										$searchClass = 'visible_block';
								}?>
								<?if(!isset($arValue['CONDITIONAL_VALUE']) || ($arValue['CONDITIONAL_VALUE'] && $arTab["OPTIONS"][$optionCode] == $arValue['CONDITIONAL_VALUE']))
								{
									$style = "style='display:block'";
								}
								else
								{
									$style = "style='display:none'";
									$searchClass = "";
								}
								?>
								<div data-optioncode="<?=$key;?>" class="depend-block js_block1 <?=$key?> <?=((isset($arValue['TO_TOP']) && $arValue['TO_TOP']) ? "to_top" : "");?>  <?=$arValue["TYPE"];?> <?=((isset($arValue['ONE_BLOCK']) && $arValue['ONE_BLOCK'] == "Y") ? "ones" : "");?>" <?=((isset($arValue['CONDITIONAL_VALUE']) && $arValue['CONDITIONAL_VALUE']) ? "data-show='".$arValue['CONDITIONAL_VALUE']."'" : "");?> data-class="<?=$key;?>" data-search="<?=$searchClass;?>" data-parent='<?=$optionCode."_".$arTab["SITE_ID"]?>' <?=$style;?>>
									<div class="inner_wrapper <?=($arValue["TYPE"] == "checkbox" ? "checkbox" : "");?>">
										<?=self::ShowAdminRow($key, $arValue, $arTab, $arControllerOption);?>
									</div>
								</div>
							<?endforeach;?>
						<?endif;?>
					</div>
					<?
				}
			}
		}
	}

	public static function ShowAdminRow($optionCode, $arOption, $arTab, $arControllerOption, $btable = false){
		$optionName = $arOption['TITLE'];
		$optionType = $arOption['TYPE'];
		$optionList = $arOption['LIST'];
		$optionDefault = $arOption['DEFAULT'];
		$optionVal = $arTab['OPTIONS'][$optionCode];
		$optionSize = $arOption['SIZE'];
		$optionCols = $arOption['COLS'];
		$optionRows = $arOption['ROWS'];
		$optionChecked = $optionVal == 'Y' ? 'checked' : '';
		$optionDisabled = isset($arControllerOption[$optionCode]) || array_key_exists('DISABLED', (array)$arOption) && $arOption['DISABLED'] == 'Y' ? 'disabled' : '';
		$optionSup_text = array_key_exists('SUP', (array)$arOption) ? $arOption['SUP'] : '';
		$optionController = isset($arControllerOption[$optionCode]) ? "title='".GetMessage("MAIN_ADMIN_SET_CONTROLLER_ALT")."'" : "";
		$optionsSiteID = $arTab['SITE_ID'];
		$isArrayItem = strpos($optionCode, '_array_') !== false;
		?>

		<?if($optionType == 'dynamic_iblock'):?>
			<?if(Loader::IncludeModule('iblock')):?>
				<div colspan="2">
					<div class="title"  align="center"><b><?=$optionName;?></b></div>
					<?
					$arIblocks = array();
					$arSort = array(
						"SORT" => "ASC",
						"ID" => "ASC"
					);
					$arFilter = array(
						"ACTIVE" => "Y",
						"SITE_ID" => $optionsSiteID,
						"TYPE" => "aspro_allcorp3_form"
					);
					$rsItems = CIBlock::GetList($arSort, $arFilter);
					while($arItem = $rsItems->Fetch()){
						if($arItem["CODE"] != "aspro_allcorp3_example" && $arItem["CODE"] != "aspro_allcorp3_order_page")
						{
							$arItem['THEME_VALUE'] = Option::get(self::moduleID, htmlspecialcharsbx($optionCode)."_".htmlspecialcharsbx(strtoupper($arItem['CODE'])), '', $optionsSiteID);
							$arIblocks[] = $arItem;
						}
					}
					if($arIblocks):?>
						<table width="100%">
							<?foreach($arIblocks as $arIblock):?>
								<tr>
									<td class="adm-detail-content-cell-l" width="50%">
										<?=GetMessage("SUCCESS_SEND_FORM", array("#IBLOCK_CODE#" => $arIblock["NAME"]));?>
									</td>
									<td class="adm-detail-content-cell-r" width="50%">
										<input type="text" <?=((isset($arOption['PARAMS']) && isset($arOption['PARAMS']['WIDTH'])) ? 'style="width:'.$arOption['PARAMS']['WIDTH'].'"' : '');?> <?=$optionController?> size="<?=$optionSize?>" maxlength="255" value="<?=htmlspecialcharsbx($arIblock['THEME_VALUE'])?>" name="<?=htmlspecialcharsbx($optionCode)."_".htmlspecialcharsbx($arIblock['CODE'])."_".$optionsSiteID?>" <?=$optionDisabled?>>
									</td>
								</tr>
							<?endforeach;?>
						</table>
					<?endif;?>
				</div>
			<?endif;?>
		<?elseif($optionType == "note"):?>
			<?if($optionCode == 'GOALS_NOTE')
			{
				$FORMS_GOALS_LIST = '';
				if(\Bitrix\Main\Loader::includeModule('form'))
				{
					if($optionsSiteID)
					{
						if($arForms = CAllcorp3Cache::CForm_GetList($by = array('by' => 's_id', 'CACHE' => array('TAG' => 'forms')), $order = 'asc', array('SITE' => $optionsSiteID, 'SITE_EXACT_MATCH' => 'Y'), $is_filtered))
						{
							foreach($arForms as $arForm)
								$FORMS_GOALS_LIST .= $arForm['NAME'].' - <i>goal_webform_success_'.$arForm['ID'].'</i><br />';
						}
					}
				}
				$arOption["NOTE"] = str_replace('#FORMS_GOALS_LIST#', $FORMS_GOALS_LIST, $arOption["NOTE"]);
			}
			?>
			<?if(!$btable):?>
				<div colspan="2" align="center">
			<?else:?>
				<td colspan="2" align="center">
			<?endif;?>
				<?=BeginNote('align="center"');?>
				<?=$arOption["NOTE"]?>
				<?=EndNote();?>
			<?if(!$btable):?>
				</div>
			<?else:?>
				</td>
			<?endif;?>
		<?else:?>
			<?if(!$isArrayItem):?>
				<?if(!isset($arOption['HIDE_TITLE_ADMIN']) || $arOption['HIDE_TITLE_ADMIN'] != 'Y'):?>
					<?if(!$btable):?>
						<div class="title_wrapper<?=(in_array($optionType, array("multiselectbox", "textarea", "statictext", "statichtml")) ? "adm-detail-valign-top" : "")?>">
					<?else:?>
						<td class="adm-detail-content-cell-l <?=(in_array($optionType, array("multiselectbox", "textarea", "statictext", "statichtml")) ? "adm-detail-valign-top" : "")?>" width="50%">
					<?endif;?>
						<div class="subtitle <?=$optionType == "link" ? 'link' : ''?>" <?=$arOption['TAB'] ? 'data-tab="'.$arOption['TAB'].'"' : ''?>>
							<?if($optionType == "checkbox"):?>
								<label for="<?=htmlspecialcharsbx($optionCode)."_".$optionsSiteID?>"><?=$optionName?></label>
							<?else:?>
								<?if($optionCode == 'PAGE_CONTACTS'):?>
									<?$optionName = Loc::getMessage("BLOCK_VIEW_TITLE");?>
								<?endif;?>
								<?=$optionName.($optionCode == "BASE_COLOR_CUSTOM" || $optionCode == "MORE_COLOR_CUSTOM" ? ' #' : '')?>
							<?endif;?>
							<?if(strlen($optionSup_text)):?>
								<span class="required"><sup><?=$optionSup_text?></sup></span>
							<?endif;?>

							<?if(isset($arOption['ADDITIONAL_OPTIONS']) && is_array($arOption['ADDITIONAL_OPTIONS'])) {?>
								<div class="additional-options">
									<?foreach($arOption['ADDITIONAL_OPTIONS'] as $subOptionKey => $subOption) {?>
										<div class="sub-item inner_wrapper <?=($subOption['TYPE'] === 'checkbox' ? 'checkbox' : '')?>">
											<?=self::ShowAdminRow($subOptionKey.'_'.$arControllerOption, $subOption, $arTab, array())?>
										</div>
									<?}?>
								</div>
							<?}?>
						</div>
					<?if(!$btable):?>
						</div>
					<?else:?>
						</td>
					<?endif;?>
				<?endif;?>
			<?endif;?>
			<?if(!$btable):?>
				<div class="value_wrapper">
			<?else:?>
				<td<?=(!$isArrayItem ? ' width="50%" ' : '')?>>
			<?endif;?>
				<?
				if ($optionCode == 'BLOG_PAGE') {
					$optionList = self::getActualParamsValue( $arTab, $arOption, '/components/bitrix/news/blog');
				} elseif($optionCode == 'NEWS_PAGE') {
					$optionList = self::getActualParamsValue( $arTab, $arOption, '/components/bitrix/news/news');
				} elseif($optionCode == 'PROJECTS_PAGE') {
					$optionList = self::getActualParamsValue( $arTab, $arOption, '/components/bitrix/news/projects');
				} elseif($optionCode == 'STAFF_PAGE') {
					$optionList = self::getActualParamsValue( $arTab, $arOption, '/components/bitrix/news/staff');
				}
				elseif($optionCode == 'PARTNERS_PAGE') {
					$optionList = self::getActualParamsValue( $arTab, $arOption, '/components/bitrix/news/partners');
				}
				elseif($optionCode == 'PARTNERS_PAGE_DETAIL') {
					$optionList = self::getActualParamsValue( $arTab, $arOption, '/components/bitrix/news/partners', 'ELEMENT');
				}
				elseif($optionCode == 'CATALOG_PAGE_DETAIL') {
					$optionList = self::getActualParamsValue( $arTab, $arOption, '/components/bitrix/catalog/main', 'ELEMENT');
				}
				elseif($optionCode == 'USE_FAST_VIEW_PAGE_DETAIL') {
					$optionList = self::getActualParamsValue( $arTab, $arOption, '/components/bitrix/catalog/main', 'FAST_VIEW_ELEMENT');
				}
				elseif($optionCode == 'VACANCY_PAGE') {
					$optionList = self::getActualParamsValue( $arTab, $arOption, '/components/bitrix/news/vacancy');
				}
				elseif($optionCode == 'LICENSES_PAGE') {
					$optionList = self::getActualParamsValue( $arTab, $arOption, '/components/bitrix/news/licenses');
				} elseif($optionCode == 'DOCUMENTS_PAGE') {
					$optionList = self::getActualParamsValue( $arTab, $arOption, '/components/bitrix/news/docs');
				} elseif($optionCode == 'GRUPPER_PROPS') {
					// redsign.grupper
					$optionList['GRUPPER']['TITLE'] = Loc::getMessage('GRUPPER_PROPS_GRUPPER');
					if(!\Bitrix\Main\Loader::includeModule('redsign.grupper'))
					{
						$optionList['GRUPPER']['DISABLED'] = 'Y';
						$optionList['GRUPPER']['TITLE'] .= Loc::getMessage('NOT_INSTALLED', array('#MODULE_NAME#' => 'redsign.grupper'));
					}

					// webdebug.utilities
					$optionList['WEBDEBUG']['TITLE'] = Loc::getMessage('GRUPPER_PROPS_WEBDEBUG');
					if(!\Bitrix\Main\Loader::includeModule('webdebug.utilities'))
					{
						$optionList['WEBDEBUG']['DISABLED'] = 'Y';
						$optionList['WEBDEBUG']['TITLE'] .= Loc::getMessage('NOT_INSTALLED', array('#MODULE_NAME#' => 'webdebug.utilities'));
					}

					// yenisite.infoblockpropsplus
					$optionList['YENISITE_GRUPPER']['TITLE'] = Loc::getMessage('GRUPPER_PROPS_YENISITE_GRUPPER');
					if(!\Bitrix\Main\Loader::includeModule('yenisite.infoblockpropsplus'))
					{
						$optionList['YENISITE_GRUPPER']['DISABLED'] = 'Y';
						$optionList['YENISITE_GRUPPER']['TITLE'] .= Loc::getMessage('NOT_INSTALLED', array('#MODULE_NAME#' => 'yenisite.infoblockpropsplus'));
					}
				} elseif($optionCode == 'PAY_SYSTEM') {
					// aspro.invoicebox
					$optionList['ASPRO_INVOICEBOX']['TITLE'] = Loc::getMessage('PAY_SYSTEM_ASPRO_INVOICEBOX');
					
					// webfly.sbrf
					$optionList['WEBFLY_SBRF']['TITLE'] = Loc::getMessage('PAY_SYSTEM_WEBFLY_SBRF');
					if(!\Bitrix\Main\Loader::includeModule('webfly.sbrf'))
					{
						$optionList['WEBFLY_SBRF']['DISABLED'] = 'Y';
						$optionList['WEBFLY_SBRF']['TITLE'] .= Loc::getMessage('NOT_INSTALLED', array('#MODULE_NAME#' => 'webfly.sbrf'));
					}

					// rover.tinkoff
					$optionList['ROVER_TINKOFF']['TITLE'] = Loc::getMessage('PAY_SYSTEM_ROVER_TINKOFF');
					if(!\Bitrix\Main\Loader::includeModule('rover.tinkoff'))
					{
						$optionList['ROVER_TINKOFF']['DISABLED'] = 'Y';
						$optionList['ROVER_TINKOFF']['TITLE'] .= Loc::getMessage('NOT_INSTALLED', array('#MODULE_NAME#' => 'rover.tinkoff'));
					}
				}
				?>

				<?if($optionType == "checkbox"):?>
					<input type="checkbox" <?=((isset($arOption['DEPENDENT_PARAMS']) && $arOption['DEPENDENT_PARAMS']) ? "class='depend-check'" : "");?> <?=$optionController?> id="<?=htmlspecialcharsbx($optionCode)."_".$optionsSiteID?>" name="<?=htmlspecialcharsbx($optionCode)."_".$optionsSiteID?>" value="Y" <?=$optionChecked?> <?=$optionDisabled?> <?=(strlen($optionDefault) ? $optionDefault : "")?>>
				<?elseif($optionType == "text" || $optionType == "password"):?>
					<?if(isset($arOption["PICKER"]) && $arOption["PICKER"] == "Y"):?>
						<?
						$defaultCode = 0;
						$customColor = str_replace('#', '', (strlen($optionVal) ? $optionVal : self::$arParametrsList[$defaultCode]['OPTIONS'][$arOption["PARENT_PROP"].'_GROUP']['ITEMS'][$arOption["PARENT_PROP"]]['LIST'][self::$arParametrsList[$defaultCode]['OPTIONS'][$arOption["PARENT_PROP"].'_GROUP']['ITEMS'][$arOption["PARENT_PROP"]]['DEFAULT']]['COLOR']));?>
						<div class="custom_block picker">
							<div class="options">
								<div class="base_color base_color_custom <?=($arTab['OPTIONS'][$arOption["PARENT_PROP"]] == 'CUSTOM' ? 'current' : '')?>" data-name="<?=htmlspecialcharsbx($optionCode)."_".$optionsSiteID?>" data-value="CUSTOM" data-color="#<?=$customColor?>">

									<span class="animation-all click_block" data-option-id="<?=$arOption["PARENT_PROP"]."_".$optionsSiteID?>" data-option-value="CUSTOM" <?=($arTab['OPTIONS'][$arOption["PARENT_PROP"]] == 'CUSTOM' ? "style='border-color:#".$customColor."'" : '')?>><span class="vals">#<?=($arTab['OPTIONS'][$arOption["PARENT_PROP"]] == 'CUSTOM' ? $customColor : '')?></span><span class="bg" data-color="<?=$customColor?>" style="background-color: #<?=$customColor?>;"></span></span>
									<input type="hidden" id="<?=htmlspecialcharsbx($optionCode)."_".$optionsSiteID?>" name="<?=htmlspecialcharsbx($optionCode)."_".$optionsSiteID?>" value="<?=$customColor?>" />
								</div>
							</div>
						</div>
					<?elseif($optionCode === 'PRIORITY_SECTION_DESCRIPTION_SOURCE'):?>
						<?
						$arPriority = explode(',', $optionVal);
						$posNone = array_search('NONE', $arPriority);
						if(!$posNone !== false){
							unset($arPriority[$posNone]);
						}

						if(!in_array('SMARTSEO', $arPriority)){
							$arPriority[] = 'SMARTSEO';
						}
						if(!in_array('SOTBIT_SEOMETA', $arPriority)){
							$arPriority[] = 'SOTBIT_SEOMETA';
						}
						if(!in_array('IBLOCK', $arPriority)){
							$arPriority[] = 'IBLOCK';
						}
						?>
						<div class="item array js_block" data-class="<?=$optionCode;?>" data-search="">
							<div>
								<div class="aspro-admin-item">
									<?foreach($arPriority as $i => $priorityCode):?>
										<?
										$bDisabled = false;
										$subtitle = Loc::getMessage('PRIORITY_SECTION_DESCRIPTION_SOURCE_'.$priorityCode);
										if($priorityCode === 'SOTBIT_SEOMETA'){
											if(!IsModuleInstalled('sotbit.seometa')){
												$bDisabled = true;
												$subtitle .= ' '.Loc::getMessage('NOT_INSTALLED', array('#MODULE_NAME#' => 'sotbit.seometa'));
											}
										}
										?>
										<div class="wrapper <?=($bDisabled ? 'disabled' : '')?>">
											<div class="inner_wrapper">
												<div class="inner">
													<div class="title_wrapper"><div class="subtitle"><?=$subtitle?></div></div>
												</div>
												<div class="drag" title="<?=Loc::getMessage("TRANSFORM_ITEM")?>"></div>
												<input type="hidden" value="<?=$priorityCode?>" name="<?=htmlspecialcharsbx($optionCode).'_'.$optionsSiteID.'[]'?>" />
											</div>
										</div>
									<?endforeach;?>
								</div>
							</div>
						</div>
					<?else:?>
						<input type="<?=$optionType?>" <?=((isset($arOption['PARAMS']) && isset($arOption['PARAMS']['WIDTH'])) ? 'style="width:'.$arOption['PARAMS']['WIDTH'].'"' : '');?> <?=$optionController?> <?=($arOption['PLACEHOLDER'] ? "placeholder='".$arOption['PLACEHOLDER']."'" : '');?> size="<?=$optionSize?>" maxlength="255" value="<?=htmlspecialcharsbx($optionVal)?>" name="<?=htmlspecialcharsbx($optionCode)."_".$optionsSiteID?>" <?=$optionDisabled?> <?=($optionCode == "password" ? "autocomplete='off'" : "")?>>
					<?endif;?>
				<?elseif($optionType == "selectbox"):?>
					<?
					if(isset($arOption['TYPE_SELECT']))
					{
						if($arOption['TYPE_SELECT'] == 'IBLOCK')
						{
							$bIBlocks = false;
							\Bitrix\Main\Loader::includeModule('iblock');
							$rsIBlock=CIBlock::GetList(array("SORT" => "ASC", "ID" => "DESC"), array("LID" => $optionsSiteID));
							$arIBlocks=array();
							while($arIBlock=$rsIBlock->Fetch()){
								$arIBlocks[$arIBlock["ID"]]["NAME"]="(".$arIBlock["ID"].") ".$arIBlock["NAME"]."[".$arIBlock["CODE"]."]";
								$arIBlocks[$arIBlock["ID"]]["CODE"]=$arIBlock["CODE"];
							}
							if($arIBlocks)
							{
								$bIBlocks = true;
							}
						}
						elseif($arOption['TYPE_SELECT'] == 'GROUP')
						{
							static $arUserGroups;
							if($arUserGroups === null){
								$DefaultGroupID = 0;
								$rsGroups = CGroup::GetList($by = "id", $order = "asc", array("ACTIVE" => "Y"));
								while($arItem = $rsGroups->Fetch()){
									$arUserGroups[$arItem["ID"]] = $arItem["NAME"];
									if($arItem["ANONYMOUS"] == "Y"){
										$DefaultGroupID = $arItem["ID"];
									}
								}
							}
							$optionList = $arUserGroups;
						}
					}
					if(!is_array($optionList)) $optionList = (array)$optionList;
					$arr_keys = array_keys($optionList);
					if(isset($arOption["TYPE_EXT"]) && $arOption["TYPE_EXT"] == "colorpicker"):?>
						<div class="bases_block">
							<input type="hidden" id="<?=$optionCode?>" name="<?=$optionCode."_".$optionsSiteID;?>" value="<?=$optionVal?>" />
							<?foreach($arOption['LIST'] as $colorCode => $arColor):?>
								<?if($colorCode !== 'CUSTOM'):?>
									<div class="base_color <?=($colorCode == $optionVal ? 'current' : '')?>" data-value="<?=$colorCode?>" data-color="<?=$arColor['COLOR']?>">
										<span class="animation-all click_block status-block"  data-option-id="<?=$optionCode?>" data-option-value="<?=$colorCode?>" title="<?=$arColor['TITLE']?>"><span style="background-color: <?=$arColor['COLOR']?>;"></span></span>
									</div>
								<?endif;?>
							<?endforeach;?>
						</div>
					<?elseif((isset($arOption["IS_ROW"]) && $arOption["IS_ROW"] == "Y") ||(isset($arOption["SHOW_IMG"]) && $arOption["SHOW_IMG"] == "Y")):?>
						<?if($arOption["HIDDEN"] != "Y"):?>

							<div class="block_with_img <?=(isset($arOption["ROWS"]) && $arOption["ROWS"] == "Y" ? 'in_row' : '');?>">
								<input type="hidden" id="<?=$optionCode?>" name="<?=$optionCode."_".$optionsSiteID;?>" value="<?=$optionVal?>" />
								<div class="rows flexbox">
									<?foreach($arOption['LIST'] as $code => $arValue):?>
										<?if($arValue["TITLE"] == 'list_elements_custom' || $arValue["TITLE"] == 'element_custom')
											$arValue["TITLE"] = 'custom';?>
										<div>
											<div class="link-item animation-boxs block status-block <?=($code == $optionVal ? 'current' : '')?>" <?=($code == $optionVal ? 'data-current="Y"' : '')?> data-value="<?=$code?>" data-site="<?=$optionsSiteID;?>">
												<span class="title"><?=$arValue["TITLE"];?></span>
												<?if($arValue["IMG"]):?>
													<span><img src="<?=$arValue["IMG"];?>" alt="<?=$arValue["TITLE"];?>" title="<?=$arValue["TITLE"];?>" class="<?=($arValue["COLORED_IMG"] ? 'colored_theme_bg' : '')?>" /></span>
													<?if(isset($arValue['ADDITIONAL_OPTIONS']) && $arValue['ADDITIONAL_OPTIONS']):?>
														<div class="subs flex-column">
															<?foreach($arValue['ADDITIONAL_OPTIONS'] as $key => $arSubOption):?>
																<?$codeTmp = (strpos($optionCode, '_TEMPLATE') !== false ? str_replace('_TEMPLATE', '_', $optionCode).$key.'_'.$code : $key.'_'.$code);?>
																<?
																if( isset($arSubOption['DEPENDS_ON'])
																	&& isset($arTab['OPTIONS'][$arSubOption['DEPENDS_ON'].'_'.$code])
																	&& $arTab['OPTIONS'][$arSubOption['DEPENDS_ON'].'_'.$code] !== 'Y'
																	)
																{
																	continue;
																}
																?>
																<div class="sub-item inner_wrapper <?=($arSubOption['TYPE'] === 'checkbox' ? 'checkbox' : '')?>">
																	<?=self::ShowAdminRow($codeTmp, $arSubOption, $arTab, array())?>
																</div>
															<?endforeach;?>
														</div>
													<?endif;?>
													<?if(isset($arValue['TOGGLE_OPTIONS']) && $arValue['TOGGLE_OPTIONS']):?>
														<div class="subs flex-column">
															<?foreach($arValue['TOGGLE_OPTIONS']['OPTIONS'] as $key => $arSubOption):?>
																<div class="sub-item inner_wrapper <?=($arSubOption['TYPE'] === 'checkbox' ? 'checkbox' : '')?>">
																	<?$codeTmp = (strpos($optionCode, '_TEMPLATE') !== false ? str_replace('_TEMPLATE', '_', $optionCode).$key.'_'.$code : $key.'_'.$code);?>
																	<?=self::ShowAdminRow($codeTmp, $arSubOption, $arTab, $code)?>
																</div>
															<?endforeach;?>
														</div>
													<?endif;?>
												<?endif;?>
											</div>
										</div>
									<?endforeach;?>
								</div>
							</div>
						<?endif;?>
					<?else:?>
						<select  <?=((isset($arOption['DEPENDENT_PARAMS']) && $arOption['DEPENDENT_PARAMS']) ? "class='depend-check'" : "");?> data-site="<?=$optionsSiteID?>" name="<?=htmlspecialcharsbx($optionCode)."_".$optionsSiteID?>" <?=$optionController?> <?=$optionDisabled?>>
							<?if($bIBlocks)
							{
								foreach($arIBlocks as $key => $arValue) {
									$selected="";
									if(!$optionVal && $arValue["CODE"]=="aspro_allcorp3_catalog"){
										$selected="selected";
									}elseif($optionVal && $optionVal==$key){
										$selected="selected";
									}?>
									<option value="<?=$key;?>" <?=$selected;?>><?=htmlspecialcharsbx($arValue["NAME"]);?></option>
								<?}
							}
							elseif($optionCode == 'GRUPPER_PROPS')
							{
								foreach($optionList as $key => $arValue):
									$selected="";
									if($optionVal && $optionVal==$key)
										$selected="selected";
									?>
									<option value="<?=$key;?>" <?=$selected;?> <?=(isset($arValue['DISABLED']) ? 'disabled' : '');?>><?=htmlspecialcharsbx($arValue["TITLE"]);?></option>
								<?endforeach;?>
							<?}
							else
							{
								if(is_array($arOption['GROUPPED_LIST'])){
									foreach($arOption['GROUPPED_LIST'] as $arGrouppedList){
										if(is_array($arGrouppedList)){
											$optionList = $arGrouppedList['LIST'];
											if(is_array($optionList)){
												$arr_keys = array_keys($optionList);

												if(strlen($arGrouppedList['TITLE'])){
													?><optgroup label="<?=$arGrouppedList['TITLE']?>"><?
												}

												for($j = 0, $c = count($arr_keys); $j < $c; ++$j){
													?><option value="<?=$arr_keys[$j]?>" <?if($optionVal == $arr_keys[$j]) echo "selected"?> <?=(isset($optionList[$arr_keys[$j]]['DISABLED']) ? 'disabled' : '');?>><?=htmlspecialcharsbx((is_array($optionList[$arr_keys[$j]]) ? $optionList[$arr_keys[$j]]["TITLE"] : $optionList[$arr_keys[$j]]))?></option><?
												}

												if(strlen($arGrouppedList['TITLE'])){
													?></optgroup><?
												}
											}
										}
									}
								}
								else{
									for($j = 0, $c = count($arr_keys); $j < $c; ++$j){
										?><option value="<?=$arr_keys[$j]?>" <?if($optionVal == $arr_keys[$j]) echo "selected"?> <?=(isset($optionList[$arr_keys[$j]]['DISABLED']) ? 'disabled' : '');?>><?=htmlspecialcharsbx((is_array($optionList[$arr_keys[$j]]) ? $optionList[$arr_keys[$j]]["TITLE"] : $optionList[$arr_keys[$j]]))?></option><?
									}
								}
							}?>
						</select>
					<?endif;?>
				<?elseif($optionType == "multiselectbox"):?>
					<?
					if(isset($arOption['TYPE_SELECT']))
					{
						if($arOption['TYPE_SELECT'] == 'IBLOCK')
						{
							static $bIBlocks;
							if ($bIBlocks === null){
								$bIBlocks = false;
								\Bitrix\Main\Loader::includeModule('iblock');
								$rsIBlock=CIBlock::GetList(array("SORT" => "ASC", "ID" => "DESC"), array("LID" => $optionsSiteID));
								$arIBlocks=array();
								while($arIBlock=$rsIBlock->Fetch()){
									$arIBlocks[$arIBlock["ID"]]["NAME"]="(".$arIBlock["ID"].") ".$arIBlock["NAME"]."[".$arIBlock["CODE"]."]";
									$arIBlocks[$arIBlock["ID"]]["CODE"]=$arIBlock["CODE"];
								}
								if($arIBlocks)
								{
									$bIBlocks = true;
								}
							}
						}
						elseif($arOption['TYPE_SELECT'] == 'GROUP')
						{
							static $arUserGroups;
							if($arUserGroups === null){
								$DefaultGroupID = 0;
								$rsGroups = CGroup::GetList($by = "id", $order = "asc", array("ACTIVE" => "Y"));
								while($arItem = $rsGroups->Fetch()){
									$arUserGroups[$arItem["ID"]] = $arItem["NAME"];
									if($arItem["ANONYMOUS"] == "Y"){
										$DefaultGroupID = $arItem["ID"];
									}
								}
							}
							$optionList = $arUserGroups;
						}
						elseif($arOption['TYPE_SELECT'] == 'SITE')
						{
							static $arSites;
							if($arSites === null){
								$rsSites = \CSite::GetList($by="sort", $order="desc", array("ACTIVE" => "Y"));

								while($arItem = $rsSites->Fetch()){
									$arSites[$arItem["ID"]] = $arItem["NAME"];
								}
							}
							$optionList = $arSites;
						}
						elseif($arOption['TYPE_SELECT'] == 'IBLOCK_PROPS')
						{
							
							if (Loader::includeModule('iblock')) {
								if($arOption['PROPS_SETTING']){
									$arFilter = [];
									if ($iblockID = Option::get(self::moduleID, $arOption['PROPS_SETTING']['IBLOCK_ID_OPTION'], '', $optionsSiteID)) {
										$arFilter['IBLOCK_ID'] = $iblockID;
									} elseif ($arOption['PROPS_SETTING']['IBLOCK_CODE']) {
										$arFilter['IBLOCK_CODE'] = $arOption['PROPS_SETTING']['IBLOCK_CODE'];
									}
									if ($arOption['PROPS_SETTING']['FILTER']) {
										$arFilter = array_merge($arFilter, $arOption['PROPS_SETTING']['FILTER']);
									}
									$arIblockProps = ['' => '-'];
									$rsProps = CIBlockProperty::GetList(
										array('SORT' => 'ASC', 'NAME' => 'ASC'),
										$arFilter,
									);
									while ($arProp = $rsProps->GetNext()) {
										if ($arOption['PROPS_SETTING']['IS_TREE']) {
											if (
												'L' == $arProp['PROPERTY_TYPE']
												|| 'E' == $arProp['PROPERTY_TYPE']
												|| ('S' == $arProp['PROPERTY_TYPE'] && 'directory' == $arProp['USER_TYPE'])
											) {
												$arIblockProps[$arProp['CODE']] = "[{$arProp['ID']}] {$arProp['NAME']} ({$arProp['CODE']})";
											}
										} else {
											$arIblockProps[$arProp['CODE']] = "[{$arProp['ID']}] {$arProp['NAME']} ({$arProp['CODE']})";
										}
									}
								}
							}
							$optionList = $arIblockProps;
						}
					}
					if(!is_array($optionList)) $optionList = (array)$optionList;
					$arr_keys = array_keys($optionList);
					$optionVal = explode(",", $optionVal);
					if(!is_array($optionVal)) $optionVal = (array)$optionVal;?>
					<?if(isset($arOption['SHOW_CHECKBOX']) && $arOption['SHOW_CHECKBOX'] == 'Y'):?>
						<div class="props">
							<?for($j = 0, $c = count($arr_keys); $j < $c; ++$j):?>
								<div class="outer_wrapper <?=(in_array($arr_keys[$j], $optionVal) ? "checked" : "");?>">
									<div class="inner_wrapper checkbox">
										<div class="title_wrapper">
											<div class="subtitle"><label for="<?=$optionCode."_".$optionsSiteID."_".$j?>"><?=htmlspecialcharsbx((is_array($optionList[$arr_keys[$j]]) ? $optionList[$arr_keys[$j]]["TITLE"] : $optionList[$arr_keys[$j]]))?></label></div>
										</div>
										<div class="value_wrapper">
											<input type="checkbox" id="<?=$optionCode."_".$optionsSiteID."_".$j?>" name="temp_<?=htmlspecialcharsbx($optionCode)."_".$optionsSiteID?>" value="<?=$arr_keys[$j]?>" <?=(in_array($arr_keys[$j], $optionVal) ? "checked" : "");?>><label for="<?=$optionCode."_".$optionsSiteID."_".$j?>"></label>
										</div>
									</div>
								</div>
							<?endfor;?>
						</div>
					<?endif;?>
					<?//else:?>
						<select data-site="<?=$optionsSiteID?>" size="<?=$optionSize?>" <?=$optionController?> <?=$optionDisabled?> multiple name="<?=htmlspecialcharsbx($optionCode)."_".$optionsSiteID?>[]" >
							<?for($j = 0, $c = count($arr_keys); $j < $c; ++$j):?>
								<option value="<?=$arr_keys[$j]?>" <?if(in_array($arr_keys[$j], $optionVal)) echo "selected"?>><?=htmlspecialcharsbx((is_array($optionList[$arr_keys[$j]]) ? $optionList[$arr_keys[$j]]["TITLE"] : $optionList[$arr_keys[$j]]))?></option>
							<?endfor;?>
						</select>
				<?elseif($optionType == "textarea"):?>
					<textarea <?=$optionController?> <?=$optionDisabled?> rows="<?=$optionRows?>" cols="<?=$optionCols?>" name="<?=htmlspecialcharsbx($optionCode)."_".$optionsSiteID?>"><?=htmlspecialcharsbx($optionVal)?></textarea>
				<?elseif($optionType == "statictext"):?>
					<?=htmlspecialcharsbx($optionVal)?>
				<?elseif($optionType == "statichtml"):?>
					<?=$optionVal?>
				<?elseif($optionType == "file"):?>
					<?$val = unserialize(Option::get(self::moduleID, $optionCode, serialize(array()), $optionsSiteID));

					$arOption['MULTIPLE'] = 'N';
					if($optionCode == 'LOGO_IMAGE' || $optionCode == 'LOGO_IMAGE_WHITE'){
						$arOption['WIDTH'] = 394;
						$arOption['HEIGHT'] = 140;
					}
					elseif($optionCode == 'FAVICON_IMAGE'){
						$arOption['WIDTH'] = 16;
						$arOption['HEIGHT'] = 16;
					}
					elseif($optionCode == 'APPLE_TOUCH_ICON_IMAGE'){
						$arOption['WIDTH'] = 180;
						$arOption['HEIGHT'] = 180;
					}
					self::__ShowFilePropertyField($optionCode."_".$optionsSiteID, $arOption, $val);?>
				<?elseif($optionType === 'includefile'):?>
					<?
					if(!is_array($arOption['INCLUDEFILE'])){
						$arOption['INCLUDEFILE'] = array($arOption['INCLUDEFILE']);
					}
					foreach($arOption['INCLUDEFILE'] as $includefile){
						$includefile = str_replace('//', '/', str_replace('#SITE_DIR#', $arTab['SITE_DIR'].'/', $includefile));
						$includefile = str_replace('//', '/', str_replace('#TEMPLATE_DIR#', $arTab['TEMPLATE']['DIR'].'/', $includefile));
						if(strpos($includefile, '#') === false){
							$template = (isset($arOption['TEMPLATE']) && strlen($arOption['TEMPLATE']) ? 'include_area.php' : $arOption['TEMPLATE']);
							if(strpos($includefile, 'invis-counter') === false || $arOption['NO_EDITOR'] === 'Y')
							{
								
								$href = (!strlen($includefile) ? "javascript:;" : "javascript: new BX.CAdminDialog({'content_url':'/bitrix/admin/public_file_edit.php?site=".$arTab['SITE_ID']."&bxpublic=Y&from=includefile".($arOption['NO_EDITOR'] == 'Y' ? "&noeditor=Y" : "")."&templateID=".$arTab['TEMPLATE']['ID']."&path=".$includefile."&lang=".LANGUAGE_ID."&template=".$template."&subdialog=Y&siteTemplateId=".$arTab['TEMPLATE']['ID']."','width':'1009','height':'503'}).Show();");
								
							}
							

							else
							{
								$href = (!strlen($includefile) ? "javascript:;" : "javascript: new BX.CAdminDialog({'content_url':'/bitrix/admin/public_file_edit.php?site=".$arTab['SITE_ID']."&bxpublic=Y&from=includefile&noeditor=Y&templateID=".$arTab['TEMPLATE']['ID']."&path=".$includefile."&lang=".LANGUAGE_ID."&template=".$template."&subdialog=Y&siteTemplateId=".$arTab['TEMPLATE']['ID']."','width':'1009','height':'503'}).Show();");
							}
							?><a class="adm-btn" href="<?=$href?>" name="<?=htmlspecialcharsbx($optionCode)."_".$optionsSiteID?>" title="<?=GetMessage('OPTIONS_EDIT_BUTTON_TITLE')?>"><?=GetMessage('OPTIONS_EDIT_BUTTON_TITLE')?></a>&nbsp;<?
						}
					}
					?>
				<?endif;?>
			<?if(!$btable):?>
				</div>
			<?else:?>
				</td>
			<?endif;?>
		<?endif;?>
	<?}

	public static function getActualParamsValue($arTab, $arOption, $path, $field = 'ELEMENTS'){
		if(isset($arOption['LIST'])){
			$optionList = $arOption['LIST'];

			// get site template
			$arTemplate = self::GetSiteTemplate($arTab['SITE_ID']);
			if($arTemplate && $arTemplate['PATH'])
			{
				if($arPageBlocks = self::GetComponentTemplatePageBlocks($arTemplate['PATH'].$path))
				{
					foreach($arOption['LIST'] as $key_list => $arValue)
					{
						if(isset($arPageBlocks[$field][$key_list]))
							;
						else
							unset($arOption['LIST'][$key_list]);
					}
				}
				$optionList = $arOption['LIST'];
			}

			return $optionList;
		}
		else{
			return array();
		}
	}

	public static function CheckColor($strColor){
		$strColor = substr(str_replace('#', '', $strColor), 0, 6);
		$strColor = base_convert(base_convert($strColor, 16, 2), 2, 16);
		for($i = 0, $l = 6 - (function_exists('mb_strlen') ? mb_strlen($strColor) : strlen($strColor)); $i < $l; ++$i)
			$strColor = '0'.$strColor;
		return $strColor;
	}

	public static function UpdateFrontParametrsValues(){
		$arBackParametrs = self::GetBackParametrsValues(SITE_ID);

		if($arBackParametrs['THEME_SWITCHER'] === 'Y'){
			$preset = isset($_REQUEST['preset']) ? (strlen($preset = trim($_REQUEST['preset'])) ? $preset : false) : false;

			if(
				isset($_REQUEST['BASE_COLOR']) ||
				$preset
			){
				if(isset($_REQUEST['BASE_COLOR'])){
					if($_REQUEST['THEME'] === 'default')
					{
						if(self::$arParametrsList && is_array(self::$arParametrsList))
						{
							foreach(self::$arParametrsList as $blockCode => $arBlock)
							{
								$_SESSION['THEME'][SITE_ID] = [];

								if(isset($_SESSION['THEME_ACTION']))
								{
									$_SESSION['THEME_ACTION'][SITE_ID] = [];
								}
							}
						}
						Option::set(self::moduleID, "NeedGenerateCustomTheme", 'Y', SITE_ID);
					}
					else
					{
						if(self::$arParametrsList && is_array(self::$arParametrsList))
						{
							foreach(self::$arParametrsList as $blockCode => $arBlock)
							{
								if($arBlock['OPTIONS'] && is_array($arBlock['OPTIONS']))
								{
									foreach($arBlock['OPTIONS'] as $optionCode => $arOption)
									{
										if($arOption['THEME'] === 'Y')
										{
											if(isset($_REQUEST[$optionCode]))
											{
												if(
													$optionCode == 'BASE_COLOR_CUSTOM' ||
													$optionCode == 'MORE_COLOR_CUSTOM'
												){
													$_REQUEST[$optionCode] = self::CheckColor($_REQUEST[$optionCode]);
												}

												if(
													$_REQUEST[$optionCode] === 'CUSTOM' &&
													(
														$optionCode == 'BASE_COLOR' ||
														$optionCode == 'MORE_COLOR'
													)
												){
													Option::set(self::moduleID, "NeedGenerateCustomTheme", 'Y', SITE_ID);
												}

												if(isset($arOption['LIST']))
												{
													if($arOption['TYPE'] == 'multiselectbox') {
														if(!$_REQUEST[$optionCode]) {
															$_SESSION['THEME'][SITE_ID][$optionCode] = 'N';
														} else {
															$_SESSION['THEME'][SITE_ID][$optionCode] = $_REQUEST[$optionCode];
														}
													} else {
														if(isset($arOption['LIST'][$_REQUEST[$optionCode]]))
															$_SESSION['THEME'][SITE_ID][$optionCode] = $_REQUEST[$optionCode];

														else
															$_SESSION['THEME'][SITE_ID][$optionCode] = $arOption['DEFAULT'];
													}
												}
												else
												{
													$_SESSION['THEME'][SITE_ID][$optionCode] = $_REQUEST[$optionCode];
												}
												if($optionCode == 'ORDER_VIEW')
													self::ClearSomeComponentsCache(SITE_ID);

												$bAdditionalOptions = isset($arOption['ADDITIONAL_OPTIONS']) && $arOption['ADDITIONAL_OPTIONS'];
												$bToggleOptions = isset($arOption['TOGGLE_OPTIONS']) && $arOption['TOGGLE_OPTIONS'];

												if($bAdditionalOptions || $bToggleOptions)
												{
													if($arOption['LIST'])
													{
														foreach($arOption['LIST'] as $key => $arListOption)
														{
															if($arListOption['ADDITIONAL_OPTIONS']) //get additional params default value
															{
																foreach($arListOption['ADDITIONAL_OPTIONS'] as $key2 => $arListOption2)
																{
																	if($_REQUEST[$key2.'_'.$key])
																	{
																		$_SESSION['THEME'][SITE_ID][$key2.'_'.$key] = $_REQUEST[$key2.'_'.$key];
																	}
																	else
																	{
																		if($arListOption2['TYPE'] == 'checkbox')
																			$_SESSION['THEME'][SITE_ID][$key2.'_'.$key] = 'N';
																		else
																			$_SESSION['THEME'][SITE_ID][$key2.'_'.$key] = $arListOption2['DEFAULT'];
																	}
																}
															}

															if($arListOption['TOGGLE_OPTIONS']) //get toggle params default value
															{
																foreach($arListOption['TOGGLE_OPTIONS']['OPTIONS'] as $key2 => $arListOption2)
																{
																	if($_REQUEST[$key2.'_'.$key])
																	{
																		$_SESSION['THEME'][SITE_ID][$key2.'_'.$key] = $_REQUEST[$key2.'_'.$key];
																	}
																	else
																	{
																		if($arListOption2['TYPE'] == 'checkbox')
																			$_SESSION['THEME'][SITE_ID][$key2.'_'.$key] = 'N';
																		else
																			$_SESSION['THEME'][SITE_ID][$key2.'_'.$key] = $arListOption2['DEFAULT'];
																	}

																	if($arListOption2['ADDITIONAL_OPTIONS']) // addition options on toggles
																	{
																		foreach($arListOption2['ADDITIONAL_OPTIONS'] as $key3 => $arListOption3)
																		{
																			if($_REQUEST[$key3.'_'.$key])
																			{
																				$_SESSION['THEME'][SITE_ID][$key3.'_'.$key] = $_REQUEST[$key3.'_'.$key];
																			}
																			else
																			{
																				if($arListOption3['TYPE'] == 'checkbox')
																					$_SESSION['THEME'][SITE_ID][$key3.'_'.$key] = 'N';
																				else
																					$_SESSION['THEME'][SITE_ID][$key3.'_'.$key] = $arListOption3['DEFAULT'];
																			}
																		}
																	}
																}
															}
														}
													}
												}

												if(isset($arOption['SUB_PARAMS']) && $arOption['SUB_PARAMS']) //nested params
												{

													if($arOption['TYPE'] == 'selectbox' && isset($arOption['LIST']))
													{
														$propValue = $_SESSION['THEME'][SITE_ID][$optionCode];
														if($arOption['SUB_PARAMS'][$propValue])
														{
															foreach($arOption['SUB_PARAMS'][$propValue] as $subkey => $arSubvalue)
															{
																if($_REQUEST[$propValue.'_'.$subkey])
																	$_SESSION['THEME'][SITE_ID][$propValue.'_'.$subkey] = $_REQUEST[$propValue.'_'.$subkey];
																else
																{
																	if($arSubvalue['TYPE'] == 'checkbox')
																		$_SESSION['THEME'][SITE_ID][$propValue.'_'.$subkey] = 'N';
																	else
																		$_SESSION['THEME'][SITE_ID][$propValue.'_'.$subkey] = $arSubvalue['DEFAULT'];
																}

																//set default template index components
																if(isset($arSubvalue['TEMPLATE']) && $arSubvalue['TEMPLATE'])
																{

																	$code_tmp = $propValue.'_'.$subkey.'_TEMPLATE';
																	if($_REQUEST[$code_tmp])
																		$_SESSION['THEME'][SITE_ID][$code_tmp] = $_REQUEST[$code_tmp];
																	if($arSubvalue['TEMPLATE']['LIST'])
																	{
																		foreach($arSubvalue['TEMPLATE']['LIST'] as $keyS => $arListOption)
																		{
																			if($arListOption['ADDITIONAL_OPTIONS'])
																			{
																				foreach($arListOption['ADDITIONAL_OPTIONS'] as $keyS2 => $arListOption2)
																				{
																					if($_REQUEST[$propValue.'_'.$subkey.'_'.$keyS2.'_'.$keyS])
																					{
																						$_SESSION['THEME'][SITE_ID][$propValue.'_'.$subkey.'_'.$keyS2.'_'.$keyS] = $_REQUEST[$propValue.'_'.$subkey.'_'.$keyS2.'_'.$keyS];
																					}
																					else
																					{
																						if($arListOption2['TYPE'] == 'checkbox')
																							$_SESSION['THEME'][SITE_ID][$propValue.'_'.$subkey.'_'.$keyS2.'_'.$keyS] = 'N';
																						else
																							$_SESSION['THEME'][SITE_ID][$propValue.'_'.$subkey.'_'.$keyS2.'_'.$keyS] = $arListOption2['DEFAULT'];
																					}
																				}
																			}
																		}
																	}
																}
															}

															//sort order prop for main page
															$param = 'SORT_ORDER_'.$optionCode.'_'.$propValue;
															if(isset($_REQUEST[$param])){
																$_SESSION['THEME'][SITE_ID][$param] = $_REQUEST[$param] ?: '';
															}
														}
													}
												}


												if(isset($arOption['DEPENDENT_PARAMS']) && $arOption['DEPENDENT_PARAMS']) //dependent params
												{
													foreach($arOption['DEPENDENT_PARAMS'] as $key => $arSubOptions){
														if($arSubOptions['THEME'] == 'Y'){
															if($_REQUEST[$key]){
																$_SESSION['THEME'][SITE_ID][$key] = $_REQUEST[$key];
															}
															else{
																if($arSubOptions['TYPE'] == 'checkbox'){
																	if(isset($_SESSION['THEME_ACTION']) && (isset($_SESSION['THEME_ACTION'][SITE_ID][$key]) && $_SESSION['THEME_ACTION'][SITE_ID][$key])){
																		$_SESSION['THEME'][SITE_ID][$key] = $_SESSION['THEME_ACTION'][SITE_ID][$key];
																		unset($_SESSION['THEME_ACTION'][SITE_ID][$key]);
																	}
																	else{
																		$_SESSION['THEME'][SITE_ID][$key] = 'N';
																	}
																}
																else{
																	if(isset($_SESSION['THEME_ACTION']) && (isset($_SESSION['THEME_ACTION'][SITE_ID][$key]) && $_SESSION['THEME_ACTION'][SITE_ID][$key])){
																		$_SESSION['THEME'][SITE_ID][$key] = $_SESSION['THEME_ACTION'][SITE_ID][$key];
																		unset($_SESSION['THEME_ACTION'][SITE_ID][$key]);
																	}
																	else{
																		$_SESSION['THEME'][SITE_ID][$key] = $arSubOptions['DEFAULT'];
																	}
																}
															}
														}
													}
												}

												$bChanged = true;
											}
											else
											{
												if($arOption['TYPE'] == 'checkbox' && !$_REQUEST[$optionCode])
												{
													$_SESSION['THEME'][SITE_ID][$optionCode] = 'N';
													if(isset($arOption['DEPENDENT_PARAMS']) && $arOption['DEPENDENT_PARAMS']) //dependent params save
													{
														foreach($arOption['DEPENDENT_PARAMS'] as $key => $arSubOptions)
														{
															if($arSubOptions['THEME'] == 'Y')
															{
																if(isset($_SESSION['THEME'][SITE_ID][$key]))
																	$_SESSION['THEME_ACTION'][SITE_ID][$key] = $_SESSION['THEME'][SITE_ID][$key];
																else
																	$_SESSION['THEME_ACTION'][SITE_ID][$key] = $arBackParametrs[$key];
															}
														}
													}
												}

												if(isset($arOption['SUB_PARAMS']) && $arOption['SUB_PARAMS']) //nested params
												{

													if($arOption['TYPE'] == 'selectbox' && isset($arOption['LIST']))
													{
														$propValue = $_SESSION['THEME'][SITE_ID][$optionCode];
														if($arOption['SUB_PARAMS'][$propValue])
														{
															foreach($arOption['SUB_PARAMS'][$propValue] as $subkey => $arSubvalue)
															{
																if($_REQUEST[$propValue.'_'.$subkey])
																	$_SESSION['THEME'][SITE_ID][$propValue.'_'.$subkey] = $_REQUEST[$propValue.'_'.$subkey];
																else
																	$_SESSION['THEME'][SITE_ID][$propValue.'_'.$subkey] = 'N';
															}
														}
													}
												}
											}
										}
									}
								}
							}
						}
						if(isset($_REQUEST["backurl"]) && $_REQUEST["backurl"])
							LocalRedirect($_REQUEST["backurl"]);
					}

					if(
						isset($_SERVER["HTTP_REFERER"]) &&
						$_SERVER["HTTP_REFERER"]
					){
						LocalRedirect($_SERVER["HTTP_REFERER"]);
					}
				}
				elseif($preset){
					self::setFrontParametrsOfPreset($preset, SITE_ID);

					if(
						!isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
						strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
					){
						LocalRedirect($GLOBALS['APPLICATION']->GetCurPageParam('', array('preset')));
					}
				}
			}
		}
		else {
			// reset all SESSION values exclude THEME_VIEW_COLOR
			if (
				is_array($_SESSION['THEME']) &&
				is_array($_SESSION['THEME'][SITE_ID])
			) {
				$themeViewColor = $_SESSION['THEME'][SITE_ID]['THEME_VIEW_COLOR'] ?? 'DEFAULT';
				$_SESSION['THEME'][SITE_ID] = [
					'THEME_VIEW_COLOR' => $themeViewColor,
				];
			}
			if (
				is_array($_SESSION['THEME_ACTION']) &&
				is_array($_SESSION['THEME_ACTION'][SITE_ID])
			) {
				$_SESSION['THEME_ACTION'][SITE_ID] = [];
			}
		}
	}

	public static function GenerateMinCss($file){
		if(file_exists($file))
		{
			$content = @file_get_contents($file);
			if($content !== false)
			{
				$content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
				$content = str_replace(array("\r\n", "\r", "\n", "\t"), '', $content);
				$content = preg_replace('/ {2,}/', ' ', $content);
				$content = str_replace(array(' : ', ': ', ' :',), ':', $content);
				$content = str_replace(array(' ; ', '; ', ' ;'), ';', $content);
				$content = str_replace(array(' > ', '> ', ' >'), '>', $content);
				$content = str_replace(array(' + ', '+ ', ' +'), '+', $content);
				$content = str_replace(array(' { ', '{ ', ' {'), '{', $content);
				$content = str_replace(array(' } ', '} ', ' }'), '}', $content);
				$content = str_replace(array(' ( ', '( ', ' ('), '(', $content);
				$content = str_replace(array(' ) ', ') ', ' )'), ')', $content);
				$content = str_replace('and(', 'and (', $content);
				$content = str_replace(')li', ') li', $content);
				$content = str_replace(').', ') .', $content);
				@file_put_contents(dirname($file).'/'.basename($file, '.css').'.min.css', $content);
			}
		}
		return false;
	}

	public static function GenerateThemes(){
		$arBackParametrs = self::GetBackParametrsValues(SITE_ID);
		$arBaseColors = self::$arParametrsList['MAIN']['OPTIONS']['BASE_COLOR']['LIST'];
		$arMoreColors = self::$arParametrsList['MAIN']['OPTIONS']['MORE_COLOR']['LIST'];
		$isCustomTheme = $_SESSION['THEME'][SITE_ID]['BASE_COLOR'] === 'CUSTOM';
		$isCustomThemeMore = $_SESSION['THEME'][SITE_ID]['MORE_COLOR'] === 'CUSTOM';

		$baseColorCustom = '';
		$lastGeneratedBaseColorCustom = Option::get(self::moduleID, 'LastGeneratedBaseColorCustom', '', SITE_ID);
		if(isset(self::$arParametrsList['MAIN']['OPTIONS']['BASE_COLOR_CUSTOM'])){
			$baseColorCustom = $arBackParametrs['BASE_COLOR_CUSTOM'] = str_replace('#', '', $arBackParametrs['BASE_COLOR_CUSTOM']);
			if($arBackParametrs['THEME_SWITCHER'] === 'Y' && strlen($_SESSION['THEME'][SITE_ID]['BASE_COLOR_CUSTOM'])){
				$baseColorCustom = $_SESSION['THEME'][SITE_ID]['BASE_COLOR_CUSTOM'] = str_replace('#', '', $_SESSION['THEME'][SITE_ID]['BASE_COLOR_CUSTOM']);
			}
		}

		$bNeedGenerateAllThemes = Option::get(self::moduleID, 'NeedGenerateThemes', 'N', SITE_ID) === 'Y';
		$bNeedGenerateCustomTheme = Option::get(self::moduleID, 'NeedGenerateCustomTheme', 'N', SITE_ID) === 'Y';
		$bGenerateAll = self::devMode || $bNeedGenerateAllThemes;
		$bGenerateCustom = $bGenerateAll || $bNeedGenerateCustomTheme || ($arBackParametrs['THEME_SWITCHER'] === 'Y' && $isCustomTheme && strlen($baseColorCustom) && $baseColorCustom != $lastGeneratedBaseColorCustom);

		if(
			$arBaseColors &&
			is_array($arBaseColors) &&
			(
				$bGenerateAll ||
				$bGenerateCustom
			)
		){
			if(!class_exists('lessc'))
				include_once 'lessc.inc.php';

			$less = new lessc;
			try{
				if(defined('SITE_TEMPLATE_PATH')){
					$templateName = array_pop(explode('/', SITE_TEMPLATE_PATH));
				}

				foreach($arBaseColors as $colorCode => $arColor){
					if(($bCustom = ($colorCode == 'CUSTOM')) && $bGenerateCustom){
						$less->setVariables(array('bcolor' => (strlen($baseColorCustom) ? '#'.$baseColorCustom : $arBaseColors[self::$arParametrsList['MAIN']['OPTIONS']['BASE_COLOR']['DEFAULT']]['COLOR'])));
					}
					elseif($bGenerateAll){
						$less->setVariables(array('bcolor' => $arColor['COLOR']));
					}

					if($bGenerateAll || ($bCustom && $bGenerateCustom)){
						if(defined('SITE_TEMPLATE_PATH')){
							$themeDirPath = $_SERVER['DOCUMENT_ROOT'].SITE_TEMPLATE_PATH.'/themes/'.strToLower($colorCode.($colorCode !== 'CUSTOM' ? '' : '_'.SITE_ID)).'/';
							if(!is_dir($themeDirPath)){
								mkdir($themeDirPath, 0755, true);
							}
							$output = $less->compileFile(__DIR__.'/../../css/colors.less', $themeDirPath.'colors.css');
							if($output){
								if($bCustom){
									Option::set(self::moduleID, 'LastGeneratedBaseColorCustom', $baseColorCustom, SITE_ID);
								}

								self::GenerateMinCss($themeDirPath.'colors.css');
							}

							if($templateName && $templateName != self::templateName) {
								$themeDirPath = $_SERVER['DOCUMENT_ROOT'].'/bitrix/templates/'.self::templateName.'/themes/'.strToLower($colorCode.($colorCode !== 'CUSTOM' ? '' : '_'.SITE_ID)).'/';
								if(!is_dir($themeDirPath)){
									mkdir($themeDirPath, 0755, true);
								}
								$output = $less->compileFile(__DIR__.'/../../css/colors.less', $themeDirPath.'colors.css');
								if($output){
									self::GenerateMinCss($themeDirPath.'colors.css');
								}
							}
						}
					}
				}
			}
			catch(exception $e)
			{
				echo 'Fatal error: '.$e->getMessage();
				die();
			}

			if($bNeedGenerateAllThemes)
				Option::set(self::moduleID, "NeedGenerateThemes", 'N', SITE_ID);
			if($bNeedGenerateCustomTheme)
				Option::set(self::moduleID, "NeedGenerateCustomTheme", 'N', SITE_ID);
		}
	}

	public static function sendAsproBIAction($action = 'unknown') {
		if(CModule::IncludeModule('main')){

		}
	}

	public static function checkAjaxRequest(){
		return (
			(
				isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
				strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
			) ||
			(strtolower($_REQUEST['ajax']) == 'y' || strtolower($_REQUEST['ajax_get']) == 'y')
		);
	}

	public static function checkRequestBlock($block = ''){
		$cacheID = false;
		if ($block) {
			$context=\Bitrix\Main\Context::getCurrent();
			$request=$context->getRequest();

			if ($request->getQuery('BLOCK') == $block) {
				$url = $request->getRequestUri();
				preg_match('/PAGEN_(.+)=/', $url, $match);
				if ($numberPage = $match[1]) {
					$pagen = $request->getQuery('PAGEN_'.$numberPage) ?? $request->getQuery('PAGEN_1');
					$cacheID = $block.$pagen;
				}
			}
		}
		return $cacheID;
	}

	public static function correctInstall(){
		if(CModule::IncludeModule('main')){
			if(Option::get(self::moduleID, 'WIZARD_DEMO_INSTALLED') == 'Y'){
				require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/classes/general/wizard.php');
				@set_time_limit(0);
				/*if(!CWizardUtil::DeleteWizard(self::partnerName.':'.self::solutionName)){
					if(!DeleteDirFilesEx($_SERVER['DOCUMENT_ROOT'].'/bitrix/wizards/'.self::partnerName.'/'.self::solutionName.'/')){
						self::removeDirectory($_SERVER['DOCUMENT_ROOT'].'/bitrix/wizards/'.self::partnerName.'/'.self::solutionName.'/');
					}
				}*/

				UnRegisterModuleDependences('main', 'OnBeforeProlog', self::moduleID, __CLASS__, 'correctInstall');
				Option::set(self::moduleID, 'WIZARD_DEMO_INSTALLED', 'N');
			}
		}
	}

	protected static function getBitrixEdition(){
		$edition = 'UNKNOWN';

		if(CModule::IncludeModule('main')){
			include_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/classes/general/update_client.php');
			$arUpdateList = CUpdateClient::GetUpdatesList(($errorMessage = ''), 'ru', 'Y');
			if(array_key_exists('CLIENT', $arUpdateList) && $arUpdateList['CLIENT'][0]['@']['LICENSE']){
				$edition = $arUpdateList['CLIENT'][0]['@']['LICENSE'];
			}
		}

		return $edition;
	}

	protected static function removeDirectory($dir){
		if($objs = glob($dir.'/*')){
			foreach($objs as $obj){
				if(is_dir($obj)){
					self::removeDirectory($obj);
				}
				else{
					if(!@unlink($obj)){
						if(chmod($obj, 0777)){
							@unlink($obj);
						}
					}
				}
			}
		}
		if(!@rmdir($dir)){
			if(chmod($dir, 0777)){
				@rmdir($dir);
			}
		}
	}

	public static function get_file_info($fileID){
		$file = CFile::GetFileArray($fileID);
		$pos = strrpos($file['FILE_NAME'], '.');
		$file['FILE_NAME'] = substr($file['FILE_NAME'], $pos);
		if(!$file['FILE_SIZE']){
			// bx bug in some version
			$file['FILE_SIZE'] = filesize($_SERVER['DOCUMENT_ROOT'].$file['SRC']);
		}
		$frm = explode('.', $file['FILE_NAME']);
		$frm = $frm[1];
		if($frm == 'doc' || $frm == 'docx'){
			$type = 'doc';
		}
		elseif($frm == 'xls' || $frm == 'xlsx'){
			$type = 'xls';
		}
		elseif($frm == 'jpg' || $frm == 'jpeg'){
			$type = 'jpg';
		}
		elseif($frm == 'png'){
			$type = 'png';
		}
		elseif($frm == 'ppt'){
			$type = 'ppt';
		}
		elseif($frm == 'tif'){
			$type = 'tif';
		}
		elseif($frm == 'txt'){
			$type = 'txt';
		}
		elseif($frm == 'rtf'){
			$type = 'rtf';
		}
		elseif($frm == 'pdf'){
			$type = 'pdf';
		}
		else{
			$type = 'none';
		}
		return $arr = array('TYPE' => $type, 'FILE_SIZE' => $file['FILE_SIZE'], 'SRC' => $file['SRC'], 'DESCRIPTION' => $file['DESCRIPTION'], 'ORIGINAL_NAME' => $file['ORIGINAL_NAME']);
	}

	public static function filesize_format($filesize){
		$formats = array(GetMessage('CT_NAME_b'), GetMessage('CT_NAME_KB'), GetMessage('CT_NAME_MB'), GetMessage('CT_NAME_GB'), GetMessage('CT_NAME_TB'));
		$format = 0;
		while($filesize > 1024 && count($formats) != ++$format){
			$filesize = round($filesize / 1024, 1);
		}
		$formats[] = GetMessage('CT_NAME_TB');
		return $filesize.' '.$formats[$format];
	}

	public static function getChilds($input, &$start = 0, $level = 0){
		$arIblockItemsMD5 = array();

		if(!$level){
			$lastDepthLevel = 1;
			if($input && is_array($input)){
				foreach($input as $i => $arItem){
					if($arItem['DEPTH_LEVEL'] > $lastDepthLevel){
						if($i > 0){
							if(!$input[$i - 1]['IS_PARENT'])
								$input[$i - 1]['NO_PARENT'] = false;
							$input[$i - 1]['IS_PARENT'] = 1;
						}
					}
					$lastDepthLevel = $arItem['DEPTH_LEVEL'];
				}
			}
		}

		$childs = array();
		$count = count($input);
		for($i = $start; $i < $count; ++$i){
			$item = $input[$i];
			if(!isset($item)){
				continue;
			}
			if($level > $item['DEPTH_LEVEL'] - 1){
				break;
			}
			else{
				if(!empty($item['IS_PARENT'])){
					$i++;
					$item['CHILD'] = self::getChilds($input, $i, $level + 1);
					$i--;
				}

				$childs[] = $item;
			}
		}
		$start = $i;

		if(is_array($childs)){
			foreach($childs as $j => $item){
				$arAttributes = [];
				if($item['PARAMS']){
					$md5 = md5($item['TEXT'].$item['LINK'].$item['SELECTED'].$item['PERMISSION'].$item['ITEM_TYPE'].$item['IS_PARENT'].serialize($item['ADDITIONAL_LINKS']).serialize($item['PARAMS']));

					// check if repeat in one section chids list
					if(isset($arIblockItemsMD5[$md5][$item['PARAMS']['DEPTH_LEVEL']])){
						if(isset($arIblockItemsMD5[$md5][$item['PARAMS']['DEPTH_LEVEL']][$level]) || ($item['DEPTH_LEVEL'] === 1 && !$level)){
							unset($childs[$j]);
							continue;
						}
					}
					if(!isset($arIblockItemsMD5[$md5])){
						$arIblockItemsMD5[$md5] = array($item['PARAMS']['DEPTH_LEVEL'] => array($level => true));
					}
					else{
						$arIblockItemsMD5[$md5][$item['PARAMS']['DEPTH_LEVEL']][$level] = true;
					}
					
					foreach ($item["PARAMS"] as $key => $item) {
						if (!is_array($item) && strpos($key, 'attr_') !== false) {
							$keyTmp = self::normalizeValue(str_replace('attr_', '', $key));
							if ($item) {
								$arAttributs[] = $keyTmp."='".self::normalizeValue($item)."'";
							} else {
								$arAttributs[] = self::normalizeValue($keyTmp);
							}
						} elseif (!is_array($item) && strpos($item, 'attr_') !== false) {
							$arAttributs[] = str_replace('attr_', '', self::normalizeValue($item));
						}
					}
			
					if (count((array)$arAttributs)) {
						array_unshift($arAttributs, '');
					}
				}

				if (count((array)$arAttributs)) {
					$childs[$j]["ATTRIBUTE"] = implode(' ', (array)$arAttributs);
				} 

			}
		}

		if(!$level){
			$arIblockItemsMD5 = array();
		}

		return $childs;
	}

	public static function getChilds2($input, &$start = 0, $level = 0){
		static $arIblockItemsMD5 = array();

		if(!$level){
			$lastDepthLevel = 1;
			if($input && is_array($input)){
				foreach($input as $i => $arItem){
					if($arItem['DEPTH_LEVEL'] > $lastDepthLevel){
						if($i > 0){
							$input[$i - 1]['IS_PARENT'] = 1;
						}
					}
					$lastDepthLevel = $arItem['DEPTH_LEVEL'];
				}
			}
		}

		$childs = array();
		$count = count($input);
		for($i = $start; $i < $count; ++$i){
			$item = $input[$i];
			if(!isset($item)){
				continue;
			}
			if($level > $item['DEPTH_LEVEL'] - 1){
				break;
			}
			else{
				if(!empty($item['IS_PARENT'])){
					$i++;
					$item['CHILD'] = self::getChilds2($input, $i, $level+1);
					$i--;
				}

				$childs[] = $item;
			}
		}
		$start = $i;

		if(is_array($childs)){
			foreach($childs as $j => $item){
				$arAttributs = [];
				if($item['PARAMS']){
					$md5 = md5($item['TEXT'].$item['LINK'].$item['SELECTED'].$item['PERMISSION'].$item['ITEM_TYPE'].$item['IS_PARENT'].serialize($item['ADDITIONAL_LINKS']).serialize($item['PARAMS']));
					if(isset($arIblockItemsMD5[$md5][$item['PARAMS']['DEPTH_LEVEL']])){
						if(isset($arIblockItemsMD5[$md5][$item['PARAMS']['DEPTH_LEVEL']][$level]) || ($item['DEPTH_LEVEL'] === 1 && !$level)){
							unset($childs[$j]);
							continue;
						}
					}
					if(!isset($arIblockItemsMD5[$md5])){
						$arIblockItemsMD5[$md5] = array($item['PARAMS']['DEPTH_LEVEL'] => array($level => true));
					}
					else{
						$arIblockItemsMD5[$md5][$item['PARAMS']['DEPTH_LEVEL']][$level] = true;
					}
					
					foreach ($item["PARAMS"] as $key => $item) {
						if (!is_array($item) && strpos($key, 'attr_') !== false) {
							$keyTmp = self::normalizeValue(str_replace('attr_', '', $key));

							if ($item) {
								$arAttributs[] = $keyTmp."='".self::normalizeValue($item)."'";
							} else {
								$arAttributs[] = self::normalizeValue($keyTmp);
							}
						} elseif (!is_array($item) && strpos($item, 'attr_') !== false) {
							$arAttributs[] = str_replace('attr_', '', self::normalizeValue($item));
						}
					}
					if (count((array)$arAttributs)) {
						array_unshift($arAttributs, '');
					}
				}
				if (count((array)$arAttributs)) {
					$childs[$j]["ATTRIBUTE"] = implode(' ', (array)$arAttributs);
				}
			}
		}

		if($GLOBALS['arTheme']['USE_REGIONALITY']['VALUE'] === 'Y' && $GLOBALS['arTheme']['USE_REGIONALITY']['DEPENDENT_PARAMS']['REGIONALITY_FILTER_ITEM']['VALUE'] === 'Y' && $GLOBALS['arRegion']){
			if(is_array($childs)){
				foreach($childs as $i => $item){
					if($item['PARAMS'] && isset($item['PARAMS']['LINK_REGION'])){
						if($item['PARAMS']['LINK_REGION']){
							if(!in_array($GLOBALS['arRegion']['ID'], $item['PARAMS']['LINK_REGION'])){
								unset($childs[$i]);
							}
						}
						else{
							unset($childs[$i]);
						}
					}
				}
			}
		}

		if(!$level){
			$arIblockItemsMD5 = array();
		}

		return $childs;
	}

	public static function normalizeValue($value){
		$value = str_replace(["'", "\""],"", $value);
		$value = strip_tags($value);
		$value = htmlspecialcharsbx($value);

		return $value;
	}

	public static function sort_sections_by_field($arr, $name){
		$count = count($arr);
		for($i = 0; $i < $count; $i++){
			for($j = 0; $j < $count; $j++){
				if(strtoupper($arr[$i]['NAME']) < strtoupper($arr[$j]['NAME'])){
					$tmp = $arr[$i];
					$arr[$i] = $arr[$j];
					$arr[$j] = $tmp;
				}
			}
		}
		return $arr;
	}

	public static function getIBItems($prop, $checkNoImage){
		$arID = array();
		$arItems = array();
		$arAllItems = array();

		if($prop && is_array($prop)){
			foreach($prop as $reviewID){
				$arID[]=$reviewID;
			}
		}
		if($checkNoImage) $empty=false;
		$arItems = self::cacheElement(false, array('ID' => $arID, 'ACTIVE' => 'Y'));
		if($arItems && is_array($arItems)){
			foreach($arItems as $key => $arItem){
				if($checkNoImage){
					if(empty($arProject['PREVIEW_PICTURE'])){
						$empty=true;
					}
				}
				$arAllItems['ITEMS'][$key] = $arItem;
				if($arItem['DETAIL_PICTURE']) $arAllItems['ITEMS'][$key]['DETAIL'] = CFile::GetFileArray( $arItem['DETAIL_PICTURE'] );
				if($arItem['PREVIEW_PICTURE']) $arAllItems['ITEMS'][$key]['PREVIEW'] = CFile::ResizeImageGet( $arItem['PREVIEW_PICTURE'], array('width' => 425, 'height' => 330), BX_RESIZE_IMAGE_EXACT, true );
			}
		}
		if($checkNoImage) $arAllItems['NOIMAGE'] = 'YES';

		return $arAllItems;
	}

	public static function showBgImage($siteID, $arTheme){
		global $APPLICATION;
		if($arTheme['SHOW_BG_BLOCK'] == 'Y')
		{
			$arBanner = self::checkBgImage($siteID);

			if($arBanner)
			{
				$image = CFile::GetFileArray($arBanner['PREVIEW_PICTURE']);
				$class = 'bg_image_site opacity1';
				if($arBanner['PROPERTY_FIXED_BANNER_VALUE'] == 'Y')
					$class .= ' fixed';
				if(self::IsMainPage())
					$class .= ' opacity';
				echo '<span class=\''.$class.'\' style=\'background-image:url('.$image["SRC"].');\'></span>';

				global $showBgBanner;
				$showBgBanner = true;
			}
		}
		return true;
	}

	public static function checkBgImage($siteID){
		global $APPLICATION, $arRegion;
		static $arBanner;
		if($arBanner === NULL)
		{
			$bgIbockID = (CAllcorp3Cache::$arIBlocks[$siteID]['aspro_allcorp3_content']['aspro_allcorp3_bg_images'][0] ? CAllcorp3Cache::$arIBlocks[$siteID]['aspro_allcorp3_content']['aspro_allcorp3_bg_images'][0] : CAllcorp3Cache::$arIBlocks[$siteID]['aspro_allcorp3_adv']['aspro_allcorp3_bg_images'][0]);

			$arFilterBanner = array('IBLOCK_ID' => $bgIbockID, 'ACTIVE'=>'Y');

			if($arRegion && isset($arTheme['REGIONALITY_FILTER_ITEM']) && $arTheme['REGIONALITY_FILTER_ITEM']['VALUE'] == 'Y')
				$arFilterBanner['PROPERTY_LINK_REGION'] = $arRegion['ID'];

			$arItems = CAllcorp3Cache::CIBLockElement_GetList(array('SORT' => 'ASC', 'CACHE' => array('TAG' => $bgIbockID)), $arFilterBanner, false, false, array('ID', 'NAME', 'PREVIEW_PICTURE', 'PROPERTY_URL', 'PROPERTY_FIXED_BANNER', 'PROPERTY_URL_NOT_SHOW'));
			$arBanner = array();

			if($arItems)
			{
				$curPage = $APPLICATION->GetCurPage();
				foreach($arItems as $arItem)
				{
					if(isset($arItem['PROPERTY_URL_VALUE']) && $arItem['PREVIEW_PICTURE'])
					{
						if(!is_array($arItem['PROPERTY_URL_VALUE']))
							$arItem['PROPERTY_URL_VALUE'] = array($arItem['PROPERTY_URL_VALUE']);
						if($arItem['PROPERTY_URL_VALUE'])
						{
							foreach($arItem['PROPERTY_URL_VALUE'] as $url)
							{
								$url=str_replace('SITE_DIR', SITE_DIR, $url);
								if($arItem['PROPERTY_URL_NOT_SHOW_VALUE'])
								{
									if(!is_array($arItem['PROPERTY_URL_NOT_SHOW_VALUE']))
										$arItem['PROPERTY_URL_NOT_SHOW_VALUE'] = array($arItem['PROPERTY_URL_NOT_SHOW_VALUE']);
									foreach($arItem['PROPERTY_URL_NOT_SHOW_VALUE'] as $url_not_show)
									{
										$url_not_show=str_replace('SITE_DIR', SITE_DIR, $url_not_show);
										if(CSite::InDir($url_not_show))
											break 2;
									}
									foreach($arItem['PROPERTY_URL_NOT_SHOW_VALUE'] as $url_not_show)
									{
										$url_not_show = str_replace('SITE_DIR', SITE_DIR, $url_not_show);
										if(CSite::InDir($url_not_show))
										{
											// continue;
											break 2;
										}
										else
										{
											if(CSite::InDir($url))
											{
												$arBanner = $arItem;
												break;
											}
										}
									}
								}
								else
								{
									if(CSite::InDir($url))
									{
										$arBanner = $arItem;
										break;
									}
								}
							}
						}
					}
				}
			}
		}
		return $arBanner;
	}

	public static function getSectionChilds($PSID, &$arSections, &$arSectionsByParentSectionID, &$arItemsBySectionID, &$aMenuLinksExt, $bMenu = false){
		if($arSections && is_array($arSections)){
			foreach($arSections as $arSection){
				if($arSection['IBLOCK_SECTION_ID'] == $PSID){
					$bCheck = false;
					if (!$bMenu) {
						$arItem = array(
							$arSection['NAME'],
							$arSection['SECTION_PAGE_URL'],
							array(),
							array(
								'FROM_IBLOCK' => 1,
								'DEPTH_LEVEL' => $arSection['DEPTH_LEVEL'],
								'SORT' => $arSection['SORT'],
								'UF_TOP_SEO' => $arSection['UF_TOP_SEO'],
								'ICON' => $arSection['UF_SECTION_ICON'] ?? $arSection['UF_ICON'] ?? false,
								'TRANSPARENT_PICTURE' => $arSection['UF_TRANSPARENT_PICTURE'] ?? false,
							)
						);
						$arItem[3]['UF_TOP_SEO'] = $arSection['UF_TOP_SEO'];
						$arItem[3]['IS_PARENT'] = (isset($arItemsBySectionID[$arSection['ID']]) || isset($arSectionsByParentSectionID[$arSection['ID']]) ? 1 : 0);
						if($arSection["PICTURE"])
							$arItem[3]["PICTURE"]=$arSection["PICTURE"];
						if($arSection["UF_REGION"])
							$arItem[3]["LINK_REGION"]=$arSection["UF_REGION"];
						$bCheck = ($arItem[3]['IS_PARENT']);
					} else {
						$arItem = array(
							'TEXT' => $arSection['NAME'],
							'LINK' => $arSection['SECTION_PAGE_URL'],
							array(),
							'PARAMS' => array(
								'FROM_IBLOCK' => 1,
								'DEPTH_LEVEL' => $arSection['DEPTH_LEVEL'],
								'SORT' => $arSection['SORT'],
								'ID' => $arSection['ID'],
								'IBLOCK_ID' => $arSection['IBLOCK_ID'],
								'UF_TOP_SEO' => $arSection['UF_TOP_SEO'],
								'ICON' => $arSection['UF_SECTION_ICON'] ?? $arSection['UF_ICON'] ?? false,
								'TRANSPARENT_PICTURE' => $arSection['UF_TRANSPARENT_PICTURE'] ?? false,
							),
							'DEPTH_LEVEL' => $arSection['DEPTH_LEVEL']
						);
						$arItem['PARAMS']['UF_TOP_SEO'] = $arSection['UF_TOP_SEO'];
						$arItem['PARAMS']['IS_PARENT'] = $arItem['IS_PARENT'] = (isset($arItemsBySectionID[$arSection['ID']]) || isset($arSectionsByParentSectionID[$arSection['ID']]) ? 1 : 0);
						if($arSection["PICTURE"])
							$arItem['PARAMS']["PICTURE"]=$arSection["PICTURE"];
						if($arSection["UF_REGION"])
							$arItem['PARAMS']["LINK_REGION"]=$arSection["UF_REGION"];
						$bCheck = ($arItem['PARAMS']['IS_PARENT']);
					}
					$aMenuLinksExt[] = $arItem;
					if($bCheck){
						// subsections
						self::getSectionChilds($arSection['ID'], $arSections, $arSectionsByParentSectionID, $arItemsBySectionID, $aMenuLinksExt, $bMenu);
						// section elements
						if($arItemsBySectionID[$arSection['ID']] && is_array($arItemsBySectionID[$arSection['ID']])){
							foreach($arItemsBySectionID[$arSection['ID']] as $arItem){
								if(is_array($arItem['DETAIL_PAGE_URL'])){
									if(isset($arItem['CANONICAL_PAGE_URL'])){
										$arItem['DETAIL_PAGE_URL'] = $arItem['CANONICAL_PAGE_URL'];
									}
									else{
										$arItem['DETAIL_PAGE_URL'] = $arItem['DETAIL_PAGE_URL'][key($arItem['DETAIL_PAGE_URL'])];
									}
								}
								$arTmpLink = array();
								if($arItem['LINK_REGION']){
                                    $arTmpLink['LINK_REGION'] =  (array)$arItem['LINK_REGION'];
                                }elseif(array_key_exists('PROPERTY_LINK_REGION_VALUE', $arItem)){
                                    $arTmpLink['LINK_REGION'] = (array)$arItem['PROPERTY_LINK_REGION_VALUE'];
                                }

								$aMenuLinksExt[] = array(
									$arItem['NAME'],
									$arItem['DETAIL_PAGE_URL'],
									array(),
									array_merge(
										array(
											'FROM_IBLOCK' => 1,
											'DEPTH_LEVEL' => ($arSection['DEPTH_LEVEL'] + 1),
											'IS_ITEM' => 1,
											'SORT' => $arItem['SORT']
										),
										$arTmpLink
									)
								);
							}
						}
					}
				}
			}
		}
	}

	public static function isChildsSelected($arChilds){
		if($arChilds && is_array($arChilds)){
			foreach($arChilds as $arChild){
				if($arChild['SELECTED']){
					return true;
				}
			}
		}
		return false;
	}

	public static function SetJSOptions(){
		?>
		<script data-skip-moving="true">
			var solutionName = 'arAllcorp3Options';
			var arAsproOptions = window[solutionName] = ({});
		</script>
		<script src="<?=SITE_TEMPLATE_PATH.'/js/setTheme.php?site_id='.SITE_ID.'&site_dir='.SITE_DIR?>" data-skip-moving="true"></script>
		<script type='text/javascript'>
		var arBasketItems = {};
		if(arAsproOptions.SITE_ADDRESS)
			arAsproOptions.SITE_ADDRESS = arAsproOptions.SITE_ADDRESS.replace(/'/g, "");
		</script>
		<?
		Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID('options-block');
		self::checkBasketItems();
		// self::getCompareItems();
		Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID('options-block', '');
	}

	/*public static function getCompareItems(){
		//CATALOG_COMPARE_LIST
		if(!defined('ADMIN_SECTION') && !CSite::inDir(SITE_DIR.'/ajax/') && \CAllcorp3::GetFrontParametrValue('CATALOG_COMPARE') === 'Y'):?>
			<script>var arBasketItems = <?=CUtil::PhpToJSObject(self::checkCompareItems(), false)?>;</script>
		<?endif;
	}*/
	
	public static function checkCompareItems(){
		$arItems = [];
		if (isset($_SESSION['CATALOG_COMPARE_LIST']) && is_array($_SESSION['CATALOG_COMPARE_LIST'])) {
			$arTmpItems = reset($_SESSION['CATALOG_COMPARE_LIST']);
			if (isset($arTmpItems['ITEMS']) && is_array($arTmpItems['ITEMS'])) {
				$arItems = array_keys($arTmpItems['ITEMS']);
				unset($arTmpItems);
			}
		}
		return $arItems;
	}

	public static function __ShowFilePropertyField($name, $arOption, $values){
		global $bCopy, $historyId;

		CModule::IncludeModule('fileman');

		if(!is_array($values)){
			$values = array($values);
		}

		if($bCopy || empty($values)){
			$values = array('n0' => 0);
		}

		$optionWidth = $arOption['WIDTH'] ?? 200;
		$optionHeight = $arOption['HEIGHT'] ?? 100;


		if($arOption['MULTIPLE'] == 'N'){
			foreach($values as $key => $val){
				if(is_array($val)){
					$file_id = $val['VALUE'];
				}
				else{
					$file_id = $val;
				}
				if($historyId > 0){
					echo CFileInput::Show($name.'['.$key.']', $file_id,
						array(
							'IMAGE' => $arOption['IMAGE'],
							'PATH' => 'Y',
							'FILE_SIZE' => 'Y',
							'DIMENSIONS' => 'Y',
							'IMAGE_POPUP' => 'Y',
							'MAX_SIZE' => array(
								'W' => $optionWidth,
								'H' => $optionHeight,
							),
						)
					);
				}
				else{

					echo CFileInput::Show($name.'['.$key.']', $file_id,
						array(
							'IMAGE' => $arOption['IMAGE'],
							'PATH' => 'Y',
							'FILE_SIZE' => 'Y',
							'DIMENSIONS' => 'Y',
							'IMAGE_POPUP' => 'Y',
							'MAX_SIZE' => array(
							'W' => $optionWidth,
							'H' => $optionHeight,
							),
						),
						array(
							'upload' => true,
							'medialib' => true,
							'file_dialog' => true,
							'cloud' => true,
							'del' => true,
							'description' => isset($arOption['WITH_DESCRIPTION']) && $arOption['WITH_DESCRIPTION'] === 'Y',
						)
					);
				}
				break;
			}
		}
		else{
			$inputName = array();
			foreach($values as $key => $val){
				if(is_array($val)){
					$inputName[$name.'['.$key.']'] = $val['VALUE'];
				}
				else{
					$inputName[$name.'['.$key.']'] = $val;
				}
			}
			if($historyId > 0){
				echo CFileInput::ShowMultiple($inputName, $name.'[n#IND#]',
					array(
						'IMAGE' => $arOption['IMAGE'],
						'PATH' => 'Y',
						'FILE_SIZE' => 'Y',
						'DIMENSIONS' => 'Y',
						'IMAGE_POPUP' => 'Y',
						'MAX_SIZE' => array(
							'W' => $optionWidth,
							'H' => $optionHeight,
						),
					),
				false);
			}
			else{
				echo CFileInput::ShowMultiple($inputName, $name.'[n#IND#]',
					array(
						'IMAGE' => $arOption['IMAGE'],
						'PATH' => 'Y',
						'FILE_SIZE' => 'Y',
						'DIMENSIONS' => 'Y',
						'IMAGE_POPUP' => 'Y',
						'MAX_SIZE' => array(
							'W' => $optionWidth,
							'H' => $optionHeight,
						),
					),
				false,
					array(
						'upload' => true,
						'medialib' => true,
						'file_dialog' => true,
						'cloud' => true,
						'del' => true,
						'description' => isset($arOption['WITH_DESCRIPTION']) && $arOption['WITH_DESCRIPTION'] === 'Y',
					)
				);
			}
		}
	}

	public static function GetCompositeOptions(){
		if (class_exists('\Bitrix\Main\Composite\Helper')){
			if (method_exists('\Bitrix\Main\Composite\Helper', 'GetOptions')){
				return \Bitrix\Main\Composite\Helper::GetOptions();
			}
		}

		return array();
	}

	public static function IsCompositeEnabled(){
		if ($arHTMLCacheOptions = self::GetCompositeOptions()){
			if (method_exists('\Bitrix\Main\Composite\Helper', 'isOn')){
				if (\Bitrix\Main\Composite\Helper::isOn()){
					if (isset($arHTMLCacheOptions['AUTO_COMPOSITE']) && $arHTMLCacheOptions['AUTO_COMPOSITE'] === 'Y'){
						return 'AUTO_COMPOSITE';
					}
					else{
						return 'COMPOSITE';
					}
				}
			}
			else{
				if ($arHTMLCacheOptions['COMPOSITE'] === 'Y'){
					return 'COMPOSITE';
				}
			}
		}

		return false;
	}

	public static function EnableComposite($auto = false, $arHTMLCacheOptions = array()){
		if (class_exists('\Bitrix\Main\Composite\Helper')){
			if (method_exists('\Bitrix\Main\Composite\Helper', 'GetOptions')){
				$arHTMLCacheOptions = is_array($arHTMLCacheOptions) ? $arHTMLCacheOptions : array();
				$arHTMLCacheOptions = array_merge(\Bitrix\Main\Composite\Helper::GetOptions(), $arHTMLCacheOptions);

				$arHTMLCacheOptions['COMPOSITE'] = $arHTMLCacheOptions['COMPOSITE'] ?? 'Y';
				$arHTMLCacheOptions['AUTO_UPDATE'] = $arHTMLCacheOptions['AUTO_UPDATE'] ?? 'Y'; // standart mode
				$arHTMLCacheOptions['AUTO_UPDATE_TTL'] = $arHTMLCacheOptions['AUTO_UPDATE_TTL'] ?? '0'; // no ttl delay
				$arHTMLCacheOptions['AUTO_COMPOSITE'] = ($auto ? 'Y' : 'N'); // auto composite mode

				\Bitrix\Main\Composite\Helper::SetEnabled(true);
				\Bitrix\Main\Composite\Helper::SetOptions($arHTMLCacheOptions);
				bx_accelerator_reset();
			}
		}
	}

	public static function GetCurrentElementFilter(&$arVariables, &$arParams){
        $arFilter = array('IBLOCK_ID' => $arParams['IBLOCK_ID'], 'INCLUDE_SUBSECTIONS' => 'Y');
        if($arParams['CHECK_DATES'] == 'Y'){
            $arFilter = array_merge($arFilter, array('ACTIVE' => 'Y', 'SECTION_GLOBAL_ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y'));
        }
        if($arVariables['ELEMENT_ID']){
            $arFilter['ID'] = $arVariables['ELEMENT_ID'];
        }
        elseif(strlen($arVariables['ELEMENT_CODE'])){
            $arFilter['CODE'] = $arVariables['ELEMENT_CODE'];
        }
		if($arVariables['SECTION_ID']){
			$arFilter['SECTION_ID'] = ($arVariables['SECTION_ID'] ? $arVariables['SECTION_ID'] : false);
		}
		if($arVariables['SECTION_CODE']){
			$arFilter['SECTION_CODE'] = ($arVariables['SECTION_CODE'] ? $arVariables['SECTION_CODE'] : false);
		}
        if(!$arFilter['SECTION_ID'] && !$arFilter['SECTION_CODE']){
            unset($arFilter['SECTION_GLOBAL_ACTIVE']);
        }
        if(strlen($arParams['FILTER_NAME'])){
        	if($GLOBALS[$arParams['FILTER_NAME']]){
				$arFilter = array_merge($arFilter, $GLOBALS[$arParams['FILTER_NAME']]);
			}
        }
        return $arFilter;
    }

	public static function GetCurrentSectionFilter(&$arVariables, &$arParams){
		$arFilter = array('IBLOCK_ID' => $arParams['IBLOCK_ID']);
		if($arParams['CHECK_DATES'] == 'Y'){
			$arFilter = array_merge($arFilter, array('ACTIVE' => 'Y', 'GLOBAL_ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y'));
		}
		if($arVariables['SECTION_ID']){
			$arFilter['ID'] = $arVariables['SECTION_ID'];
		}
		if(strlen($arVariables['SECTION_CODE'])){
			$arFilter['CODE'] = $arVariables['SECTION_CODE'];
		}
		if(!$arVariables['SECTION_ID'] && !strlen($arFilter['CODE'])){
			$arFilter['ID'] = 0; // if section not found
		}
		if(
			$arParams['CACHE_GROUPS'] == 'Y' &&
			$GLOBALS['USER']
		){
			$arFilter['CHECK_PERMISSIONS'] = 'Y';
			$arFilter['GROUPS'] = $GLOBALS['USER']->GetGroups();
		}

		return $arFilter;
	}

	public static function GetCurrentSectionElementFilter(&$arVariables, &$arParams, $CurrentSectionID = false, $ShowAllSection = false){
		$arFilter = array('IBLOCK_ID' => $arParams['IBLOCK_ID'], 'INCLUDE_SUBSECTIONS' => 'N');

		if(isset($arParams['INCLUDE_SUBSECTIONS'])){
			$arFilter['INCLUDE_SUBSECTIONS'] = $arParams['INCLUDE_SUBSECTIONS'];
			if($arParams['INCLUDE_SUBSECTIONS'] == 'A'){
				$arFilter['SECTION_GLOBAL_ACTIVE'] = $arFilter['INCLUDE_SUBSECTIONS'] = 'Y';
			}
		}

		if($arParams['CHECK_DATES'] == 'Y'){
			$arFilter = array_merge($arFilter, array('ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y'));
		}

		$arFilter['SECTION_ID'] = ($CurrentSectionID !== false ? $CurrentSectionID : ($arVariables['SECTION_ID'] ? $arVariables['SECTION_ID'] : false));

		if(!$arFilter['SECTION_ID'] && $arVariables['SECTION_CODE']){
			$arFilter['SECTION_CODE'] = $arVariables['SECTION_CODE'];
		}

		// if(!$arFilter['SECTION_ID'] && !$arVariables['SECTION_CODE']){
		// 	unset($arFilter['SECTION_GLOBAL_ACTIVE']);
		// 	$arFilter['INCLUDE_SUBSECTIONS'] = 'N';
		// }

		if(isset($arParams['INCLUDE_SUBSECTIONS']) && $arParams['INCLUDE_SUBSECTIONS']=="Y" && isset($arFilter["SECTION_GLOBAL_ACTIVE"]) && $ShowAllSection){
			unset($arFilter['SECTION_GLOBAL_ACTIVE']);
		}

		if(strlen($arParams['FILTER_NAME'])){
			$GLOBALS[$arParams['FILTER_NAME']] = (array)$GLOBALS[$arParams['FILTER_NAME']];
			foreach($arUnsetFilterFields = array('SECTION_ID', 'SECTION_CODE', 'SECTION_ACTIVE', 'SECTION_GLOBAL_ACTIVE') as $filterUnsetField){
				foreach($GLOBALS[$arParams['FILTER_NAME']] as $filterField => $filterValue){
					if(($p = strpos($filterUnsetField, $filterField)) !== false && $p < 2){
						unset($GLOBALS[$arParams['FILTER_NAME']][$filterField]);
					}
				}
			}

			if($GLOBALS[$arParams['FILTER_NAME']]){
				$arFilter = array_merge($arFilter, $GLOBALS[$arParams['FILTER_NAME']]);
			}
		}
		return $arFilter;
	}

	public static function GetCurrentSectionSubSectionFilter(&$arVariables, &$arParams, $CurrentSectionID = false){
		$arFilter = array('IBLOCK_ID' => $arParams['IBLOCK_ID']);
		if($arParams['CHECK_DATES'] == 'Y'){
			$arFilter = array_merge($arFilter, array('ACTIVE' => 'Y', 'GLOBAL_ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y'));
		}

		$arFilter['SECTION_ID'] = ($CurrentSectionID !== false ? $CurrentSectionID : ($arVariables['SECTION_ID'] ? $arVariables['SECTION_ID'] : false));
		if(!$arFilter['SECTION_ID']){
			$arFilter['INCLUDE_SUBSECTIONS'] = 'N';
			$arFilter['DEPTH_LEVEL'] = '1';
			unset($arFilter['GLOBAL_ACTIVE']);
		}
		return $arFilter;
	}

	public static function GetIBlockAllElementsFilter(&$arParams){
		$arFilter = array('IBLOCK_ID' => $arParams['IBLOCK_ID'], 'INCLUDE_SUBSECTIONS' => 'Y');
		if($arParams['CHECK_DATES'] == 'Y'){
			$arFilter = array_merge($arFilter, array('ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y'));
		}
		if(strlen($arParams['FILTER_NAME']) && (array)$GLOBALS[$arParams['FILTER_NAME']]){
			$arFilter = array_merge($arFilter, (array)$GLOBALS[$arParams['FILTER_NAME']]);
		}
		return $arFilter;
	}

	public static function CheckSmartFilterSEF($arParams, $component){
		if($arParams['SEF_MODE'] === 'Y' && strlen($arParams['FILTER_URL_TEMPLATE']) && is_object($component)){
			$arVariables = $arDefaultUrlTemplates404 = $arDefaultVariableAliases404 = $arDefaultVariableAliases = array();
			$smartBase = ($arParams["SEF_URL_TEMPLATES"]["section"] ? $arParams["SEF_URL_TEMPLATES"]["section"] : "#SECTION_ID#/");
			$arParams["SEF_URL_TEMPLATES"]["smart_filter"] = $smartBase."filter/#SMART_FILTER_PATH#/apply/";
			$arComponentVariables = array("SECTION_ID", "SECTION_CODE", "ELEMENT_ID", "ELEMENT_CODE", "action");
			$engine = new CComponentEngine($component);
			$engine->addGreedyPart("#SECTION_CODE_PATH#");
			$engine->addGreedyPart("#SMART_FILTER_PATH#");
			$engine->setResolveCallback(array("CIBlockFindTools", "resolveComponentEngine"));
			$arUrlTemplates = CComponentEngine::MakeComponentUrlTemplates($arDefaultUrlTemplates404, $arParams["SEF_URL_TEMPLATES"]);
			$componentPage = $engine->guessComponentPath($arParams["SEF_FOLDER"], $arUrlTemplates, $arVariables);
			if($componentPage === 'smart_filter'){
				$arVariableAliases = CComponentEngine::MakeComponentVariableAliases($arDefaultVariableAliases404, $arParams["VARIABLE_ALIASES"]);
				CComponentEngine::InitComponentVariables($componentPage, $arComponentVariables, $arVariableAliases, $arVariables);
				return $arResult = array("FOLDER" => $arParams["SEF_FOLDER"], "URL_TEMPLATES" => $arUrlTemplates, "VARIABLES" => $arVariables, "ALIASES" => $arVariableAliases);
			}
		}

		return false;
	}

	public static function AddMeta($arParams = array()){
		self::$arMetaParams = array_merge((array)self::$arMetaParams, (array)$arParams);
	}

	public static function SetMeta(){
		global $APPLICATION, $arSite, $arRegion, $arTheme;

		$PageH1 = $APPLICATION->GetTitle();
		$PageMetaTitleBrowser = $APPLICATION->GetPageProperty('title');
		$DirMetaTitleBrowser = $APPLICATION->GetDirProperty('title');
		$PageMetaDescription = $APPLICATION->GetPageProperty('description');
		$DirMetaDescription = $APPLICATION->GetDirProperty('description');

		$bShowSiteName = (Option::get(self::moduleID, "HIDE_SITE_NAME_TITLE", "N") == "N");
		$site_name = $arSite['SITE_NAME'];
		if(!$bShowSiteName)
			$site_name = '';
		// set title
		if(!CSite::inDir(SITE_DIR.'index.php')){
			// var_dump($PageH1);
			if(!strlen($PageMetaTitleBrowser))
			{
				if(!strlen($DirMetaTitleBrowser)){
					$PageMetaTitleBrowser = $PageH1.((strlen($PageH1) && strlen($site_name)) ? ' - ' : '' ).$site_name;
					$APPLICATION->SetPageProperty('title', $PageMetaTitleBrowser);
				}
			}
			else
			{
				$PageMetaTitleBrowser .= (strlen($site_name) ? ' - ' : '' ).$site_name;
				$APPLICATION->SetPageProperty('title', $PageMetaTitleBrowser);
			}
		}
		else{
			if(!strlen($PageMetaTitleBrowser)){
				if(!strlen($DirMetaTitleBrowser)){
					$PageMetaTitleBrowser = $site_name.((strlen($site_name) && strlen($PageH1)) ? ' - ' : '' ).$PageH1;
					$APPLICATION->SetPageProperty('title', $PageMetaTitleBrowser);
				}
			}
		}

		// check Open Graph required meta properties
		$addr = (CMain::IsHTTPS() ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'];
		if(!strlen(self::$arMetaParams['og:title'])){
			self::$arMetaParams['og:title'] = $PageMetaTitleBrowser;
		}
		if(!strlen(self::$arMetaParams['og:type'])){
			self::$arMetaParams['og:type'] = 'website';
		}
		if(!strlen(self::$arMetaParams['og:image'])){
			self::$arMetaParams['og:image'] = $arTheme['LOGO_IMAGE']['VALUE']; // site logo
		}
		if(!strlen(self::$arMetaParams['og:url'])){
			self::$arMetaParams['og:url'] = $_SERVER['REQUEST_URI'];
		}
		if(!strlen(self::$arMetaParams['og:description'])){
			self::$arMetaParams['og:description'] = (strlen($PageMetaDescription) ? $PageMetaDescription : $DirMetaDescription);
		}

		foreach(self::$arMetaParams as $metaName => $metaValue){
			if(strlen($metaValue = strip_tags($metaValue))){
				$metaValue = str_replace('//', '/', $metaValue);
				if($metaName === 'og:image' || $metaName === 'og:url')
					$metaValue = $addr.$metaValue;
				$APPLICATION->AddHeadString('<meta property="'.$metaName.'" content="'.$metaValue.'" />', true);
				if($metaName === 'og:image'){
					$APPLICATION->AddHeadString('<link rel="image_src" href="'.$metaValue.'"  />', true);
				}
			}
		}

		if($arRegion)
		{
			$arTagSeoMarks = array();
			foreach($arRegion as $key => $value)
			{
				if(strpos($key, 'PROPERTY_REGION_TAG') !== false && strpos($key, '_VALUE_ID') === false)
				{
					$tag_name = str_replace(array('PROPERTY_', '_VALUE'), '', $key);
					$arTagSeoMarks['#'.$tag_name.'#'] = $key;
				}
			}

			if($arTagSeoMarks)
				CAllcorp3Regionality::addSeoMarks($arTagSeoMarks);
		}

	}

	public static function getSectionInheritedUF($arParams = array()){
		$arResult = array();

		if($arParams){
			$iblockId = $arParams['iblockId'] ?? false;
			$sectionId = $arParams['sectionId'] ?? false;

			$arParams['select'] = $arParams['select'] ?? array();
			$arSelect = is_array($arParams['select']) ? $arParams['select'] : array();
			$arSelect[] = 'ID';
			$arSelect[] = 'IBLOCK_ID';
			$arSelect[] = 'IBLOCK_SECTION_ID';

			$arParams['filter'] = $arParams['filter'] ?? array();
			$arFilter = is_array($arParams['filter']) ? $arParams['filter'] : array();

			$arParams['enums'] = $arParams['enums'] ?? array();
			$arEnums = is_array($arParams['enums']) ? $arParams['enums'] : array();

			if($sectionId && $arSelect){
				$arFilter['ID'] = $sectionId;

				if($iblockId){
					$arFilter['IBLOCK_ID'] = $iblockId;
				}
				elseif($arFilter['IBLOCK_ID']){
					$iblockId = $arFilter['IBLOCK_ID'];
				}

				$arSection = CAllcorp3Cache::CIBlockSection_GetList(
					array(
						'CACHE' => array(
							'MULTI' => 'N',
							'TAG' => CAllcorp3Cache::GetIBlockCacheTag($iblockId),
						)
					),
					$arFilter,
					false,
					$arSelect
				);

				if($arSection){
					foreach($arSelect as $i => $field){
						if(strpos($field, 'UF_') !== false){
							if( array_key_exists($field, $arSection) && $arSection[$field] ){
								unset($arSelect[$i]);

								if(in_array($field, $arEnums)){
									$dbRes = CUserFieldEnum::GetList(array(), array("ID" => $arSection[$field]));
									if($arValue = $dbRes->GetNext()){
										$arResult[$field] = $arValue['XML_ID'];
									}
								}
								else{
									$arResult[$field] = $arSection[$field];
								}
							}
						}
					}

					if(
						$arSelect &&
						$arSection['IBLOCK_SECTION_ID']
					){
						$arResult = array_merge($arResult, self::getSectionInheritedUF(
							array(
								'iblockId' => $arSection['IBLOCK_ID'],
								'sectionId' => $arSection['IBLOCK_SECTION_ID'],
								'select' => $arSelect,
								'filter' => $arParams['filter'],
								'enums' => $arParams['enums'],
							)
						));
					}
				}
			}
		}

		return $arResult;
	}

	public static function PrepareItemProps($arProps){
		if (is_array($arProps) && $arProps) {
			foreach ($arProps as $PCODE => $arProperty) {
				if (
					in_array($PCODE, array('PERIOD', 'TITLE_BUTTON', 'LINK_BUTTON', 'REDIRECT', 'LINK_PROJECTS', 'LINK_REVIEWS', 'DOCUMENTS', 'FORM_ORDER', 'FORM_QUESTION', 'PHOTOPOS', 'TASK_PROJECT', 'PHOTOS', 'LINK_COMPANY', 'GALLEY_BIG', 'LINK_SERVICES', 'LINK_GOODS', 'LINK_GOODS_FILTER', 'LINK_STAFF', 'LINK_SALE', 'LINK_FAQ', 'PRICE', 'PRICEOLD', 'LINK_NEWS', 'LINK_TIZERS', 'LINK_ARTICLES', 'LINK_STUDY', 'SEND_MESS', 'FORM_QUESTION_SIDE', 'INCLUDE_TEXT', 'POPUP_VIDEO', 'SHOW_ON_INDEX_PAGE', 'STATUS', 'ARTICLE', 'ECONOMY', 'PRICE', 'PRICE_OLD', 'PRICE_CURRENCY', 'FILTER_PRICE', 'CODE_TEXT', 'BEST_ITEM', 'BNR_TOP', 'BNR_TOP_UNDER_HEADER', 'BNR_TOP_COLOR', 'MAIN_COLOR', 'BNR_TOP_COLOR', 'HIT', 'BLOG_POST_ID', 'BLOG_COMMENTS_CNT', 'VIDEO_IFRAME', 'SALE_NUMBER', 'H3_GOODS', 'FILTER_URL', 'ONLY_ONE_PRICE', 'ICON', 'BUTTON1TEXT', 'BUTTON1LINK', 'BUTTON1TARGET', 'BUTTON1CLASS', 'BUTTON1COLOR', 'BUTTON2TEXT', 'BUTTON2LINK', 'BUTTON2TARGET', 'BUTTON2CLASS', 'BUTTON2COLOR', 'TARIF_ITEM')) ||
					(in_array($arProperty['PROPERTY_TYPE'], ['E', 'F', 'G'])) ||
					(in_array($arProperty['USER_TYPE'], ['Date', 'DateTime', 'video']))
				) {
					unset($arProps[$PCODE]);
				}
				elseif (!$arProperty["VALUE"]) {
					unset($arProps[$PCODE]);
				}
				elseif(
					strpos($PCODE, 'TARIF_PRICE') !== false ||
					strpos($PCODE, 'TARIF_PRICE_DISC') !== false ||
					strpos($PCODE, 'TARIF_PRICE_ONE') !== false ||
					strpos($PCODE, 'TARIF_PRICE_ECONOMY') !== false ||
					strpos($PCODE, 'FILTER_PRICE') !== false
				){
					unset($arProps[$PCODE]);
				}
			}
		} else {
			$arProps = array();
		}
		
		return $arProps;
	}

	public static function ShowCabinetLink($arOptions){
		$arDefaulOptions = array(
			'SHOW_SVG' => true,
			'CLASS_SVG' => '',
			'TEXT_LOGIN' => GetMessage('CABINET_LINK'),
			'TEXT_NO_LOGIN' => GetMessage('LOGIN'),
			'DROPDOWN_TOP' => false,
			'SHOW_MENU' => true,
		);
		$arOptions = array_merge($arDefaulOptions, $arOptions);

		global $APPLICATION;
		static $cabinet_call;
		$iCalledID = ++$cabinet_call;

		$userID = self::GetUserID();
		global $USER;

		Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID('cabinet-link'.$iCalledID);
		?>
		<!-- noindex -->
		<?if($userID):?>
			<a class="header-cabinet__link fill-theme-hover light-opacity-hover dark_link avt" title="<?=$arOptions['TEXT_LOGIN'];?>" href="<?=self::GetFrontParametrValue('PERSONAL_PAGE_URL') ? self::GetFrontParametrValue('PERSONAL_PAGE_URL') : SITE_DIR."cabinet/"?>">
				<?if($arOptions['SHOW_SVG']):?>
					<?=self::showIconSvg(' header-cabinet__icon banner-light-icon-fill menu-light-icon-fill', SITE_TEMPLATE_PATH.'/images/svg/User_black.svg', $arOptions['TEXT_LOGIN'], $arOptions['CLASS_SVG']);?>
				<?endif;?>
				<?if($arOptions['TEXT_LOGIN']):?>
					<span class="header-cabinet__name header__icon-name menu-light-text dark_link banner-light-text"><?=$arOptions['TEXT_LOGIN'];?></span>
				<?endif;?>
			</a>
			<?if($arOptions['SHOW_MENU']):?>
				<?@include(str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].'/'.SITE_DIR.'include/header/menu.cabinet_dropdown.php'));?>
			<?endif;?>
		<?else:?>
			<?$url = ((isset($_GET['backurl']) && $_GET['backurl']) ? $_GET['backurl'] : $APPLICATION->GetCurUri());?>
			<a class="header-cabinet__link fill-theme-hover light-opacity-hover dark_link animate-load" data-event="jqm" title="<?=$arOptions['TEXT_NO_LOGIN'] ?: GetMessage('LOGIN'); ?>" data-param-type="auth" data-param-backurl="<?=$url;?>" data-name="auth" href="<?=self::GetFrontParametrValue('PERSONAL_PAGE_URL') ? self::GetFrontParametrValue('PERSONAL_PAGE_URL') : SITE_DIR."cabinet/"?>">
				<?if($arOptions['SHOW_SVG']):?>
					<?=self::showIconSvg('cabinet banner-light-icon-fill menu-light-icon-fill', SITE_TEMPLATE_PATH.'/images/svg/Lock_black.svg', $arOptions['TEXT_NO_LOGIN'], $arOptions['CLASS_SVG']);?>
				<?endif;?>
				<?if($arOptions['TEXT_NO_LOGIN']):?>
					<span class="header-cabinet__name header__icon-name menu-light-text dark_link banner-light-text"><?=$arOptions['TEXT_NO_LOGIN']?></span>
				<?endif;?>
			</a>
		<?endif;?>
		<!-- /noindex -->
		<?
		Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID('cabinet-link'.$iCalledID);
	}

	public static function ShowPrintLink($txt=''){
		$html = '';

		$arTheme = self::GetFrontParametrsValues(SITE_ID);
		if($arTheme['PRINT_BUTTON'] == 'Y')
		{
			if(!$txt)
				$txt = GetMessage('PRINT_LINK');
			$html = '<div class="print-link"><i class="icon"><svg id="Print.svg" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path class="cls-1" d="M1553,287h-2v3h-8v-3h-2a2,2,0,0,1-2-2v-5a2,2,0,0,1,2-2h2v-4h8v4h2a2,2,0,0,1,2,2v5A2,2,0,0,1,1553,287Zm-8,1h4v-4h-4v4Zm4-12h-4v2h4v-2Zm4,4h-12v5h2v-3h8v3h2v-5Z" transform="translate(-1539 -274)"/></svg></i>';
			if($txt)
				$html .= '<span class="text">'.$txt.'</span>';
			$html .= '</div>';
		}
		return $html;
	}

	public static function ShowBasketLink($class_link = 'top-btn hover', $class_icon = '', $txt = '', $show_price = false){
		static $basket_call;
		$iCalledID = ++$basket_call;
		Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID('basket-link'.$iCalledID);

		$arTheme = self::GetFrontParametrsValues(SITE_ID);

		if(
			$arTheme['ORDER_VIEW'] == 'Y' &&
			(
				$arTheme['ORDER_BASKET_VIEW'] == 'HEADER' &&
				(
					!self::IsBasketPage($arTheme['BASKET_PAGE_URL']) &&
					!self::IsOrderPage($arTheme['ORDER_PAGE_URL'])
				)
			)
		){
			$type_svg = ($class_icon ? '_'.$class_icon : '');
			$arItems = self::getBasketItems();

			$summ = $count = 0;
			if($arItems){
				foreach($arItems as $arItem){
					if(
						!($arItem['ID']) ||
						!strlen($arItem['NAME'])
					){
						continue;
					}

					++$count;

					if(strlen(trim($arItem['PROPERTY_PRICE_VALUE']))){
						$summ += floatval(str_replace(' ', '', $arItem['PROPERTY_FILTER_PRICE_VALUE'])) * $arItem['QUANTITY'];
					}
				}
			}

			$bEmptyBasket = !$count;
			$title_text = $bEmptyBasket ? GetMessage('EMPTY_BASKET') : GetMessage('TITLE_BASKET', array('#SUMM#' => self::FormatSumm($summ, 1)));
			?>
			<div class="basket top">
				<!-- noindex -->
				<a rel="nofollow" title="<?=$title_text?>" href="<?=$arTheme['BASKET_PAGE_URL']?>" class="fill-theme-hover light-opacity-hover <?=$arTheme['ORDER_BASKET_VIEW']?> <?=$class_link.' '.$class_icon?>">
					<span class="js-basket-block header-cart__inner <?=($bEmptyBasket ? 'header-cart__inner--empty' : '')?>">
						<?=self::showIconSvg('basket banner-light-icon-fill menu-light-icon-fill', SITE_TEMPLATE_PATH.'/images/svg/Basket'.$type_svg.'_black.svg', '', $class_icon)?>
						<span class="header-cart__count bg-more-theme count<?=($bEmptyBasket ? ' empted' : '')?>"><?=$count?></span>
					</span>

					<?if(strlen($txt)):?>
						<span class="header__icon-name header-cart__name dark_link menu-light-text banner-light-text"><?=$txt?></span>
					<?endif;?>
				</a>
				<div class="basket-dropdown"></div>
				<!-- /noindex -->
			</div>
		<?
		}
		Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID('basket-link'.$iCalledID);
	}
	
	public static function ShowCompareLink($class_link = 'top-btn hover', $class_icon = '', $txt = '', $bForceShow = false){
		static $compare_call;
		$iCalledID = ++$compare_call;
		Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID('compare-link'.$iCalledID);

		$arTheme = self::GetFrontParametrsValues(SITE_ID);

		if ($arTheme['ORDER_BASKET_VIEW'] != 'FLY' || $bForceShow) {?>
			<?$GLOBALS['APPLICATION']->IncludeComponent("bitrix:main.include", ".default",
				array(
					"COMPONENT_TEMPLATE" => ".default",
					"PATH" => SITE_DIR."ajax/show_compare_preview_top.php",
					"AREA_FILE_SHOW" => "file",
					"AREA_FILE_SUFFIX" => "",
					"AREA_FILE_RECURSIVE" => "Y",
					"CLASS_LINK" => $class_link,
					"CLASS_ICON" => $class_icon,
					"MESSAGE" => $txt,
					"FROM_MODULE" => "Y",
					"EDIT_TEMPLATE" => "standard.php"
				),
				false, array('HIDE_ICONS' => 'Y')
			);?>
		<?}
		Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID('compare-link'.$iCalledID);
	}

	public static function ShowMobileMenuCabinet(){
		static $mcabinet_call;
		global $APPLICATION, $arTheme;

		$iCalledID = ++$mcabinet_call;
		Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID('mobilemenu__cabinet'.$iCalledID);
		?>
		<?if($arTheme['CABINET']['VALUE'] === 'Y'):?>
			<?$APPLICATION->IncludeComponent(
				"bitrix:menu",
				"cabinet_mobile",
				Array(
					"COMPONENT_TEMPLATE" => "cabinet_mobile",
					"MENU_CACHE_TIME" => "3600000",
					"MENU_CACHE_TYPE" => "A",
					"MENU_CACHE_USE_GROUPS" => "Y",
					"MENU_CACHE_GET_VARS" => array(
					),
					"DELAY" => "N",
					"MAX_LEVEL" => "4",
					"ALLOW_MULTI_SELECT" => "Y",
					"ROOT_MENU_TYPE" => "cabinet",
					"CHILD_MENU_TYPE" => "left",
					"USE_EXT" => "Y"
				)
			);?>
		<?endif;?>
		<?
		Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID('mobilemenu__cabinet'.$iCalledID);
	}

	public static function ShowMobileMenuBasket(){
		static $mbasket_call;
		$iCalledID = ++$mbasket_call;
		Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID('mobilemenu__cart'.$iCalledID);

		$arTheme = self::GetFrontParametrsValues(SITE_ID);

		if(
			$arTheme['ORDER_VIEW'] == 'Y'
		){
			$userID = self::GetUserID();
			$arItems = ((isset($_SESSION[SITE_ID][$userID]['BASKET_ITEMS']) && is_array($_SESSION[SITE_ID][$userID]['BASKET_ITEMS']) && $_SESSION[SITE_ID][$userID]['BASKET_ITEMS']) ? $_SESSION[SITE_ID][$userID]['BASKET_ITEMS'] : array());

			$summ = $count = 0;
			if($arItems){
				foreach($arItems as $arItem){
					if(
						!($arItem['ID']) ||
						!strlen($arItem['NAME'])
					){
						continue;
					}

					++$count;

					if(strlen(trim($arItem['PROPERTY_PRICE_VALUE']))){
						$summ += floatval(str_replace(' ', '', $arItem['PROPERTY_FILTER_PRICE_VALUE'])) * $arItem['QUANTITY'];
					}
				}
			}

			$bEmptyBasket = !$count;
			$title_text = $bEmptyBasket ? GetMessage('EMPTY_BASKET') : GetMessage('TITLE_BASKET', array('#SUMM#' => self::FormatSumm($summ, 1)));
			?>
			<div class="mobilemenu__menu mobilemenu__menu--cart">
				<ul class="mobilemenu__menu-list">
					<li class="mobilemenu__menu-item mobilemenu__menu-item--with-icon<?=(CAllcorp3::IsBasketPage() ? ' mobilemenu__menu-item--selected' : '')?>">
						<div class="link-wrapper bg-opacity-theme-parent-hover fill-theme-parent-all color-theme-parent-all basket">
							<a class="dark_link" href="<?=$arTheme['BASKET_PAGE_URL']?>" rel="nofollow" title="<?=$title_text?>">
								<span class="js-basket-block header-cart__inner mobilemenu__menu-item-svg <?=($bEmptyBasket ? 'header-cart__inner--empty' : '')?>">
									<?=self::showIconSvg('basket fill-theme-target', SITE_TEMPLATE_PATH."/images/svg/Basket_black.svg");?>
									<span class="header-cart__count bg-more-theme count<?=($bEmptyBasket ? ' empted' : '')?>"><?=$count?></span>
								</span>
								<span class="font_15"><?=GetMessage('BASKET')?></span>
							</a>
						</div>
					</li>
				</ul>
			</div>
			<?
		}

		Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID('mobilemenu__cart'.$iCalledID);
	}
	
	public static function ShowMobileMenuCompare(){
		static $mcompare_call;
		$iCalledID = ++$mcompare_call;
		Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID('mobilemenu__compare'.$iCalledID);

		$arTheme = self::GetFrontParametrsValues(SITE_ID);

		if(
			$arTheme['CATALOG_COMPARE'] == 'Y'
		){
			$userID = self::GetUserID();
			$arItems = self::checkCompareItems();
			$count = count($arItems);
			$bEmptyCompare = !$count;
			$title_text = GetMessage('CATALOG_COMPARE_ELEMENTS_ALL');
			?>
			<div class="mobilemenu__menu mobilemenu__menu--compare">
				<ul class="mobilemenu__menu-list">
					<li class="mobilemenu__menu-item mobilemenu__menu-item--with-icon<?=(CAllcorp3::IsComparePage() ? ' mobilemenu__menu-item--selected' : '')?>">
						<div class="link-wrapper bg-opacity-theme-parent-hover fill-theme-parent-all color-theme-parent-all fill-use-888 fill-theme-use-svg-hover">
							<a class="icon-block-with-counter dark_link" href="<?=$arTheme['COMPARE_PAGE_URL']?>" rel="nofollow" title="<?=$title_text?>">
								<span class="icon-block-with-counter__inner js-compare-block mobilemenu__menu-item-svg <?=(!$bEmptyCompare ? 'icon-block-with-counter--count' : '')?>">
									<?=self::showSpriteIconSvg(SITE_TEMPLATE_PATH."/images/svg/catalog/item_icons.svg#compare", "compare", ['WIDTH' => 14,'HEIGHT' => 18]);?>
									<span class="icon-count icon-count--compare bg-more-theme count<?=($bEmptyCompare ? ' empted' : '')?>"><?=$count?></span>
								</span>
								<span class="font_15"><?=GetMessage('COMPARE_TEXT')?></span>
							</a>
						</div>
					</li>
				</ul>
			</div>
			<?
		}

		Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID('mobilemenu__compare'.$iCalledID);
	}

	public static function ShowMobileMenuPhones($arOptions = array()){
		static $mphones_call;
		global $arRegion, $arTheme;

		$arDefaulOptions = array(
			'CLASS' => '',
			'CALLBACK' => true,
		);
		$arOptions = array_merge($arDefaulOptions, $arOptions);

		$arBackParametrs = self::GetBackParametrsValues(SITE_ID);
		$iCountPhones = ($arRegion ? count($arRegion['PHONES']) : $arBackParametrs['HEADER_PHONES']);

		if($iCountPhones){
			$phone = ($arRegion ? $arRegion['PHONES'][0] : $arBackParametrs['HEADER_PHONES_array_PHONE_VALUE_0']);
			$href = 'tel:'.str_replace(array(' ', '-', '(', ')'), '', $phone);
			if(!strlen($href)){
				$href = 'javascript:;';
			}
			$description = ($arRegion ? $arRegion['PROPERTY_PHONES_DESCRIPTION'][0] : $arBackParametrs['HEADER_PHONES_array_PHONE_DESCRIPTION_0']);
			?>
			<li class="mobilemenu__menu-item mobilemenu__menu-item--with-icon mobilemenu__menu-item--parent">
				<div class="link-wrapper bg-opacity-theme-parent-hover fill-theme-parent-all color-theme-parent-all">
					<a class="dark_link" href="<?=$href?>" rel="nofollow">
						<?=CAllcorp3::showIconSvg('phone mobilemenu__menu-item-svg fill-theme-target', SITE_TEMPLATE_PATH."/images/svg/Phone_big.svg");?>
						<span class="font_18"><?=$phone?></span>
						<?if(strlen($description)):?>
							<span class="font_12 color_999 phones__phone-descript"><?=$description?></span>
						<?endif;?>
						<?=CAllcorp3::showIconSvg(' down menu-arrow bg-opacity-theme-target fill-theme-target fill-dark-light-block', SITE_TEMPLATE_PATH.'/images/svg/Triangle_right.svg', '', '', true, false);?>
					</a>
					<span class="toggle_block"></span>
				</div>
				<ul class="mobilemenu__menu-dropdown dropdown">
					<li class="mobilemenu__menu-item mobilemenu__menu-item--back">
						<div class="link-wrapper stroke-theme-parent-all colored_theme_hover_bg-block animate-arrow-hover color-theme-parent-all">
							<a class="arrow-all arrow-all--wide stroke-theme-target" href="" rel="nofollow">
								<?=CAllcorp3::showIconSvg(' arrow-all__item-arrow', SITE_TEMPLATE_PATH.'/images/svg/Arrow_lg.svg');?>
								<div class="arrow-all__item-line colored_theme_hover_bg-el"></div>
							</a>
						</div>
					</li>
					<li class="mobilemenu__menu-item mobilemenu__menu-item--title">
						<div class="link-wrapper">
							<a class="dark_link" href="">
								<span class="font_18 font_bold"><?=Loc::getMessage('ALLCORP3_T_MENU_CALLBACK')?></span>
							</a>
						</div>
					</li>
					<?for($i = 0; $i < $iCountPhones; ++$i):?>
						<?
						$phone = ($arRegion ? $arRegion['PHONES'][$i] : $arBackParametrs['HEADER_PHONES_array_PHONE_VALUE_'.$i]);
						$href = 'tel:'.str_replace(array(' ', '-', '(', ')'), '', $phone);
						if(!strlen($href)){
							$href = 'javascript:;';
						}
						$description = ($arRegion ? $arRegion['PROPERTY_PHONES_DESCRIPTION'][$i] : $arBackParametrs['HEADER_PHONES_array_PHONE_DESCRIPTION_'.$i]);
						?>
						<li class="mobilemenu__menu-item">
							<div class="link-wrapper bg-opacity-theme-parent-hover fill-theme-parent-all">
								<a class="dark_link phone" href="<?=$href?>" rel="nofollow">
									<span class="font_18"><?=$phone?></span>
									<?if(strlen($description)):?>
										<span class="font_12 color_999 phones__phone-descript"><?=$description?></span>
									<?endif;?>
								</a>
							</div>
						</li>
					<?endfor;?>

					<?if($arOptions['CALLBACK']):?>
						<li class="mobilemenu__menu-item mobilemenu__menu-item--callback">
							<div class="animate-load btn btn-default btn-transparent-border btn-wide" data-event="jqm" data-param-id="<?=self::getFormID("aspro_allcorp3_callback");?>" data-name="callback">
								<?=GetMessage('CALLBACK')?>
							</div>
						</li>
					<?endif;?>
				</ul>
			</li>
			<?
		}

		if($arRegion){
			Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID('mobilemenu__phone'.$iCalledID);
		}
	}

	public static function ShowMobileMenuRegions(){
		static $mregions_call;
		global $APPLICATION, $arRegion, $arRegions;

		if($arRegion){
			$type_regions = self::GetFrontParametrValue('REGIONALITY_TYPE');

			$arRegions = CAllcorp3Regionality::getRegions();
			$regionID = ($arRegion ? $arRegion['ID'] : '');
			$iCountRegions = count($arRegions);

			$iCalledID = ++$mregions_call;
			Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID('mobilemenu__region'.$iCalledID);
			?>
			<!-- noindex -->
			<div class="mobilemenu__menu mobilemenu__menu--regions">
				<ul class="mobilemenu__menu-list">
					<li class="mobilemenu__menu-item mobilemenu__menu-item--with-icon mobilemenu__menu-item--parent">
						<div class="link-wrapper bg-opacity-theme-parent-hover fill-theme-parent-all color-theme-parent-all">
							<a class="dark_link" href="" title="<?=htmlspecialcharsbx($arSite['LANG'])?>">
								<?//=self::showIconSvg(' mobilemenu__menu-item-svg fill-theme-target', SITE_TEMPLATE_PATH.'/images/svg/region.svg');?>
								<?=self::showIconSvg('region mobilemenu__menu-item-svg fill-theme-target', SITE_TEMPLATE_PATH.'/images/svg/region_big.svg');?>
								<span class="font_15"><?=$arRegion['NAME']?></span>
								<?=CAllcorp3::showIconSvg(' down menu-arrow bg-opacity-theme-target fill-theme-target fill-dark-light-block', SITE_TEMPLATE_PATH.'/images/svg/Triangle_right.svg', '', '', true, false);?>
							</a>
							<span class="toggle_block"></span>
						</div>
						<?
						$host = (CMain::IsHTTPS() ? 'https://' : 'http://');
						$uri = $APPLICATION->GetCurUri();
						?>
						<ul class="mobilemenu__menu-dropdown dropdown">
							<li class="mobilemenu__menu-item mobilemenu__menu-item--back">
								<div class="link-wrapper stroke-theme-parent-all colored_theme_hover_bg-block animate-arrow-hover color-theme-parent-all">
									<a class="arrow-all arrow-all--wide stroke-theme-target" href="" rel="nofollow">
										<?=CAllcorp3::showIconSvg(' arrow-all__item-arrow', SITE_TEMPLATE_PATH.'/images/svg/Arrow_lg.svg');?>
										<div class="arrow-all__item-line colored_theme_hover_bg-el"></div>
									</a>
								</div>
							</li>
							<li class="mobilemenu__menu-item mobilemenu__menu-item--title">
								<div class="link-wrapper">
									<a class="dark_link" href="">
										<span class="font_18 font_bold"><?=Loc::getMessage('ALLCORP3_T_MENU_REGIONS')?></span>
									</a>
								</div>
							</li>
							<?foreach($arRegions as $arItem):?>
								<?
								$href = $uri;
								if($arItem['PROPERTY_MAIN_DOMAIN_VALUE'] && $type_regions == 'SUBDOMAIN'){
									$href = $host.$arItem['PROPERTY_MAIN_DOMAIN_VALUE'].$uri;
								}
								?>
								<li class="mobilemenu__menu-item mobilemenu__menu-item--city<?=($arItem['ID'] === $arRegion['ID'] ? ' mobilemenu__menu-item--selected' : '')?>">
									<div class="link-wrapper bg-opacity-theme-parent-hover fill-theme-parent-all">
										<a class="dark_link" rel="nofollow" href="<?=$href?>" data-id="<?=$arItem['ID']?>">
											<span class="font_15"><?=$arItem['NAME']?></span>
										</a>
									</div>
								</li>
							<?endforeach;?>
						</ul>
					</li>
				</ul>
			</div>
			<!-- /noindex -->
			<?
			Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID('mobilemenu__region'.$iCalledID);
		}
	}

	public static function ShowTopDetailBanner($arResult, $arParams){
		$bg = ((isset($arResult['PROPERTIES']['BNR_TOP_BG']) && $arResult['PROPERTIES']['BNR_TOP_BG']['VALUE']) ? CFile::GetPath($arResult['PROPERTIES']['BNR_TOP_BG']['VALUE']) : SITE_TEMPLATE_PATH.'/images/top-bnr.jpg');
		$bShowBG = (isset($arResult['PROPERTIES']['BNR_TOP_IMG']) && $arResult['PROPERTIES']['BNR_TOP_IMG']['VALUE']);
		$title = ($arResult['IPROPERTY_VALUES'] && strlen($arResult['IPROPERTY_VALUES']['ELEMENT_PAGE_TITLE']) ? $arResult['IPROPERTY_VALUES']['ELEMENT_PAGE_TITLE'] : $arResult['NAME']);
		$text_color_style = ((isset($arResult['PROPERTIES']['CODE_TEXT']) && $arResult['PROPERTIES']['CODE_TEXT']['VALUE']) ? 'style="color:'.$arResult['PROPERTIES']['CODE_TEXT']['VALUE'].'"' : '');
		$bLanding = (isset($arResult['IS_LANDING']) && $arResult['IS_LANDING'] == 'Y');
		?>
	<?}

	public static function checkContentBlock($file, $prop = 'PROPERTY_ADDRESS_VALUE'){
		global $arRegion;
		if((CAllcorp3::checkContentFile($file) && !$arRegion) || ($arRegion && $arRegion[$prop]))
			return true;
		return false;
	}

	public static function formatJsName($name = ''){
		$name = str_replace('\\', '%99', $name); // replace symbol \
		return htmlspecialcharsbx($name);
	}

	public static function GetUserID(){
		static $userID;
		if($userID === NULL)
		{
			global $USER;
			$userID = $USER->GetID();
			$userID = ($userID > 0 ? $userID : 0);
		}
		return $userID;
	}

	public static function options_replace($arA, $arB){
		if(is_array($arA) && is_array($arB)){
			foreach($arA as $key => $value){
				if(array_key_exists($key, $arB)){
					if(is_array($value)){
						$arA[$key] = self::options_replace($arA[$key], $arB[$key]);
					}
					else{
						$arA[$key] = $arB[$key];
					}
				}
			}
		}
		else{
			$arA = $arB;
		}

		return $arA;
	}

	public static function getCurrentThematic($SITE_ID = ''){
		$SITE_ID = strlen($SITE_ID) ? $SITE_ID : (defined('SITE_ID') ? SITE_ID : '');

		return Option::get(self::moduleID, 'THEMATIC', 'UNIVERSAL', $SITE_ID);
	}

	public static function getCurrentPreset($SITE_ID = ''){
		static $arCurPresets;

		if(!isset($arCurPresets)){
			$arCurPresets = array();
		}

		$SITE_ID = strlen($SITE_ID) ? $SITE_ID : (defined('SITE_ID') ? SITE_ID : '');

		if(!isset($arCurPresets[$SITE_ID])){
			$arCurPresets[$SITE_ID] = false;

			$arPresets = array();
			if(strlen($curThematic = self::getCurrentThematic($SITE_ID))){
				if(self::$arThematicsList && self::$arThematicsList[$curThematic]){
					$arPresets = self::$arPresetsList;
					foreach($arPresets as $id => &$arPreset){
						if(in_array($id, self::$arThematicsList[$curThematic]['PRESETS']['LIST'])){
							if(self::$arThematicsList[$curThematic]['OPTIONS'] && is_array(self::$arThematicsList[$curThematic]['OPTIONS'])){
								$arPreset['OPTIONS'] = self::options_replace($arPreset['OPTIONS'], self::$arThematicsList[$curThematic]['OPTIONS']);
							}
						}
						else{
							unset($arPresets[$id]);
						}
					}
					unset($arPreset);
				}
			}

			if($arPresets){
				$arFrontParametrs = self::GetFrontParametrsValues($SITE_ID);

				foreach(self::$arParametrsList as $blockCode => $arBlock){
					foreach($arBlock['OPTIONS'] as $optionCode => $arOption){
						if($arOption['THEME'] === 'Y'){
							foreach($arPresets as $id => &$arPreset){
								if($arPreset['OPTIONS']){
									if(array_key_exists($optionCode, $arPreset['OPTIONS'])){
										$presetValue = $arPreset['OPTIONS'][$optionCode];

										if(array_key_exists($optionCode, $arFrontParametrs)){
											if(is_array($presetValue)){
												if(array_key_exists('VALUE', $presetValue)){
													if($arFrontParametrs[$optionCode] != $presetValue['VALUE']){
														unset($arPresets[$id]);
														continue;
													}
												}

												if(is_array($presetValue['ADDITIONAL_OPTIONS'])){
													if(is_array($presetValue['ADDITIONAL_OPTIONS'])){
														foreach($presetValue['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $subAddOptionValue){
															if(isset($arFrontParametrs[$subAddOptionCode.'_'.$presetValue['VALUE']])){
																if($arFrontParametrs[$subAddOptionCode.'_'.$presetValue['VALUE']] != $subAddOptionValue){
																	unset($arPresets[$id]);
																	continue 2;
																}
															}
														}
													}
												}

												if(is_array($presetValue['TOGGLE_OPTIONS'])){
													if(is_array($presetValue['TOGGLE_OPTIONS'])){
														foreach($presetValue['TOGGLE_OPTIONS'] as $toggleOptionCode => $toggleOptionValue){
															if(isset($arFrontParametrs[$toggleOptionCode.'_'.$presetValue['VALUE']])){
																if(is_array($toggleOptionValue)){
																	if(array_key_exists('VALUE', $toggleOptionValue)){
																		if($arFrontParametrs[$toggleOptionCode.'_'.$presetValue['VALUE']] != $toggleOptionValue['VALUE']){
																			unset($arPresets[$id]);
																			continue 2;
																		}
																	}

																	if(array_key_exists('ADDITIONAL_OPTIONS', $toggleOptionValue)){
																		foreach($toggleOptionValue['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $subAddOptionValue){
																			if(isset($arFrontParametrs[$subAddOptionCode.'_'.$presetValue['VALUE']])){
																				if($arFrontParametrs[$subAddOptionCode.'_'.$presetValue['VALUE']] != $subAddOptionValue){
																					unset($arPresets[$id]);
																					continue 3;
																				}
																			}
																		}
																	}
																}
																else{
																	if($arFrontParametrs[$toggleOptionCode.'_'.$presetValue['VALUE']] != $toggleOptionValue){
																		unset($arPresets[$id]);
																		continue 2;
																	}
																}
															}
														}
													}
												}

												if(is_array($presetValue['SUB_PARAMS'])){
													foreach($presetValue['SUB_PARAMS'] as $subOptionCode => $subValue){
														if(isset($arFrontParametrs[$presetValue['VALUE'].'_'.$subOptionCode])){
															if(is_array($subValue)){
																if(array_key_exists('VALUE', $subValue)){
																	if($arFrontParametrs[$presetValue['VALUE'].'_'.$subOptionCode] != $subValue['VALUE']){
																		unset($arPresets[$id]);
																		continue 2;
																	}

																	if(array_key_exists('TEMPLATE', $subValue) && array_key_exists($presetValue['VALUE'].'_'.$subOptionCode.'_TEMPLATE', $arFrontParametrs)){
																		if($arFrontParametrs[$presetValue['VALUE'].'_'.$subOptionCode.'_TEMPLATE'] != $subValue['TEMPLATE']){
																			unset($arPresets[$id]);
																			continue 2;
																		}

																		if(array_key_exists('ADDITIONAL_OPTIONS', $subValue)){
																			foreach($subValue['ADDITIONAL_OPTIONS'] as $addSubOptionTemplateCode => $addSubOptionTemplateValue){
																				if(array_key_exists($presetValue['VALUE'].'_'.$subOptionCode.'_'.$addSubOptionTemplateCode.'_'.$subValue['TEMPLATE'], $arFrontParametrs)){
																					if($arFrontParametrs[$presetValue['VALUE'].'_'.$subOptionCode.'_'.$addSubOptionTemplateCode.'_'.$subValue['TEMPLATE']] != $addSubOptionTemplateValue){
																						unset($arPresets[$id]);
																						continue 3;
																					}
																				}
																			}
																		}
																	}

																	if(is_array($subValue['INDEX_BLOCK_OPTIONS'])){
																		if(is_array($subValue['INDEX_BLOCK_OPTIONS']['TOP'])){
																			foreach($subValue['INDEX_BLOCK_OPTIONS']['TOP'] as $topCode => $topValue){
																				if(array_key_exists($topCode.'_'.$subOptionCode.'_'.$presetValue['VALUE'], $arFrontParametrs)){
																					if($arFrontParametrs[$topCode.'_'.$subOptionCode.'_'.$presetValue['VALUE']] != $topValue){
																						unset($arPresets[$id]);
																						continue 2;
																					}
																				}
																			}
																		}

																		if(is_array($subValue['INDEX_BLOCK_OPTIONS']['BOTTOM'])){
																			foreach($subValue['INDEX_BLOCK_OPTIONS']['BOTTOM'] as $bottomCode => $bottomValue){
																				if(array_key_exists($bottomCode.'_'.$subOptionCode.'_'.$presetValue['VALUE'], $arFrontParametrs)){
																					if($arFrontParametrs[$bottomCode.'_'.$subOptionCode.'_'.$presetValue['VALUE']] != $bottomValue){
																						unset($arPresets[$id]);
																						continue 2;
																					}
																				}
																			}
																		}
																	}
																}
															}
															else{
																if($arFrontParametrs[$presetValue['VALUE'].'_'.$subOptionCode] != $subValue){
																	unset($arPresets[$id]);
																	continue 2;
																}
															}
														}
													}
												}

												if(is_array($presetValue['DEPENDENT_PARAMS'])){
													foreach($presetValue['DEPENDENT_PARAMS'] as $depOptionCode => $depValue){
														if(isset($arFrontParametrs[$depOptionCode])){
															if(is_array($depValue)){
																if(array_key_exists('VALUE', $depValue)){
																	if($arFrontParametrs[$depOptionCode] != $depValue['VALUE']){
																		unset($arPresets[$id]);
																		continue 2;
																	}

																	if(is_array($depValue['TOGGLE_OPTIONS'])){
																		if(is_array($depValue['TOGGLE_OPTIONS'])){
																			foreach($depValue['TOGGLE_OPTIONS'] as $toggleOptionCode => $toggleOptionValue){
																				if(isset($arFrontParametrs[$toggleOptionCode.'_'.$depValue['VALUE']])){
																					if(is_array($toggleOptionValue)){
																						if(array_key_exists('VALUE', $toggleOptionValue)){
																							if($arFrontParametrs[$toggleOptionCode.'_'.$depValue['VALUE']] != $toggleOptionValue['VALUE']){
																								unset($arPresets[$id]);
																								continue 3;
																							}
																						}

																						if(array_key_exists('ADDITIONAL_OPTIONS', $toggleOptionValue)){
																							foreach($toggleOptionValue['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $subAddOptionValue){
																								if(isset($arFrontParametrs[$subAddOptionCode.'_'.$depValue['VALUE']])){
																									if($arFrontParametrs[$subAddOptionCode.'_'.$depValue['VALUE']] != $subAddOptionValue){
																										unset($arPresets[$id]);
																										continue 4;
																									}
																								}
																							}
																						}
																					}
																					else{
																						if($arFrontParametrs[$toggleOptionCode.'_'.$depValue['VALUE']] != $toggleOptionValue){
																							unset($arPresets[$id]);
																							continue 3;
																						}
																					}
																				}
																			}
																		}
																	}
																}
															}
															else{
																if($arFrontParametrs[$depOptionCode] != $depValue){
																	unset($arPresets[$id]);
																	continue 2;
																}
															}
														}
													}
												}

												if(array_key_exists('ORDER', $presetValue)){
													if(isset($arFrontParametrs['SORT_ORDER_'.$optionCode.'_'.$presetValue['VALUE']])){
														if($arFrontParametrs['SORT_ORDER_'.$optionCode.'_'.$presetValue['VALUE']] != $presetValue['ORDER']){
															unset($arPresets[$id]);
															continue;
														}
													}
												}
											}
											else{
												if($arFrontParametrs[$optionCode] != $presetValue){
													unset($arPresets[$id]);
													continue;
												}
											}
										}
									}
								}
								else{
									unset($arPresets[$id]);
									continue;
								}
							}
							unset($arPreset);
						}
					}
				}
			}

			if($arPresets){
				return $arCurPresets[$SITE_ID] = key($arPresets);
			}
		}

		return $arCurPresets[$SITE_ID];
	}

	public static function getOptionsOfPreset($presetId){
		if(($presetId = intval($presetId) > 0 ? intval($presetId) : false) > 0){
			if(
				self::$arPresetsList &&
				isset(self::$arPresetsList[$presetId]) &&
				is_array(self::$arPresetsList[$presetId])
			){
				return self::$arPresetsList[$presetId]['OPTIONS'];
			}
		}

		return array();
	}

	public static function getThemeParametrsValues($bFront = true, $arExcludeBlockCodes = array(), $SITE_ID = '', $SITE_DIR = ''){
		$arThemeParametrsValues = array();

		$SITE_ID = strlen($SITE_ID) ? $SITE_ID : (defined('SITE_ID') ? SITE_ID : '');
		$SITE_DIR = strlen($SITE_DIR) ? $SITE_DIR : (defined('SITE_DIR') ? SITE_DIR : '');

		$arParametrs = $bFront ? self::GetFrontParametrsValues($SITE_ID, $SITE_DIR, false) : self::GetBackParametrsValues($SITE_ID, $SITE_DIR, false);

		foreach(self::$arParametrsList as $blockCode => $arBlock){
			if(
				$arBlock['OPTIONS'] &&
				$arBlock['THEME'] === 'Y' &&
				!in_array($blockCode, $arExcludeBlockCodes)
			){
				foreach($arBlock['OPTIONS'] as $optionCode => $arOption){
					if($arOption['THEME'] === 'Y'){
						if(isset($arParametrs[$optionCode])){
							if(
								(
									$optionCode === 'MORE_COLOR' ||
									$optionCode === 'MORE_COLOR_CUSTOM'
								) &&
								$arParametrs['USE_MORE_COLOR'] === 'N'
							){
								continue;
							}

							if($arOption['TYPE'] === 'backButton'){
								continue;
							}

							$val = $arParametrs[$optionCode];

							if(
								$optionCode === 'BASE_COLOR_CUSTOM' ||
								$optionCode === 'CUSTOM_BGCOLOR_THEME' ||
								$optionCode === 'MORE_COLOR_CUSTOM'
							){
								$val = str_replace('#', '', $val);
							}

							$arThemeParametrsValues[$optionCode] = $val;

							if(
								is_array($arOption['LIST']) &&
								$arOption['LIST'][$val] &&
								is_array($arOption['LIST'][$val])
							){
								if(
									$arOption['LIST'][$val]['ADDITIONAL_OPTIONS'] &&
									is_array($arOption['LIST'][$val]['ADDITIONAL_OPTIONS'])
								){
									foreach($arOption['LIST'][$val]['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $arSubAddOption){
										if(isset($arParametrs[$subAddOptionCode.'_'.$val])){
											$subAddOptionValue = $arParametrs[$subAddOptionCode.'_'.$val];
										}
										else{
											if($arSubAddOption['TYPE'] === 'checkbox'){
												$subAddOptionValue = 'N';
											}
											else{
												$subAddOptionValue = $arSubAddOption['DEFAULT'];
											}
										}

										$arThemeParametrsValues[$subAddOptionCode.'_'.$val] = $subAddOptionValue;
									}
								}

								if(
									$arOption['LIST'][$val]['TOGGLE_OPTIONS'] &&
									is_array($arOption['LIST'][$val]['TOGGLE_OPTIONS']) &&
									$arOption['LIST'][$val]['TOGGLE_OPTIONS']['OPTIONS'] &&
									is_array($arOption['LIST'][$val]['TOGGLE_OPTIONS']['OPTIONS'])
								){
									foreach($arOption['LIST'][$val]['TOGGLE_OPTIONS']['OPTIONS'] as $toggleOptionCode => $arToggleOption){
										if($arToggleOption['TYPE'] !== 'link'){
											if(isset($arParametrs[$toggleOptionCode.'_'.$val])){
												$toggleOptionValue = $arParametrs[$toggleOptionCode.'_'.$val];
											}
											else{
												if($arToggleOption['TYPE'] === 'checkbox'){
													$toggleOptionValue = 'N';
												}
												else{
													$toggleOptionValue = $arToggleOption['DEFAULT'];
												}
											}

											$arThemeParametrsValues[$toggleOptionCode.'_'.$val] = $toggleOptionValue;

											if(is_array($arToggleOption['ADDITIONAL_OPTIONS'])){
												foreach($arToggleOption['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $arSubAddOption){
													if(isset($arParametrs[$subAddOptionCode.'_'.$val])){
														$subAddOptionValue = $arParametrs[$subAddOptionCode.'_'.$val];
													}
													else{
														if($arSubAddOption['TYPE'] === 'checkbox'){
															$subAddOptionValue = 'N';
														}
														else{
															$subAddOptionValue = $arSubAddOption['DEFAULT'];
														}
													}

													$arThemeParametrsValues[$subAddOptionCode.'_'.$val] = $subAddOptionValue;
												}
											}
										}
									}
								}
							}

							if($arOption['SUB_PARAMS']){
								$arThemeParametrsValues['SORT_ORDER_INDEX_TYPE_index1'] = $arParametrs['SORT_ORDER_INDEX_TYPE_index1'];

								if($arOption['SUB_PARAMS'][$val]){
									if(!$arThemeParametrsValues['SORT_ORDER_INDEX_TYPE_index1']){
										$arThemeParametrsValues['SORT_ORDER_INDEX_TYPE_index1'] = implode(',', array_keys($arOption['SUB_PARAMS'][$val]));
									}

									foreach($arOption['SUB_PARAMS'][$val] as $subOptionCode => $arSubOption){
										if($arSubOption['THEME'] === 'Y'){
											if(is_array($arSubOption['INDEX_BLOCK_OPTIONS'])){
												if(is_array($arSubOption['INDEX_BLOCK_OPTIONS']['TOP'])){
													foreach($arSubOption['INDEX_BLOCK_OPTIONS']['TOP'] as $topCode => $topValue){
														if(isset($arParametrs[$topCode.'_'.$subOptionCode.'_'.$val])){
															$arThemeParametrsValues[$topCode.'_'.$subOptionCode.'_'.$val] = $arParametrs[$topCode.'_'.$subOptionCode.'_'.$val];
														}
														else{
															$arThemeParametrsValues[$topCode.'_'.$subOptionCode.'_'.$val] = 'N';
														}
													}
												}

												if(is_array($arSubOption['INDEX_BLOCK_OPTIONS']['BOTTOM'])){
													foreach($arSubOption['INDEX_BLOCK_OPTIONS']['BOTTOM'] as $bottomCode => $arBottomValue){
														if(isset($arParametrs[$bottomCode.'_'.$subOptionCode.'_'.$val])){
															$arThemeParametrsValues[$bottomCode.'_'.$subOptionCode.'_'.$val] = $arParametrs[$bottomCode.'_'.$subOptionCode.'_'.$val];
														}
														else{
															if($arBottomValue['TYPE'] === 'checkbox'){
																$arThemeParametrsValues[$bottomCode.'_'.$subOptionCode.'_'.$val] = 'N';
															}
														}
													}
												}
											}

											if($arSubOption['TEMPLATE']){
												if(isset($arParametrs[$val.'_'.$subOptionCode])){
													$arThemeParametrsValues[$val.'_'.$subOptionCode] = $arParametrs[$val.'_'.$subOptionCode];
												}
												else{
													$arThemeParametrsValues[$val.'_'.$subOptionCode] = 'N';
												}

												if(isset($arParametrs[$val.'_'.$subOptionCode.'_TEMPLATE'])){
													$arThemeParametrsValues[$val.'_'.$subOptionCode.'_TEMPLATE'] = $arParametrs[$val.'_'.$subOptionCode.'_TEMPLATE'];

													if($arSubOption['TEMPLATE']['TYPE'] === 'selectbox' && $arSubOption['TEMPLATE']['LIST']){
														$arSubOptionTemplateValue = $arSubOption['TEMPLATE']['LIST'][$arThemeParametrsValues[$val.'_'.$subOptionCode.'_TEMPLATE']];
														if($arSubOptionTemplateValue && is_array($arSubOptionTemplateValue) && $arSubOptionTemplateValue['ADDITIONAL_OPTIONS'] && is_array($arSubOptionTemplateValue['ADDITIONAL_OPTIONS'])){
															foreach($arSubOptionTemplateValue['ADDITIONAL_OPTIONS'] as $addSubOptionTemplateCode => $arAddSubOptionTemplate){
																if(isset($arParametrs[$val.'_'.$subOptionCode.'_'.$addSubOptionTemplateCode.'_'.$arThemeParametrsValues[$val.'_'.$subOptionCode.'_TEMPLATE']])){
																	$arThemeParametrsValues[$val.'_'.$subOptionCode.'_'.$addSubOptionTemplateCode.'_'.$arThemeParametrsValues[$val.'_'.$subOptionCode.'_TEMPLATE']] = $arParametrs[$val.'_'.$subOptionCode.'_'.$addSubOptionTemplateCode.'_'.$arThemeParametrsValues[$val.'_'.$subOptionCode.'_TEMPLATE']];
																}
																else{
																	if($arAddSubOptionTemplate['TYPE'] === 'checkbox'){
																		$arThemeParametrsValues[$val.'_'.$subOptionCode.'_'.$addSubOptionTemplateCode.'_'.$arThemeParametrsValues[$val.'_'.$subOptionCode.'_TEMPLATE']] = 'N';
																	}
																	else{
																		$arThemeParametrsValues[$val.'_'.$subOptionCode.'_'.$addSubOptionTemplateCode.'_'.$arThemeParametrsValues[$val.'_'.$subOptionCode.'_TEMPLATE']] = $arAddSubOptionTemplate['DEFAULT'];
																	}
																}
															}
														}
													}
												}
											}
											else{
												if(isset($arParametrs[$val.'_'.$subOptionCode])){
													$arThemeParametrsValues[$val.'_'.$subOptionCode] = $arParametrs[$val.'_'.$subOptionCode];
												}
												else{
													if($arSubOption['TYPE'] === 'checkbox'){
														if($arSubOption['THEME'] !== 'N'){
															$arThemeParametrsValues[$val.'_'.$subOptionCode] = 'N';
														}
													}
												}
											}
										}
									}
								}
							}

							if($arOption['DEPENDENT_PARAMS']){
								foreach($arOption['DEPENDENT_PARAMS'] as $depOptionCode => $arDepOption){
									if($arDepOption['THEME'] === 'Y'){
										if(isset($arParametrs[$depOptionCode])){
											$depOptionValue = $arParametrs[$depOptionCode];
										}
										else{
											if($arDepOption['TYPE'] === 'checkbox'){
												$depOptionValue = 'N';
											}
											else{
												$depOptionValue = $arDepOption['DEFAULT'];
											}
										}

										$arThemeParametrsValues[$depOptionCode] = $depOptionValue;

										if(
											is_array($arDepOption['LIST']) &&
											$arDepOption['LIST'][$depOptionValue] &&
											is_array($arDepOption['LIST'][$depOptionValue])
										){
											if(
												$arDepOption['LIST'][$depOptionValue]['TOGGLE_OPTIONS'] &&
												is_array($arDepOption['LIST'][$depOptionValue]['TOGGLE_OPTIONS']) &&
												$arDepOption['LIST'][$depOptionValue]['TOGGLE_OPTIONS']['OPTIONS'] &&
												is_array($arDepOption['LIST'][$depOptionValue]['TOGGLE_OPTIONS']['OPTIONS'])
											){
												foreach($arDepOption['LIST'][$depOptionValue]['TOGGLE_OPTIONS']['OPTIONS'] as $toggleOptionCode => $arToggleOption){
													if($arToggleOption['TYPE'] !== 'link'){
														if(isset($arParametrs[$toggleOptionCode.'_'.$depOptionValue])){
															$toggleOptionValue = $arParametrs[$toggleOptionCode.'_'.$depOptionValue];
														}
														else{
															if($arToggleOption['TYPE'] === 'checkbox'){
																$toggleOptionValue = 'N';
															}
															else{
																$toggleOptionValue = $arToggleOption['DEFAULT'];
															}
														}

														$arThemeParametrsValues[$toggleOptionCode.'_'.$depOptionValue] = $toggleOptionValue;

														if(is_array($arToggleOption['ADDITIONAL_OPTIONS'])){
															foreach($arToggleOption['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $arSubAddOption){
																if(isset($arParametrs[$subAddOptionCode.'_'.$depOptionValue])){
																	$subAddOptionValue = $arParametrs[$subAddOptionCode.'_'.$depOptionValue];
																}
																else{
																	if($arSubAddOption['TYPE'] === 'checkbox'){
																		$subAddOptionValue = 'N';
																	}
																	else{
																		$subAddOptionValue = $arSubAddOption['DEFAULT'];
																	}
																}

																$arThemeParametrsValues[$subAddOptionCode.'_'.$depOptionValue] = $subAddOptionValue;
															}
														}
													}
												}
											}
										}
									}
								}
							}
						}
						else{
							if($arOption['TYPE'] === 'checkbox'){
								if($arOption['THEME'] !== 'N'){
									$arThemeParametrsValues[$optionCode] = 'N';
								}
							}
						}
					}
				}
			}
		}

		return $arThemeParametrsValues;
	}

	public static function getPresetOptions($arThemeParametrsValues, $arExcludeBlockCodes = array()){
		$arPresetOptions = array();

		foreach(self::$arParametrsList as $blockCode => $arBlock){
			if(
				$arBlock['OPTIONS'] &&
				$arBlock['THEME'] === 'Y' &&
				!in_array($blockCode, $arExcludeBlockCodes)
			){
				foreach($arBlock['OPTIONS'] as $optionCode => $arOption){
					if($arOption['THEME'] === 'Y'){
						if(isset($arThemeParametrsValues[$optionCode])){
							if(
								(
									$optionCode === 'MORE_COLOR' ||
									$optionCode === 'MORE_COLOR_CUSTOM'
								) &&
								$arThemeParametrsValues['USE_MORE_COLOR'] === 'N'
							){
								continue;
							}

							if($arOption['TYPE'] === 'backButton'){
								continue;
							}

							$val = $arThemeParametrsValues[$optionCode];

							if(
								$optionCode === 'BASE_COLOR_CUSTOM' ||
								$optionCode === 'CUSTOM_BGCOLOR_THEME' ||
								$optionCode === 'MORE_COLOR_CUSTOM'
							){
								$val = str_replace('#', '', $val);
							}

							$arPresetOptions[$optionCode] = array(
								'VALUE' => $val,
							);

							if(
								is_array($arOption['LIST']) &&
								$arOption['LIST'][$val] &&
								is_array($arOption['LIST'][$val])
							){
								if(
									$arOption['LIST'][$val]['ADDITIONAL_OPTIONS'] &&
									is_array($arOption['LIST'][$val]['ADDITIONAL_OPTIONS'])
								){
									$arPresetOptions[$optionCode]['ADDITIONAL_OPTIONS'] = array();

									foreach($arOption['LIST'][$val]['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $arSubAddOption){
										if(isset($arThemeParametrsValues[$subAddOptionCode.'_'.$val])){
											$subAddOptionValue = $arThemeParametrsValues[$subAddOptionCode.'_'.$val];
										}
										else{
											if($arSubAddOption['TYPE'] === 'checkbox'){
												$subAddOptionValue = 'N';
											}
											else{
												$subAddOptionValue = $arSubAddOption['DEFAULT'];
											}
										}

										$arPresetOptions[$optionCode]['ADDITIONAL_OPTIONS'][$subAddOptionCode] = $subAddOptionValue;
									}
								}

								if(
									$arOption['LIST'][$val]['TOGGLE_OPTIONS'] &&
									is_array($arOption['LIST'][$val]['TOGGLE_OPTIONS']) &&
									$arOption['LIST'][$val]['TOGGLE_OPTIONS']['OPTIONS'] &&
									is_array($arOption['LIST'][$val]['TOGGLE_OPTIONS']['OPTIONS'])
								){
									$arPresetOptions[$optionCode]['TOGGLE_OPTIONS'] = array();

									foreach($arOption['LIST'][$val]['TOGGLE_OPTIONS']['OPTIONS'] as $toggleOptionCode => $arToggleOption){
										if(isset($arThemeParametrsValues[$toggleOptionCode.'_'.$val])){
											$toggleOptionValue = $arThemeParametrsValues[$toggleOptionCode.'_'.$val];
										}
										else{
											if($arToggleOption['TYPE'] === 'checkbox'){
												$toggleOptionValue = 'N';
											}
											else{
												$toggleOptionValue = $arToggleOption['DEFAULT'];
											}
										}

										$arPresetOptions[$optionCode]['TOGGLE_OPTIONS'][$toggleOptionCode] = $toggleOptionValue;

										if(is_array($arToggleOption['ADDITIONAL_OPTIONS'])){
											$arPresetOptions[$optionCode]['TOGGLE_OPTIONS'][$toggleOptionCode] = array(
												'VALUE' => $toggleOptionValue,
												'ADDITIONAL_OPTIONS' => array(),
											);

											foreach($arToggleOption['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $arSubAddOption){
												if(isset($arThemeParametrsValues[$subAddOptionCode.'_'.$val])){
													$subAddOptionValue = $arThemeParametrsValues[$subAddOptionCode.'_'.$val];
												}
												else{
													if($arSubAddOption['TYPE'] === 'checkbox'){
														$subAddOptionValue = 'N';
													}
													else{
														$subAddOptionValue = $arSubAddOption['DEFAULT'];
													}
												}

												$arPresetOptions[$optionCode]['TOGGLE_OPTIONS'][$toggleOptionCode]['ADDITIONAL_OPTIONS'][$subAddOptionCode] = $subAddOptionValue;
											}
										}

										if($arToggleOption['TYPE'] === 'link'){
											if(is_array($arPresetOptions[$optionCode]['TOGGLE_OPTIONS'][$toggleOptionCode])){
												unset($arPresetOptions[$optionCode]['TOGGLE_OPTIONS'][$toggleOptionCode]['VALUE']);
											}
											else{
												unset($arPresetOptions[$optionCode]['TOGGLE_OPTIONS'][$toggleOptionCode]);
											}
										}
									}
								}
							}

							if($arOption['SUB_PARAMS']){
								$arPresetOptions[$optionCode]['SUB_PARAMS'] = array();
								$arPresetOptions[$optionCode]['ORDER'] = $arThemeParametrsValues['SORT_ORDER_INDEX_TYPE_index1'];

								if($arOption['SUB_PARAMS'][$val]){
									if(!$arPresetOptions[$optionCode]['ORDER']){
										$arPresetOptions[$optionCode]['ORDER'] = implode(',', array_keys($arOption['SUB_PARAMS'][$val]));
									}

									foreach($arOption['SUB_PARAMS'][$val] as $subOptionCode => $arSubOption){
										if($arSubOption['THEME'] === 'Y'){
											if(
												$arSubOption['TEMPLATE'] ||
												is_array($arSubOption['INDEX_BLOCK_OPTIONS'])
											){
												$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode] = array();
											}

											if(is_array($arSubOption['INDEX_BLOCK_OPTIONS'])){
												$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['INDEX_BLOCK_OPTIONS'] = array();

												if(is_array($arSubOption['INDEX_BLOCK_OPTIONS']['TOP'])){
													$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['INDEX_BLOCK_OPTIONS']['TOP'] = array();

													foreach($arSubOption['INDEX_BLOCK_OPTIONS']['TOP'] as $topCode => $topValue){
														if(isset($arThemeParametrsValues[$topCode.'_'.$subOptionCode.'_'.$val])){
															$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['INDEX_BLOCK_OPTIONS']['TOP'][$topCode] = $arThemeParametrsValues[$topCode.'_'.$subOptionCode.'_'.$val];
														}
														else{
															$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['INDEX_BLOCK_OPTIONS']['TOP'][$topCode] = 'N';
														}
													}
												}

												if(is_array($arSubOption['INDEX_BLOCK_OPTIONS']['BOTTOM'])){
													$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['INDEX_BLOCK_OPTIONS']['BOTTOM'] = array();

													foreach($arSubOption['INDEX_BLOCK_OPTIONS']['BOTTOM'] as $bottomCode => $arBottomValue){
														if(isset($arThemeParametrsValues[$bottomCode.'_'.$subOptionCode.'_'.$val])){
															$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['INDEX_BLOCK_OPTIONS']['BOTTOM'][$bottomCode] = $arThemeParametrsValues[$bottomCode.'_'.$subOptionCode.'_'.$val];
														}
														else{
															if($arBottomValue['TYPE'] === 'checkbox'){
																$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['INDEX_BLOCK_OPTIONS']['BOTTOM'][$bottomCode] = 'N';
															}
														}
													}
												}
											}

											if($arSubOption['TEMPLATE']){
												if(isset($arThemeParametrsValues[$val.'_'.$subOptionCode])){
													$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['VALUE'] = $arThemeParametrsValues[$val.'_'.$subOptionCode];
												}
												else{
													$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['VALUE'] = 'N';
												}

												if(isset($arThemeParametrsValues[$val.'_'.$subOptionCode.'_TEMPLATE'])){
													$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['TEMPLATE'] = $arThemeParametrsValues[$val.'_'.$subOptionCode.'_TEMPLATE'];

													if($arSubOption['TEMPLATE']['TYPE'] === 'selectbox' && $arSubOption['TEMPLATE']['LIST']){
														$arSubOptionTemplateValue = $arSubOption['TEMPLATE']['LIST'][$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['TEMPLATE']];
														if($arSubOptionTemplateValue && is_array($arSubOptionTemplateValue) && $arSubOptionTemplateValue['ADDITIONAL_OPTIONS'] && is_array($arSubOptionTemplateValue['ADDITIONAL_OPTIONS'])){
															$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['ADDITIONAL_OPTIONS'] = array();
															foreach($arSubOptionTemplateValue['ADDITIONAL_OPTIONS'] as $addSubOptionTemplateCode => $arAddSubOptionTemplate){
																if(isset($arThemeParametrsValues[$val.'_'.$subOptionCode.'_'.$addSubOptionTemplateCode.'_'.$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['TEMPLATE']])){
																	$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['ADDITIONAL_OPTIONS'][$addSubOptionTemplateCode] = $arThemeParametrsValues[$val.'_'.$subOptionCode.'_'.$addSubOptionTemplateCode.'_'.$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['TEMPLATE']];
																}
																else{
																	if($arAddSubOptionTemplate['TYPE'] === 'checkbox'){
																		$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['ADDITIONAL_OPTIONS'][$addSubOptionTemplateCode] = 'N';
																	}
																	else{
																		$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['ADDITIONAL_OPTIONS'][$addSubOptionTemplateCode] = $arAddSubOptionTemplate['DEFAULT'];
																	}
																}
															}
														}
													}
												}
											}
											else{
												if(isset($arThemeParametrsValues[$val.'_'.$subOptionCode])){
													if(is_array($arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode])){
														$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['VALUE'] = $arThemeParametrsValues[$val.'_'.$subOptionCode];
													}
													else{
														$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode] = $arThemeParametrsValues[$val.'_'.$subOptionCode];
													}
												}
												else{
													if($arSubOption['TYPE'] === 'checkbox'){
														if($arSubOption['THEME'] !== 'N'){
															if(is_array($arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode])){
																$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode]['VALUE'] = 'N';
															}
															else{
																$arPresetOptions[$optionCode]['SUB_PARAMS'][$subOptionCode] = 'N';
															}
														}
													}
												}
											}
										}
									}
								}
							}

							if($arOption['DEPENDENT_PARAMS']){
								$arPresetOptions[$optionCode]['DEPENDENT_PARAMS'] = array();

								foreach($arOption['DEPENDENT_PARAMS'] as $depOptionCode => $arDepOption){
									if($arDepOption['THEME'] === 'Y'){
										if(isset($arThemeParametrsValues[$depOptionCode])){
											$depOptionValue = $arThemeParametrsValues[$depOptionCode];
										}
										else{
											if($arDepOption['TYPE'] === 'checkbox'){
												$depOptionValue = 'N';
											}
											else{
												$depOptionValue = $arDepOption['DEFAULT'];
											}
										}

										$arPresetOptions[$optionCode]['DEPENDENT_PARAMS'][$depOptionCode] = $depOptionValue;

										if(
											is_array($arDepOption['LIST']) &&
											$arDepOption['LIST'][$depOptionValue] &&
											is_array($arDepOption['LIST'][$depOptionValue])
										){
											if(
												$arDepOption['LIST'][$depOptionValue]['TOGGLE_OPTIONS'] &&
												is_array($arDepOption['LIST'][$depOptionValue]['TOGGLE_OPTIONS']) &&
												$arDepOption['LIST'][$depOptionValue]['TOGGLE_OPTIONS']['OPTIONS'] &&
												is_array($arDepOption['LIST'][$depOptionValue]['TOGGLE_OPTIONS']['OPTIONS'])
											){
												$arPresetOptions[$optionCode]['DEPENDENT_PARAMS'][$depOptionCode] = array(
													'VALUE' => $depOptionValue,
													'TOGGLE_OPTIONS' => array()
												);

												foreach($arDepOption['LIST'][$depOptionValue]['TOGGLE_OPTIONS']['OPTIONS'] as $toggleOptionCode => $arToggleOption){
													if(isset($arThemeParametrsValues[$toggleOptionCode.'_'.$depOptionValue])){
														$toggleOptionValue = $arThemeParametrsValues[$toggleOptionCode.'_'.$depOptionValue];
													}
													else{
														if($arToggleOption['TYPE'] === 'checkbox'){
															$toggleOptionValue = 'N';
														}
														else{
															$toggleOptionValue = $arToggleOption['DEFAULT'];
														}
													}

													$arPresetOptions[$optionCode]['DEPENDENT_PARAMS'][$depOptionCode]['TOGGLE_OPTIONS'][$toggleOptionCode] = $toggleOptionValue;

													if(is_array($arToggleOption['ADDITIONAL_OPTIONS'])){
														$arPresetOptions[$optionCode]['DEPENDENT_PARAMS'][$depOptionCode]['TOGGLE_OPTIONS'][$toggleOptionCode] = array(
															'VALUE' => $toggleOptionValue,
															'ADDITIONAL_OPTIONS' => array(),
														);

														foreach($arToggleOption['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $arSubAddOption){
															if(isset($arThemeParametrsValues[$subAddOptionCode.'_'.$depOptionValue])){
																$subAddOptionValue = $arThemeParametrsValues[$subAddOptionCode.'_'.$depOptionValue];
															}
															else{
																if($arSubAddOption['TYPE'] === 'checkbox'){
																	$subAddOptionValue = 'N';
																}
																else{
																	$subAddOptionValue = $arSubAddOption['DEFAULT'];
																}
															}

															$arPresetOptions[$optionCode]['DEPENDENT_PARAMS'][$depOptionCode]['TOGGLE_OPTIONS'][$toggleOptionCode]['ADDITIONAL_OPTIONS'][$subAddOptionCode] = $subAddOptionValue;
														}
													}

													if($arToggleOption['TYPE'] === 'link'){
														if(is_array($arPresetOptions[$optionCode]['DEPENDENT_PARAMS'][$depOptionCode]['TOGGLE_OPTIONS'][$toggleOptionCode])){
															unset($arPresetOptions[$optionCode]['DEPENDENT_PARAMS'][$depOptionCode]['TOGGLE_OPTIONS'][$toggleOptionCode]['VALUE']);
														}
														else{
															unset($arPresetOptions[$optionCode]['DEPENDENT_PARAMS'][$depOptionCode]['TOGGLE_OPTIONS'][$toggleOptionCode]);
														}
													}
												}
											}
										}
									}
								}
							}

							if(count(array_keys($arPresetOptions[$optionCode])) == 1){
								$arPresetOptions[$optionCode] = $val;
							}
						}
						else{
							if($arOption['TYPE'] === 'checkbox'){
								if($arOption['THEME'] !== 'N'){
									$arPresetOptions[$optionCode] = 'N';
								}
							}
						}
					}
				}
			}
		}

		return $arPresetOptions;
	}

	public static function setFrontPresetOptions($arPresetOptions, $SITE_ID = ''){
		if(
			$arPresetOptions &&
			is_array($arPresetOptions)
		){
			$SITE_ID = strlen($SITE_ID) ? $SITE_ID : (defined('SITE_ID') ? SITE_ID : '');

			if(strlen($curThematic = self::getCurrentThematic($SITE_ID))){
				if(self::$arThematicsList && self::$arThematicsList[$curThematic]){
					$arPresetOptions = self::options_replace($arPresetOptions, self::$arThematicsList[$curThematic]['OPTIONS']);

					if($arPresetOptions){
						foreach($arPresetOptions as $optionCode => $optionVal){
							if(!is_array($optionVal)){
								$_SESSION['THEME'][$SITE_ID][$optionCode] = $optionVal;

								if($optionVal === 'CUSTOM'){
									if(
										$optionCode === 'BASE_COLOR' ||
										$optionCode === 'MORE_COLOR'
									){
										Option::set(self::moduleID, 'NeedGenerateCustomTheme', 'Y', $SITE_ID);
									}
								}
							}
							else{
								if(array_key_exists('VALUE', $optionVal)){
									$propValue = $optionVal['VALUE'];
									$_SESSION['THEME'][$SITE_ID][$optionCode] = $propValue;

									if(array_key_exists('ADDITIONAL_OPTIONS', $optionVal)){
										foreach($optionVal['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $subAddOptionValue){
											$_SESSION['THEME'][$SITE_ID][$subAddOptionCode.'_'.$propValue] = $subAddOptionValue;
										}
									}

									if(array_key_exists('TOGGLE_OPTIONS', $optionVal)){
										foreach($optionVal['TOGGLE_OPTIONS'] as $toggleOptionCode => $toggleOptionValue){
											if(is_array($toggleOptionValue)){
												if(array_key_exists('VALUE', $toggleOptionValue)){
													$_SESSION['THEME'][$SITE_ID][$toggleOptionCode.'_'.$propValue] = $toggleOptionValue['VALUE'];
												}

												if(array_key_exists('ADDITIONAL_OPTIONS', $toggleOptionValue)){
													foreach($toggleOptionValue['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $subAddOptionValue){
														$_SESSION['THEME'][$SITE_ID][$subAddOptionCode.'_'.$propValue] = $subAddOptionValue;
													}
												}
											}
											else{
												$_SESSION['THEME'][$SITE_ID][$toggleOptionCode.'_'.$propValue] = $toggleOptionValue;
											}
										}
									}

									if(array_key_exists('SUB_PARAMS', $optionVal)){
										foreach($optionVal['SUB_PARAMS'] as $subOptionCode => $arSubOption){
											if(is_array($arSubOption)){
												if(array_key_exists('VALUE', $arSubOption)){
													$_SESSION['THEME'][$SITE_ID][$propValue.'_'.$subOptionCode] = $arSubOption['VALUE'];
												}

												if(array_key_exists('TEMPLATE', $arSubOption)){
													$_SESSION['THEME'][$SITE_ID][$propValue.'_'.$subOptionCode.'_TEMPLATE'] = $arSubOption['TEMPLATE'];

													if(is_array($arSubOption['ADDITIONAL_OPTIONS'])){
														foreach($arSubOption['ADDITIONAL_OPTIONS'] as $addSubOptionTemplateCode => $addSubOptionTemplateValue){
															$_SESSION['THEME'][$SITE_ID][$propValue.'_'.$subOptionCode.'_'.$addSubOptionTemplateCode.'_'.$arSubOption['TEMPLATE']] = $addSubOptionTemplateValue;
														}
													}
												}

												if(is_array($arSubOption['INDEX_BLOCK_OPTIONS'])){
													if(is_array($arSubOption['INDEX_BLOCK_OPTIONS']['TOP'])){
														foreach($arSubOption['INDEX_BLOCK_OPTIONS']['TOP'] as $topCode => $topValue){
															$_SESSION['THEME'][$SITE_ID][$topCode.'_'.$subOptionCode.'_'.$propValue] = $topValue;
														}
													}

													if(is_array($arSubOption['INDEX_BLOCK_OPTIONS']['BOTTOM'])){
														foreach($arSubOption['INDEX_BLOCK_OPTIONS']['BOTTOM'] as $bottomCode => $bottomValue){
															$_SESSION['THEME'][$SITE_ID][$bottomCode.'_'.$subOptionCode.'_'.$propValue] = $bottomValue;
														}
													}
												}
											}
											else{
												$_SESSION['THEME'][$SITE_ID][$propValue.'_'.$subOptionCode] = $arSubOption;
											}
										}
									}

									if(array_key_exists('DEPENDENT_PARAMS', $optionVal)){
										foreach($optionVal['DEPENDENT_PARAMS'] as $depOptionCode => $depOptionVal){
											if(is_array($depOptionVal)){
												if(array_key_exists('VALUE', $depOptionVal)){
													$_SESSION['THEME'][$SITE_ID][$depOptionCode] = $depOptionVal['VALUE'];

													if(array_key_exists('TOGGLE_OPTIONS', $depOptionVal)){
														foreach($depOptionVal['TOGGLE_OPTIONS'] as $toggleOptionCode => $toggleOptionValue){
															if(is_array($toggleOptionValue)){
																if(array_key_exists('VALUE', $toggleOptionValue)){
																	$_SESSION['THEME'][$SITE_ID][$toggleOptionCode.'_'.$depOptionVal['VALUE']] = $toggleOptionValue['VALUE'];
																}

																if(array_key_exists('ADDITIONAL_OPTIONS', $toggleOptionValue)){
																	foreach($toggleOptionValue['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $subAddOptionValue){
																		$_SESSION['THEME'][$SITE_ID][$subAddOptionCode.'_'.$depOptionVal['VALUE']] = $subAddOptionValue;
																	}
																}
															}
															else{
																$_SESSION['THEME'][$SITE_ID][$toggleOptionCode.'_'.$depOptionVal['VALUE']] = $toggleOptionValue;
															}
														}
													}
												}
											}
											else{
												$_SESSION['THEME'][$SITE_ID][$depOptionCode] = $depOptionVal;
											}
										}
									}

									if(array_key_exists('ORDER', $optionVal)){
										$_SESSION['THEME'][$SITE_ID]['SORT_ORDER_'.$optionCode.'_'.$propValue] = $optionVal['ORDER'];
									}
								}
							}
						}
					}

					return true;
				}
			}
		}

		return false;
	}

	public static function setFrontParametrsOfPreset($presetId, $SITE_ID = ''){
		return self::setFrontPresetOptions(self::getOptionsOfPreset($presetId), $SITE_ID);
	}

	public static function setBackPresetOptions($arPresetOptions, $SITE_ID = ''){
		if(
			$arPresetOptions &&
			is_array($arPresetOptions)
		){
			$SITE_ID = strlen($SITE_ID) ? $SITE_ID : (defined('SITE_ID') ? SITE_ID : '');

			$_SESSION['THEME'][$SITE_ID] = [];

			if(strlen($curThematic = self::getCurrentThematic($SITE_ID))){
				if(self::$arThematicsList && self::$arThematicsList[$curThematic]){
					$arPresetOptions = self::options_replace($arPresetOptions, self::$arThematicsList[$curThematic]['OPTIONS']);

					if($arPresetOptions){
						foreach($arPresetOptions as $optionCode => $optionVal){
							if(!is_array($optionVal)){
								Option::set(self::moduleID, $optionCode, $optionVal, $SITE_ID);

								if($optionVal === 'CUSTOM'){
									if(
										$optionCode === 'BASE_COLOR' ||
										$optionCode === 'MORE_COLOR'
									){
										Option::set(self::moduleID, 'NeedGenerateCustomTheme', 'Y', $SITE_ID);
									}
								}
							}
							else{
								if(array_key_exists('VALUE', $optionVal)){
									$propValue = $optionVal['VALUE'];
									Option::set(self::moduleID, $optionCode, $propValue, $SITE_ID);

									if(array_key_exists('ADDITIONAL_OPTIONS', $optionVal)){
										foreach($optionVal['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $subAddOptionValue){
											Option::set(self::moduleID, $subAddOptionCode.'_'.$propValue, $subAddOptionValue, $SITE_ID);
										}
									}

									if(array_key_exists('TOGGLE_OPTIONS', $optionVal)){
										foreach($optionVal['TOGGLE_OPTIONS'] as $toggleOptionCode => $toggleOptionValue){
											if(is_array($toggleOptionValue)){
												if(array_key_exists('VALUE', $toggleOptionValue)){
													Option::set(self::moduleID, $toggleOptionCode.'_'.$propValue, $toggleOptionValue['VALUE'], $SITE_ID);
												}

												if(array_key_exists('ADDITIONAL_OPTIONS', $toggleOptionValue)){
													foreach($toggleOptionValue['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $subAddOptionValue){
														Option::set(self::moduleID, $subAddOptionCode.'_'.$propValue, $subAddOptionValue, $SITE_ID);
													}
												}
											}
											else{
												Option::set(self::moduleID, $toggleOptionCode.'_'.$propValue, $toggleOptionValue, $SITE_ID);
											}
										}
									}

									if(array_key_exists('SUB_PARAMS', $optionVal)){
										$arSubValues = array();
										foreach($optionVal['SUB_PARAMS'] as $subOptionCode => $arSubOption){
											if(is_array($arSubOption)){
												if(array_key_exists('VALUE', $arSubOption)){
													$arSubValues[$subOptionCode] = $arSubOption['VALUE'];
												}

												if(array_key_exists('TEMPLATE', $arSubOption)){
													Option::set(self::moduleID, $propValue.'_'.$subOptionCode.'_TEMPLATE', $arSubOption['TEMPLATE'], $SITE_ID);

													if(array_key_exists('ADDITIONAL_OPTIONS', $arSubOption)){
													 	foreach($arSubOption['ADDITIONAL_OPTIONS'] as $addSubOptionTemplateCode => $addSubOptionTemplateValue){
															Option::set(self::moduleID, $propValue.'_'.$subOptionCode.'_'.$addSubOptionTemplateCode.'_'.$arSubOption['TEMPLATE'], $addSubOptionTemplateValue, $SITE_ID);
														}
													}
												}

												if(is_array($arSubOption['INDEX_BLOCK_OPTIONS'])){
													if(is_array($arSubOption['INDEX_BLOCK_OPTIONS']['TOP'])){
														foreach($arSubOption['INDEX_BLOCK_OPTIONS']['TOP'] as $topCode => $topValue){
															Option::set(self::moduleID, $topCode.'_'.$subOptionCode.'_'.$propValue, $topValue, $SITE_ID);
														}
													}

													if(is_array($arSubOption['INDEX_BLOCK_OPTIONS']['BOTTOM'])){
														foreach($arSubOption['INDEX_BLOCK_OPTIONS']['BOTTOM'] as $bottomCode => $bottomValue){
															Option::set(self::moduleID, $bottomCode.'_'.$subOptionCode.'_'.$propValue, $bottomValue, $SITE_ID);
														}
													}
												}
											}
											else{
												$arSubValues[$subOptionCode] = $arSubOption;
											}
										}

										if($arSubValues){
											Option::set(self::moduleID, 'NESTED_OPTIONS_'.$optionCode.'_'.$propValue, serialize($arSubValues), $SITE_ID);
										}
									}

									if(array_key_exists('DEPENDENT_PARAMS', $optionVal)){
										foreach($optionVal['DEPENDENT_PARAMS'] as $depOptionCode => $depOptionVal){
											if(is_array($depOptionVal)){
												if(array_key_exists('VALUE', $depOptionVal)){
													Option::set(self::moduleID, $depOptionCode, $depOptionVal['VALUE'], $SITE_ID);
												}

												if(array_key_exists('TOGGLE_OPTIONS', $depOptionVal)){
													foreach($depOptionVal['TOGGLE_OPTIONS'] as $toggleOptionCode => $toggleOptionValue){
														if(is_array($toggleOptionValue)){
															if(array_key_exists('VALUE', $toggleOptionValue)){
																Option::set(self::moduleID, $toggleOptionCode.'_'.$depOptionVal['VALUE'], $toggleOptionValue['VALUE'], $SITE_ID);
															}

															if(array_key_exists('ADDITIONAL_OPTIONS', $toggleOptionValue)){
																foreach($toggleOptionValue['ADDITIONAL_OPTIONS'] as $subAddOptionCode => $subAddOptionValue){
																	Option::set(self::moduleID, $subAddOptionCode.'_'.$depOptionVal['VALUE'], $subAddOptionValue, $SITE_ID);
																}
															}
														}
														else{
															Option::set(self::moduleID, $toggleOptionCode.'_'.$depOptionVal['VALUE'], $toggleOptionValue, $SITE_ID);
														}
													}
												}
											}
											else{
												Option::set(self::moduleID, $depOptionCode, $depOptionVal, $SITE_ID);
											}
										}
									}

									if(array_key_exists('ORDER', $optionVal)){
										Option::set(self::moduleID, 'SORT_ORDER_'.$optionCode.'_'.$propValue, $optionVal['ORDER'], $SITE_ID);
									}
								}
							}
						}
					}

					return true;
				}
			}
		}

		return false;
	}

	public static function setBackParametrsOfPreset($presetId, $SITE_ID = ''){
		return self::setBackPresetOptions(self::getOptionsOfPreset($presetId), $SITE_ID);
	}

	public static function getCurrentPresetBannerIndex($SITE_ID = ''){
		if (Bitrix\Main\Config\Option::get(self::moduleID, 'USE_BIG_BANNERS', 'N', $SITE_ID) !== 'Y') 
			return 1;

		$SITE_ID = strlen($SITE_ID) ? $SITE_ID : (defined('SITE_ID') ? SITE_ID : '');

		if ($curPreset = self::getCurrentPreset($SITE_ID)) {
			$precetID = $curPreset;
		}
		elseif ($curThematic = self::getCurrentThematic($SITE_ID)) {
			$precetID = self::$arThematicsList[$curThematic]['PRESETS']['DEFAULT'];
		}
		else {
			$precetID = self::$arThematicsList['UNVERSAL']['PRESETS']['DEFAULT'];
		}

		if($precetID){
			self::$arPresetsList[$precetID]['BANNER_INDEX'] = intval(self::$arPresetsList[$precetID]['BANNER_INDEX']);
			
			return self::$arPresetsList[$precetID]['BANNER_INDEX'] > 1 ? self::$arPresetsList[$precetID]['BANNER_INDEX'] : 1;
		}
		else{
			return 1;
		}
	}

	public static function CheckAdditionalChainInMultiLevel(&$arResult, &$arParams, &$arElement){
		global $APPLICATION;
		$APPLICATION->arAdditionalChain = false;
		if($arParams['INCLUDE_IBLOCK_INTO_CHAIN'] == 'Y' && isset(CAllcorp3Cache::$arIBlocksInfo[$arParams['IBLOCK_ID']]['NAME']))
			$APPLICATION->AddChainItem(CAllcorp3Cache::$arIBlocksInfo[$arParams['IBLOCK_ID']]['NAME'], $arElement['~LIST_PAGE_URL']);

		if($arParams['ADD_SECTIONS_CHAIN'] == 'Y')
		{
			if($arSection = CAllcorp3Cache::CIBlockSection_GetList(array('CACHE' => array('TAG' => CAllcorp3Cache::GetIBlockCacheTag($arElement['IBLOCK_ID']), 'MULTI' => 'N')), self::GetCurrentSectionFilter($arResult['VARIABLES'], $arParams), false, array('ID', 'NAME')))
			{
				$rsPath = CIBlockSection::GetNavChain($arParams['IBLOCK_ID'], $arSection['ID']);
				$rsPath->SetUrlTemplates('', $arParams['SECTION_URL']);
				while($arPath = $rsPath->GetNext())
				{
					$ipropValues = new \Bitrix\Iblock\InheritedProperty\SectionValues($arParams['IBLOCK_ID'], $arPath['ID']);
					$arPath['IPROPERTY_VALUES'] = $ipropValues->getValues();
					$arSection['PATH'][] = $arPath;
					$arSection['SECTION_URL'] = $arPath['~SECTION_PAGE_URL'];
				}

				foreach($arSection['PATH'] as $arPath)
				{
					if($arPath['IPROPERTY_VALUES']['SECTION_PAGE_TITLE'] != '')
						$APPLICATION->AddChainItem($arPath['IPROPERTY_VALUES']['SECTION_PAGE_TITLE'], $arPath['~SECTION_PAGE_URL']);
					else
						$APPLICATION->AddChainItem($arPath['NAME'], $arPath['~SECTION_PAGE_URL']);
				}
			}
		}
		if($arParams['ADD_ELEMENT_CHAIN'] == 'Y')
		{
			$ipropValues = new \Bitrix\Iblock\InheritedProperty\ElementValues($arParams['IBLOCK_ID'], $arElement['ID']);
			$arElement['IPROPERTY_VALUES'] = $ipropValues->getValues();
			if($arElement['IPROPERTY_VALUES']['ELEMENT_PAGE_TITLE'] != '')
				$APPLICATION->AddChainItem($arElement['IPROPERTY_VALUES']['ELEMENT_PAGE_TITLE']);
			else
				$APPLICATION->AddChainItem($arElement['NAME']);
		}
	}

	public static function CheckDetailPageUrlInMultilevel(&$arResult){
		if($arResult['ITEMS']){
			$arItemsIDs = $arItems = array();
			$CurrentSectionID = false;
			foreach($arResult['ITEMS'] as $arItem)
				$arItemsIDs[] = $arItem['ID'];

			$arItems = CAllcorp3Cache::CIBLockElement_GetList(array('CACHE' => array('TAG' => CAllcorp3Cache::GetIBlockCacheTag($arParams['IBLOCK_ID']), 'GROUP' => array('ID'), 'MULTI' => 'N')), array('ID' => $arItemsIDs), false, false, array('ID', 'IBLOCK_SECTION_ID', 'DETAIL_PAGE_URL'));
			if($arResult['SECTION']['PATH'])
			{
				for($i = count($arResult['SECTION']['PATH']) - 1; $i >= 0; --$i)
				{
					if(CSite::InDir($arResult['SECTION']['PATH'][$i]['SECTION_PAGE_URL']))
					{
						$CurrentSectionID = $arResult['SECTION']['PATH'][$i]['ID'];
						break;
					}
				}
			}
			foreach($arResult['ITEMS'] as $i => $arItem)
			{
				if(is_array($arItems[$arItem['ID']]['DETAIL_PAGE_URL']))
				{
					if($arItems[$arItem['ID']]['DETAIL_PAGE_URL'][$CurrentSectionID])
						$arResult['ITEMS'][$i]['DETAIL_PAGE_URL'] = $arItems[$arItem['ID']]['DETAIL_PAGE_URL'][$CurrentSectionID];
				}
				if(is_array($arItems[$arItem['ID']]['IBLOCK_SECTION_ID']))
					$arResult['ITEMS'][$i]['IBLOCK_SECTION_ID'] = $CurrentSectionID;
			}
		}
	}

	public static function unique_multidim_array($array, $key) {
	    $temp_array = array();
	    $i = 0;
	    $key_array = array();

	    foreach($array as $val) {
	        if (!in_array($val[$key], $key_array)) {
	            $key_array[$i] = $val[$key];
	            $temp_array[$i] = $val;
	        }
	        $i++;
	    }
	    return $temp_array;
	}

	public static function RegisterExtensions(){

		\CJSCore::RegisterExt(self::partnerName.'_font-awesome', array(
			'css' => SITE_TEMPLATE_PATH.'/css/fonts/font-awesome/css/font-awesome.min.css',
		));

		\CJSCore::RegisterExt(self::partnerName.'_fancybox', array(
			'js' => SITE_TEMPLATE_PATH.'/js/jquery.fancybox.js',
			'css' => SITE_TEMPLATE_PATH.'/css/jquery.fancybox.css',
		));
	}

	public static function InitExtensions($arExtensions){
		$arExtensions = is_array($arExtensions) ? $arExtensions : (array)$arExtensions;

		if($arExtensions){
			$arExtensions = array_map(function($ext){
				return strpos($ext, self::partnerName) !== false ? $ext : self::partnerName.'_'.$ext;
			}, $arExtensions);

			CJSCore::Init($arExtensions);
		}
	}

	public static function Start($siteID = 's1'){
		global $APPLICATION, $arRegion;

		if(CModule::IncludeModuleEx(self::moduleID) == 1)
		{
			// is Lighthouse
			$bPageSpeedTest = self::isPageSpeedTest();
			if($bPageSpeedTest){
				\Bitrix\Main\Data\StaticHtmlCache::getInstance()->markNonCacheable();
			}

			// mutation observer
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/observer.js');

			// inline jquery for fast inline CheckTopMenuDotted function
			$APPLICATION->AddHeadString('
				<script data-skip-moving="true" src="/bitrix/js/'.self::moduleID.'/jquery/jquery-2.1.3.min.js"></script>
				<script data-skip-moving="true" src="'.SITE_TEMPLATE_PATH.'/js/speed.min.js?='.filemtime($_SERVER['DOCUMENT_ROOT'].SITE_TEMPLATE_PATH.'/js/speed.min.js').'"></script>
			');

			if(!defined('ASPRO_USE_ONENDBUFFERCONTENT_HANDLER')){
				define('ASPRO_USE_ONENDBUFFERCONTENT_HANDLER', 'Y');
			}

			$APPLICATION->SetPageProperty("viewport", "initial-scale=1.0, width=device-width, maximum-scale=1");
			$APPLICATION->SetPageProperty("HandheldFriendly", "true");
			$APPLICATION->SetPageProperty("apple-mobile-web-app-capable", "yes");
			$APPLICATION->SetPageProperty("apple-mobile-web-app-status-bar-style", "black");
			$APPLICATION->SetPageProperty("SKYPE_TOOLBAR", "SKYPE_TOOLBAR_PARSER_COMPATIBLE");

			self::UpdateFrontParametrsValues(); //update theme values
			self::setThemeColorsValues(); // set theme colors vars values

			if(
				!defined('NOT_GENERATE_THEME') &&
				(
					!isset($_SERVER["HTTP_X_REQUESTED_WITH"]) || 
					strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != "xmlhttprequest"
				)
			){
				self::GenerateThemes($siteID); //generate theme.css and bgtheme.css
			}

			$arTheme = self::GetFrontParametrsValues($siteID); //get site options

			self::setFonts($arTheme);

			if($arTheme['USE_REGIONALITY'] == 'Y'){
				$arRegion = CAllcorp3Regionality::getCurrentRegion(); //get current region from regionality module
			}

			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/bootstrap.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/theme-elements.css');

			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/jqModal.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/jquery.mCustomScrollbar.min.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/vendor/css/ripple.css');

			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/animation/animate.min.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/animation/animation_ext.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/vendor/css/carousel/owl/owl.carousel.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/vendor/css/carousel/owl/owl.theme.default.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/owl-styles.css', true);
			
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/buttons.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/svg.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/header.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/footer.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/menu-top.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/mega-menu.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/mobile-header.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/mobile-menu.css');

			if($arTheme['TOP_MENU_FIXED'] === 'Y'){
				$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/header-fixed.css');
			}

			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/search-title.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/page-title-breadcrumb-pagination.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/social-icons.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/left-menu.css');

			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/tabs.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/top-menu.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/detail-gallery.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/detail.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/banners.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/yandex-map.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/bg-banner.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/smart-filter.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/basket.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/contacts.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/regions.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/profile.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/item-views.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/catalog.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/reviews.css');
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/projects.css');
			
			if($arTheme['H1_STYLE'] == 2){
				$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/h1-normal.css');
			}
			elseif($arTheme['H1_STYLE'] == 1){
				$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/h1-bold.css');
			}
			elseif($arTheme['H1_STYLE'] == 3){
				$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/h1-light.css');
			}

			self::setBlocksCss();

			if(self::IsMainPage()){
				$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/index-page.css');
			}

			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/form.css', true);
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/colored.css', true);
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/responsive.css', true);
			$APPLICATION->AddHeadString('<link href="'.$APPLICATION->oAsset->getFullAssetPath(SITE_TEMPLATE_PATH.'/css/print.css').'" data-template-style="true" rel="stylesheet" media="print">');
				
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/vendor/jquery.easing.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/vendor/jquery.cookie.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/vendor/bootstrap.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/vendor/jquery.validate.min.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/vendor/js/ripple.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/detectmobilebrowser.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/matchMedia.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.actual.min.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery-ui.min.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.plugin.min.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.inputmask.bundle.min.js', true);
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.alphanumeric.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.autocomplete.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.mousewheel.min.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.mobile.custom.touch.min.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.mCustomScrollbar.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jqModal.js');
			
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.uniform.min.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/vendor/js/carousel/owl/owl.carousel.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.countdown.min.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.countdown-ru.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/scrollTabs.js');

			self::setBlocksJs();

			if($arTheme['USE_LAZY_LOAD'] === 'Y'){
				$APPLICATION->AddHeadString('<script>window.lazySizesConfig = window.lazySizesConfig || {};lazySizesConfig.loadMode = 1;lazySizesConfig.expand = 200;lazySizesConfig.expFactor = 1;lazySizesConfig.hFac = 0.1;</script>');
				$APPLICATION->AddHeadString('<script src="'.SITE_TEMPLATE_PATH.'/vendor/lazysizes.min.js" data-skip-moving="true" defer=""></script>');
				$APPLICATION->AddHeadString('<script src="'.SITE_TEMPLATE_PATH.'/vendor/ls.unveilhooks.min.js" data-skip-moving="true" defer=""></script>');
			}

			if(self::IsMainPage()){
				$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/video_banner.js');
				$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.waypoints.min.js');
				$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/jquery.counterup.js');
			}

			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/general.js');
			$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/custom.js');

			// defer actual counter (compare)
			$APPLICATION->AddHeadString('<script data-skip-moving="true" src="'.SITE_TEMPLATE_PATH.'/js/actual.counter.min.js?='.filemtime($_SERVER['DOCUMENT_ROOT'].SITE_TEMPLATE_PATH.'/js/actual.counter.min.js').'" defer></script>');

			if(strlen($arTheme['FAVICON_IMAGE'])){
				$file_ext = pathinfo($arTheme['FAVICON_IMAGE'], PATHINFO_EXTENSION);
				$fav_ext = $file_ext ? $file_ext : 'ico';
				$fav_type = '';

				switch ($fav_ext) {
					case 'ico':
						$fav_type = 'image/x-icon';
						break;
					case 'svg':
						$fav_type = 'image/svg+xml';
						break;
					case 'png':
						$fav_type = 'image/png';
						break;
					case 'jpg':
						$fav_type = 'image/jpeg';
						break;
					case 'gif':
						$fav_type = 'image/gif';
						break;
					case 'bmp':
						$fav_type = 'image/bmp';
						break;
				}

				$APPLICATION->AddHeadString('<link rel="shortcut icon" href="'.$arTheme['FAVICON_IMAGE'].'" type="'.$fav_type.'" />', true);
			}

			if(strlen($arTheme['APPLE_TOUCH_ICON_IMAGE']))
				$APPLICATION->AddHeadString('<link rel="apple-touch-icon" sizes="180x180" href="'.$arTheme['APPLE_TOUCH_ICON_IMAGE'].'" />', true);
			
			// change default logo color
			if (
				$arTheme['THEME_VIEW_COLOR'] === 'DARK' ||
				(
					$arTheme['THEME_VIEW_COLOR'] === 'DEFAULT' &&
					isset($_COOKIE['prefers-color-scheme']) &&
					$_COOKIE['prefers-color-scheme'] === 'dark'
				)
			) {
				$APPLICATION->SetPageProperty('HEADER_LOGO', 'light');
				$APPLICATION->SetPageProperty('HEADER_FIXED_LOGO', 'light');
				$APPLICATION->SetPageProperty('HEADER_MOBILE_LOGO', 'light');
			}

			self::setLogoColor();
			self::setMobileLogoColor();
				
			$url = (CMain::IsHTTPS() ? 'https://' : 'http://').$_SERVER['SERVER_NAME'].$APPLICATION->GetCurUri();
//			$APPLICATION->AddHeadString('<link rel="alternate" media="only screen and (max-width: 640px)" href="'.$url.'"/>');

			//old_code
			self::RegisterExtensions();
			
			// register js and css libs
			\Aspro\Allcorp3\Functions\Extensions::register();
			
			// CJSCore::Init(array('jquery2'));
			CAjax::Init();

			if($arTheme['ORDER_VIEW'] === 'Y' || $arTheme['CATALOG_COMPARE'] === 'Y'){
				Aspro\Allcorp3\Functions\Extensions::init('notice');
			}

			self::showBgImage($siteID, $arTheme);

			\Aspro\Allcorp3\Functions\Extensions::init(['logo']);
		}
		else
		{
			$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/styles.css');
			$APPLICATION->SetTitle(GetMessage("ERROR_INCLUDE_MODULE"));
			$APPLICATION->IncludeFile(SITE_DIR."include/error_include_module.php", Array(), Array()); die();
		}

		// need for solution class and variables
		include_once($_SERVER['DOCUMENT_ROOT'].SITE_TEMPLATE_PATH.'/vendor/php/solution.php');
	}

	public static function ShowPageProps($prop){
		/** @global CMain $APPLICATION */
		global $APPLICATION;
		$APPLICATION->AddBufferContent(array("CAllcorp3", "GetPageProps"), $prop);
	}

	public static function GetPageProps($prop){
		/** @global CMain $APPLICATION */
		global $APPLICATION;

		if($prop == 'ERROR_404')
		{
			return (defined($prop) ? 'with_error' : '');
		}
		else
		{
			$val = $APPLICATION->GetProperty($prop);
			if(!empty($val))
				return $val;
		}
		return '';
	}

	public static function CopyFaviconToSiteDir($arValue, $siteID = ''){
		if(($siteID)){
			if(is_string($arValue) && $arValue) {
				$arValue = unserialize($arValue);
			}

			if(isset($arValue[0]) && $arValue[0]){
				$imageSrc = $_SERVER['DOCUMENT_ROOT'].CFile::GetPath($arValue[0]);
			}
			else{
				if($arTemplate = self::GetSiteTemplate($siteID)){
					$imageSrc = preg_replace('@/+@', '/', $arTemplate['PATH'].'/images/favicon.ico');
				}
			}

			$arSite = CSite::GetByID($siteID)->Fetch();


			if(!file_exists($imageSrc)){
				$imageSrc = preg_replace('@/+@', '/', $arSite['ABS_DOC_ROOT'].'/'.$arSite['DIR'].'/include/favicon.ico');
			}

			if(file_exists($imageSrc)){

				$file_ext = pathinfo($imageSrc, PATHINFO_EXTENSION);
				$fav_ext = $file_ext ? $file_ext : 'ico';

				$imageDest = preg_replace('@/+@', '/', $arSite['ABS_DOC_ROOT'].'/'.$arSite['DIR'].'/favicon.'.$fav_ext);

				if(file_exists($imageDest)){
					if(sha1_file($imageSrc) == sha1_file($imageDest)){
						return;
					}
				}

				$arFavExtFiles = array('ico', 'png', 'gif', 'bmp', 'jpg', 'svg');
				foreach( $arFavExtFiles as $unlinkExt){
					$imageUnlink = preg_replace('@/+@', '/', $arSite['ABS_DOC_ROOT'].'/'.$arSite['DIR'].'/favicon.'.$unlinkExt);
					var_dump($imageUnlink);
					if(file_exists($imageUnlink)){
						@unlink($imageUnlink);
					}
				}

				//@unlink($imageDest);
				@copy($imageSrc, $imageDest);
			}
		}
	}

	public static function GetSiteTemplate($siteID = ''){
		static $arCache;
		$arTemplate = array();

		if(strlen($siteID)){
			if(!isset($arCache)){
				$arCache = array();
			}

			if(!isset($arCache[$siteID])){
				$dbRes = CSite::GetTemplateList($siteID);
				while($arTemplate = $dbRes->Fetch()){
					if(!strlen($arTemplate['CONDITION'])){
						if(file_exists(($arTemplate['PATH'] = $_SERVER['DOCUMENT_ROOT'].'/bitrix/templates/'.$arTemplate['TEMPLATE']))){
							$arTemplate['DIR'] = '/bitrix/templates/'.$arTemplate['TEMPLATE'];
							break;
						}
						elseif(file_exists(($arTemplate['PATH'] = $_SERVER['DOCUMENT_ROOT'].'/local/templates/'.$arTemplate['TEMPLATE']))){
							$arTemplate['DIR'] = '/local/templates/'.$arTemplate['TEMPLATE'];
							break;
						}
					}
				}

				if($arTemplate){
					$arCache[$siteID] = $arTemplate;
				}
			}
			else{
				$arTemplate = $arCache[$siteID];
			}
		}

		return $arTemplate;
	}

	public static function FormatSumm($strPrice, $quantity = 1){
		$strSumm = '';

		if(strlen($strPrice = trim($strPrice))){
			$currency = '';
			$price = floatval(str_replace(' ', '', $strPrice));
			$summ = $price * $quantity;

			$strSumm = str_replace(trim(str_replace($currency, '', $strPrice)), str_replace('.00', '', number_format($summ, 2, '.', ' ')), $strPrice);
		}

		return $strSumm;
	}

	public static function ShowListRegions($arParams = array()){?>
		<?global $arTheme, $APPLICATION;
		static $list_regions_call;
		$iCalledID = ++$list_regions_call;?>
		<?$frame = new \Bitrix\Main\Page\FrameHelper('header-regionality-block'.$iCalledID);?>
		<?$frame->begin();?>
		<?$APPLICATION->IncludeComponent(
			"aspro:regionality.list.allcorp3",
			strtolower($arTheme["USE_REGIONALITY"]["DEPENDENT_PARAMS"]["REGIONALITY_VIEW"]["VALUE"]),
			$arParams,
			false,
			array('HIDE_ICONS' => 'Y')
		);?>
		<?$frame->end();?>
	<?}

	public static function FormatPriceShema($strPrice = '', $bShowSchema = true, $arElementProps = []){
		if (strlen($strPrice = trim($strPrice))){
			$bFilterPrice = false;
			if (isset($arElementProps["FILTER_PRICE"]) && $arElementProps["FILTER_PRICE"]["VALUE"] !== '' && $arElementProps["FILTER_PRICE"]["VALUE"] >= 0 && $bShowSchema) {
				$strPrice.= '<meta itemprop="price" content="'.$arElementProps["FILTER_PRICE"]["VALUE"].'">';
				$bFilterPrice = true;
			}

			if (isset($arElementProps["PRICE_CURRENCY"]) && $arElementProps["PRICE_CURRENCY"]["VALUE_XML_ID"] != NULL) {
				$strPrice = str_replace('#CURRENCY#',$arElementProps["PRICE_CURRENCY"]["VALUE"], $strPrice);
			}

			if (isset($arElementProps["PRICE_CURRENCY"]) && $arElementProps["PRICE_CURRENCY"]["VALUE_XML_ID"] != NULL && $bShowSchema) {
				$strPrice.= '<meta itemprop="priceCurrency" content="'.$arElementProps["PRICE_CURRENCY"]["VALUE_XML_ID"].'">';

			} else {
				$arCur = array(
					'$' => 'USD',
					GetMessage('ALLCORP3_CUR_EUR1') => 'EUR',
					GetMessage('ALLCORP3_CUR_RUB1') => 'RUB',
					GetMessage('ALLCORP3_CUR_RUB2') => 'RUB',
					GetMessage('ALLCORP3_CUR_UAH1') => 'UAH',
					GetMessage('ALLCORP3_CUR_UAH2') => 'UAH',
					GetMessage('ALLCORP3_CUR_RUB3') => 'RUB',
					GetMessage('ALLCORP3_CUR_RUB4') => 'RUB',
					GetMessage('ALLCORP3_CUR_RUB5') => 'RUB',
					GetMessage('ALLCORP3_CUR_RUB6') => 'RUB',
					GetMessage('ALLCORP3_CUR_RUB3') => 'RUB',
					GetMessage('ALLCORP3_CUR_UAH3') => 'UAH',
					GetMessage('ALLCORP3_CUR_RUB5') => 'RUB',
					GetMessage('ALLCORP3_CUR_UAH6') => 'UAH',
				);
				foreach($arCur as $curStr => $curCode){
					if(strpos($strPrice, $curStr) !== false){
						$priceVal = str_replace($curStr, '', $strPrice);
						if($bShowSchema)
							return str_replace(array($curStr, $priceVal), array('<span class="currency" itemprop="priceCurrency" content="'.$curCode.'">'.$curStr.'</span>', '<span itemprop="price" content="'.$priceVal.'">'.$priceVal.'</span>'), $strPrice);
						else
							return str_replace(array($curStr, $priceVal), array('<span class="currency">'.$curStr.'</span>', '<span>'.$priceVal.'</span>'), $strPrice);
					}
				}
			}
		}
		return $strPrice;
	}

	public static function GetBannerStyle($bannerwidth, $topmenu){
       /* $style = "";

        if($bannerwidth == "WIDE"){
            $style = ".maxwidth-banner{max-width: 1550px;}";
        }
        elseif($bannerwidth == "MIDDLE"){
            $style = ".maxwidth-banner{max-width: 1450px;}";
        }
        elseif($bannerwidth == "NARROW"){
            $style = ".maxwidth-banner{max-width: 1343px; padding: 0 16px;}";
			if($topmenu !== 'LIGHT'){
				$style .= ".banners-big{margin-top:20px;}";
			}
        }
        else{
            $style = ".maxwidth-banner{max-width: auto;}";
        }

        return "<style>".$style."</style>";*/
    }

    public static function GetIndexPageBlocks($pageAbsPath, $pageBlocksPrefix, $pageBlocksDirName = 'page_blocks'){
    	$arResult = array();

    	if($pageAbsPath && $pageBlocksPrefix){
    		$pageAbsPath = str_replace('//', '//', $pageAbsPath).'/';
    		if(is_dir($pageBlocksAbsPath = str_replace('', '', $pageAbsPath.(strlen($pageBlocksDirName) ? $pageBlocksDirName : '')))){
    			if($arPageBlocks = glob($pageBlocksAbsPath.'/*.php')){
		    		foreach($arPageBlocks as $file){
						$file = str_replace('.php', '', basename($file));
						if(strpos($file, $pageBlocksPrefix) !== false){
							$arResult[$file] = $file;
						}
					}
    			}
    		}
    	}

    	return $arResult;
    }

    public static function GetComponentTemplatePageBlocks($templateAbsPath, $pageBlocksDirName = 'page_blocks'){
    	$arResult = array('SECTIONS' => array(), 'SUBSECTIONS' => array(), 'ELEMENTS' => array(), 'ELEMENTS_TABLE' => array(), 'ELEMENTS_LIST' => array(), 'ELEMENTS_PRICE' => array(), 'ELEMENT' => array());

    	if($templateAbsPath){
    		$templateAbsPath = str_replace('//', '//', $templateAbsPath).'/';
    		if(is_dir($pageBlocksAbsPath = str_replace('//', '/', $templateAbsPath.(strlen($pageBlocksDirName) ? $pageBlocksDirName : '')))){
    			if($arPageBlocks = glob($pageBlocksAbsPath.'/*.php')){
		    		foreach($arPageBlocks as $file){
						$file = str_replace('.php', '', basename($file));
						if(strpos($file, 'sections_') !== false){
							$arResult['SECTIONS'][$file] = $file;
						}
						elseif(strpos($file, 'section_') !== false){
							$arResult['SUBSECTIONS'][$file] = $file;
						}
						elseif(strpos($file, 'list_elements_') !== false){
							$arResult['ELEMENTS'][$file] = $file;
						}
						elseif(strpos($file, 'catalog_table') !== false){
							$arResult['ELEMENTS_TABLE'][$file] = $file;
						}
						elseif(strpos($file, 'catalog_list') !== false){
							$arResult['ELEMENTS_LIST'][$file] = $file;
						}
						elseif(strpos($file, 'catalog_price') !== false){
							$arResult['ELEMENTS_PRICE'][$file] = $file;
						}
						elseif(strpos($file, 'element_') !== false){
							$arResult['ELEMENT'][$file] = $file;
						}
						elseif(strpos($file, 'fast_view_') !== false){
							$arResult['FAST_VIEW_ELEMENT'][$file] = $file;
						}
						elseif(strpos($file, 'bigdata_') !== false){
							$arResult['BIGDATA'][$file] = $file;
						}
						elseif(strpos($file, 'landing_') !== false){
							$arResult['LANDING'][$file] = $file;
						}
					}
    			}
    		}
    	}

    	return $arResult;
    }

    public static function GetComponentTemplatePageBlocksParams($arPageBlocks){
    	$arResult = array();

    	if($arPageBlocks && is_array($arPageBlocks)){
    		if(isset($arPageBlocks['SECTIONS']) && $arPageBlocks['SECTIONS'] && is_array($arPageBlocks['SECTIONS'])){
    			$arResult['SECTIONS_TYPE_VIEW'] = array(
					'PARENT' => 'BASE',
					'SORT' => 1,
					'NAME' => GetMessage('M_SECTIONS_TYPE_VIEW'),
					'TYPE' => 'LIST',
					'VALUES' => $arPageBlocks['SECTIONS'],
					'DEFAULT' => key($arPageBlocks['SECTIONS']),
					'REFRESH' => 'Y',
				);
    		}
    		if(isset($arPageBlocks['SUBSECTIONS']) && $arPageBlocks['SUBSECTIONS'] && is_array($arPageBlocks['SUBSECTIONS'])){
    			$arResult['SECTION_TYPE_VIEW'] = array(
					'PARENT' => 'BASE',
					'SORT' => 1,
					'NAME' => GetMessage('M_SECTION_TYPE_VIEW'),
					'TYPE' => 'LIST',
					'VALUES' => $arPageBlocks['SUBSECTIONS'],
					'DEFAULT' => key($arPageBlocks['SUBSECTIONS']),
					'REFRESH' => 'Y',
				);
    		}
    		if(isset($arPageBlocks['ELEMENTS']) && $arPageBlocks['ELEMENTS'] && is_array($arPageBlocks['ELEMENTS'])){
    			$arResult['SECTION_ELEMENTS_TYPE_VIEW'] = array(
					'PARENT' => 'BASE',
					'SORT' => 1,
					'NAME' => GetMessage('M_SECTION_ELEMENTS_TYPE_VIEW'),
					'TYPE' => 'LIST',
					'VALUES' => $arPageBlocks['ELEMENTS'],
					'DEFAULT' => key($arPageBlocks['ELEMENTS']),
					'REFRESH' => 'Y',
				);
    		}
    		if(isset($arPageBlocks['ELEMENTS_PRICE']) && $arPageBlocks['ELEMENTS_PRICE'] && is_array($arPageBlocks['ELEMENTS_PRICE'])){
    			$arResult['ELEMENTS_PRICE_TYPE_VIEW'] = array(
					'PARENT' => 'BASE',
					'SORT' => 1,
					'NAME' => GetMessage('M_ELEMENTS_PRICE_TYPE_VIEW'),
					'TYPE' => 'LIST',
					'VALUES' => $arPageBlocks['ELEMENTS_PRICE'],
					'DEFAULT' => key($arPageBlocks['ELEMENTS_PRICE']),
				);
    		}
    		if(isset($arPageBlocks['ELEMENTS_LIST']) && $arPageBlocks['ELEMENTS_LIST'] && is_array($arPageBlocks['ELEMENTS_LIST'])){
    			$arResult['ELEMENTS_LIST_TYPE_VIEW'] = array(
					'PARENT' => 'BASE',
					'SORT' => 1,
					'NAME' => GetMessage('M_ELEMENTS_LIST_TYPE_VIEW'),
					'TYPE' => 'LIST',
					'VALUES' => $arPageBlocks['ELEMENTS_LIST'],
					'DEFAULT' => key($arPageBlocks['ELEMENTS_LIST']),
				);
    		}
    		if(isset($arPageBlocks['ELEMENTS_TABLE']) && $arPageBlocks['ELEMENTS_TABLE'] && is_array($arPageBlocks['ELEMENTS_TABLE'])){
    			$arResult['ELEMENTS_TABLE_TYPE_VIEW'] = array(
					'PARENT' => 'BASE',
					'SORT' => 1,
					'NAME' => GetMessage('M_ELEMENTS_TABLE_TYPE_VIEW'),
					'TYPE' => 'LIST',
					'VALUES' => $arPageBlocks['ELEMENTS_TABLE'],
					'DEFAULT' => key($arPageBlocks['ELEMENTS_TABLE']),
					'REFRESH' => 'Y',
				);
    		}
    		if(isset($arPageBlocks['ELEMENT']) && $arPageBlocks['ELEMENT'] && is_array($arPageBlocks['ELEMENT'])){
    			$arResult['ELEMENT_TYPE_VIEW'] = array(
					'PARENT' => 'BASE',
					'SORT' => 1,
					'NAME' => GetMessage('M_ELEMENT_TYPE_VIEW'),
					'TYPE' => 'LIST',
					'VALUES' => $arPageBlocks['ELEMENT'],
					'DEFAULT' => key($arPageBlocks['ELEMENT']),
				);
    		}
    	}

    	return $arResult;
    }

   protected static function IsComponentTemplateHasModuleElementsPageBlocksParam($templateName, $arExtParams = array()){
    	$section_param = ((isset($arExtParams['SECTION']) && $arExtParams['SECTION']) ? $arExtParams['SECTION'] : 'SECTION');
    	$template_param = ((isset($arExtParams['OPTION']) && $arExtParams['OPTION']) ? $arExtParams['OPTION'] : strtoupper($templateName));
	    return $templateName && isset(self::$arParametrsList[$section_param]['OPTIONS'][$template_param.'_PAGE']);
    }

    protected static  function IsComponentTemplateHasModuleElementPageBlocksParam($templateName, $arExtParams = array()){
    	$section_param = ((isset($arExtParams['SECTION']) && $arExtParams['SECTION']) ? $arExtParams['SECTION'] : 'SECTION');
    	$template_param = ((isset($arExtParams['OPTION']) && $arExtParams['OPTION']) ? $arExtParams['OPTION'] : strtoupper($templateName));
	    return $templateName && isset(self::$arParametrsList[$section_param]['OPTIONS'][$template_param.'_PAGE_DETAIL']);
    }

    protected static  function IsComponentTemplateHasModuleElementsTemplatePageBlocksParam($templateName, $arExtParams = array()){
    	$section_param = ((isset($arExtParams['SECTION']) && $arExtParams['SECTION']) ? $arExtParams['SECTION'] : 'SECTION');
    	$template_param = ((isset($arExtParams['OPTION']) && $arExtParams['OPTION']) ? $arExtParams['OPTION'] : strtoupper($templateName));
	    return $templateName && isset(self::$arParametrsList[$section_param]['OPTIONS'][$template_param]);
    }

    public static function AddComponentTemplateModulePageBlocksParams($templateAbsPath, &$arParams, $arExtParams = array(), $listParam = ''){
    	if($templateAbsPath && $arParams && is_array($arParams)){
    		$templateAbsPath = str_replace('//', '//', $templateAbsPath).'/';
    		$templateName = basename($templateAbsPath);
    		if(self::IsComponentTemplateHasModuleElementsPageBlocksParam($templateName, $arExtParams)){
    			$arParams['SECTION_ELEMENTS_TYPE_VIEW']['VALUES'] = array_merge(array('FROM_MODULE' => GetMessage('M_FROM_MODULE_PARAMS')), $arParams['SECTION_ELEMENTS_TYPE_VIEW']['VALUES']);
    			$arParams['SECTION_ELEMENTS_TYPE_VIEW']['DEFAULT'] = 'FROM_MODULE';
    		}
    		if(self::IsComponentTemplateHasModuleElementPageBlocksParam($templateName, $arExtParams)){
    			$arParams['ELEMENT_TYPE_VIEW']['VALUES'] = array_merge(array('FROM_MODULE' => GetMessage('M_FROM_MODULE_PARAMS')), $arParams['ELEMENT_TYPE_VIEW']['VALUES']);
    			$arParams['ELEMENT_TYPE_VIEW']['DEFAULT'] = 'FROM_MODULE';
    		}
    		if(self::IsComponentTemplateHasModuleElementsTemplatePageBlocksParam($templateName, $arExtParams)){
    			$param = $arExtParams['OPTION'];
    			if($listParam)
    				$param = $listParam;

    			$arParams[$param]['VALUES'] = array_merge(array('FROM_MODULE' => GetMessage('M_FROM_MODULE_PARAMS')), $arParams[$param]['VALUES']);
    			$arParams[$param]['DEFAULT'] = 'FROM_MODULE';
    		}
    	}
    }

    public static function CheckComponentTemplatePageBlocksParams(&$arParams, $templateAbsPath, $pageBlocksDirName = 'page_blocks'){
    	$arPageBlocks = self::GetComponentTemplatePageBlocks($templateAbsPath, $pageBlocksDirName);

    	if(!isset($arParams['SECTIONS_TYPE_VIEW']) || !$arParams['SECTIONS_TYPE_VIEW'] || (!isset($arPageBlocks['SECTIONS'][$arParams['SECTIONS_TYPE_VIEW']]) && $arParams['SECTIONS_TYPE_VIEW'] !== 'FROM_MODULE')){
    		$arParams['SECTIONS_TYPE_VIEW'] = key($arPageBlocks['SECTIONS']);
    	}
    	if(!isset($arParams['SECTION_TYPE_VIEW']) || !$arParams['SECTION_TYPE_VIEW'] || (!isset($arPageBlocks['SUBSECTIONS'][$arParams['SECTION_TYPE_VIEW']]) && $arParams['SECTION_TYPE_VIEW'] !== 'FROM_MODULE')){
    		$arParams['SECTION_TYPE_VIEW'] = key($arPageBlocks['SUBSECTIONS']);
    	}
    	if(!isset($arParams['SECTION_ELEMENTS_TYPE_VIEW']) || !$arParams['SECTION_ELEMENTS_TYPE_VIEW'] || (!isset($arPageBlocks['ELEMENTS'][$arParams['SECTION_ELEMENTS_TYPE_VIEW']]) && $arParams['SECTION_ELEMENTS_TYPE_VIEW'] !== 'FROM_MODULE')){
    		$arParams['SECTION_ELEMENTS_TYPE_VIEW'] = key($arPageBlocks['ELEMENTS']);
    	}
    	if(!isset($arParams['ELEMENTS_TABLE_TYPE_VIEW']) || !$arParams['ELEMENTS_TABLE_TYPE_VIEW'] || (!isset($arPageBlocks['ELEMENTS_TABLE'][$arParams['ELEMENTS_TABLE_TYPE_VIEW']]) && $arParams['ELEMENTS_TABLE_TYPE_VIEW'] !== 'FROM_MODULE')){
    		$arParams['ELEMENTS_TABLE_TYPE_VIEW'] = key($arPageBlocks['ELEMENTS_TABLE']);
    	}
    	if(!isset($arParams['ELEMENTS_LIST_TYPE_VIEW']) || !$arParams['ELEMENTS_LIST_TYPE_VIEW'] || (!isset($arPageBlocks['ELEMENTS_LIST'][$arParams['ELEMENTS_LIST_TYPE_VIEW']]) && $arParams['ELEMENTS_LIST_TYPE_VIEW'] !== 'FROM_MODULE')){
    		$arParams['ELEMENTS_LIST_TYPE_VIEW'] = key($arPageBlocks['ELEMENTS_LIST']);
    	}
    	if(!isset($arParams['ELEMENTS_PRICE_TYPE_VIEW']) || !$arParams['ELEMENTS_PRICE_TYPE_VIEW'] || (!isset($arPageBlocks['ELEMENTS_PRICE'][$arParams['ELEMENTS_PRICE_TYPE_VIEW']]) && $arParams['ELEMENTS_PRICE_TYPE_VIEW'] !== 'FROM_MODULE')){
    		$arParams['ELEMENTS_PRICE_TYPE_VIEW'] = key($arPageBlocks['ELEMENTS_PRICE']);
    	}
    	if(!isset($arParams['ELEMENT_TYPE_VIEW']) || !$arParams['ELEMENT_TYPE_VIEW'] || (!isset($arPageBlocks['ELEMENT'][$arParams['ELEMENT_TYPE_VIEW']]) && $arParams['ELEMENT_TYPE_VIEW'] !== 'FROM_MODULE')){
    		$arParams['ELEMENT_TYPE_VIEW'] = key($arPageBlocks['ELEMENT']);
    	}
    }

	public static function Add2OptionCustomPageBlocks(&$arOption, $templateAbsPath, $filename, $prefix = ''){
		if($arOption && isset($arOption['LIST'])){
			if($templateAbsPath)
			{
	    		$templateAbsPath = str_replace('//', '//', $templateAbsPath).'/';
	    		if(is_dir($pageBlocksAbsPath = str_replace('//', '/', $templateAbsPath)))
	    		{
	    			if($arPageBlocks = glob($pageBlocksAbsPath.'/'.$filename.'*.php'))
	    			{
			    		foreach($arPageBlocks as $file)
			    		{
			    			$replace = array(
								$filename,
			    				'.php',
			    			);
							$title = basename($file);
							$file = str_replace($replace, '', basename($file));
			    			if($prefix) {
			    				$file = $prefix.$file;
							}
							if(!isset($arOption['LIST'][$file]))
							{
								$arOption['LIST'][$file] = array(
									'TITLE' => $title,
									'HIDE' => 'Y',
									'IS_CUSTOM' => 'Y',
								);
							}
			    		}
			    	}
				}
				if(!$arOption['DEFAULT'] && $arOption['LIST'])
					$arOption['DEFAULT'] = key($arOption['LIST']);
			}
		}
    }

    public static function Add2OptionCustomComponentTemplatePageBlocks(&$arOption, $templateAbsPath){
		if($arOption && isset($arOption['LIST'])){
			if($arPageBlocks = self::GetComponentTemplatePageBlocks($templateAbsPath)){
				foreach($arPageBlocks['ELEMENTS'] as $page => $value){
					if(!isset($arOption['LIST'][$page])){
						$arOption['LIST'][$page] = array(
							'TITLE' => $value,
							'HIDE' => 'Y',
							'IS_CUSTOM' => 'Y',
						);
					}
				}
				if(!$arOption['DEFAULT'] && $arOption['LIST']){
					$arOption['DEFAULT'] = key($arOption['LIST']);
				}
			}
		}
    }

    public static function Add2OptionCustomComponentTemplatePageBlocksElement(&$arOption, $templateAbsPath, $field = 'ELEMENT'){
		if($arOption && isset($arOption['LIST'])){
			if($arPageBlocks = self::GetComponentTemplatePageBlocks($templateAbsPath)){

				foreach($arPageBlocks[$field] as $page => $value){
					if(!isset($arOption['LIST'][$page])){
						$arOption['LIST'][$page] = array(
							'TITLE' => $value,
							'HIDE' => 'Y',
							'IS_CUSTOM' => 'Y',
						);
					}
				}
				if(!$arOption['DEFAULT'] && $arOption['LIST']){
					$arOption['DEFAULT'] = key($arOption['LIST']);
				}
			}
		}
    }

    public static function formatProps($arItem){
    	$arProps = array();
    	foreach($arItem['DISPLAY_PROPERTIES'] as $code => $arProp)
    	{
    		if($arProp['VALUE'])
    		{
    			if(!in_array($arProp['CODE'], array('PERIOD', 'PHOTOS', 'PRICE', 'PRICEOLD', 'ARTICLE', 'STATUS', 'DOCUMENTS', 'LINK_GOODS', 'LINK_STAFF', 'LINK_REVIEWS', 'LINK_PROJECTS', 'LINK_SERVICES', 'FORM_ORDER', 'FORM_QUESTION', 'PHOTOPOS', 'FILTER_PRICE', 'SHOW_ON_INDEX_PAGE', 'BNR_TOP', 'BNR_TOP_IMG', 'BNR_TOP_BG', 'CODE_TEXT', 'HIT', 'VIDEO', 'VIDEO_IFRAME', 'GALLEY_BIG')) && ($arProp['PROPERTY_TYPE'] != 'E' && $arProp['PROPERTY_TYPE'] != 'G'))
    			{
    				if(is_array($arProp['DISPLAY_VALUE']))
    					$arProp['VALUE'] = implode(', ', (array)$arProp['DISPLAY_VALUE']);
    				$arProps[$code] = $arProp;
    			}
    		}
    	}
    	return $arProps;
    }

    public static function FormatNewsUrl($arItem){
    	$url = $arItem['DETAIL_PAGE_URL'];
    	if(strlen($arItem['DISPLAY_PROPERTIES']['REDIRECT']['VALUE']))
		{
			$url = $arItem['DISPLAY_PROPERTIES']['REDIRECT']['VALUE'];
			return $url;
		}
    	if($arItem['ACTIVE_FROM'])
    	{
    		if($arDateTime = ParseDateTime($arItem['ACTIVE_FROM'], FORMAT_DATETIME))
    		{
		        $url = str_replace("#YEAR#", $arDateTime['YYYY'], $arItem['DETAIL_PAGE_URL']);
		        return $url;
    		}
    	}
    	return $url;
    }

    public static function GetItemsYear($arParams){
    	$arResult = array();
    	$arItems = CAllcorp3Cache::CIBLockElement_GetList(array('SORT' => 'ASC', 'NAME' => 'ASC', 'CACHE' => array('TAG' => CAllcorp3Cache::GetIBlockCacheTag($arParams['IBLOCK_ID']))), array('IBLOCK_ID' => $arParams['IBLOCK_ID'], 'ACTIVE' => 'Y'), false, false, array('ID', 'NAME', 'ACTIVE_FROM'));
		if($arItems)
		{
			foreach($arItems as $arItem)
			{
				if($arItem['ACTIVE_FROM'])
				{
					if($arDateTime = ParseDateTime($arItem['ACTIVE_FROM'], FORMAT_DATETIME))
						$arResult[$arDateTime['YYYY']] = $arDateTime['YYYY'];
				}
			}
		}
		return $arResult;
    }

	public static function GetDirMenuParametrs($dir){
		if(strlen($dir))
		{
			$file = str_replace('//', '/', $dir.'/.section.php');
			if(file_exists($file)){
				@include($file);
				return $arDirProperties;
			}
		}

		return false;
	}

	public static function IsMainPage(){
		static $result;

		if(!isset($result))
			$result = CSite::InDir(SITE_DIR.'index.php');

		return $result;
	}

	public static function IsBasketPage($url_link = ''){
		static $result;

		if(!isset($result))
		{
			if(!$url_link)
			{
				$arOptions = self::GetBackParametrsValues(SITE_ID);
				if(!strlen($arOptions["BASKET_PAGE_URL"]))
					$arOptions["BASKET_PAGE_URL"] = SITE_DIR."cart/";
				$url_link = $arOptions["BASKET_PAGE_URL"];
			}
			$result = CSite::InDir($url_link);
		}

		return $result;
	}
	
	public static function IsComparePage($url_link = ''){
		static $result;

		if(!isset($result))
		{
			if(!$url_link)
			{
				$arOptions = self::GetBackParametrsValues(SITE_ID);
				if(!strlen($arOptions["COMPARE_PAGE_URL"]))
					$arOptions["COMPARE_PAGE_URL"] = SITE_DIR."catalog/compare.php";
				$url_link = $arOptions["COMPARE_PAGE_URL"];
			}
			$result = CSite::InDir($url_link);
		}

		return $result;
	}

	public static function IsOrderPage($url_link = ''){
		static $result;

		if(!isset($result))
		{
			if(!$url_link)
			{
				$arOptions = self::GetBackParametrsValues(SITE_ID);
				if(!strlen($arOptions["ORDER_PAGE_URL"]))
					$arOptions["ORDER_PAGE_URL"] = SITE_DIR."cart/order/";
				$url_link = $arOptions["ORDER_PAGE_URL"];
			}
			$result = CSite::InDir($url_link);
		}

		return $result;
	}

	public static function IsPersonalPage($page = ''){
		static $result;

		if(!isset($result))
		{
			if(!$page)
			{
				$arOptions = self::GetBackParametrsValues(SITE_ID);
				if(!strlen($arOptions['PERSONAL_PAGE_URL']))
					$arOptions['PERSONAL_PAGE_URL'] = SITE_DIR.'cabinet/';
				$page = $arOptions['PERSONAL_PAGE_URL'];
			}
			$result = CSite::InDir($page);
		}

		return $result;
	}

	public static function IsCatalogPage($page = ''){
		static $result;

		if(!isset($result))
		{
			if(!$page)
			{
				$arOptions = self::GetBackParametrsValues(SITE_ID);
				if(!strlen($arOptions['CATALOG_PAGE_URL']))
					$arOptions['CATALOG_PAGE_URL'] = SITE_DIR.'product/';
				$page = $arOptions['CATALOG_PAGE_URL'];
			}
			$result = CSite::InDir($page);
		}

		return $result;
	}

	public static function getConditionClass(){
		global $APPLICATION, $sideMenuHeader;

		$class = $APPLICATION->AddBufferContent(array('CAllcorp3', 'showPageClass'));
		

		if ($APPLICATION->GetProperty('MENU') === 'N') {
			$class = ' hide_menu_page';
		}
		if ($APPLICATION->GetProperty('HIDETITLE') === 'Y') {
			$class .= ' hide_title_page';
		}
		if ($APPLICATION->GetProperty('FULLWIDTH') === 'Y') {
			$class .= ' wide_page';
		}
		if (self::IsMainPage()) {
			$class .= ' front_page';
		}

		$arSiteThemeOptions = self::GetFrontParametrsValues(SITE_ID);
		$class .= ' region_confirm_'.strtolower($arSiteThemeOptions['REGIONALITY_CONFIRM']);
		$class .= ' header_fill_'.strtolower($arSiteThemeOptions['MENU_COLOR']);
		$class .= ' all_title_'.strtolower($arSiteThemeOptions['H1_STYLE']);
		$class .= ' menu_lowercase_'.strtolower($arSiteThemeOptions['MENU_LOWERCASE']);

		$class .= ' fixed_'.strtolower($arSiteThemeOptions['TOP_MENU_FIXED']);
		$class .= ' mfixed_'.strtolower($arSiteThemeOptions['HEADER_MOBILE_FIXED']);
		$class .= ' mfixed_view_'.strtolower($arSiteThemeOptions['HEADER_MOBILE_SHOW']);
		$class .= ' title_position_'.strtolower($arSiteThemeOptions['PAGE_TITLE_POSITION']);
		$class .= ' mmenu_'.($arSiteThemeOptions['HEADER_MOBILE_MENU_OPEN'] == 1 ? 'leftside' : 'dropdown');
		$class .= ' mheader-v'.$arSiteThemeOptions['HEADER_MOBILE'];
		$class .= ' footer-v'.strtolower($arSiteThemeOptions['FOOTER_TYPE']);
		$class .= ' fill_bg_'.strtolower($arSiteThemeOptions['SHOW_BG_BLOCK']);
		$class .= ' header-v'.$arSiteThemeOptions['HEADER_TYPE'];
		$class .= ' title-v'.$arSiteThemeOptions['PAGE_TITLE'];
		$class .= ' bottom-icons-panel_'.strtolower($arSiteThemeOptions['BOTTOM_ICONS_PANEL']);
		$class .= $arSiteThemeOptions['ORDER_VIEW'] === 'Y' && $arSiteThemeOptions['ORDER_BASKET_VIEW'] === 'HEADER'? ' with_order' : '';
		$class .= $arSiteThemeOptions['CABINET'] === 'Y' ? ' with_cabinet' : '';
		$class .= intval($arSiteThemeOptions['HEADER_PHONES']) > 0 ? ' with_phones' : '';

		

		global $showBgBanner;
		if($showBgBanner)
			$class .= ' visible_banner';

		/* default|light|dark theme */
		$class .= ' theme-'.strtolower($arSiteThemeOptions['THEME_VIEW_COLOR']);
		/* */

		return $class;
	}

	public static function showPageClass(){
		global $bodyDopClass, $bannerTemplate, $bWideImg, $tizersPadding0, $sideMenuHeader, $APPLICATION, $arMergeOptions;

		$bSideBlockLeft = $APPLICATION->GetProperty('MENU_ONLY_LEFT') === 'Y';
		$bSideBlockRight = $APPLICATION->GetProperty('MENU_ONLY_RIGHT') === 'Y';

		$class = $bannerTemplate;
		$class .= ((strpos($bannerTemplate, 'big-short_mix') !== false || strpos($bannerTemplate, 'big-short_small') !== false) ? ' banner_offset' : '');

		$class .= ($tizersPadding0 ? ' tizersPadding0' : '');

		$class .= ' '.$bodyDopClass;

		if($bWideImg)
			$class .= ' with_custom_img';

		if ($bSideBlockLeft) {
			$class .= ' side_left';
		}
		if ($bSideBlockRight) {
			$class .= ' side_right';
		}

		$sideMenu = isset($arMergeOptions['SIDE_MENU']) ? $arMergeOptions['SIDE_MENU'] : self::GetFrontParametrValue( 'SIDE_MENU', SITE_ID);
		$bSwitcher = self::GetFrontParametrValue( 'THEME_SWITCHER', SITE_ID) === "Y";
		if (!$bSideBlockLeft && !$bSideBlockRight) {			
			$class .= ' side_'.strtolower($sideMenu);
		}

		return $class;
	}

	public static function goto404Page(){
		global $APPLICATION;

		if($_SESSION['SESS_INCLUDE_AREAS']){
			echo '</div>';
		}
		echo '</div>';
		$APPLICATION->IncludeFile(SITE_DIR.'404.php', array(), array('MODE' => 'html'));
		die();
	}

	/*public static function checkRestartBuffer(){
		global $APPLICATION;
		static $bRestarted;

		if($bRestarted)
			die();


		if((isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == "xmlhttprequest") || (strtolower($_REQUEST['ajax']) == 'y'))
		{
			$APPLICATION->RestartBuffer();
			$bRestarted = true;
		}
	}*/

	public static function checkRestartBuffer($bFront = false, $param = '', $reset = false){
		global $APPLICATION, $isIndex;
		static $bRestarted, $bFrontRestarted;

		if(!$bFront)
		{
			if($bRestarted)
				die();
		}
		else
		{
			if($bFrontRestarted && !$reset)
				die();
		}


		if(self::checkAjaxRequest())
		{
			$APPLICATION->RestartBuffer();
			if(!$bFront)
			{
				if(!$isIndex)
				{
					$bRestarted = true;
					$bFrontRestarted = false;
				}
			}
			else
			{
				if($param)
				{
					$context=\Bitrix\Main\Context::getCurrent();
					$request=$context->getRequest();

					if($request->getQuery('BLOCK') == $param)
					{
						$bRestarted = false;
						$bFrontRestarted = true;
					}
				}
				else
				{
					$bRestarted = false;
					$bFrontRestarted = true;
				}
			}
		}
	}

	public static function UpdateFormEvent(&$arFields){
		if($arFields['ID'] && $arFields['IBLOCK_ID'])
		{
			// find aspro form event for this iblock
			$arEventIDs = array('ASPRO_SEND_FORM_'.$arFields['IBLOCK_ID'], 'ASPRO_SEND_FORM_ADMIN_'.$arFields['IBLOCK_ID']);
			$arLangIDs = array('ru', 'en');
			static $arEvents;
			if($arEvents == NULL)
			{
				foreach($arEventIDs as $EVENT_ID)
				{
					foreach($arLangIDs as $LANG_ID)
					{
						$resEvents = CEventType::GetByID($EVENT_ID, $LANG_ID);
						$arEvents[$EVENT_ID][$LANG_ID] = $resEvents->Fetch();
					}
				}
			}
			if($arEventIDs)
			{
				foreach($arEventIDs as $EVENT_ID)
				{
					foreach($arLangIDs as $LANG_ID)
					{
						if($arEvent = &$arEvents[$EVENT_ID][$LANG_ID])
						{
							if(strpos($arEvent['DESCRIPTION'], $arFields['NAME'].': #'.$arFields['CODE'].'#') === false){
								$arEvent['DESCRIPTION'] = str_replace('#'.$arFields['CODE'].'#', '-', $arEvent['DESCRIPTION']);
								$arEvent['DESCRIPTION'] .= $arFields['NAME'].': #'.$arFields['CODE']."#\n";
								CEventType::Update(array('ID' => $arEvent['ID']), $arEvent);
							}
						}
					}
				}
			}
		}
	}

	public static function ShowHeaderMobilePhones($arOptions = array()){
		static $hphones_call_m;
		global $arRegion, $arTheme, $APPLICATION;
		$arDefaulOptions = array(
			'CLASS' => '',
			'CALLBACK' => true,
		);
		$arOptions = array_merge($arDefaulOptions, $arOptions);

		$arBackParametrs = self::GetBackParametrsValues(SITE_ID);
		$iCountPhones = ($arRegion ? count($arRegion['PHONES']) : $arBackParametrs['HEADER_PHONES']);
		?>
		<?if($arRegion):?>
			<?
			$iCalledID = ++$hphones_call_m;
			$frame = new \Bitrix\Main\Page\FrameHelper('header-allphones-block'.$iCalledID);
			$frame->begin();
			?>
		<?endif;?>
		<?if($iCountPhones): // count of phones?>
			<?
			$phone = ($arRegion ? $arRegion['PHONES'][0] : $arBackParametrs['HEADER_PHONES_array_PHONE_VALUE_0']);
			$href = 'tel:'.str_replace(array(' ', '-', '(', ')'), '', $phone);
			if(!strlen($href)){
				$href = 'javascript:;';
			}
			?>
			<div class="phones__inner phones__inner--with_dropdown <?=$arOptions['CLASS']?> fill-theme-parent">
				<span class="icon-block__only-icon fill-theme-hover menu-light-icon-fill fill-theme-target">
					<?=self::showIconSvg("", SITE_TEMPLATE_PATH."/images/svg/Phone_big.svg");?>
				</span>
				<div id="mobilephones" class="phones__dropdown">
					<div class="mobilephones__menu-dropdown dropdown dropdown--relative">
						<?// close icon?>
						<span class="mobilephones__close stroke-theme-hover" title="<?=\Bitrix\Main\Localization\Loc::getMessage('CLOSE_BLOCK');?>">
							<?=CAllcorp3::showIconSvg('', SITE_TEMPLATE_PATH.'/images/svg/Close.svg')?>
						</span>

						<div class="mobilephones__menu-item mobilephones__menu-item--title">
							<span class="color_333 font_18 font_bold"><?=Loc::getMessage('ALLCORP3_T_MENU_CALLBACK')?></span>
						</div>

						<?for($i = 0; $i < $iCountPhones; ++$i):?>
							<?
							$phone = ($arRegion ? $arRegion['PHONES'][$i] : $arBackParametrs['HEADER_PHONES_array_PHONE_VALUE_'.$i]);
							$href = 'tel:'.str_replace(array(' ', '-', '(', ')'), '', $phone);
							if(!strlen($href)){
								$href = 'javascript:;';
							}
							$description = ($arRegion ? $arRegion['PROPERTY_PHONES_DESCRIPTION'][$i] : $arBackParametrs['HEADER_PHONES_array_PHONE_DESCRIPTION_'.$i]);
							?>
							<div class="mobilephones__menu-item">
								<div class="link-wrapper bg-opacity-theme-parent-hover fill-theme-parent-all">
									<a class="dark_link phone" href="<?=$href?>" rel="nofollow">
										<span class="font_18"><?=$phone?></span>
										<?if(strlen($description)):?>
											<span class="font_12 color_999 phones__phone-descript"><?=$description?></span>
										<?endif;?>
									</a>
								</div>
							</div>
						<?endfor;?>

						<?if($arOptions['CALLBACK']):?>
							<div class="mobilephones__menu-item mobilephones__menu-item--callback">
								<div class="animate-load btn btn-default btn-transparent-border btn-wide" data-event="jqm" data-param-id="<?=self::getFormID("aspro_allcorp3_callback");?>" data-name="callback">
									<?=GetMessage('CALLBACK')?>
								</div>
							</div>
						<?endif;?>
					</div>
				</div>
			</div>
		<?endif;?>
		<?if($arRegion):?>
			<?$frame->end();?>
		<?endif;?>
		<?
	}

	public static function ShowHeaderPhones($class = '', $icon = '', $only_icon = false, $dropdownTop = false, $bDropdownCallback = 'Y', $bDropdownEmail = 'Y', $bDropdownSocial = 'Y', $bDropdownAddress = 'Y', $bDropdownSchedule = 'Y'){
		global $APPLICATION, $arRegion;

		$iCalledID = ++$hphones_call;
		$arBackParametrs = self::GetBackParametrsValues(SITE_ID);
		$iCountPhones = ($arRegion ? count($arRegion['PHONES']) : $arBackParametrs['HEADER_PHONES']);
		?>
		<?if($arRegion):?>
			<?$frame = new \Bitrix\Main\Page\FrameHelper('header-allphones-block'.$iCalledID);?>
			<?$frame->begin();?>
		<?endif;?>

		<?if($iCountPhones): // count of phones?>
			<?
			$phone = ($arRegion ? $arRegion['PHONES'][0] : $arBackParametrs['HEADER_PHONES_array_PHONE_VALUE_0']);
			$href = 'tel:'.str_replace(array(' ', '-', '(', ')'), '', $phone);
			if(!strlen($href)){
				$href = 'javascript:;';
			}
			$bDropDownPhones = ((int)$iCountPhones > 1);
			?>
			<div class="phones__inner<?=($bDropDownPhones ? ' phones__inner--with_dropdown' : '')?><?=($class ? ' '.$class : '')?> fill-theme-parent">
				<?$phone_icon = ($icon ? $icon : 'Phone_sm.svg');?>
				<span class="icon-block__only-icon banner-light-icon-fill menu-light-icon-fill fill-theme-target">
					<?=self::showIconSvg("", SITE_TEMPLATE_PATH."/images/svg/Phone_big.svg");?>
				</span>
				<span class="icon-block__icon banner-light-icon-fill menu-light-icon-fill">
					<?=self::showIconSvg("", SITE_TEMPLATE_PATH."/images/svg/".$phone_icon);?>
				</span>

				<?if(!$only_icon):?>
					<a class="phones__phone-link phones__phone-first dark_link banner-light-text menu-light-text icon-block__name" href="<?=$href?>"><?=$phone?></a>
				<?endif;?>
				<?if($iCountPhones >= 1 || $only_icon): // if more than one?>
					<div class="phones__dropdown">
						<div class="dropdown dropdown--relative">
							<?for($i = 0; $i < $iCountPhones; ++$i):?>
								<?
								$phone = ($arRegion ? $arRegion['PHONES'][$i] : $arBackParametrs['HEADER_PHONES_array_PHONE_VALUE_'.$i]);
								$href = 'tel:'.str_replace(array(' ', '-', '(', ')'), '', $phone);
								if(!strlen($href)){
									$href = 'javascript:;';
								}
								$description = ($arRegion ? $arRegion['PROPERTY_PHONES_DESCRIPTION'][$i] : $arBackParametrs['HEADER_PHONES_array_PHONE_DESCRIPTION_'.$i]);
								$description = (strlen($description) ? '<span class="phones__phone-descript phones__dropdown-title">'.$description.'</span>' : '');
								?>
								<div class="phones__phone-more dropdown__item color-theme-hover <?=$i == 0 ? 'dropdown__item--first' : ''?> <?=$i == $iCountPhones - 1 ? 'dropdown__item--last' : ''?>">
									<a class="phones__phone-link dark_link <?=(strlen($description) ? '' : 'phones__phone-link--no_descript')?>" rel="nofollow" href="<?=$href?>"><?=$phone?><?=$description?></a>
								</div>
							<?endfor;?>
							<?if($bDropdownCallback != 'N'):?>
								<div class="phones__dropdown-item callback-item">
									<div class="animate-load btn btn-default btn-wide" data-event="jqm" data-param-id="<?=self::getFormID("aspro_allcorp3_callback");?>" data-name="callback">
										<?=GetMessage('CALLBACK')?>
									</div>
								</div>
							<?endif;?>
							<?if($bDropdownEmail != 'N'){
								self::showEmail(
									array(
										'CLASS' => 'phones__dropdown-value',
										'SHOW_SVG' => false,
										'TITLE' => GetMessage('EMAIL'),
										'TITLE_CLASS' => 'phones__dropdown-title',
										'LINK_CLASS' => 'dark_link',
										'WRAPPER' => 'phones__dropdown-item',
									)
								);
							}
							if($bDropdownAddress != 'N'){
								self::showAddress(
									array(
										'CLASS' => 'phones__dropdown-value',
										'SHOW_SVG' => false,
										'TITLE' => GetMessage('ADDRESS'),
										'TITLE_CLASS' => 'phones__dropdown-title',
										'WRAPPER' => 'phones__dropdown-item',
										'NO_LIGHT' => true,
										'LARGE' => true,
									)
								);
							}

							if($bDropdownSchedule != 'N'){
								self::showSchedule(
									array(
										'CLASS' => 'phones__dropdown-value',
										'SHOW_SVG' => false,
										'TITLE' => GetMessage('SCHEDULE'),
										'TITLE_CLASS' => 'phones__dropdown-title',
										'WRAPPER' => 'phones__dropdown-item',
										'NO_LIGHT' => true,
										'LARGE' => true,
									)
								);
							}
							if($bDropdownSocial != 'N'){
								include $_SERVER['DOCUMENT_ROOT'].SITE_DIR.'include/header/phones-social.info.php';
							}
							
							?>
						</div>
					</div>
					<?if(!$only_icon):?>
						<span class="more-arrow banner-light-icon-fill menu-light-icon-fill fill-dark-light-block">
							<?=self::showIconSvg("", SITE_TEMPLATE_PATH."/images/svg/more_arrow.svg", "", "", false);?>
						</span>
					<?endif;?>
				<?endif;?>
			</div>
			<?$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH.'/js/phones.js');?>
		<?endif;?>
		<?if($arRegion):?>
			<?$frame->end();?>
		<?endif;?>
		<?
	}

	public static function showContactImg($bGallery = false){
		global $arRegion, $APPLICATION;

		$iCalledID = ++$cimg_call;
		$bRegionContact = (\Bitrix\Main\Config\Option::get(self::moduleID, 'SHOW_REGION_CONTACT', 'N') == 'Y');
		$bFromRegion = $arRegion && $bRegionContact;
		$bImg = ($bFromRegion ? $arRegion['PROPERTY_REGION_TAG_CONTACT_IMG_VALUE'] : self::checkContentFile(SITE_DIR.'include/contacts-site-image.php'));
		?>
		<?if($arRegion):?>
			<?$frame = new \Bitrix\Main\Page\FrameHelper('header-allcimg-block'.$iCalledID);?>
			<?$frame->begin();?>
		<?endif;?>
		<?if($bImg):?>
			<?if($bFromRegion):?>
				<?
				$arImgRegion = array();
				if($imageID = ($arRegion['PROPERTY_REGION_TAG_CONTACT_IMG_VALUE'] ? $arRegion['PROPERTY_REGION_TAG_CONTACT_IMG_VALUE'] : false)){
					$arImgRegion = CFile::GetFileArray($imageID);
				}

				if($bGallery){
					$arPhotos = array();
					if($imageID){
						$arPhotos[] = array(
							'ID' => $imageID,
							'ORIGINAL' => $arImgRegion['SRC'],
							'PREVIEW' => CFile::ResizeImageGet($imageID, array('width' => 600, 'height' => 600), BX_RESIZE_IMAGE_PROPORTIONAL),
							'DESCRIPTION' => (strlen($arImgRegion['DESCRIPTION']) ? $arImgRegion['DESCRIPTION'] : ''),
						);
					}
					if(is_array($arRegion['PROPERTY_REGION_TAG_MORE_PHOTOS_VALUE'])) {
						foreach($arRegion['PROPERTY_REGION_TAG_MORE_PHOTOS_VALUE'] as $i => $photoID){
							$arPhotos[] = array(
								'ID' => $photoID,
								'ORIGINAL' => CFile::GetPath($photoID),
								'PREVIEW' => CFile::ResizeImageGet($photoID, array('width' => 600, 'height' => 600), BX_RESIZE_IMAGE_PROPORTIONAL),
								'DESCRIPTION' => $arRegion['PROPERTY_REGION_TAG_MORE_PHOTOS_DESCRIPTION'][$i],
							);
						}
					}
				}
				?>
				<?if($bGallery && $arPhotos):?>
					<?self::InitExtensions('fancybox');?>
					<!-- noindex-->
						<div class="contacts-detail__image contacts-detail__image--gallery rounded-4 swipeignore">
							<div class="text-center gallery-big">
								<div class="owl-carousel owl-carousel--outer-dots owl-carousel--nav-hover-visible owl-bg-nav owl-carousel--light owl-carousel--button-wide owl-carousel--button-offset-half" data-slider="content-detail-gallery__slider" data-plugin-options='{"items": "1", "autoplay" : false, "autoplayTimeout" : "3000", "smartSpeed":1000, "dots": true, "dotsContainer": false, "nav": true, "loop": false, "index": true, "margin": 10}'>
								<?foreach($arPhotos as $i => $arPhoto):?>
									<div class="item">
										<a href="<?=$arPhoto['ORIGINAL']?>" class="fancy" data-fancybox="item_slider" target="_blank" title="<?=$arPhoto['DESCRIPTION']?>">
											<div style="background-image:url('<?=$arPhoto['PREVIEW']['src']?>')"></div>
										</a>
									</div>
								<?endforeach;?>
								</div>
							</div>
						</div>
					<!-- /noindex-->
				<?elseif($arImgRegion):?>
					<div class="contact-property contact-property--image rounded-4">
						<img src="<?=$arImgRegion['SRC'];?>" alt="" />
					</div>
				<?endif;?>
			<?else:?>
				<div class="contact-property contact-property--image rounded-4">
					<?$APPLICATION->IncludeFile(SITE_DIR."include/contacts-site-image.php", Array(), Array("MODE" => "html", "NAME" => "Image"));?>
				</div>
			<?endif;?>
		<?endif;?>
		<?if($arRegion):?>
			<?$frame->end();?>
		<?endif;?>
		<?
	}

	public static function showContactPhones($txt = '', $wrapTable = true, $class = '', $icon = 'Phone_black.svg'){
		static $cphones_call;
		global $arRegion, $APPLICATION;

		$iCalledID = ++$cphones_call;
		$arBackParametrs = self::GetBackParametrsValues(SITE_ID);
		$bRegionContact = (\Bitrix\Main\Config\Option::get(self::moduleID, 'SHOW_REGION_CONTACT', 'N') == 'Y');
		$bFromRegion = $arRegion && $bRegionContact;
		$iCountPhones = ($bFromRegion ? count($arRegion['PHONES']) : $arBackParametrs['HEADER_PHONES']);

		if($arRegion){
			$frame = new \Bitrix\Main\Page\FrameHelper('header-allcphones-block'.$iCalledID);
			$frame->begin();
		}
		?>
		<?if($iCountPhones):?>
			<div class="contact-property contact-property--phones">
				<div class="contact-property__label font_13 color_999"><?=($txt ? $txt : Loc::getMessage('SPRAVKA'));?></div>

				<?if($bFromRegion):?>
					<div class="<?=($class ? ' '.$class : '')?>">
				<?else:?>
					<div class="contact-property__value <?=($class ? $class : 'dark_link')?>" itemprop="telephone">
				<?endif;?>

					<?for($i = 0; $i < $iCountPhones; ++$i):?>
							<?
							$phone = ($bFromRegion ? $arRegion['PHONES'][$i] : $arBackParametrs['HEADER_PHONES_array_PHONE_VALUE_'.$i]);
							$href = 'tel:'.str_replace(array(' ', '-', '(', ')'), '', $phone);
							if(!strlen($href)){
								$href = 'javascript:;';
							}

							$description = ($bFromRegion ? $arRegion['PROPERTY_PHONES_DESCRIPTION'][$i] : $arBackParametrs['HEADER_PHONES_array_PHONE_DESCRIPTION_'.$i]);
							$description = (!empty($description)) ? 'title="' . $description . '"' : '';
							?>
							<div class="contact-property__value <?=($class ? $class : 'dark_link')?>" itemprop="telephone"><a <?=$description?> href="<?=$href?>"><?=$phone?></a></div>
					<?endfor;?>

				<?if($bFromRegion):?>
					</div>
				<?else:?>
					</div>
				<?endif;?>
			</div>
		<?endif;?>
		<?
		if($arRegion){
			$frame->end();
		}
	}

	public static function showContactEmail($txt = '', $wrapTable = true, $class = '', $icon = 'Email.svg'){
		global $arRegion, $APPLICATION;

		$iCalledID = ++$cemail_call;
		$bRegionContact = (\Bitrix\Main\Config\Option::get(self::moduleID, 'SHOW_REGION_CONTACT', 'N') == 'Y');
		$bFromRegion = $arRegion && $bRegionContact;
		$bEmail = ($bFromRegion ? $arRegion['PROPERTY_EMAIL_VALUE'] : self::checkContentFile(SITE_DIR.'include/contacts-site-email.php'));
		?>
		<?if($arRegion):?>
			<?$frame = new \Bitrix\Main\Page\FrameHelper('header-allcemail-block'.$iCalledID);?>
			<?$frame->begin();?>
		<?endif;?>
		<?if($bEmail):?>
			<div class="contact-property contact-property--email">
				<div class="contact-property__label font_13 color_999"><?=($txt ? $txt : Loc::getMessage('SPRAVKA'));?></div>
				<?if($bFromRegion):?>
					<div class="<?=($class ? ' '.$class : '')?>">
						<?foreach($arRegion['PROPERTY_EMAIL_VALUE'] as $value):?>
							<div class="contact-property__value <?=($class ? $class : 'dark_link')?>" itemprop="email">
								<a href="mailto:<?=$value;?>"><?=$value;?></a>
							</div>
						<?endforeach;?>
					</div>
				<?else:?>
					<div class="contact-property__value <?=($class ? $class : 'dark_link')?>" itemprop="email"><?$APPLICATION->IncludeFile(SITE_DIR."include/contacts-site-email.php", Array(), Array("MODE" => "html", "NAME" => "email"));?></div>
				<?endif;?>
			</div>
		<?endif;?>
		<?if($arRegion):?>
			<?$frame->end();?>
		<?endif;?>
		<?
	}

	public static function showContactAddr($txt = '', $wrapTable = true, $class = '', $icon = 'Addres_black.svg'){
		global $arRegion, $APPLICATION;

		$iCalledID = ++$caddr_call;
		$bRegionContact = (\Bitrix\Main\Config\Option::get(self::moduleID, 'SHOW_REGION_CONTACT', 'N') == 'Y');
		$bFromRegion = $arRegion && $bRegionContact;
		$bAddr = ($bFromRegion ? $arRegion['PROPERTY_ADDRESS_VALUE']['TEXT'] : self::checkContentFile(SITE_DIR.'include/contacts-site-address.php'));
		?>
		<?if($arRegion):?>
			<?$frame = new \Bitrix\Main\Page\FrameHelper('header-allcaddr-block'.$iCalledID);?>
			<?$frame->begin();?>
		<?endif;?>
		<?if($bAddr):?>
			<div class="contact-property contact-property--address">
				<div class="contact-property__label font_13 color_999"><?=$txt;?></div>
				<?if($bFromRegion):?>
					<div itemprop="address" class="contact-property__value <?=($class ? $class : 'color_333')?>">
						<?=$arRegion['PROPERTY_ADDRESS_VALUE']['TEXT'];?>
					</div>
				<?else:?>
					<div itemprop="address" class="contact-property__value <?=($class ? $class : 'color_333')?>"><?$APPLICATION->IncludeFile(SITE_DIR."include/contacts-site-address.php", Array(), Array("MODE" => "html", "NAME" => "address"));?></div>
				<?endif;?>
			</div>
		<?endif;?>
		<?if($arRegion):?>
			<?$frame->end();?>
		<?endif;?>
		<?
	}

	public static function showContactSchedule($txt = '', $wrapTable = true, $class = '', $icon = '', $subclass = ''){
		global $arRegion, $APPLICATION;

		$iCalledID = ++$cshc_call;
		$bRegionContact = (\Bitrix\Main\Config\Option::get(self::moduleID, 'SHOW_REGION_CONTACT', 'N') == 'Y');
		$bFromRegion = $arRegion && $bRegionContact;
		$bContent = ($bFromRegion ? $arRegion['PROPERTY_SHCEDULE_VALUE']['TEXT'] : self::checkContentFile(SITE_DIR.'include/contacts-site-schedule.php'));
		?>
		<?if($arRegion):?>
			<?$frame = new \Bitrix\Main\Page\FrameHelper('header-allcaddr-block'.$iCalledID);?>
			<?$frame->begin();?>
		<?endif;?>
		<?if($bContent):?>
			<div class="contact-property contact-property--schedule">
				<div class="contact-property__label font_13 color_999"><?=$txt;?></div>
				<?if($bFromRegion):?>
					<div class="contact-property__value <?=($class ? $class : 'color_333')?>">
						<?=$arRegion['PROPERTY_SHCEDULE_VALUE']['TEXT'];?>
					</div>
				<?else:?>
					<div class="contact-property__value <?=($class ? $class : 'color_333')?>"><?$APPLICATION->IncludeFile(SITE_DIR."include/contacts-site-schedule.php", Array(), Array("MODE" => "html", "NAME" => "schedule"));?></div>
				<?endif;?>
			</div>
		<?endif;?>
		<?if($arRegion):?>
			<?$frame->end();?>
		<?endif;?>
		<?
	}

	public static function showContactDesc(){
		global $arRegion, $APPLICATION;

		$iCalledID = ++$cdesc_call;
		$bRegionContact = (\Bitrix\Main\Config\Option::get(self::moduleID, 'SHOW_REGION_CONTACT', 'N') == 'Y');
		$bFromRegion = $arRegion && $bRegionContact;
		$bDesc = ($bFromRegion ? $arRegion['PROPERTY_REGION_TAG_CONTACT_TEXT_VALUE']['TEXT'] : self::checkContentFile(SITE_DIR.'include/contacts-site-about.php'));
		?>
		<?if($arRegion):?>
			<?$frame = new \Bitrix\Main\Page\FrameHelper('header-allcdesc-block'.$iCalledID);?>
			<?$frame->begin();?>
		<?endif;?>
		<?if($bDesc):?>
			<div itemprop="description" class="contact-property contact-property--decription">
				<div class="contact-property__text font_large color_666">
					<?if($bFromRegion):?>
						<?=$arRegion['PROPERTY_REGION_TAG_CONTACT_TEXT_VALUE']['TEXT'];?>
					<?else:?>
						<?$APPLICATION->IncludeFile(SITE_DIR."include/contacts-site-about.php", Array(), Array("MODE" => "html", "NAME" => "Contacts about"));?>
					<?endif;?>
				</div>
			</div>
		<?endif;?>
		<?if($arRegion):?>
			<?$frame->end();?>
		<?endif;?>
		<?
	}

	public static function showCompanyFront(){
		global $arRegion, $APPLICATION;
		$iCalledID = ++$companyfr_call;
		?>
		<?if($arRegion):?>
			<?$frame = new \Bitrix\Main\Page\FrameHelper('company-front-block'.$iCalledID);?>
			<?$frame->begin();?>
		<?endif;?>
		<?if($arRegion):?>
			<?=$arRegion['DETAIL_TEXT'];?>
		<?else:?>
			<div class="col-md-8 col-sm-12 col-xs-12">
				<?$APPLICATION->IncludeFile(SITE_DIR."include/mainpage/company_text.php", Array(), Array(
				    "MODE"      => "html",
				    "NAME"      => GetMessage("COMPANY_TEXT"),
				    ));
				?>
			</div>
			<div class="col-md-4 hidden-xs hidden-sm">
				<?$APPLICATION->IncludeFile(SITE_DIR."include/mainpage/company_img.php", Array(), Array(
				    "MODE"      => "html",
				    "NAME"      => GetMessage("COMPANY_IMG"),
				    ));
				?>
			</div>
		<?endif;?>
		<?if($arRegion):?>
			<?$frame->end();?>
		<?endif;?>
		<?
	}

	public static function showSchedule($arOptions = array()){
		$arDefaulOptions = array(
			'CLASS' => 'schedule',
			'CLASS_SVG' => 'schedule',
			'SVG_NAME' => 'Schedule_big.svg',
			'SHOW_SVG' => true,
			'TITLE' => '',
			'TITLE_CLASS' => '',
			'WRAPPER' => '',
			'FONT_SIZE' => '',
			'NO_LIGHT' => true,
		);
		$arOptions = array_merge($arDefaulOptions, $arOptions);

		global $arRegion, $APPLICATION;
		$iCalledID = ++$hshc_call;
		
		$bBlock = ($arRegion ? isset($arRegion['PROPERTY_SHCEDULE_VALUE']['TEXT']) : self::checkContentFile(SITE_DIR.'include/header-schedule.php'));
		?>
		<?if($arRegion):?>
			<?$frame = new \Bitrix\Main\Page\FrameHelper('header-allhsch-block'.$iCalledID);?>
			<?$frame->begin();?>
		<?endif;?>
		<?if($bBlock):?>
			<?if($arOptions['WRAPPER']):?>
				<div class="<?=$arOptions['WRAPPER']?>">
			<?endif;?>

			<?if($arOptions['TITLE']):?>
				<div class="schedule__title <?=$arOptions['TITLE_CLASS'] ? $arOptions['TITLE_CLASS'] : ''?>">
					<?=$arOptions['TITLE']?>
				</div>
			<?endif?>

			<div class="<?=($arOptions['CLASS'] ? $arOptions['CLASS'] : '')?>">
				<?if($arOptions['SVG_NAME'] && $arOptions['SHOW_SVG']):?>
					<?=self::showIconSvg($arOptions['CLASS_SVG'], SITE_TEMPLATE_PATH."/images/svg/".$arOptions['SVG_NAME']);?>
				<?endif;?>
				<div class="schedule__text <?=$arOptions['FONT_SIZE'] ? 'font_'.$arOptions['FONT_SIZE'] : ''?> <?=$arOptions['NO_LIGHT'] ? '' : 'banner-light-text menu-light-text'?>">
					<?if($arRegion):?>
						<?=$arRegion['PROPERTY_SHCEDULE_VALUE']['TEXT'];?>
					<?else:?>
						<?$APPLICATION->IncludeFile(SITE_DIR."include/header-schedule.php", Array(), Array("MODE" => "html", "NAME" => "schedule"));?>
					<?endif;?>
				</div>
			</div>

			<?if($arOptions['WRAPPER']):?>
				</div>
			<?endif;?>
		<?endif;?>
		<?if($arRegion):?>
			<?$frame->end();?>
		<?endif;?>
		<?
	}

	public static function showAddress($arOptions = array()){
		static $addr_call;
		global $arRegion, $APPLICATION;

		$arDefaulOptions = array(
			'CLASS' => '',
			'CLASS_SVG' => 'address',
			'SVG_NAME' => 'Addres_black.svg',
			'SHOW_SVG' => true,
			'TITLE' => '',
			'TITLE_CLASS' => '',
			'WRAPPER' => '',
			'FONT_SIZE' => '',
			'LARGE' => false,
			'NO_LIGHT' => true,
		);
		$arOptions = array_merge($arDefaulOptions, $arOptions);

		if($arRegion){
			$iCalledID = ++$addr_call;
			$frame = new \Bitrix\Main\Page\FrameHelper('address-block'.$iCalledID);
			$frame->begin();
		}
		?>
		<?if($arRegion):?>
			<?if($arRegion['PROPERTY_ADDRESS_VALUE']):?>
				<?if($arOptions['WRAPPER']):?>
					<div class="<?=$arOptions['WRAPPER']?>">
				<?endif;?>

				<?if($arOptions['TITLE']):?>
					<div class="address__title <?=$arOptions['TITLE_CLASS'] ? $arOptions['TITLE_CLASS'] : ''?>">
						<?=$arOptions['TITLE']?>
					</div>
				<?endif?>

				<div <?=($arOptions['CLASS'] ? 'class="'.$arOptions['CLASS'].'"' : '')?>>
					<?if($arOptions['SVG_NAME'] && $arOptions['SHOW_SVG']):?>
						<span class="icon-block__icon icon-block__icon--top banner-light-icon-fill menu-light-icon-fill">
							<?=self::showIconSvg($arOptions['CLASS_SVG'], SITE_TEMPLATE_PATH."/images/svg/".$arOptions['SVG_NAME']);?>
						</span>
					<?endif;?>
					<div class="address__text <?=$arOptions['FONT_SIZE'] ? 'font_'.$arOptions['FONT_SIZE'] : ''?> <?=$arOptions['LARGE'] ? 'address__text--large' : ''?> <?=$arOptions['NO_LIGHT'] ? '' : 'banner-light-text menu-light-text'?>">
						<?=$arRegion['PROPERTY_ADDRESS_VALUE']['TEXT'];?>
					</div>
				</div>

				<?if($arOptions['WRAPPER']):?>
					</div>
				<?endif;?>
			<?endif;?>
		<?else:?>
			<?if(self::checkContentFile(SITE_DIR.'include/contacts-site-address.php')):?>
				<?if($arOptions['WRAPPER']):?>
					<div class="<?=$arOptions['WRAPPER']?>">
				<?endif;?>

				<?if($arOptions['TITLE']):?>
					<div class="address__title <?=$arOptions['TITLE_CLASS'] ? $arOptions['TITLE_CLASS'] : ''?>">
						<?=$arOptions['TITLE']?>
					</div>
				<?endif?>

				<div <?=($arOptions['CLASS'] ? 'class="'.$arOptions['CLASS'].'"' : '')?>>
					<?if($arOptions['SVG_NAME'] && $arOptions['SHOW_SVG']):?>
						<span class="icon-block__icon icon-block__icon--top banner-light-icon-fill menu-light-icon-fill">
							<?=self::showIconSvg($arOptions['CLASS_SVG'], SITE_TEMPLATE_PATH."/images/svg/".$arOptions['SVG_NAME']);?>
						</span>
					<?endif;?>
					<div class="address__text <?=$arOptions['LARGE'] ? 'address__text--large' : ''?> <?=$arOptions['NO_LIGHT'] ? '' : 'banner-light-text menu-light-text'?>">
						<?$APPLICATION->IncludeFile(SITE_DIR."include/contacts-site-address.php", array(), array(
								"MODE" => "html",
								"NAME" => "Address in title",
								"TEMPLATE" => "include_area.php",
							)
						);?>
					</div>
				</div>

				<?if($arOptions['WRAPPER']):?>
					</div>
				<?endif;?>
			<?endif;?>
		<?endif;?>
		<?
		if($arRegion){
			$frame->end();
		}
	}

	public static function showEmail($arOptions = array()){
		static $addr_call;
		global $arRegion, $APPLICATION;

		$arDefaulOptions = array(
			'CLASS' => 'email',
			'CLASS_SVG' => 'email',
			'SVG_NAME' => 'Email.svg',
			'SHOW_SVG' => true,
			'TITLE' => '',
			'TITLE_CLASS' => '',
			'LINK_CLASS' => '',
			'WRAPPER' => '',
		);
		$arOptions = array_merge($arDefaulOptions, $arOptions);

		if($arRegion){
			$iCalledID = ++$addr_call;
			$frame = new \Bitrix\Main\Page\FrameHelper('email-block'.$iCalledID);
			$frame->begin();
		}
		?>
		<?if($arRegion):?>
			<?if($arRegion['PROPERTY_EMAIL_VALUE']):?>
				<?if($arOptions['WRAPPER']):?>
					<div class="<?=$arOptions['WRAPPER']?>">
				<?endif;?>
				<?if($arOptions['TITLE']):?>
					<div class="email__title <?=$arOptions['TITLE_CLASS'] ? $arOptions['TITLE_CLASS'] : ''?>">
						<?=$arOptions['TITLE']?>
					</div>
				<?endif?>

				<div <?=($arOptions['CLASS'] ? 'class="'.$arOptions['CLASS'].'"' : '')?>>
					<?if($arOptions['SVG_NAME'] && $arOptions['SHOW_SVG']):?>
						<?=self::showIconSvg($arOptions['CLASS_SVG'], SITE_TEMPLATE_PATH."/images/svg/".$arOptions['SVG_NAME']);?>
					<?endif;?>
					<div>
					<?foreach($arRegion['PROPERTY_EMAIL_VALUE'] as $value):?>
						<div>
							<a <?=$arOptions['LINK_CLASS'] ? 'class="'.$arOptions['LINK_CLASS'].'"' : ''?> href="mailto:<?=$value;?>"><?=$value;?></a>
						</div>
					<?endforeach;?>
					</div>
				</div>

				<?if($arOptions['WRAPPER']):?>
					</div>
				<?endif;?>
			<?endif;?>
		<?else:?>
			<?if(self::checkContentFile(SITE_DIR.'include/contacts-site-email.php')):?>
				<?if($arOptions['WRAPPER']):?>
					<div class="<?=$arOptions['WRAPPER']?>">
				<?endif;?>
				<?if($arOptions['TITLE']):?>
					<div class="email__title <?=$arOptions['TITLE_CLASS'] ? $arOptions['TITLE_CLASS'] : ''?>">
						<?=$arOptions['TITLE']?>
					</div>
				<?endif?>

				<div <?=($arOptions['CLASS'] ? 'class="'.$arOptions['CLASS'].'"' : '')?>>
					<?if($arOptions['SHOW_SVG']):?>
						<?=self::showIconSvg($arOptions['CLASS_SVG'], SITE_TEMPLATE_PATH."/images/svg/".$arOptions['SVG_NAME']);?>
					<?endif;?>
					<div>
						<?$APPLICATION->IncludeFile(SITE_DIR."include/contacts-site-email.php", array(), array(
								"MODE" => "html",
								"NAME" => "E-mail",
								"TEMPLATE" => "include_area",
							)
						);?>
					</div>
				</div>

				<?if($arOptions['WRAPPER']):?>
					</div>
				<?endif;?>
			<?endif;?>
		<?endif;?>
		<?
		if($arRegion){
			$frame->end();
		}
	}

	public static function showRightDok(){
		if ($handler = \Aspro\Functions\CAsproAllcorp3::getCustomFunc(__FUNCTION__)) {
			return call_user_func_array($handler, []);
		}

		$callbackBlock = self::GetFrontParametrValue('CALLBACK_SIDE_BUTTON') == 'Y';
		$questionBlock = self::GetFrontParametrValue('QUESTION_SIDE_BUTTON') == 'Y';
		$reviewBlock = self::GetFrontParametrValue('REVIEWS_SIDE_BUTTON') == 'Y';
		$compareBlock = self::GetFrontParametrValue('CATALOG_COMPARE') == 'Y' && self::GetFrontParametrValue('ORDER_BASKET_VIEW') == 'FLY';
		?>
		<?if($callbackBlock || $questionBlock || $reviewBlock || $compareBlock):?>
			<div class="right_dok">
				<?if($compareBlock):?>
					<span class="link fill-theme-hover" title="<?=GetMessage("S_CALLBACK")?>">
						<?self::ShowCompareLink('fly_compare', '', '', true);?>
					</span>
				<?endif;?>
				<?if($callbackBlock):?>
					<span class="link fill-theme-hover" title="<?=GetMessage("S_CALLBACK")?>">
						<span class="animate-load" data-event="jqm" data-param-id="<?=self::getFormID("aspro_allcorp3_callback");?>" data-name="callback"><?=self::showIconSvg("call", SITE_TEMPLATE_PATH."/images/svg/back_call.svg");?></span>
					</span>
				<?endif;?>
				<?if($reviewBlock):?>
					<span class="link fill-theme-hover" title="<?=GetMessage("S_FEEDBACK")?>">
						<span class="animate-load" data-event="jqm" data-param-id="<?=self::getFormID("aspro_allcorp3_feedback");?>" data-name="review"><?=self::showIconSvg("review", SITE_TEMPLATE_PATH."/images/svg/send_review.svg");?></span>
					</span>
				<?endif;?>
				<?if($questionBlock):?>
					<span class="link fill-theme-hover" title="<?=GetMessage("S_QUESTION")?>">
						<span class="animate-load" data-event="jqm" data-param-id="<?=self::getFormID("aspro_allcorp3_question");?>" data-name="question"><?=self::showIconSvg("ask", SITE_TEMPLATE_PATH."/images/svg/ask_question.svg");?></span>
					</span>
				<?endif;?>
			</div>
		<?endif;?>
	<?}

	public static function checkBasketItems(){
		if(!defined('ADMIN_SECTION') && !CSite::inDir(SITE_DIR.'/ajax/')):?>
			<script>var arBasketItems = <?=CUtil::PhpToJSObject(self::getBasketItems(), false)?>;</script>
		<?endif;
	}

	public static function getBasketItems(){
		global $APPLICATION, $arSite, $USER;

		if (!defined('ADMIN_SECTION') && \Bitrix\Main\Loader::includeModule('iblock')) {
			$userID = $USER->GetID();
			$userID = ($userID > 0 ? $userID : 0);
			$arBackParametrs = self::GetFrontParametrsValues(SITE_ID);
			$bOrderViewBasket = ($arBackParametrs['ORDER_VIEW'] == 'Y' ? true : false);

			if ($bOrderViewBasket && isset($_SESSION[SITE_ID][$userID]['BASKET_ITEMS']) && is_array($_SESSION[SITE_ID][$userID]['BASKET_ITEMS']) && $_SESSION[SITE_ID][$userID]['BASKET_ITEMS']) {
				$arIBlocks = $arBasketItemsIDs = array();

				foreach ($_SESSION[SITE_ID][$userID]['BASKET_ITEMS'] as $arBasketItem) {
					if(isset($arBasketItem['IBLOCK_ID']) && intval($arBasketItem['IBLOCK_ID']) > 0 && !in_array($arBasketItem['IBLOCK_ID'], $arIBlocks))
						$arIBlocks[] = $arBasketItem['IBLOCK_ID'];

					$arBasketItemsIDs[] = $arBasketItem['ID'];
				}

				$dbRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => $arIBlocks, 'ID' => $arBasketItemsIDs, 'PROPERTY_FORM_ORDER_VALUE' => false), false, false, array('ID'));
				while ($arRes = $dbRes->Fetch()) {
					unset($_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$arRes['ID']]);
				}

				return $_SESSION[SITE_ID][$userID]['BASKET_ITEMS'];
			}

			return array();
		}

		return false;
	}

	// DO NOT USE - FOR OLD VERSIONS
	public static function linkShareImage($previewPictureID = false, $detailPictureID = false){
		global $APPLICATION;

		if($linkSaherImageID = ($detailPictureID ? $detailPictureID : ($previewPictureID ? $previewPictureID : false)))
			$APPLICATION->AddHeadString('<link rel="image_src" href="'.CFile::GetPath($linkSaherImageID).'"  />', true);
	}

	public static function processBasket(){
		global $USER;
		$userID = $USER->GetID();
		$userID = ($userID > 0 ? $userID : 0);

		if(
			$_SERVER['REQUEST_METHOD'] === 'POST' &&
			isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest'
		){
			if(isset($_REQUEST['itemData']) && is_array($_REQUEST['itemData'])){
				$_REQUEST['itemData'] = array_map('self::conv', $_REQUEST['itemData']);
			}

			if(isset($_REQUEST['removeAll']) && $_REQUEST['removeAll'] === 'Y'){
				unset($_SESSION[SITE_ID][$userID]['BASKET_ITEMS']);
			}
			elseif(isset($_REQUEST['itemData']['ID']) && intval($_REQUEST['itemData']['ID']) > 0){
				if(!is_array($_SESSION[SITE_ID][$userID]['BASKET_ITEMS'])){
					$_SESSION[SITE_ID][$userID]['BASKET_ITEMS'] = array();
				}

				if(isset($_REQUEST['remove']) && $_REQUEST['remove'] === 'Y'){
					if(isset($_SESSION[SITE_ID][$userID]['BASKET_ITEMS']) && isset($_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$_REQUEST['itemData']['ID']])){
						unset($_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$_REQUEST['itemData']['ID']]);
					}
				}
				elseif(isset($_REQUEST['quantity']) && floatval($_REQUEST['quantity']) > 0){
					$_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$_REQUEST['itemData']['ID']] = (isset($_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$_REQUEST['itemData']['ID']]) ? $_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$_REQUEST['itemData']['ID']] : $_REQUEST['itemData']);
					$_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$_REQUEST['itemData']['ID']]['QUANTITY'] = $_REQUEST['quantity'];
				}
			}

			/*optovaya pokupka*/
			if (
				(isset($_POST['type']) && $_POST['type'] === 'multiple') &&
				(isset($_POST['items']) && is_array($_POST['items']) && $_POST['items'])
			) {
				/* convert cyrilic symbols */
				foreach ($_POST['items'] as $key => $arItem) {
					$_POST['items'][$key] = array_map('self::conv', $arItem);
				}

				if (!is_array($_SESSION[SITE_ID][$userID]['BASKET_ITEMS'])) {
					$_SESSION[SITE_ID][$userID]['BASKET_ITEMS'] = array();
				}

				foreach ($_POST['items'] as $key => $arItem) {
					$quantity = $arItem['QUANTITY'];
					unset($arItem['QUANTITY']);

					$_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$arItem['ID']] = (
					  isset($_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$arItem['ID']]) ?
						$_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$arItem['ID']] :
						$arItem
					);
					$_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$arItem['ID']]['QUANTITY'] = (
					  isset($_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$arItem['ID']]['QUANTITY']) ?
						$_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$arItem['ID']]['QUANTITY'] += $quantity :
						$quantity
					);
				}

				/*
				$_SESSION[SITE_ID][$userID]['BASKET_ITEMS'][$_REQUEST['itemData']['ID']]['QUANTITY'] = $_REQUEST['quantity'];
				*/
			}
		}

		return $_SESSION[SITE_ID][$userID]['BASKET_ITEMS'];
	}

	public static function conv($n){
		return iconv('UTF-8', SITE_CHARSET, $n);
	}

	public static function getDataItem($el, $toJSON = true){
		$name = $el['NAME'];
		$priceFilter = $el['PROPERTIES']['FILTER_PRICE']['VALUE'] ?? $el['DISPLAY_PROPERTIES']['FILTER_PRICE']['VALUE'];
		$price = $el['PROPERTIES']['PRICE']['VALUE'] ?? $el['DISPLAY_PROPERTIES']['PRICE']['VALUE'];
		$priceOld = $el['PROPERTIES']['PRICEOLD']['VALUE'] ?? $el['DISPLAY_PROPERTIES']['PRICEOLD']['VALUE'];
		$currency = $el['PROPERTIES']['PRICE_CURRENCY']['VALUE'] ?? $el['DISPLAY_PROPERTIES']['PRICE_CURRENCY']['VALUE'];

		// tariff default price
		if(
			$el &&
			is_array($el['DEFAULT_PRICE'])
		){
			if($el['DEFAULT_PRICE']['FILTER_PRICE'] !== false){
				$priceFilter = $el['DEFAULT_PRICE']['FILTER_PRICE'];
			}

			if($el['DEFAULT_PRICE']['PRICE'] !== false){
				$price = $el['DEFAULT_PRICE']['PRICE'];
			}

			if($el['DEFAULT_PRICE']['OLDPRICE'] !== false){
				$priceOld = $el['DEFAULT_PRICE']['OLDPRICE'];
			}

			$name = $name.' ('.$el['DEFAULT_PRICE']['TITLE'].')';
		}

		$dataItem = array(
			"IBLOCK_ID" => $el['IBLOCK_ID'],
			"ID" => $el['ID'],
			"NAME" => $name,
			"DETAIL_PAGE_URL" => $el['DETAIL_PAGE_URL'],
			"PREVIEW_PICTURE" => (is_array($el['PREVIEW_PICTURE']) ? $el['PREVIEW_PICTURE']['ID'] : $el['PREVIEW_PICTURE']),
			"DETAIL_PICTURE" => (is_array($el['DETAIL_PICTURE']) ? $el['DETAIL_PICTURE']['ID'] : $el['DETAIL_PICTURE']),
			"PROPERTY_FILTER_PRICE_VALUE" => $priceFilter,
			"PROPERTY_PRICE_VALUE" => $price,
			"PROPERTY_PRICEOLD_VALUE" => $priceOld,
			"PROPERTY_PRICE_CURRENCY_VALUE" => $currency,
			"PROPERTY_ARTICLE_VALUE" => $el['DISPLAY_PROPERTIES']['ARTICLE']['VALUE'],
			"PROPERTY_STATUS_VALUE" => $el['DISPLAY_PROPERTIES']['STATUS']['VALUE_ENUM_ID'],
		);
		if ($el['PARENT_ID']) {
			$dataItem['PARENT_ID'] = $el['PARENT_ID'];
		}

		global $APPLICATION;
		if ($toJSON) {
			$dataItem = $APPLICATION->ConvertCharsetArray($dataItem, SITE_CHARSET, 'UTF-8');
			$dataItem = json_encode($dataItem);
			$dataItem = htmlspecialchars($dataItem);
		}
		return $dataItem;
	}

	public static function utf8_substr_replace($original, $replacement, $position, $length){
		$startString = mb_substr($original, 0, $position, "UTF-8");
		$endString = mb_substr($original, $position + $length, mb_strlen($original), "UTF-8");

		$out = $startString.$replacement.$endString;

		return $out;
	}

	public static function ShowRSSIcon($href){
		Aspro\Functions\CAsproAllcorp3::showRSSIcon(
			array(
				'URL' => $href,
			)
		);
	}

	public static function getFieldImageData(array &$arItem, array $arKeys, $entity = 'ELEMENT', $ipropertyKey = 'IPROPERTY_VALUES'){
		if (empty($arItem) || empty($arKeys))
            return;

        $entity = (string)$entity;
        $ipropertyKey = (string)$ipropertyKey;

        foreach ($arKeys as $fieldName)
        {
            if(!isset($arItem[$fieldName]) || (!isset($arItem['~'.$fieldName]) || !$arItem['~'.$fieldName]))
                continue;
            $imageData = false;
            $imageId = (int)$arItem['~'.$fieldName];
            if ($imageId > 0)
                $imageData = \CFile::getFileArray($imageId);
            unset($imageId);
            if (is_array($imageData))
            {
                if (isset($imageData['SAFE_SRC']))
                {
                    $imageData['UNSAFE_SRC'] = $imageData['SRC'];
                    $imageData['SRC'] = $imageData['SAFE_SRC'];
                }
                else
                {
                    $imageData['UNSAFE_SRC'] = $imageData['SRC'];
                    $imageData['SRC'] = \CHTTP::urnEncode($imageData['SRC'], 'UTF-8');
                }
                $imageData['ALT'] = '';
                $imageData['TITLE'] = '';

                if ($ipropertyKey != '' && isset($arItem[$ipropertyKey]) && is_array($arItem[$ipropertyKey]))
                {
                    $entityPrefix = $entity.'_'.$fieldName;
                    if (isset($arItem[$ipropertyKey][$entityPrefix.'_FILE_ALT']))
                        $imageData['ALT'] = $arItem[$ipropertyKey][$entityPrefix.'_FILE_ALT'];
                    if (isset($arItem[$ipropertyKey][$entityPrefix.'_FILE_TITLE']))
                        $imageData['TITLE'] = $arItem[$ipropertyKey][$entityPrefix.'_FILE_TITLE'];
                    unset($entityPrefix);
                }
                if ($imageData['ALT'] == '' && isset($arItem['NAME']))
                    $imageData['ALT'] = $arItem['NAME'];
                if ($imageData['TITLE'] == '' && isset($arItem['NAME']))
                    $imageData['TITLE'] = $arItem['NAME'];
            }
            $arItem[$fieldName] = $imageData;
            unset($imageData);
        }

        unset($fieldName);
	}

	public static function getSectionsIds_NotInRegion($iblockId = false, $regionId = false){
		static $arCache, $arIblockHasUFRegion;

		$arSectionsIds = array();

		if(!$iblockId){
			$iblockId = CAllcorp3Cache::$arIBlocks[SITE_ID]['aspro_allcorp3_catalog']['aspro_allcorp3_catalog'][0];
		}

		if($iblockId){
			if(!isset($arIblockHasUFRegion)){
				$arIblockHasUFRegion = array();
			}

			if(!isset($arIblockHasUFRegion[$iblockId])){
				$arIblockHasUFRegion[$iblockId] = false;

				$rsData = \CUserTypeEntity::GetList(array('ID' => 'ASC'), array('ENTITY_ID' => 'IBLOCK_'.$iblockId.'_SECTION', 'FIELD_NAME' => 'UF_REGION'));
				if($arRes = $rsData->Fetch()){
					$arIblockHasUFRegion[$iblockId] = true;
				}
			}

			if($arIblockHasUFRegion[$iblockId]){
				if(!$regionId && $GLOBALS['arRegion']){
					$regionId = $GLOBALS['arRegion']['ID'];
				}

				if($regionId){
					if(!isset($arCache)){
						$arCache = array();
					}

					if(!isset($arCache[$iblockId])){
						$arCache[$iblockId] = array();
					}

					if(!isset($arCache[$iblockId][$regionId])){
						if($arSections = CAllcorp3Cache::CIBLockSection_GetList(
							array(
								'CACHE' => array(
									'TAG' => CAllcorp3Cache::GetIBlockCacheTag($iblockId),
									'MULTI' => 'Y'
								)
							),
							array(
								'IBLOCK_ID' => $iblockId,
								'!UF_REGION' => $regionId,
							),
							false,
							array(
								'ID',
								'RIGHT_MARGIN',
								'LEFT_MARGIN',
							),
							false
						)){
							$arSectionsIds = array_column($arSections, 'ID');

							if($arSectionsIds){
								if($arSectionsIds_ = CAllcorp3Cache::CIBLockSection_GetList(
									array(
										'CACHE' => array(
											'TAG' => CAllcorp3Cache::GetIBlockCacheTag($iblockId),
											'MULTI' => 'Y',
											'RESULT' => array('ID'),
										)
									),
									array(
										'IBLOCK_ID' => $iblockId,
										'ID' => $arSectionsIds,
										'UF_REGION' => $regionId,
									),
									false,
									array('ID'),
									false
								)){
									$arSectionsIds = array_diff($arSectionsIds, $arSectionsIds_);
								}
							}

							$arSubSectionsIds = array();
							foreach($arSections as $arSection){
								if(in_array($arSection['ID'], $arSectionsIds)){
									if(($arSection['LEFT_MARGIN'] + 1) < $arSection['RIGHT_MARGIN']){
										$arSubSectionsIds[] = $arSection['ID'];
									}
								}
							}

							while($arSubSectionsIds){
								if($arSections = CAllcorp3Cache::CIBLockSection_GetList(
									array(
										'CACHE' => array(
											'TAG' => CAllcorp3Cache::GetIBlockCacheTag($iblockId),
											'MULTI' => 'Y'
										)
									),
									array(
										'IBLOCK_ID' => $iblockId,
										'SECTION_ID' => $arSubSectionsIds,
									),
									false,
									array(
										'ID',
										'RIGHT_MARGIN',
										'LEFT_MARGIN',
									),
									false
								)){
									$arSubSectionsIds = array_column($arSections, 'ID');
									if($arSubSectionsIds){
										if($arSectionsIds_ = CAllcorp3Cache::CIBLockSection_GetList(
											array(
												'CACHE' => array(
													'TAG' => CAllcorp3Cache::GetIBlockCacheTag($iblockId),
													'MULTI' => 'Y',
													'RESULT' => array('ID'),
												)
											),
											array(
												'IBLOCK_ID' => $iblockId,
												'ID' => $arSubSectionsIds,
												'UF_REGION' => $regionId,
											),
											false,
											array('ID'),
											false
										)){
											$arSubSectionsIds = array_diff($arSubSectionsIds, $arSectionsIds_);
										}
									}

									if($arSubSectionsIds){
										$arSectionsIds = array_merge($arSectionsIds, $arSubSectionsIds);
									}

									$arSubSubSectionsIds = array();
									foreach($arSections as $arSection){
										if(in_array($arSection['ID'], $arSubSectionsIds)){
											if(($arSection['LEFT_MARGIN'] + 1) < $arSection['RIGHT_MARGIN']){
												$arSubSubSectionsIds[] = $arSection['ID'];
											}
										}
									}
									$arSubSectionsIds = $arSubSubSectionsIds;
								}
								else{
									$arSubSectionsIds = array();
								}
							}
						}

						$arCache[$iblockId][$regionId] = $arSectionsIds;
					}
					else{
						$arSectionsIds = $arCache[$iblockId][$regionId];
					}
				}
			}
		}

		return $arSectionsIds;
	}

	public static function makeSectionFilterInRegion(&$arFilter, $regionId = false){

		$bFilterItem = false;
		if( isset($GLOBALS['arTheme']['USE_REGIONALITY']['VALUE']) ) {
			$bFilterItem = $GLOBALS['arTheme']['USE_REGIONALITY']['VALUE'] === 'Y' && $GLOBALS['arTheme']['USE_REGIONALITY']['DEPENDENT_PARAMS']['REGIONALITY_FILTER_ITEM']['VALUE'] === 'Y';
		} else {
			$bFilterItem = $GLOBALS['arTheme']['USE_REGIONALITY'] === 'Y' && $GLOBALS['arTheme']['REGIONALITY_FILTER_ITEM'] === 'Y';
		}

		if($bFilterItem){
			$iblockId = $arFilter['IBLOCK_ID'];
			if(!$iblockId){
				$iblockId = CAllcorp3Cache::$arIBlocks[SITE_ID]['aspro_allcorp3_catalog']['aspro_allcorp3_catalog'][0];
			}

			if($iblockId){
				if(!$regionId && $GLOBALS['arRegion']){
					$regionId = $GLOBALS['arRegion']['ID'];
				}

				if($regionId){
					if($arSectionsIds = self::getSectionsIds_NotInRegion($arFilter['IBLOCK_ID'], $regionId)){
						$arFilter['!ID'] = $arSectionsIds;
					}
				}
			}
		}

		return $arFilter;
	}

	public static function makeElementFilterInRegion(&$arFilter, $regionId = false){

		$bFilterItem = false;
		if( isset($GLOBALS['arTheme']['USE_REGIONALITY']['VALUE']) ) {
			$bFilterItem = $GLOBALS['arTheme']['USE_REGIONALITY']['VALUE'] === 'Y' && $GLOBALS['arTheme']['USE_REGIONALITY']['DEPENDENT_PARAMS']['REGIONALITY_FILTER_ITEM']['VALUE'] === 'Y';
		} else {
			$bFilterItem = $GLOBALS['arTheme']['USE_REGIONALITY'] === 'Y' && $GLOBALS['arTheme']['REGIONALITY_FILTER_ITEM'] === 'Y';
		}

		if($bFilterItem){
			$iblockId = $arFilter['IBLOCK_ID'];
			if(!$iblockId){
				$iblockId = CAllcorp3Cache::$arIBlocks[SITE_ID]['aspro_allcorp3_catalog']['aspro_allcorp3_catalog'][0];
			}

			if($iblockId){
				if(!$regionId && $GLOBALS['arRegion']){
					$regionId = $GLOBALS['arRegion']['ID'];
				}

				if($regionId){
					if($arSectionsIds = self::getSectionsIds_NotInRegion($arFilter['IBLOCK_ID'], $regionId)){
						$arFilter['!IBLOCK_SECTION_ID'] = $arSectionsIds;
					}
				}
			}
		}
		return $arFilter;
	}

	public static function checkElementsIdsInRegion(&$arIds, $iblockId = false, $regionId = false){

		$bFilterItem = false;
		if( isset($GLOBALS['arTheme']['USE_REGIONALITY']['VALUE']) ) {
			$bFilterItem = $GLOBALS['arTheme']['USE_REGIONALITY']['VALUE'] === 'Y' && $GLOBALS['arTheme']['USE_REGIONALITY']['DEPENDENT_PARAMS']['REGIONALITY_FILTER_ITEM']['VALUE'] === 'Y';
		} else {
			$bFilterItem = $GLOBALS['arTheme']['USE_REGIONALITY'] === 'Y' && $GLOBALS['arTheme']['REGIONALITY_FILTER_ITEM'] === 'Y';
		}

		if($bFilterItem && $arIds){
			if(!$iblockId){
				$iblockId = CAllcorp3Cache::$arIBlocks[SITE_ID]['aspro_allcorp3_catalog']['aspro_allcorp3_catalog'][0];
			}

			if($iblockId){
				if(!$regionId && $GLOBALS['arRegion']){
					$regionId = $GLOBALS['arRegion']['ID'];
				}

				if($regionId){
					if($arSectionsIds = self::getSectionsIds_NotInRegion($arFilter['IBLOCK_ID'], $regionId)){
						$arIds = CAllcorp3Cache::CIBLockElement_GetList(
							array(
								'CACHE' => array(
									'TAG' => CAllcorp3Cache::GetIBlockCacheTag($iblockId),
									'RESULT' => array('ID'),
									'MULTI' => 'Y',
								)
							),
							array(
								'ID' => $arIds,
								'IBLOCK_ID' => $iblockId,
								'!IBLOCK_SECTION_ID' => $arSectionsIds,
							),
							false,
							false,
							array('ID')
						);
					}
				}
			}
		}

		return $arIds;
	}

	public static function drawFormField($FIELD_SID, $arQuestion, $type = 'POPUP', $arParams = array()){?>
		<?$arQuestion["HTML_CODE"] = str_replace('name=', 'data-sid="'.$FIELD_SID.'" name=', $arQuestion["HTML_CODE"]);?>
		<?$arQuestion["HTML_CODE"] = str_replace('left', '', $arQuestion["HTML_CODE"]);?>
		<?$arQuestion["HTML_CODE"] = str_replace('size="0"', '', $arQuestion["HTML_CODE"]);?>
		<?if($arQuestion['STRUCTURE'][0]['FIELD_TYPE'] == 'hidden'):?>
			<?if($FIELD_SID == 'TOTAL_SUMM')
			{
				if($arParams['TOTAL_SUMM'])
				{
					$arQuestion["HTML_CODE"] = str_replace('value="', 'value="'.$arParams['TOTAL_SUMM'], $arQuestion["HTML_CODE"]);
				}
			}?>
			<?=$arQuestion["HTML_CODE"];?>
		<?else:?>
			<div class="row" data-SID="<?=$FIELD_SID?>">
				<div class="col-md-12 <?=(in_array($arQuestion['STRUCTURE'][0]['FIELD_TYPE'], array("checkbox", "radio")) ? "style_check bx_filter" : "");?>">
					<?$filed = ( $arQuestion['VALUE'] || $_REQUEST['form_'.$arQuestion['STRUCTURE'][0]['FIELD_TYPE'].'_'.$arQuestion['STRUCTURE'][0]['ID']] || $arQuestion['STRUCTURE'][0]['VALUE'] ? "input-filed" : "");?>
					<div class="form-group <?=(!in_array($arQuestion['STRUCTURE'][0]['FIELD_TYPE'], array("file", "image", "checkbox", "radio", "multiselect", "date1")) ? "fill-animate" : "");?>">
						<label class="font_13 color_999" for="<?=$type.'_'.$FIELD_SID?>"><span><?=$arQuestion["CAPTION"]?><?=($arQuestion["REQUIRED"] == "Y" ? '&nbsp;<span class="required-star">*</span>' : '')?></span></label>
						<div class="input <?=(($arQuestion['STRUCTURE'][0]['FIELD_TYPE'] == 'date') ? 'dates' : '')?>">
							<?
							/*if($arQuestion['STRUCTURE'][0]['FIELD_TYPE'] != 'date')
							{*/
								if(strpos($arQuestion["HTML_CODE"], "class=") === false)
								{
									$arQuestion["HTML_CODE"] = str_replace('input', 'input class=""', $arQuestion["HTML_CODE"]);
								}
								$arQuestion["HTML_CODE"] = str_replace('class="', 'class="form-control ', $arQuestion["HTML_CODE"]);
								$arQuestion["HTML_CODE"] = str_replace('class="', 'id="'.$type.'_'.$FIELD_SID.'" class="', $arQuestion["HTML_CODE"]);
							//}


							if (is_array($arResult["FORM_ERRORS"]) && array_key_exists($FIELD_SID, $arResult['FORM_ERRORS'])) {
								$arQuestion["HTML_CODE"] = str_replace('class="', 'class="error ', $arQuestion["HTML_CODE"]);
							}

							if ($arQuestion["REQUIRED"] == "Y") {
								$arQuestion["HTML_CODE"] = str_replace('name=', 'required name=', $arQuestion["HTML_CODE"]);
							}

							if ($arQuestion["STRUCTURE"][0]["FIELD_TYPE"] == "email") {
								$arQuestion["HTML_CODE"] = str_replace('type="text"', 'type="email" placeholder=""', $arQuestion["HTML_CODE"]);
							}

							if (strpos($arQuestion["HTML_CODE"], "phone") !== false) {
								$arQuestion["HTML_CODE"] = str_replace('type="text"', 'type="tel"', $arQuestion["HTML_CODE"]);
							}

							if ($filed) {
								$arQuestion["HTML_CODE"] = str_replace('class="', 'class="'.$filed.' ', $arQuestion["HTML_CODE"]);
							}

							?>
							<?if($FIELD_SID == 'RATING'):?>
								<div class="votes_block with-text">
									<div class="ratings">
										<?for($i=1;$i<=5;$i++):?>
											<div class="item-rating" data-message="<?=GetMessage('RATING_MESSAGE_'.$i)?>"><?=static::showIconSvg("star", SITE_TEMPLATE_PATH."/images/svg/star.svg");?></div>
										<?endfor;?>
									</div>
									<div class="rating_message muted" data-message="<?=GetMessage('RATING_MESSAGE_0')?>"><?=GetMessage('RATING_MESSAGE_0')?></div>
									<?=str_replace('type="text"', 'type="hidden"', $arQuestion["HTML_CODE"])?>
								</div>
							<?elseif($arQuestion['STRUCTURE'][0]['FIELD_TYPE'] == "checkbox" || $arQuestion['STRUCTURE'][0]['FIELD_TYPE'] == "radio"):?>
								<?foreach($arQuestion['STRUCTURE'] as $arTmpQuestion):?>
									<?$name = $arTmpQuestion["FIELD_TYPE"]."_".$FIELD_SID;?>
									<?$name .= ($arQuestion['STRUCTURE'][0]['FIELD_TYPE'] == "checkbox" ? "[]" : "")?>
									<?$typeField = $arQuestion['STRUCTURE'][0]['FIELD_TYPE'] == "checkbox" ? "checkbox" : "radiobox"?>
									<div class="filter <?=$arTmpQuestion["FIELD_TYPE"];?>">
										<input id="s<?=$arTmpQuestion["ID"];?>" class="form-<?=$typeField?>__input form-<?=$typeField?>__input--visible" name="form_<?=$name;?>" type="<?=$arTmpQuestion["FIELD_TYPE"];?>" value="<?=$arTmpQuestion["ID"];?>"/>
										<label class="form-<?=$typeField?>__label" for="s<?=$arTmpQuestion["ID"];?>"><?=$arTmpQuestion["MESSAGE"];?><span class="form-<?=$typeField?>__box"></span></label>
										
									</div>
								<?endforeach;?>
							<?else:?>
								<?=$arQuestion["HTML_CODE"]?>
							<?endif;?>
						</div>
						<?if( !empty( $arQuestion["HINT"] ) ){?>
							<div class="hint"><?=$arQuestion["HINT"]?></div>	
						<?}?>
					</div>
				</div>
			</div>
		<?endif;?>
	<?}

	public static function getFormID($code = '', $site = SITE_ID){
		global $arTheme;
		$form_id = 0;
		if($code)
		{
			if(self::GetFrontParametrValue('USE_BITRIX_FORM') == 'Y' && \Bitrix\Main\Loader::includeModule('form'))
			{
				$rsForm = CForm::GetList($by = 'id', $order = 'asc', array('ACTIVE' => 'Y', 'SID' => $code, 'SITE' => array($site), 'SID_EXACT_MATCH' => 'N'), $is_filtered);
				if($item = $rsForm->Fetch())
					$form_id = $item['ID'];
				else
					$form_id = CAllcorp3Cache::$arIBlocks[$site]["aspro_allcorp3_form"][$code][0];
			}
			else
			{
				$form_id = CAllcorp3Cache::$arIBlocks[$site]["aspro_allcorp3_form"][$code][0];
			}
		}
		return $form_id;
	}

	public static function checkContentFile($path){
		if(File::isFileExists($_SERVER['DOCUMENT_ROOT'].$path))
			$content = File::getFileContents($_SERVER['DOCUMENT_ROOT'].$path);
		return (!empty($content));
	}

	public static function get_banners_position($position){
		$arTheme = self::GetFrontParametrsValues(SITE_ID);
		if ($arTheme["ADV_".$position] == 'Y') {
			global $APPLICATION;
			$APPLICATION->IncludeComponent(
				"bitrix:news.list",
				"banners",
				array(
					"IBLOCK_TYPE" => "aspro_allcorp3_adv",
					"IBLOCK_ID" => CAllcorp3Cache::$arIBlocks[SITE_ID]["aspro_allcorp3_adv"]["aspro_allcorp3_banners"][0],
					"POSITION"	=> $position,
					"PAGE"		=> $APPLICATION->GetCurPage(),
					"NEWS_COUNT" => "100",
					"SORT_BY1" => "SORT",
					"SORT_ORDER1" => "ASC",
					"SORT_BY2" => "ID",
					"SORT_ORDER2" => "ASC",
					"FIELD_CODE" => array(
						0 => "NAME",
						2 => "PREVIEW_PICTURE",
					),
					"PROPERTY_CODE" => array(
						0 => "LINK",
						1 => "TARGET",
						2 => "BGCOLOR",
						3 => "SHOW_SECTION",
						4 => "SHOW_PAGE",
						5 => "HIDDEN_XS",
						6 => "HIDDEN_SM",
						7 => "POSITION",
						8 => "SIZING",
					),
					"CHECK_DATES" => "Y",
					"FILTER_NAME" => "arRegionLink",
					"DETAIL_URL" => "",
					"AJAX_MODE" => "N",
					"AJAX_OPTION_JUMP" => "N",
					"AJAX_OPTION_STYLE" => "Y",
					"AJAX_OPTION_HISTORY" => "N",
					"CACHE_TYPE" => "A",
					"CACHE_TIME" => "3600000",
					"CACHE_FILTER" => "Y",
					"CACHE_GROUPS" => "N",
					"PREVIEW_TRUNCATE_LEN" => "150",
					"ACTIVE_DATE_FORMAT" => "d.m.Y",
					"SET_TITLE" => "N",
					"SET_STATUS_404" => "N",
					"INCLUDE_IBLOCK_INTO_CHAIN" => "N",
					"ADD_SECTIONS_CHAIN" => "N",
					"HIDE_LINK_WHEN_NO_DETAIL" => "N",
					"PARENT_SECTION" => "",
					"PARENT_SECTION_CODE" => "",
					"INCLUDE_SUBSECTIONS" => "Y",
					"PAGER_TEMPLATE" => ".default",
					"DISPLAY_TOP_PAGER" => "N",
					"DISPLAY_BOTTOM_PAGER" => "N",
					"PAGER_TITLE" => "",
					"PAGER_SHOW_ALWAYS" => "N",
					"PAGER_DESC_NUMBERING" => "N",
					"PAGER_DESC_NUMBERING_CACHE_TIME" => "3600000",
					"PAGER_SHOW_ALL" => "N",
					"AJAX_OPTION_ADDITIONAL" => "",
					"SHOW_DETAIL_LINK" => "N",
					"SET_BROWSER_TITLE" => "N",
					"SET_META_KEYWORDS" => "N",
					"SET_META_DESCRIPTION" => "N",
					"COMPONENT_TEMPLATE" => "banners",
					"SET_LAST_MODIFIED" => "N",
					"COMPOSITE_FRAME_MODE" => "A",
					"COMPOSITE_FRAME_TYPE" => "AUTO",
					"PAGER_BASE_LINK_ENABLE" => "N",
					"SHOW_404" => "N",
					"MESSAGE_404" => ""
				),
				false, array('ACTIVE_COMPONENT' => 'Y')
			);
		}
	}

	public static function getIndexBlockClasses($options){
		$topOffset = $options['BOTTOM']['TOP_OFFSET']['VALUE'];
		$bottomOffset = $options['BOTTOM']['BOTTOM_OFFSET']['VALUE'];

		$bFon = $options['TOP']['FON'] == 'Y';
		$bDelimiter = $options['TOP']['DELIMITER'] == 'Y';

		$result = 'index-block';
		if($topOffset) {
			$result .= ' index-block--padding-top-'.$topOffset;
		}
		if($bottomOffset) {
			$result .= ' index-block--padding-bottom-'.$bottomOffset;
		}
		if($bFon) {
			$result .= ' index-block--fon';
		}
		if($bDelimiter) {
			$result .= ' index-block--delimiter';
		}
		if ($options['AJAX']['ENABLE'] === 'Y' && $options['AJAX']['FILE_PATH']) {
			$result .= ' js-load-block';
		}

		return $result;
	}

	public static function setBlocksCss() {
		$arBlocks = glob($_SERVER['DOCUMENT_ROOT'].SITE_TEMPLATE_PATH.'/css/blocks/*.css');

		foreach($arBlocks as $blockPath) {
			if(strpos($blockPath, '.min.css') === false) {
				$currentPath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $blockPath);
				$GLOBALS['APPLICATION']->SetAdditionalCSS($currentPath);
			}
		}
	}

	public static function setBlocksJs() {
		$arBlocks = glob($_SERVER['DOCUMENT_ROOT'].SITE_TEMPLATE_PATH.'/js/blocks/*.js');

		foreach($arBlocks as $blockPath) {
			if(strpos($blockPath, '.min.js') === false) {
				$currentPath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $blockPath);
				$GLOBALS['APPLICATION']->AddHeadScript($currentPath);
			}
		}
	}

	public static function setGenerateColorsOn($siteId = 's1') {
		\Bitrix\Main\Config\Option::set(ALLCORP3_MODULE_ID, 'NeedGenerateThemes', 'Y', $siteId);
		\Bitrix\Main\Config\Option::set(ALLCORP3_MODULE_ID, 'NeedGenerateCustomTheme', 'Y', $siteId);
	}

	public static function setFonts($arTheme) {
		global $APPLICATION;

		$bSelfHostedFonts = $arTheme['SELF_HOSTED_FONTS'] !== 'N';

		if(!$arTheme['CUSTOM_FONT']){
			if(
				!$arTheme['FONT_STYLE'] ||
				!self::$arParametrsList['MAIN']['OPTIONS']['FONT_STYLE']['LIST'][$arTheme['FONT_STYLE']]
			){
				$arTheme['FONT_STYLE'] = 10;
			}

			$font_family = self::$arParametrsList['MAIN']['OPTIONS']['FONT_STYLE']['LIST'][$arTheme['FONT_STYLE']]['LINK'];
			$font_template = self::$arParametrsList['MAIN']['OPTIONS']['FONT_STYLE']['LIST'][$arTheme['FONT_STYLE']]['TEMPLATE_LINK'];

			if(
				$bSelfHostedFonts &&
				$font_template &&
				@file_exists($_SERVER['DOCUMENT_ROOT'].SITE_TEMPLATE_PATH.$font_template)
			){
				//$APPLICATION->AddHeadString('<link href="'.SITE_TEMPLATE_PATH.$font_template.'" rel="preload" as="style">');
				$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.$font_template);
			}
			else{
				$APPLICATION->AddHeadString('<link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>');
				$APPLICATION->AddHeadString('<link href="'.(CMain::IsHTTPS() ? 'https' : 'http').'://fonts.googleapis.com/css?family='.$font_family.'" rel="preload" as="style" crossorigin>');
				$APPLICATION->AddHeadString('<link rel="stylesheet" href="'.(CMain::IsHTTPS() ? 'https' : 'http').'://fonts.googleapis.com/css?family='.$font_family.'" crossorigin>');
			}
		}
		else{
			$APPLICATION->AddHeadString('<link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>');
			$APPLICATION->AddHeadString('<'.$arTheme['CUSTOM_FONT'].' rel="preload" as="style" crossorigin>');
			$APPLICATION->AddHeadString('<'.$arTheme['CUSTOM_FONT'].' rel="stylesheet" crossorigin>');
		}

		if($arTheme['TITLE_FONT'] == 'Y'){
			if(
				$arTheme['TITLE_FONT_STYLE'] &&
				!self::$arParametrsList['MAIN']['OPTIONS']['TITLE_FONT']['DEPENDENT_PARAMS']['TITLE_FONT_STYLE']['LIST'][$arTheme['TITLE_FONT_STYLE']]
			){
				$arTheme['TITLE_FONT_STYLE'] = '';
			}

			if($arTheme['TITLE_FONT_STYLE']){
				$title_font = self::$arParametrsList['MAIN']['OPTIONS']['TITLE_FONT']['DEPENDENT_PARAMS']['TITLE_FONT_STYLE']['LIST'][$arTheme['TITLE_FONT_STYLE']];
				$title_font_family = $title_font['LINK'];
				$title_font_template = $title_font['TEMPLATE_LINK'];

				if(
					!isset($font_family) ||
					$title_font_family != $font_family
				){
					if(
						$bSelfHostedFonts &&
						$font_template &&
						@file_exists($_SERVER['DOCUMENT_ROOT'].SITE_TEMPLATE_PATH.$title_font_template)
					){
						//$APPLICATION->AddHeadString('<link href="'.SITE_TEMPLATE_PATH.$title_font_template.'" rel="preload" as="style">');
						$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.$title_font_template);
					}
					else{
						$APPLICATION->AddHeadString('<link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>');
						$APPLICATION->AddHeadString('<link href="'.(CMain::IsHTTPS() ? 'https' : 'http').'://fonts.googleapis.com/css?family='.$title_font_family.'" rel="preload" as="style" crossorigin>');
						$APPLICATION->AddHeadString('<link rel="stylesheet" href="'.(CMain::IsHTTPS() ? 'https' : 'http').'://fonts.googleapis.com/css?family='.$title_font_family.'" crossorigin>');
					}
				}

				$APPLICATION->AddHeadString('<style>.switcher-title {font-family: "'.$title_font['TITLE'].'", Arial, sans-serif;}</style>');
			}
		}
		?>
		<script>
		document.fonts.onloadingdone = function() {
			if (typeof CheckTopMenuDotted === 'function') {
				CheckTopMenuDotted();
			}
		}
		</script>
		<?
	}

	public static function setThemeColorsValues() {
		global $APPLICATION, $baseColor;
		$arThemeValues = self::GetFrontParametrsValues(SITE_ID);

		$isBaseCustom = $arThemeValues['BASE_COLOR'] == 'CUSTOM';
		$isMoreEnabled = $arThemeValues['USE_MORE_COLOR'] == 'Y';
		$isMoreCustom = $arThemeValues['MORE_COLOR'] == 'CUSTOM';

		$arBaseColors = self::$arParametrsList['MAIN']['OPTIONS']['BASE_COLOR']['LIST'];
		$arMoreColors = self::$arParametrsList['MAIN']['OPTIONS']['MORE_COLOR']['LIST'];

		if($isBaseCustom){
			$baseColor = (strpos($arThemeValues['BASE_COLOR_CUSTOM'], '#') === false ? '#' : '').$arThemeValues['BASE_COLOR_CUSTOM'];
		}
		else{
			$baseColor = $arBaseColors[$arThemeValues['BASE_COLOR']]['COLOR'];
		}

		if($isMoreEnabled){
			if($isMoreCustom){
				$moreColor = (strpos($arThemeValues['MORE_COLOR_CUSTOM'], '#') === false ? '#' : '').$arThemeValues['MORE_COLOR_CUSTOM'];
			}
			else{
				$moreColor = $arMoreColors[$arThemeValues['MORE_COLOR']]['COLOR'];
			}
		}
		else{
			$moreColor = $baseColor;
		}

		$borderRadius = $arThemeValues['ROUND_ELEMENTS'] === 'Y' ? 26 : 4;
		$textTransform = $arThemeValues['FONT_BUTTONS'] === 'UPPER' ? 'uppercase' : 'none';
		$letterSpacing = $arThemeValues['FONT_BUTTONS'] === 'UPPER' ? '0.8px' : 'normal';
		$buttonFontSize = $arThemeValues['FONT_BUTTONS'] === 'UPPER' ? '2px' : '0%';
		$buttonPadding1px = $arThemeValues['FONT_BUTTONS'] === 'UPPER' ? '1px' : '0%';
		$buttonPadding2px = $arThemeValues['FONT_BUTTONS'] === 'UPPER' ? '2px' : '0%';

		//hsl color
		$colorHsl = self::hexToHSL($baseColor);
		$hueColor = $colorHsl[0];
		$saturationColor = $colorHsl[1];
		$lightnessColor = $colorHsl[2];

		//hsl more color
		$moreColorHsl = self::hexToHSL($moreColor);
		$hueMoreColor = $moreColorHsl[0];
		$saturationMoreColor = $moreColorHsl[1];
		$lightnessMoreColor = $moreColorHsl[2];

		$lightnessHoverDiff = 6;

		$APPLICATION->AddHeadString('<style>html {--theme-base-color: '.$baseColor.';--theme-base-opacity-color: '.$baseColor.'1a;--theme-more-color: '.$moreColor.';--theme-border-radius:'.$borderRadius.'px;--theme-text-transform:'.$textTransform.';--theme-letter-spacing:'.$letterSpacing.';--theme-button-font-size:'.$buttonFontSize.';--theme-button-padding-2px:'.$buttonPadding2px.';--theme-button-padding-1px:'.$buttonPadding1px.';--theme-more-color-hue:'.$hueMoreColor.';--theme-more-color-saturation:'.$saturationMoreColor.'%'.';--theme-more-color-lightness:'.$lightnessMoreColor.'%'.';--theme-base-color-hue:'.$hueColor.';--theme-base-color-saturation:'.$saturationColor.'%'.';--theme-base-color-lightness:'.$lightnessColor.'%'.';--theme-lightness-hover-diff:'.$lightnessHoverDiff.'%'.'}</style>');

		// self::setDarkTheme();
	}

	static public function setDarkTheme(){
		global $APPLICATION;
		$APPLICATION->AddHeadString('<style>:root {--ON_toggle: initial; --OFF_toggle: }</style>');
	}

	static public function nlo($code, $attrs = ''){
		static $arAvailable, $isStarted, $arNlo;

		if(!isset($arAvailable)){
			$arAvailable = array(
				'menu-fixed' => $GLOBALS['arTheme']['NLO_MENU']['VALUE'] === 'Y',
				'menu-mobile' => $GLOBALS['arTheme']['NLO_MENU']['VALUE'] === 'Y',
				'menu-megafixed' => $GLOBALS['arTheme']['NLO_MENU']['VALUE'] === 'Y',
			);

			$arNlo = array();
		}

		if(
			$arAvailable[$code] &&
			!isset($_REQUEST['BLOCK']) &&
			!isset($_REQUEST['IS_AJAX'])
		){
			if(
				isset($_REQUEST['nlo']) &&
				$_REQUEST['nlo'] === $code
			){
				if(isset($isStarted)){
					die();
				}

				$isStarted = true;
				$GLOBALS['APPLICATION']->RestartBuffer();

				return true;
			}
			else{
				if($arNlo[$code]){
					echo '</div>';
				}
				else{
					$arNlo[$code] = true;
					echo '<div '.(strlen($attrs) ? $attrs : '').' data-nlo="'.$code.'">';
				}

				return false;
			}
		}

		return true;
	}

	public static function getSectionDescriptionPriority($siteId = ''){
		static $arCacheValues;

		$siteId = strlen($siteId) ? $siteId : (defined('SITE_ID') ? SITE_ID : '');

		if(!isset($arCacheValues)){
			$arCacheValues = array();
		}

		if(!isset($arCacheValues[$siteId])){
			$arCacheValues[$siteId] = array();
		}

		$arPriority =& $arCacheValues[$siteId];

		if(!$arPriority){
			$arPriority = array();

			if($siteId){
				if(static::GetFrontParametrValue('USE_PRIORITY_SECTION_DESCRIPTION_SOURCE', $siteId) === 'Y'){
					$priority = static::GetFrontParametrValue('PRIORITY_SECTION_DESCRIPTION_SOURCE', $siteId);
					$arPriority = explode(',', $priority);

					if(!in_array('SMARTSEO', $arPriority)){
						$arPriority[] = 'SMARTSEO';
					}
					if(!in_array('SOTBIT_SEOMETA', $arPriority)){
						$arPriority[] = 'SOTBIT_SEOMETA';
					}
					if(!in_array('IBLOCK', $arPriority)){
						$arPriority[] = 'IBLOCK';
					}
				}
			}
		}

		return $arPriority;
	}

	public static function unsetViewPart($viewCode, $searchPartContent){
		if(
			$GLOBALS['APPLICATION']->__view &&
			is_array($GLOBALS['APPLICATION']->__view) &&
			array_key_exists($viewCode, $GLOBALS['APPLICATION']->__view) &&
			$GLOBALS['APPLICATION']->__view[$viewCode] &&
			is_array($GLOBALS['APPLICATION']->__view[$viewCode])
		){
			$searchPartContent = trim($searchPartContent);

			foreach($GLOBALS['APPLICATION']->__view[$viewCode] as $i => $arPartContent){
				$partContent = trim($arPartContent[0]);

				if($partContent === $searchPartContent){
					unset($GLOBALS['APPLICATION']->__view[$viewCode][$i]);
					break;
				}
			}
		}
	}

	public static function replaceViewPart($viewCode, $searchPartContent, $newPartContent){
		if(
			$GLOBALS['APPLICATION']->__view &&
			is_array($GLOBALS['APPLICATION']->__view) &&
			array_key_exists($viewCode, $GLOBALS['APPLICATION']->__view) &&
			$GLOBALS['APPLICATION']->__view[$viewCode] &&
			is_array($GLOBALS['APPLICATION']->__view[$viewCode])
		){
			$searchPartContent = trim($searchPartContent);
			$newPartContent = trim($newPartContent);

			foreach($GLOBALS['APPLICATION']->__view[$viewCode] as $i => $arPartContent){
				$partContent = trim($arPartContent[0]);

				if($partContent === $searchPartContent){
					$GLOBALS['APPLICATION']->__view[$viewCode][$i][0] = $newPartContent;
					break;
				}
			}
		}
	}

	public static function setCatalogSectionDescription(array $arParams){
		$siteId = strlen($siteId) ? $siteId : (defined('SITE_ID') ? SITE_ID : '');

		$posSectionDescr = static::GetFrontParametrValue('SHOW_SECTION_DESCRIPTION', $siteId);

		/*$GLOBALS['APPLICATION']->IncludeComponent(
			"aspro:smartseo.content.allcorp3",
			".default",
			array(
				"FIELDS" => array(
					"TOP_DESCRIPTION",
		            "BOTTOM_DESCRIPTION",
		            "ADDITIONAL_DESCRIPTION",
				),
				"SHOW_VIEW_CONTENT" => "Y",
				"CODE_VIEW_CONTENT" => "smartseo",
			),
			false,
			array("HIDE_ICONS" => "Y")
		);*/

		if(\Bitrix\Main\Loader::includeModule("sotbit.seometa")){
			// unset, because the sotbit:seo.meta component may have already been included
			unset($GLOBALS['APPLICATION']->__view['sotbit_seometa_h1']);
			unset($GLOBALS['APPLICATION']->__view['sotbit_seometa_top_desc']);
			unset($GLOBALS['APPLICATION']->__view['sotbit_seometa_bottom_desc']);
			unset($GLOBALS['APPLICATION']->__view['sotbit_seometa_add_desc']);
			unset($GLOBALS['APPLICATION']->__view['sotbit_seometa_file']);

			$GLOBALS['APPLICATION']->IncludeComponent(
				"sotbit:seo.meta",
				".default",
				array(
					"FILTER_NAME" => $arParams["FILTER_NAME"],
					"SECTION_ID" => $arParams["SECTION_ID"],
					"CACHE_TYPE" => $arParams["CACHE_TYPE"],
					"CACHE_TIME" => $arParams["CACHE_TIME"],
				),
				false,
				array("HIDE_ICONS" => "Y")
			);
		}

		$top_desc = trim($GLOBALS['APPLICATION']->GetViewContent('top_desc'));
		$bottom_desc = trim($GLOBALS['APPLICATION']->GetViewContent('bottom_desc'));
		$smartseo_top_desc = trim($GLOBALS['APPLICATION']->GetViewContent('smartseo_top_description'));
		$smartseo_bottom_desc = trim($GLOBALS['APPLICATION']->GetViewContent('smartseo_bottom_description'));
		$smartseo_add_desc = trim($GLOBALS['APPLICATION']->GetViewContent('smartseo_additional_description'));
		$sotbit_top_desc = trim($GLOBALS['APPLICATION']->GetViewContent('sotbit_seometa_top_desc'));
		$sotbit_bottom_desc = trim($GLOBALS['APPLICATION']->GetViewContent('sotbit_seometa_bottom_desc'));
		$sotbit_add_desc = trim($GLOBALS['APPLICATION']->GetViewContent('sotbit_seometa_add_desc'));

		$bShowTopDescInTop = $bShowAdditionalDescInTop2 = $bShowAdditionalDescInBottom = $bShowBottomDescInBottom = $bShowSeoDesc = false;

		if($arParams['SEO_ITEM']){
			$bShowTopDescInTop = true;
			$bShowBottomDescInBottom = true;
			$bShowAdditionalDescInTop2 = true;
			$bShowSeoDesc = true;
		}
		elseif(
			$arParams['SHOW_SECTION_DESC'] !== 'N' &&
			strpos($_SERVER['REQUEST_URI'], 'PAGEN') === false
		){
			$bShowTopDescInTop = $posSectionDescr === 'BOTH' || $posSectionDescr === 'TOP';
			$bShowBottomDescInBottom = $posSectionDescr === 'BOTH' || $posSectionDescr === 'BOTTOM';
			$bShowAdditionalDescInBottom = true;
			$bShowSeoDesc = true;
		}

		if($bShowSeoDesc){
			if(strlen($smartseo_top_desc)){
				$GLOBALS['APPLICATION']->AddViewContent('top_content', '<div class="group_description_block top color_666 smartseo-block">'.$smartseo_top_desc.'</div>');
			}

			if(strlen($sotbit_top_desc)){
				$GLOBALS['APPLICATION']->AddViewContent('top_content', '<div class="group_description_block top color_666 sotbit-block">'.$sotbit_top_desc.'</div>');
			}

			if(strlen($smartseo_bottom_desc)){
				static::replaceViewPart('smartseo_bottom_description', $smartseo_bottom_desc, '<div class="group_description_block bottom color_666 smartseo-block">'.$smartseo_bottom_desc.'</div>');
			}

			if(strlen($sotbit_bottom_desc)){
				static::replaceViewPart('sotbit_seometa_bottom_desc', $sotbit_bottom_desc, '<div class="group_description_block bottom color_666 sotbit-block">'.$sotbit_bottom_desc.'</div>');
			}
		}
		else{
			unset($GLOBALS['APPLICATION']->__view['smartseo_bottom_description']);
			unset($GLOBALS['APPLICATION']->__view['sotbit_seometa_bottom_desc']);
		}

		if(!$bShowTopDescInTop){
			unset($GLOBALS['APPLICATION']->__view['top_desc']);
			static::unsetViewPart('top_content', $top_desc);
		}

		if(!$bShowBottomDescInBottom){
			unset($GLOBALS['APPLICATION']->__view['bottom_desc']);
		}

		if($bShowAdditionalDescInTop2){
			if(strlen($smartseo_add_desc)){
				$GLOBALS['APPLICATION']->AddViewContent('top_content2', '<div class="group_description_block top color_666 smartseo-block">'.$smartseo_add_desc.'</div>');
			}

			if(strlen($sotbit_add_desc)){
				$GLOBALS['APPLICATION']->AddViewContent('top_content2', '<div class="group_description_block top color_666 sotbit-block">'.$sotbit_add_desc.'</div>');
			}
		}

		if($bShowAdditionalDescInBottom){
			if(strlen($smartseo_add_desc)){
				static::replaceViewPart('smartseo_additional_description', $smartseo_add_desc, '<div class="group_description_block bottom color_666 smartseo-block">'.$smartseo_add_desc.'</div>');
			}

			if(strlen($sotbit_add_desc)){
				static::replaceViewPart('sotbit_seometa_add_desc', $sotbit_add_desc, '<div class="group_description_block bottom color_666 sotbit-block">'.$sotbit_add_desc.'</div>');
			}
		}
		else{
			unset($GLOBALS['APPLICATION']->__view['smartseo_additional_description']);
			unset($GLOBALS['APPLICATION']->__view['sotbit_seometa_add_desc']);
		}

		$arPriority = static::getSectionDescriptionPriority(SITE_ID);
		if($arPriority){
			$bTopDescFilled = $bTopDesc2Filled = $bBottomDescFilled = false;

			foreach($arPriority as $priorityCode){
				if($priorityCode === 'IBLOCK'){
					if(strlen($top_desc)){
						if($bTopDescFilled){
							unset($GLOBALS['APPLICATION']->__view['top_desc']);
							static::unsetViewPart('top_content', $top_desc);
						}
						else{
							$bTopDescFilled = true;
						}
					}

					if(strlen($bottom_desc)){
						if($bBottomDescFilled){
							unset($GLOBALS['APPLICATION']->__view['bottom_desc']);
						}
						else{
							$bBottomDescFilled = true;
						}
					}
				}
				elseif($priorityCode === 'SMARTSEO'){
					if(strlen($smartseo_top_desc) && $bShowSeoDesc){
						if($bTopDescFilled){
							unset($GLOBALS['APPLICATION']->__view['smartseo_top_description']);
							static::unsetViewPart('top_content', '<div class="group_description_block top color_666 smartseo-block">'.$smartseo_top_desc.'</div>');
						}
						else{
							$bTopDescFilled = true;

							if(strlen($top_desc)){
								static::unsetViewPart('top_content', '<div class="group_description_block top color_666 smartseo-block">'.$smartseo_top_desc.'</div>');
								static::replaceViewPart('top_content', $top_desc, '<div class="group_description_block top color_666 smartseo-block">'.$smartseo_top_desc.'</div>');
							}
						}
					}

					if(strlen($smartseo_add_desc) && $bShowAdditionalDescInTop2){
						if($bTopDesc2Filled){
							unset($GLOBALS['APPLICATION']->__view['smartseo_additional_description']);
							static::unsetViewPart('top_content2', '<div class="group_description_block top color_666 smartseo-block">'.$smartseo_add_desc.'</div>');
						}
						else{
							$bTopDesc2Filled = true;
						}
					}

					if(strlen(($bShowSeoDesc ? $smartseo_bottom_desc : '').($bShowAdditionalDescInBottom ? $smartseo_add_desc : ''))){
						if($bBottomDescFilled){
							unset($GLOBALS['APPLICATION']->__view['smartseo_bottom_description']);
							unset($GLOBALS['APPLICATION']->__view['smartseo_additional_description']);
						}
						else{
							$bBottomDescFilled = true;
						}
					}
				}
				else{
					if(strlen($sotbit_top_desc) && $bShowSeoDesc){
						if($bTopDescFilled){
							unset($GLOBALS['APPLICATION']->__view['sotbit_top_desc']);
							static::unsetViewPart('top_content', '<div class="group_description_block top color_666 sotbit-block">'.$sotbit_top_desc.'</div>');
						}
						else{
							$bTopDescFilled = true;

							if(strlen($top_desc)){
								static::unsetViewPart('top_content', '<div class="group_description_block top color_666 sotbit-block">'.$sotbit_top_desc.'</div>');
								static::replaceViewPart('top_content', $top_desc, '<div class="group_description_block top color_666 sotbit-block">'.$sotbit_top_desc.'</div>');
							}
						}
					}

					if(strlen($sotbit_add_desc) && $bShowAdditionalDescInTop2){
						if($bTopDesc2Filled){
							unset($GLOBALS['APPLICATION']->__view['sotbit_add_desc']);
							static::unsetViewPart('top_content2', '<div class="group_description_block top color_666 sotbit-block">'.$sotbit_add_desc.'</div>');
						}
						else{
							$bTopDesc2Filled = true;
						}
					}

					if(strlen(($bShowSeoDesc ? $sotbit_bottom_desc : '').($bShowAdditionalDescInBottom ? $sotbit_add_desc : ''))){
						if($bBottomDescFilled){
							unset($GLOBALS['APPLICATION']->__view['sotbit_bottom_desc']);
							unset($GLOBALS['APPLICATION']->__view['sotbit_add_desc']);
						}
						else{
							$bBottomDescFilled = true;
						}
					}
				}
			}
		}
	}

	public static function GetFileInfo($arItem){
		$arTmpItem = CFile::GetFileArray($arItem);
		switch($arTmpItem["CONTENT_TYPE"]){
			case 'application/pdf': $type="pdf"; break;
			case 'application/vnd.ms-excel': $type="excel"; break;
			case 'application/vnd.ms-office': $type="excel"; break;
			case 'application/xls': $type="excel"; break;
			case 'application/octet-stream': $type="word"; break;
			case 'application/msword': $type="word"; break;
			case 'image/jpeg': $type="jpg"; break;
			case 'image/tiff': $type="tiff"; break;
			case 'image/png': $type="png"; break;
			default: $type="default"; break;
		}
		if($type == "default")
		{
			$frm = explode('.', $arTmpItem['FILE_NAME']);
			$frm = $frm[1];
			if($frm == 'doc' || $frm == 'docx')
				$type = 'doc';
			elseif($frm == 'xls' || $frm == 'xlsx')
				$type = 'xls';
			elseif($frm == 'jpg' || $frm == 'jpeg')
				$type = 'jpg';
			elseif($frm == 'png')
				$type = 'png';
			elseif($frm == 'ppt' || $frm == 'pptx')
				$type = 'ppt';
			elseif($frm == 'tif')
				$type = 'tif';
			elseif($frm == 'pdf')
				$type = 'pdf';
			elseif($frm == 'txt')
				$type = 'txt';
			elseif($frm == 'gif')
				$type = 'gif';
			elseif($frm == 'bmp')
				$type = 'bmp';
			else
				$type = 'file';
		}

		$filesize = $arTmpItem["FILE_SIZE"];
		if($filesize > 1024){
			$filesize = ($filesize / 1024);
			if($filesize > 1024){
				$filesize = ($filesize / 1024);
				if($filesize > 1024){
					$filesize = ($filesize / 1024);
					$filesize = round($filesize, 1);
					$filesize_format=str_replace(".", ",", $filesize).GetMessage('CT_NAME_GB');
				}
				else{
					$filesize = round($filesize, 1);
					$filesize_format=str_replace(".", ",", $filesize).GetMessage('CT_NAME_MB');
				}
			}
			else{
				$filesize = round($filesize, 1);
				$filesize_format=str_replace(".", ",", $filesize).GetMessage('CT_NAME_KB');
			}
		}
		else{
			$filesize = round($filesize, 1);
			$filesize_format=str_replace(".", ",", $filesize).GetMessage('CT_NAME_b');
		}
		$fileName = substr($arTmpItem["ORIGINAL_NAME"], 0, strrpos($arTmpItem["ORIGINAL_NAME"], '.'));
		return array("TYPE" => $type, "FILE_SIZE" => $filesize, "FILE_SIZE_FORMAT" => $filesize_format, "DESCRIPTION" => ( $arTmpItem["DESCRIPTION"] ? $arTmpItem["DESCRIPTION"] : $fileName), "SRC" => $arTmpItem["SRC"]);
	}

	public static function hexToRgb($hex, $alpha = false) {
		$hex      = str_replace('#', '', $hex);
		$length   = strlen($hex);
		$rgb[] = hexdec($length == 6 ? substr($hex, 0, 2) : ($length == 3 ? str_repeat(substr($hex, 0, 1), 2) : 0));
		$rgb[] = hexdec($length == 6 ? substr($hex, 2, 2) : ($length == 3 ? str_repeat(substr($hex, 1, 1), 2) : 0));
		$rgb[] = hexdec($length == 6 ? substr($hex, 4, 2) : ($length == 3 ? str_repeat(substr($hex, 2, 1), 2) : 0));
		if ( $alpha ) {
		   $rgb[] = $alpha;
		}
		return $rgb;
	 }

	public static function hexToHSL($color) {

		$rgbColor = self::hexToRgb($color);

		$r = $rgbColor[0] / 255;
		$g = $rgbColor[1] / 255;
		$b = $rgbColor[2] / 255;

		$min = min($r, $g, $b);
		$max = max($r, $g, $b);

		$L = ($min + $max) / 2;
		if ($min == $max) {
			$S = $H = 0;
		} else {
			if ($L < 0.5)
				$S = ($max - $min)/($max + $min);
			else
				$S = ($max - $min)/(2.0 - $max - $min);

			if ($r == $max) $H = ($g - $b)/($max - $min);
			elseif ($g == $max) $H = 2.0 + ($b - $r)/($max - $min);
			elseif ($b == $max) $H = 4.0 + ($r - $g)/($max - $min);

		}

		$out = array(round(($H < 0 ? $H + 6 : $H)*60),
			round($S*100),
			round($L*100),
		);

		if (count($rgbColor) > 3) $out[] = $rgbColor[3]; // copy alpha
		return $out;
	}
	public static function getSvgSizeFromName($name, $default = ['WIDTH' => 10, 'HEIGHT' => 10]){
		$sizeSvg = [];
		$arName = explode('-', $name);
		$bSizeFromName = count($arName) > 1;

		if($bSizeFromName){
			$sizeSvg['HEIGHT'] = (int) array_pop($arName);
			$sizeSvg['WIDTH'] = (int) array_pop($arName);
		} else {
			$sizeSvg = $default;
		}
		return $sizeSvg;
	}
}?>