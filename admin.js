function TwylahWidgetMenuPreviewUpdate (e){
	var widgetMenu = jQuery(e).parents(".twylah-widget-menu");
	var widgetPreview = widgetMenu.find(".twylah-widget-menu-previews .twylah-widget-preview");
	widgetPreview.each(function(itm){
		type = this.id.split("-");
		if(e.value == type[type.length-2]) jQuery(this).show();
		else jQuery(this).hide();
	});
	
}