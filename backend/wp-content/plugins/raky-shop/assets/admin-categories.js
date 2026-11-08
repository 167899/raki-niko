/* Кнопки «+ Додати підкатегорію» й «× Видалити» в дереві категорій товару. Дані: rakyCategories з includes/admin.php. */
jQuery(function ($) {
	var defaultCategory = rakyCategories.defaultCategory;
	var deleteNonce = rakyCategories.deleteNonce;
	// Після додавання WordPress дублює батьківську категорію зверху: переносимо нову підкатегорію в наявну.
	function mergeDuplicates() {
		var rows = {};
		$('#product_catchecklist > li').get().reverse().forEach(function (element) {
			var row = $(element);
			var id = row.find('> label input').val();
			if (!rows[id]) {
				rows[id] = row;
				return;
			}
			var list = rows[id].children('ul.children');
			if (!list.length) list = $('<ul class="children"></ul>').appendTo(rows[id]);
			row.children('ul.children').children('li').each(function () {
				var value = $(this).find('> label input').val();
				if (!list.find('> li > label input[value="' + value + '"]').length) $(this).appendTo(list);
			});
			row.remove();
		});
	}
	function decorate() {
		mergeDuplicates();
		$('#product_catchecklist li').each(function () {
			var row = $(this);
			if (row.children('.raky-row-actions').length) return;
			var id = row.find('> label input').val();
			var actions = $('<div class="raky-row-actions" style="margin:2px 0 6px 24px;font-size:12px"></div>');
			if (row.parent().is('#product_catchecklist')) {
				$('<button type="button" class="button-link raky-add-sub" style="margin-right:10px;font-size:12px"></button>').text('+ Додати підкатегорію').data('term', id).appendTo(actions);
			}
			if (id !== defaultCategory) {
				$('<button type="button" class="button-link button-link-delete raky-delete" style="font-size:12px"></button>').text('× Видалити').data('term', id).appendTo(actions);
			}
			row.children('label').first().after(actions);
		});
	}
	function defaultRow() {
		return $('#product_catchecklist > li').filter(function () {
			return $(this).find('> label input').val() === defaultCategory;
		});
	}
	$('#product_catchecklist').on('click', '.raky-add-sub', function () {
		if ($('#product_cat-adder').hasClass('wp-hidden-children')) $('#product_cat-add-toggle').trigger('click');
		$('#newproduct_cat_parent').val($(this).data('term'));
		$('#newproduct_cat').trigger('focus');
	});
	$('#product_catchecklist').on('click', '.raky-delete', function () {
		var button = $(this);
		var row = button.closest('li');
		var name = row.find('> label').text().trim();
		var question = 'Видалити «' + name + '»? Підкатегорії й товари перейдуть у «Без категорії».';
		if (!window.confirm(question)) return;
		button.prop('disabled', true);
		$.post(ajaxurl, { action: 'raky_delete_category', term_id: button.data('term'), _ajax_nonce: deleteNonce })
			.done(function (response) {
				if (!response.success) {
					button.prop('disabled', false);
					window.alert(response.data);
					return;
				}
				var target = defaultRow();
				var list = target.children('ul.children');
				if (!list.length) list = $('<ul class="children"></ul>').appendTo(target);
				row.children('ul.children').children('li').appendTo(list);
				if (row.find('> label input').prop('checked')) target.find('> label input').prop('checked', true);
				$('#newproduct_cat_parent option[value="' + button.data('term') + '"]').remove();
				$('#product_catchecklist-pop input[value="' + button.data('term') + '"]').closest('li').remove();
				row.remove();
			})
			.fail(function () {
				button.prop('disabled', false);
				window.alert('Не вдалося видалити категорію. Спробуйте ще раз.');
			});
	});
	decorate();
	$(document).ajaxComplete(decorate);
});
