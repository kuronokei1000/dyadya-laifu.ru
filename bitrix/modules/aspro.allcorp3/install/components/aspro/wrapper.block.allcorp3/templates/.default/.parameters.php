<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

if(!CModule::IncludeModule("iblock"))
	return;

$arIBlockType = CIBlockParameters::GetIBlockTypes();

$arIBlock = array();
$rsIBlock = CIBlock::GetList(Array("sort" => "asc"), Array("TYPE" => $arCurrentValues["IBLOCK_TYPE"], "ACTIVE"=>"Y"));
while($arr=$rsIBlock->Fetch())
	$arIBlock[$arr["ID"]] = "[".$arr["ID"]."] ".$arr["NAME"];

$arProperty_LNS = $arProperty_N = $arProperty_X = $arProperty_S = $arProperty_E = array();
if (0 < intval($arCurrentValues['IBLOCK_ID']))
{
	$rsProp = CIBlockProperty::GetList(Array("sort"=>"asc", "name"=>"asc"), Array("IBLOCK_ID"=>$arCurrentValues["IBLOCK_ID"], "ACTIVE"=>"Y"));
	while ($arr=$rsProp->Fetch())
	{
		if($arr["PROPERTY_TYPE"] != "F")
			$arProperty[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];

		if($arr["PROPERTY_TYPE"]=="N")
			$arProperty_N[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];
		if($arr["PROPERTY_TYPE"]=="S")
			$arProperty_S[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];

		if($arr["PROPERTY_TYPE"]!="F")
		{
			if($arr["PROPERTY_TYPE"] == "L")
				$arProperty_X[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];
			elseif($arr["PROPERTY_TYPE"] == "E" && $arr["LINK_IBLOCK_ID"] > 0)
				$arProperty_E[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];
		}
	}
}

$arProperty_UF = array();
$arSProperty_LNS = array();
$arUserFields = $GLOBALS["USER_FIELD_MANAGER"]->GetUserFields("IBLOCK_".$arCurrentValues["IBLOCK_ID"]."_SECTION");
foreach($arUserFields as $FIELD_NAME=>$arUserField)
{
	$arProperty_UF[$FIELD_NAME] = $arUserField["LIST_COLUMN_LABEL"]? $arUserField["LIST_COLUMN_LABEL"]: $FIELD_NAME;
	if($arUserField["USER_TYPE"]["BASE_TYPE"]=="string")
		$arSProperty_LNS[$FIELD_NAME] = $arProperty_UF[$FIELD_NAME];
}

$arSort = CIBlockParameters::GetElementSortFields(
	array('SHOWS', 'SORT', 'TIMESTAMP_X', 'NAME', 'ID', 'ACTIVE_FROM', 'ACTIVE_TO'),
	array('KEY_LOWERCASE' => 'Y')
);

$arAscDesc = array(
	"asc" => GetMessage("IBLOCK_SORT_ASC"),
	"desc" => GetMessage("IBLOCK_SORT_DESC"),
);

$arTemplateParameters= Array(
	"IBLOCK_TYPE" => array(
		"PARENT" => "BASE",
		"NAME" => GetMessage("IBLOCK_TYPE"),
		"TYPE" => "LIST",
		"VALUES" => $arIBlockType,
		"REFRESH" => "Y",
	),
	"IBLOCK_ID" => array(
		"PARENT" => "BASE",
		"NAME" => GetMessage("IBLOCK_IBLOCK"),
		"TYPE" => "LIST",
		"ADDITIONAL_VALUES" => "Y",
		"VALUES" => $arIBlock,
		"REFRESH" => "Y",
	),
	/*"SECTION_ID" => array(
		"PARENT" => "BASE",
		"NAME" => GetMessage("IBLOCK_SECTION_ID"),
		"TYPE" => "STRING",
		"DEFAULT" => '',
	),
	"SECTION_CODE" => array(
		"PARENT" => "BASE",
		"NAME" => GetMessage("IBLOCK_SECTION_CODE"),
		"TYPE" => "STRING",
		"DEFAULT" => '',
	),*/
	"ELEMENT_SORT_FIELD" => array(
		"PARENT" => "DATA_SOURCE",
		"NAME" => GetMessage("IBLOCK_ELEMENT_SORT_FIELD"),
		"TYPE" => "LIST",
		"VALUES" => $arSort,
		"ADDITIONAL_VALUES" => "Y",
		"DEFAULT" => "sort",
	),
	"ELEMENT_SORT_ORDER" => array(
		"PARENT" => "DATA_SOURCE",
		"NAME" => GetMessage("IBLOCK_ELEMENT_SORT_ORDER"),
		"TYPE" => "LIST",
		"VALUES" => $arAscDesc,
		"DEFAULT" => "asc",
		"ADDITIONAL_VALUES" => "Y",
	),
	"ELEMENT_SORT_FIELD2" => array(
		"PARENT" => "DATA_SOURCE",
		"NAME" => GetMessage("IBLOCK_ELEMENT_SORT_FIELD2"),
		"TYPE" => "LIST",
		"VALUES" => $arSort,
		"ADDITIONAL_VALUES" => "Y",
		"DEFAULT" => "id",
	),
	"ELEMENT_SORT_ORDER2" => array(
		"PARENT" => "DATA_SOURCE",
		"NAME" => GetMessage("IBLOCK_ELEMENT_SORT_ORDER2"),
		"TYPE" => "LIST",
		"VALUES" => $arAscDesc,
		"DEFAULT" => "desc",
		"ADDITIONAL_VALUES" => "Y",
	),
	"FILTER_NAME" => array(
		"PARENT" => "DATA_SOURCE",
		"NAME" => GetMessage("IBLOCK_FILTER_NAME_IN"),
		"TYPE" => "STRING",
		"DEFAULT" => "arrFilter",
	),
	"TITLE" => array(
		"PARENT" => "DATA_SOURCE",
		"NAME" => GetMessage("T_TITLE_BLOCK"),
		"TYPE" => "STRING",
		"DEFAULT" => "",
	),
	'SUBTITLE' => array(
		'NAME' => GetMessage('T_SUBTITLE'),
		'TYPE' => 'STRING',
		'DEFAULT' => '',
	),
	"RIGHT_TITLE" => array(
		"PARENT" => "DATA_SOURCE",
		"NAME" => GetMessage("T_TITLE_BLOCK_ALL"),
		"TYPE" => "STRING",
		"DEFAULT" => "",
	),
	"RIGHT_LINK" => array(
		"PARENT" => "DATA_SOURCE",
		"NAME" => GetMessage("T_ALL_URL"),
		"TYPE" => "STRING",
		"DEFAULT" => "",
	),
	'SHOW_PREVIEW_TEXT' => array(
		'NAME' => GetMessage('T_SHOW_PREVIEW_TEXT'),
		'TYPE' => 'CHECKBOX',
		'DEFAULT' => 'Y',
	),
	"MAP_TYPE" => array(
		"PARENT" => "DATA_SOURCE",
		"NAME" => GetMessage("T_MAP_TYPE"),
		"TYPE" => "LIST",
		"VALUES" => [0 => 'Yandex', 1 => 'Google'],
		"DEFAULT" => 0,
	),
	"CODE_BLOCK" => Array(
		"NAME" => GetMessage("T_CODE_BLOCK"),
		"TYPE" => "STRING",
		"DEFAULT" => "",
	),
	"WIDE" => Array(
		"NAME" => GetMessage("T_NARROW"),
		"TYPE" => "LIST",
		"VALUES" => [
			'FROM_THEME' => GetMessage('V_FROM_THEME'),
			'Y' => GetMessage('V_YES'),
			'N' => GetMessage('V_NO'),
		],
		"DEFAULT" => "N",
    ),
	/*"ELEMENT_COUNT" => array(
		"PARENT" => "VISUAL",
		"NAME" => GetMessage("IBLOCK_PAGE_ELEMENT_COUNT"),
		"TYPE" => "STRING",
		"DEFAULT" => "30",
	),*/
);?>