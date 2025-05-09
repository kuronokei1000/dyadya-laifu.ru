<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
$this->setFrameMode(true);
?>
<div id="isotope" class="am-container isotope">
<?foreach($arResult["ITEMS"] as $arItem):?>
	<?
	$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
	$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
	?>
	<?//if($arParams["DISPLAY_PICTURE"]!="N" && is_array($arItem["PREVIEW_PICTURE"])):?>

        <?
            foreach ($arItem['PROPERTIES']['MORE_PHOTO']['VALUE'] as $arPhoto):
            $img = CFile::ResizeImageGet($arPhoto, array('width'=>500, 'height'=>500), BX_RESIZE_IMAGE_PROPORTIONAL, true, false, false, 100);
             ?>
            <a class="isotope-item fancy" data-fancybox="gallery" href="<?=CFILE::GetPath($arPhoto);?>" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
                <img src="<?=$img['src'];?>" alt="<?=$arItem["PREVIEW_PICTURE"]["ALT"]?>">
            </a>
        <? endforeach; ?>



	<?//endif?>
<?endforeach;?>
</div>
<style>
    .am-container.isotope {
        margin: 0 auto;
    }
    .am-container .isotope-item {
        /*margin: 0 5px 10px 5px;*/
        margin-bottom: 20px;
    }

    /*.am-wrapper{
        float:left;
        position:relative;
        overflow:hidden;
    }
    .am-wrapper img{
        position:absolute;
        outline:none;
    }*/
</style>
<script>
    /*
(function(window,$,undefined){Array.max=function(array){return Math.max.apply(Math,array)};Array.min=function(array){return Math.min.apply(Math,array)};var $event=$.event,resizeTimeout;$event.special.smartresize={setup:function(){$(this).bind("resize",$event.special.smartresize.handler)},teardown:function(){$(this).unbind("resize",$event.special.smartresize.handler)},handler:function(event,execAsap){var context=this,args=arguments;event.type="smartresize";if(resizeTimeout){clearTimeout(resizeTimeout)}resizeTimeout=setTimeout(function(){jQuery.event.handle.apply(context,args)},execAsap==="execAsap"?0:50)}};$.fn.smartresize=function(fn){return fn?this.bind("smartresize",fn):this.trigger("smartresize",["execAsap"])};$.fn.imagesLoaded=function(callback){var $images=this.find('img'),len=$images.length,_this=this,blank='data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';function triggerCallback(){callback.call(_this,$images)}function imgLoaded(){if(--len<=0&&this.src!==blank){setTimeout(triggerCallback);$images.unbind('load error',imgLoaded)}}if(!len){triggerCallback()}$images.bind('load error',imgLoaded).each(function(){if(this.complete||this.complete===undefined){var src=this.src;this.src=blank;this.src=src}});return this};$.Montage=function(options,element){this.element=$(element).show();this.cache={};this.heights=new Array();this._create(options)};$.Montage.defaults={liquid:true,margin:1,minw:70,minh:20,maxh:250,alternateHeight:false,alternateHeightRange:{min:100,max:300},fixedHeight:null,minsize:false,fillLastRow:false};$.Montage.prototype={_getImageWidth:function($img,h){var i_w=$img.width(),i_h=$img.height(),r_i=i_h/i_w;return Math.ceil(h/r_i)},_getImageHeight:function($img,w){var i_w=$img.width(),i_h=$img.height(),r_i=i_h/i_w;return Math.ceil(r_i*w)},_chooseHeight:function(){if(this.options.minsize){return Array.min(this.heights)}var result={},max=0,res,val,min;for(var i=0,total=this.heights.length;i<total;++i){var val=this.heights[i],inc=(result[val]||0)+1;if(val<this.options.minh||val>this.options.maxh)continue;result[val]=inc;if(inc>=max){max=inc;res=val}}for(var i in result){if(result[i]===max){val=i;min=min||val;if(min<this.options.minh)min=null;else if(min>val)min=val;if(min===null)min=val}}if(min===undefined)min=this.heights[0];res=min;return res},_stretchImage:function($img){var prevWrapper_w=$img.parent().width(),new_w=prevWrapper_w+this.cache.space_w_left,crop={x:new_w,y:this.theHeight};var new_image_w=$img.width()+this.cache.space_w_left,new_image_h=this._getImageHeight($img,new_image_w);this._cropImage($img,new_image_w,new_image_h,crop);this.cache.space_w_left=this.cache.container_w;if(this.options.alternateHeight)this.theHeight=Math.floor(Math.random()*(this.options.alternateHeightRange.max-this.options.alternateHeightRange.min+1)+this.options.alternateHeightRange.min)},_updatePrevImage:function($nextimg){var $prevImage=this.element.find('img.montage:last');this._stretchImage($prevImage);this._insertImage($nextimg)},_insertImage:function($img){var new_w=this._getImageWidth($img,this.theHeight);if(this.options.minsize&&!this.options.alternateHeight){if(this.cache.space_w_left<=this.options.margin*2){this._updatePrevImage($img)}else{if(new_w>this.cache.space_w_left){var crop={x:this.cache.space_w_left,y:this.theHeight};this._cropImage($img,new_w,this.theHeight,crop);this.cache.space_w_left=this.cache.container_w;$img.addClass('montage')}else{var crop={x:new_w,y:this.theHeight};this._cropImage($img,new_w,this.theHeight,crop);this.cache.space_w_left-=new_w;$img.addClass('montage')}}}else{if(new_w<this.options.minw){if(this.options.minw>this.cache.space_w_left){this._updatePrevImage($img)}else{var new_w=this.options.minw,new_h=this._getImageHeight($img,new_w),crop={x:new_w,y:this.theHeight};this._cropImage($img,new_w,new_h,crop);this.cache.space_w_left-=new_w;$img.addClass('montage')}}else{if(new_w>this.cache.space_w_left&&this.cache.space_w_left<this.options.minw){this._updatePrevImage($img)}else if(new_w>this.cache.space_w_left&&this.cache.space_w_left>=this.options.minw){var crop={x:this.cache.space_w_left,y:this.theHeight};this._cropImage($img,new_w,this.theHeight,crop);this.cache.space_w_left=this.cache.container_w;if(this.options.alternateHeight)this.theHeight=Math.floor(Math.random()*(this.options.alternateHeightRange.max-this.options.alternateHeightRange.min+1)+this.options.alternateHeightRange.min);$img.addClass('montage')}else{var crop={x:new_w,y:this.theHeight};this._cropImage($img,new_w,this.theHeight,crop);this.cache.space_w_left-=new_w;$img.addClass('montage')}}}},_cropImage:function($img,w,h,cropParam){var dec=this.options.margin*2;var $wrapper=$img.parent('a');this._resizeImage($img,w,h);$img.css({left:-(w-cropParam.x)/2+'px',top:-(h-cropParam.y)/2+'px'});$wrapper.addClass('am-wrapper').css({width:cropParam.x-dec+'px',height:cropParam.y+'px',margin:this.options.margin})},_resizeImage:function($img,w,h){$img.css({width:w+'px',height:h+'px'})},_reload:function(){var new_el_w=this.element.width();if(new_el_w!==this.cache.container_w){this.element.hide();this.cache.container_w=new_el_w;this.cache.space_w_left=new_el_w;var instance=this;instance.$imgs.removeClass('montage').each(function(i){instance._insertImage($(this))});if(instance.options.fillLastRow&&instance.cache.space_w_left!==instance.cache.container_w){instance._stretchImage(instance.$imgs.eq(instance.totalImages-1))}instance.element.show()}},_create:function(options){this.options=$.extend(true,{},$.Montage.defaults,options);var instance=this,el_w=instance.element.width();instance.$imgs=instance.element.find('img');instance.totalImages=instance.$imgs.length;if(instance.options.liquid)$('html').css('overflow-y','scroll');if(!instance.options.fixedHeight){instance.$imgs.each(function(i){var $img=$(this),img_w=$img.width();if(img_w<instance.options.minw&&!instance.options.minsize){var new_h=instance._getImageHeight($img,instance.options.minw);instance.heights.push(new_h)}else{instance.heights.push($img.height())}})}instance.theHeight=(!instance.options.fixedHeight&&!instance.options.alternateHeight)?instance._chooseHeight():instance.options.fixedHeight;if(instance.options.alternateHeight)instance.theHeight=Math.floor(Math.random()*(instance.options.alternateHeightRange.max-instance.options.alternateHeightRange.min+1)+instance.options.alternateHeightRange.min);instance.cache.container_w=el_w;instance.cache.space_w_left=el_w;instance.$imgs.each(function(i){instance._insertImage($(this))});if(instance.options.fillLastRow&&instance.cache.space_w_left!==instance.cache.container_w){instance._stretchImage(instance.$imgs.eq(instance.totalImages-1))}$(window).bind('smartresize.montage',function(){instance._reload()})},add:function($images,callback){var $images_stripped=$images.find('img');this.$imgs=this.$imgs.add($images_stripped);this.totalImages=this.$imgs.length;this._add($images,callback)},_add:function($images,callback){var instance=this;$images.find('img').each(function(i){instance._insertImage($(this))});if(instance.options.fillLastRow&&instance.cache.space_w_left!==instance.cache.container_w)instance._stretchImage(instance.$imgs.eq(instance.totalImages-1));if(callback)callback.call($images)},destroy:function(callback){this._destroy(callback)},_destroy:function(callback){this.$imgs.removeClass('montage').css({position:'',width:'',height:'',left:'',top:''}).unwrap();if(this.options.liquid)$('html').css('overflow','');this.element.unbind('.montage').removeData('montage');$(window).unbind('.montage');if(callback)callback.call()},option:function(key,value){if($.isPlainObject(key)){this.options=$.extend(true,this.options,key)}}};var logError=function(message){if(this.console){console.error(message)}};$.fn.montage=function(options){if(typeof options==='string'){var args=Array.prototype.slice.call(arguments,1);this.each(function(){var instance=$.data(this,'montage');if(!instance){logError("cannot call methods on montage prior to initialization; "+"attempted to call method '"+options+"'");return}if(!$.isFunction(instance[options])||options.charAt(0)==="_"){logError("no such method '"+options+"' for montage instance");return}instance[options].apply(instance,args)})}else{this.each(function(){var instance=$.data(this,'montage');if(instance){instance.option(options||{});instance._reload()}else{$.data(this,'montage',new $.Montage(options,this))}})}return this}})(window,jQuery);
console.log('!!!', $)
$(function() {
$('.am-container').montage({
liquid: false,
fillLastRow: true,
margin: 4,
fixedHeight: 160
});
});*/
</script>
