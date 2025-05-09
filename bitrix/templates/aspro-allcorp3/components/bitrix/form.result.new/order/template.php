<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();?>
<?use \Bitrix\Main\Localization\Loc;?>
<div class="form order<?=($arResult['FORM_NOTE'] ? ' success' : '')?><?=($arResult['isFormErrors'] == 'Y' ? ' error' : '')?>">
	<!--noindex-->
	<?if($arResult["isFormErrors"] == "Y"):?>
		<div class="form-error alert alert-danger"><?=$arResult["FORM_ERRORS_TEXT"]?></div>
	<?endif;?>
	<?=$arResult["FORM_HEADER"]?>
	<?=bitrix_sessid_post();?>
		<div class="row">
			<div class="col-md-12 col-sm-12">
				<?if($arResult["isFormDescription"] == "Y"):?>
					<div class="description"><?=$arResult["FORM_DESCRIPTION"]?></div>
				<?endif;?>
			</div>
			<div class="col-md-12 col-sm-12">
				<?if(is_array($arResult["QUESTIONS"])):?>
					<?foreach($arResult["QUESTIONS"] as $FIELD_SID => $arQuestion):?>
						<?if($FIELD_SID == "ORDER_LIST"):?>
							<div class="hidden">
						<?endif;?>
						<?CAllcorp3::drawFormField($FIELD_SID, $arQuestion, 'ORDER', $arParams);?>
						<?if($FIELD_SID == "ORDER_LIST"):?>
							</div>
						<?endif;?>
					<?endforeach;?>
				<?endif;?>
				<?if($arResult["isUseCaptcha"] == "Y"):?>
					<div class="form-control captcha-row clearfix">
						<label class="font_13 color_999"><span><?=GetMessage("FORM_CAPRCHE_TITLE")?>&nbsp;<span class="star">*</span></span></label>
						<div class="captcha_image">
							<img src="/bitrix/tools/captcha.php?captcha_sid=<?=htmlspecialcharsbx($arResult["CAPTCHACode"])?>" border="0" />
							<input type="hidden" name="captcha_sid" value="<?=htmlspecialcharsbx($arResult["CAPTCHACode"])?>" />
							<div class="captcha_reload"></div>
						</div>
						<div class="captcha_input">
							<input type="text" class="inputtext captcha" name="captcha_word" size="30" maxlength="50" value="" required />
						</div>
					</div>
				<?elseif($arParams["HIDDEN_CAPTCHA"] == "Y"):?>
					<textarea name="nspm" style="display:none;"></textarea>
				<?endif;?>
				<div class="form-footer">
					<?if($arParams["SHOW_LICENCE"] == "Y"):?>
						<div class="licence_block form-checkbox">
							<input type="hidden" name="aspro_allcorp3_form_validate">
							<input type="checkbox" class="form-checkbox__input form-checkbox__input--visible" id="licenses_inline_<?=$arResult["arForm"]["ID"];?>" <?=(COption::GetOptionString("aspro.allcorp3", "LICENCE_CHECKED", "N") == "Y" ? "checked" : "");?> name="licenses_popup" required value="Y">
							<label for="licenses_inline_<?=$arResult["arForm"]["ID"];?>" class="form-checkbox__label">
								<span>
									<?include(str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'].SITE_DIR."include/licenses_text.php"));?>
								</span>
								<span class="form-checkbox__box"></span>
							</label>
						</div>
					<?endif;?>
					<p class="">
						<input type="submit" onclick="ym(97297136,'reachGoal','sent_form'); return true;" class="btn btn-default btn-lg" value="<?=$arResult["arForm"]["BUTTON"]?>" name="web_form_submit">
					</p>
					<p>&nbsp;</p>
				</div>
			</div>
		</div>
	<?=$arResult["FORM_FOOTER"]?>
	<!--/noindex-->
	<script type="text/javascript">
	BX.message({
            FORM_FILE_DEFAULT: '<?= Loc::getMessage('FORM_FILE_DEFAULT') ?>',
		});
	$(document).ready(function(){
		if(arAllcorp3Options['THEME']['USE_SALE_GOALS'] !== 'N'){
			var eventdata = {goal: 'goal_order_begin'};
			BX.onCustomEvent('onCounterGoals', [eventdata]);
		}
		var sessionID = '<?=bitrix_sessid()?>';
		$('input[data-sid=SESSION_ID]').val(sessionID);

		$('form[name="<?=$arResult["arForm"]["VARNAME"]?>"]').validate({
			highlight: function( element ){
				$(element).parent().addClass('error');
			},
			unhighlight: function( element ){
				$(element).parent().removeClass('error');
			},
			submitHandler: function( form ){
				if( $('form[name="<?=$arResult["arForm"]["VARNAME"]?>"]').valid() ){
					setTimeout(function() {
						$(form).find('button[type="submit"]').attr("disabled", "disabled");
					}, 300);
					var eventdata = {type: 'form_submit', form: form, form_name: '<?=$arResult["arForm"]["VARNAME"]?>'};
					BX.onCustomEvent('onSubmitForm', [eventdata]);
				}
			},
			errorPlacement: function( error, element ){
				error.insertBefore(element);
			},
			messages:{
				licenses_popup: {
				required : BX.message('JS_REQUIRED_LICENSES')
				}
			}
		});

		
		if(arAllcorp3Options['THEME']['PHONE_MASK'].length){
			var base_mask = arAllcorp3Options['THEME']['PHONE_MASK'].replace( /(\d)/g, '_' );
			$('form[name="<?=$arResult["arForm"]["VARNAME"]?>"] input.phone').inputmask('mask', {'mask': arAllcorp3Options['THEME']['PHONE_MASK'], 'showMaskOnHover': false });
			$('form[name="<?=$arResult["arForm"]["VARNAME"]?>"] input.phone').blur(function(){
				if( $(this).val() == base_mask || $(this).val() == '' ){
					if( $(this).hasClass('required') ){
						$(this).parent().find('div.error').html(BX.message('JS_REQUIRED'));
					}
				}
			});
		}
		
		if(arAllcorp3Options['THEME']['DATE_MASK'].length)
		{
			$('form[name="<?=$arResult["arForm"]["VARNAME"]?>"] input.date').inputmask('datetime', {
				'inputFormat':  arAllcorp3Options['THEME']['DATE_MASK'],
				'placeholder': arAllcorp3Options['THEME']['DATE_PLACEHOLDER'],
				'showMaskOnHover': false
			});
		}

		if(arAllcorp3Options['THEME']['DATETIME_MASK'].length)
		{
			$('form[name="<?=$arResult["arForm"]["VARNAME"]?>"] input.datetime').inputmask('datetime', {
				'inputFormat':  arAllcorp3Options['THEME']['DATETIME_MASK'],
				'placeholder': arAllcorp3Options['THEME']['DATETIME_PLACEHOLDER'],
				'showMaskOnHover': false
			});
		}

		$('.jqmClose').on('click', function(e){
			e.preventDefault();
			$(this).closest('.jqmWindow').jqmHide();
		})

		$('input[type=file]').uniform({fileButtonHtml: BX.message('JS_FILE_BUTTON_NAME'), fileDefaultHtml: BX.message('FORM_FILE_DEFAULT')});
		$(document).on('change', 'input[type=file]', function(){
			if($(this).val())
			{
				$(this).closest('.uploader').addClass('files_add');
			}
			else
			{
				$(this).closest('.uploader').removeClass('files_add');
			}
		})
		$('.form .add_file').on('click', function(){
			var index = $(this).closest('.input').find('input[type=file]').length+1;
			$('<input type="file" id="POPUP_FILE" name="FILE_n'+index+'"   class="inputfile" value="" />').insertBefore($(this));
			$('input[type=file]').uniform({fileButtonHtml: BX.message('JS_FILE_BUTTON_NAME'), fileDefaultHtml: BX.message('FORM_FILE_DEFAULT')});
		})
	});
	</script>
</div>