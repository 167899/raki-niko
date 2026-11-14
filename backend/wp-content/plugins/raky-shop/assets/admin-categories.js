/*
 * Дерево категорій товару: «+ Додати підкатегорію», «× Видалити», олівець для перейменування
 * й перетягування за ☰, щоб змінити порядок. Дані: rakyCategories з includes/admin.php.
 */
jQuery(function ($) {
	var defaultCategory = rakyCategories.defaultCategory;
	var deleteNonce = rakyCategories.deleteNonce;
	var editNonce = rakyCategories.editNonce;
	var iconStyle = 'font-size:16px;width:16px;height:16px;vertical-align:text-bottom;color:#787c82';
	function labelText(row) {
		return row.children('label').first().contents().filter(function () {
			return this.nodeType === 3;
		});
	}
	// Порядок категорій одного рівня зберігається одразу після перетягування.
	function saveOrder(list) {
		var ids = list.children('li').map(function () {
			return $(this).find('> label input').val();
		}).get();
		$.post(ajaxurl, { action: 'raky_order_categories', ids: ids, _ajax_nonce: editNonce }).done(function (response) {
			if (!response.success) window.alert(response.data);
		}).fail(function () {
			window.alert('Не вдалося зберегти порядок категорій. Оновіть сторінку й спробуйте ще раз.');
		});
	}
	function sortable() {
		$('#product_catchecklist').add('#product_catchecklist ul.children').each(function () {
			var list = $(this);
			if (list.data('rakySortable')) return;
			list.data('rakySortable', true).sortable({
				items: '> li',
				handle: '> .raky-drag',
				axis: 'y',
				placeholder: 'raky-drop',
				forcePlaceholderSize: true,
				update: function () {
					saveOrder(list);
				},
			});
		});
	}
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
			$('<span class="raky-drag dashicons dashicons-menu" title="Перетягніть, щоб змінити порядок"></span>')
				.attr('style', iconStyle + ';cursor:move;margin-right:4px')
				.prependTo(row);
			if (id !== defaultCategory) {
				$('<button type="button" class="button-link raky-rename" title="Перейменувати"><span class="dashicons dashicons-edit"></span></button>')
					.data('term', id)
					.attr('style', 'margin-left:4px;text-decoration:none')
					.find('.dashicons').attr('style', iconStyle).end()
					.insertAfter(row.children('label').first());
			}
			var actions = $('<div class="raky-row-actions" style="margin:2px 0 6px 24px;font-size:12px"></div>');
			if (row.parent().is('#product_catchecklist')) {
				$('<button type="button" class="button-link raky-add-sub" style="margin-right:10px;font-size:12px"></button>').text('+ Додати підкатегорію').data('term', id).appendTo(actions);
			}
			if (id !== defaultCategory) {
				$('<button type="button" class="button-link button-link-delete raky-delete" style="font-size:12px"></button>').text('× Видалити').data('term', id).appendTo(actions);
			}
			// Кнопки — окремим рядком під назвою й олівцем.
			(row.children('.raky-rename').first().length ? row.children('.raky-rename').first() : row.children('label').first()).after(actions);
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
	// Олівець: назва перетворюється на поле, Enter зберігає, Esc скасовує.
	$('#product_catchecklist').on('click', '.raky-rename', function () {
		var button = $(this);
		var row = button.closest('li');
		var label = row.children('label').first();
		var text = labelText(row);
		var old = text.text().trim();
		var field = $('<input type="text" class="raky-rename-field" style="width:150px;margin-left:4px">').val(old);
		label.hide();
		button.hide();
		field.insertAfter(label).trigger('focus').trigger('select');
		var done = false;
		function close() {
			done = true;
			field.remove();
			label.show();
			button.show();
		}
		function save() {
			if (done) return;
			var name = field.val().trim();
			if (!name || name === old) return close();
			done = true;
			field.prop('disabled', true);
			$.post(ajaxurl, { action: 'raky_rename_category', term_id: button.data('term'), name: name, _ajax_nonce: editNonce })
				.done(function (response) {
					if (!response.success) {
						window.alert(response.data);
						done = false;
						field.prop('disabled', false).trigger('focus');
						return;
					}
					var fresh = response.data.name;
					text.last()[0].nodeValue = ' ' + fresh;
					$('#newproduct_cat_parent option[value="' + button.data('term') + '"]').text(fresh);
					var popular = $('#product_catchecklist-pop input[value="' + button.data('term') + '"]').parent();
					popular.contents().filter(function () {
						return this.nodeType === 3;
					}).last().each(function () {
						this.nodeValue = ' ' + fresh;
					});
					close();
				})
				.fail(function () {
					window.alert('Не вдалося перейменувати категорію. Спробуйте ще раз.');
					done = false;
					field.prop('disabled', false).trigger('focus');
				});
		}
		field.on('keydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault(); // інакше Enter відправив би форму товару
				save();
			} else if (event.key === 'Escape') {
				close();
			}
		});
		field.on('blur', save);
	});
	$('<style>#product_catchecklist .raky-drop{height:22px;border:1px dashed #c3c4c7;background:#f6f7f7;list-style:none}#product_catchecklist li > label{display:inline}</style>').appendTo('head');
	decorate();
	sortable();
	$(document).ajaxComplete(function () {
		decorate();
		sortable();
	});
});
