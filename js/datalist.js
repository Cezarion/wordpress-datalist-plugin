(function($) {
	var app =
	{
		init: function()
		{
			var searchInput 	= 	$("#node-search"),
				btnClear 		= 	$('#node-clear'),
				json_url 		= 	searchInput.attr('data-src'),
				tableData 		= 	$('#datalist');


			btnClear.on('click' , function(){
				tableData.find('tr').removeClass('found');
				searchInput.val('');
			});

			searchInput.autocomplete(
			{
				source: function(request, response) {
					var matcher = new RegExp($.ui.autocomplete.escapeRegex(request.term), "i");
					$.ajax(
					{
						url: json_url,
						dataType: "json",
						data: { term: request.term },
						success: function(data)
						{
							response($.map(data.contents, function(v, i) {
								var text = v.nom;
								if (text && (!request.term || matcher.test(text))) {
									return {
										label: v.nom+ ' ' +v.prenom+' '+v.ville,
										value: v.nom+ ' ' +v.prenom+' '+v.ville,
										index: i
									};
								}
							}));
						}
					});
				},
				minLength: 3,
				select: function(event, ui) {
					var index = ui.item.index + 1; // html start from 0
					btnClear.trigger('click');
					tableData.find('tr').eq(index).addClass('found');
					searchInput.val( ui.item.label );
				}
			});
		}
	};

	app.init();

})(jQuery);