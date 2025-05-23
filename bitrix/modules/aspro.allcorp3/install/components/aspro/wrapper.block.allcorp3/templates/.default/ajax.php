<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<?use \Bitrix\Main\Localization\Loc;?>
<?$frame = $this->createFrame()->begin('');?>
	<?$APPLICATION->IncludeComponent(
		"bitrix:news.list", 
		"map-list", 
		array(
			"IBLOCK_TYPE" => $arParams['IBLOCK_TYPE'],
			"IBLOCK_ID" => $arParams['IBLOCK_ID'],
			"NEWS_COUNT" => "999",
			"SORT_BY1" => "SORT",
			"SORT_ORDER1" => "DESC",
			"SORT_BY2" => "SORT",
			"SORT_ORDER2" => "ASC",
			"SORT_BY1" => $arParams['ELEMENT_SORT_FIELD'],
			"SORT_ORDER1" => $arParams['ELEMENT_SORT_ORDER'],
			"SHOW_DISCOUNT_TIME_EACH_SKU" => "N",
			"SORT_BY2" => $arParams["ELEMENT_SORT_FIELD2"],
			"SORT_ORDER2" => $arParams["ELEMENT_SORT_ORDER2"],
			"FILTER_NAME" => $arParams['FILTER_NAME'],
			"FIELD_CODE" => array(
				0 => "PREVIEW_PICTURE",
				1 => "NAME",
			),
			"PROPERTY_CODE" => array(
				0 => "EMAIL",
				1 => "PHONE",
				2 => "ADDRESS",
				3 => "SCHEDULE",
				4 => "METRO",
				5 => "MAP",
			),
			"CHECK_DATES" => "Y",
			"DETAIL_URL" => "",
			"AJAX_MODE" => "N",
			"AJAX_OPTION_JUMP" => "N",
			"AJAX_OPTION_STYLE" => "Y",
			"AJAX_OPTION_HISTORY" => "N",
			"CACHE_TYPE" => $arParams["CACHE_TYPE"],
			"CACHE_TIME" => $arParams["CACHE_TIME"],
			"CACHE_GROUPS" => $arParams["CACHE_GROUPS"],
			"CACHE_FILTER" => $arParams["CACHE_FILTER"],
			"PREVIEW_TRUNCATE_LEN" => "",
			"ACTIVE_DATE_FORMAT" => "d.m.Y",
			"SET_TITLE" => "N",
			"SET_STATUS_404" => "N",
			"INCLUDE_IBLOCK_INTO_CHAIN" => "N",
			"ADD_SECTIONS_CHAIN" => "N",
			"HIDE_LINK_WHEN_NO_DETAIL" => "N",
			"PARENT_SECTION" => "",
			"PARENT_SECTION_CODE" => "",
			"DISPLAY_TOP_PAGER" => "N",
			"DISPLAY_BOTTOM_PAGER" => "N",
			"PAGER_TITLE" => "",
			"PAGER_SHOW_ALWAYS" => "N",
			"PAGER_TEMPLATE" => "",
			"PAGER_DESC_NUMBERING" => "N",
			"PAGER_DESC_NUMBERING_CACHE_TIME" => "3600",
			"PAGER_SHOW_ALL" => "N",
			"DISPLAY_DATE" => "Y",
			"DISPLAY_NAME" => "Y",
			"DISPLAY_PICTURE" => "N",
			"DISPLAY_PREVIEW_TEXT" => "N",
			"AJAX_OPTION_ADDITIONAL" => "",
			"COMPONENT_TEMPLATE" => "front_map3",
			"SET_BROWSER_TITLE" => "N",
			"SET_META_KEYWORDS" => "N",
			"SET_META_DESCRIPTION" => "N",
			"SET_LAST_MODIFIED" => "N",
			"INCLUDE_SUBSECTIONS" => "Y",
			"STRICT_SECTION_CHECK" => "N",
			"TITLE" => $arParams['TITLE'],
			"SHOW_TITLE" => $arParams['SHOW_TITLE'],
			"TITLE_POSITION" => $arParams['TITLE_POSITION'],
			"SUBTITLE" => $arParams['SUBTITLE'],
			"SHOW_PREVIEW_TEXT" => $arParams['SHOW_PREVIEW_TEXT'],
			"RIGHT_TITLE" => $arParams['RIGHT_TITLE'],
			"RIGHT_LINK" => $arParams['RIGHT_LINK'],
			"WIDE" => $arParams['WIDE'],
			"OFFSET" => $arParams["OFFSET"],
			"MAP_TYPE" => $arParams['MAP_TYPE'],
			"PAGER_BASE_LINK_ENABLE" => "N",
			"SHOW_404" => "N",
			"MESSAGE_404" => ""
		),
		false, ['HIDE_ICONS' => 'Y']
	);?>
<?$frame->end();?>