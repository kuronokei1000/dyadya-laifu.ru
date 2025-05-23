<?if( !defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true ) die();?>

<?global $USER, $APPLICATION;
if( !$USER->IsAuthorized() ){?>
	<?
	$APPLICATION->SetPageProperty('MENU', 'N');
	$APPLICATION->SetPageProperty("TITLE_CLASS", "center");
	$APPLICATION->SetTitle(GetMessage("TITLE"));
	?>
	<?$APPLICATION->IncludeComponent(
		"bitrix:system.auth.form",
		"main",
		Array(
			"AUTH_URL" => $arResult["SEF_FOLDER"].$arResult["URL_TEMPLATES"]["auth"],
			"REGISTER_URL" => $arResult["SEF_FOLDER"].$arResult["URL_TEMPLATES"]["registration"],
			"FORGOT_PASSWORD_URL" => $arResult["SEF_FOLDER"].$arResult["URL_TEMPLATES"]["forgot_password"],
			"CHANGE_PASSWORD_URL" => $arResult["SEF_FOLDER"].$arResult["URL_TEMPLATES"]["change_password"],
			"PROFILE_URL" => $arResult["SEF_FOLDER"],
			"SHOW_ERRORS" => "Y",
		)
	);?>
<?}else{?>
	<?$APPLICATION->IncludeComponent(
	"bitrix:main.profile", 
	"main", 
	array(
		"AJAX_MODE" => "N",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "Y",
		"AJAX_OPTION_HISTORY" => "N",
		"SET_TITLE" => "N",
		"SEND_INFO" => "N",
		"CHECK_RIGHTS" => "N",
		"USER_PROPERTY_NAME" => "",
		"AJAX_OPTION_ADDITIONAL" => "",
		"COMPONENT_TEMPLATE" => "main",
		"USER_PROPERTY" => array(
		),
		"COMPOSITE_FRAME_MODE" => "A",
		"COMPOSITE_FRAME_TYPE" => "AUTO"
	),
	false
);?>
<?}?>