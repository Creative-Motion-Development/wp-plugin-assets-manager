(function($) {
	class cEditorCondition {
		constructor(editor, group, options) {
			this.editor = editor;
			this.group = group;
			this.element = editor.element;

			this.options = $.extend({}, {
				index: null,
				operator: 'equals'
			}, options);

			this._index = this.options.index;
			this._conditionElement = this._createMarkup();

			this._hintElement = this._conditionElement.find(".wam-cleditor__hint");
			this._hintContentElement = this._conditionElement.find(".wam-cleditor__hint-content");

			this._prepareFields(true);
			this._register_events()
		}

		getData() {
			var paramOptions = this._getParamOptions(),
				currentParam = this._conditionElement.find(".wam-cleditor__param-select").val(),
				$operator = this._conditionElement.find(".wam-cleditor__operator-select"),
				currentOperator = $operator.val();

			var value = null;

			if( 'select' === paramOptions['type'] ) {
				value = this._getSelectValue(paramOptions);
			} else if( 'integer' === paramOptions['type'] ) {
				value = this._getIntegerValue(paramOptions);
			} else {
				value = this._getTextValue(paramOptions);
			}

			return {
				param: currentParam,
				operator: currentOperator,
				type: paramOptions['type'],
				value: value
			};
		}

		_createMarkup() {
			var conditionTmpl = this.editor.getTemplate(".wam-cleditor__condition");
			this.group.groupElement.find(".wam-cleditor__conditions").append(conditionTmpl);
			return conditionTmpl;
		}

		_remove() {
			this.group.removeCondition(this._index);

			this._conditionElement.remove();

			this.group.groupElement.trigger('winp.conditions-changed');
			this.element.trigger('wam.editor-updated');
		}

		_register_events() {
			var self = this;

			this._conditionElement.find(".wam-cleditor__param-select").change(function() {
				self._prepareFields();
				self.element.trigger('wam.editor-updated');
			});

			this._conditionElement.find(".wam-cleditor__operator-select").change(function() {
				self.element.trigger('wam.editor-updated');
			});

			this._conditionElement.find(".wam-cleditor__condition-value").on('change keyup', function() {
				self.element.trigger('wam.editor-updated');
			})

			// buttons
			this._conditionElement.find(".js-wam-cleditor__condition-remove").click(function() {
				self._remove();
				return false;
			});

			this._conditionElement.find(".js-wam-cleditor__condition-add-and").click(function() {
				self.group.addCondition();
				return false;
			});
		}

		_prepareFields(isInit) {
			if( isInit && this.options.param ) {
				this._selectParam(this.options.param);
			}

			var paramOptions = this._getParamOptions();

			this._setParamHint(paramOptions.description);

			var operators = [];

			if( 'select' === paramOptions['type'] || paramOptions['onlyEquals'] ) {
				operators = ['equals', 'notequal'];
			} else if( 'date' === paramOptions['type'] ) {
				operators = ['equals', 'notequal', 'younger', 'older', 'between'];
			} else if( 'date-between' === paramOptions['type'] ) {
				operators = ['between'];
			} else if( 'integer' === paramOptions['type'] ) {
				operators = ['equals', 'notequal', 'less', 'greater', 'between'];
			} else {
				operators = ['equals', 'notequal', 'contains', 'notcontain'];
			}

			this._setOperators(operators);

			if( isInit && this.options.operator ) {
				this._selectOperator(this.options.operator);
			} else {
				this._selectFirstOperator();
			}

			this._createValueControl(paramOptions, isInit);
		}

		/**
		 * Displays and configures the param hint.
		 */
		_setParamHint(description) {

			if( description ) {
				this._hintContentElement.html(description);
				this._hintElement.show();
			} else {
				this._hintElement.hide();
			}
		}

		/**
		 * Creates control to specify value.
		 */
		_createValueControl(paramOptions, isInit) {
			if( 'select' === paramOptions['type'] ) {
				this._createValueAsSelect(paramOptions, isInit);
			} else if( 'integer' === paramOptions['type'] ) {
				this._createValueAsInteger(paramOptions, isInit);
			} else {
				this._createValueAsText(paramOptions, isInit);
			}
		}

		// -------------------
		// Select Control
		// -------------------

		/**
		 * Creates the Select control.
		 */
		_createValueAsSelect(paramOptions, isInit) {
			var self = this;

			let createSelectField = function(values) {
				var $select = self._createSelect(values);
				self._insertValueControl($select);
				if( isInit && self.options.value ) {
					self._setSelectValue(self.options.value);
				}
				self._conditionElement.find(".wam-cleditor__condition-value").trigger("insert.select");
			};

			if( !paramOptions['values'] ) {
				return;
			}
			if( 'ajax' === paramOptions['values']['type'] ) {

				var $fakeSelect = self._createSelect([
					{
						value: null,
						title: '- loading -'
					}
				]);
				self._insertValueControl($fakeSelect);

				$fakeSelect.attr('disabled', 'disabled');
				$fakeSelect.addClass('wam-cleditor__fake-select');

				if( isInit && this.options.value ) {
					$fakeSelect.data('value', this.options.value);
				}

				var req = $.ajax({
					url: window.ajaxurl,
					method: 'post',
					data: {
						action: paramOptions['values']['action']
					},
					dataType: 'json',
					success: function(data) {
						createSelectField(data.values);
					},
					error: function() {
						console.log('Unexpected error during the ajax request.');
					},
					complete: function() {
						if( $fakeSelect ) {
							$fakeSelect.remove();
						}
						$fakeSelect = null;
					}
				});
			} else {
				createSelectField(paramOptions['values']);
			}
		}

		/**
		 * Returns a value for the select control.
		 */
		_getSelectValue() {
			var $select = this._conditionElement.find(".wam-cleditor__condition-value select");

			var value = $select.val();
			if( !value ) {
				value = $select.data('value');
			}
			return value;
		}

		/**
		 * Sets a select value.
		 */
		_setSelectValue(value) {
			var $select = this._conditionElement.find(".wam-cleditor__condition-value select");

			if( $select.hasClass('.wam-cleditor__fake-select') ) {
				$select.data('value', value);
			} else {
				$select.val(value);
			}
		}

		// -------------------
		// Integer Control
		// -------------------

		/**
		 * Creates a control for the input linked with the integer.
		 */
		_createValueAsInteger(paramOptions, isInit) {
			var self = this;

			var $operator = this._conditionElement.find(".wam-cleditor__operator-select");

			$operator.on('change', function() {
				var currentOperator = $operator.val();

				var $control;
				if( 'between' === currentOperator ) {
					$control = $("<span><input type='text' class='wam-cleditor__integer-start' /> and <input type='text' class='wam-cleditor__integer-end' /></span>");
				} else {
					$control = $("<input type='text' class='wam-cleditor__integer-solo' /></span>");
				}

				self._insertValueControl($control);
			});

			$operator.change();
			if( isInit && this.options.value ) {
				this._setIntegerValue(this.options.value);
			}
		}

		/**
		 * Returns a value for the Integer control.
		 */
		_getIntegerValue() {
			var value = {};

			var $operator = this._conditionElement.find(".wam-cleditor__operator-select");
			var currentOperator = $operator.val();

			if( 'between' === currentOperator ) {
				value.range = true;
				value.start = this._conditionElement.find(".wam-cleditor__integer-start").val();
				value.end = this._conditionElement.find(".wam-cleditor__integer-end").val();

			} else {
				value = this._conditionElement.find(".wam-cleditor__integer-solo").val();
			}

			return value;
		}

		/**
		 * Sets a value for the Integer control.
		 */
		_setIntegerValue(value) {
			if( !value ) {
				value = {};
			}

			if( value.range ) {
				this._conditionElement.find(".wam-cleditor__integer-start").val(value.start);
				this._conditionElement.find(".wam-cleditor__integer-end").val(value.end);
			} else {
				this._conditionElement.find(".wam-cleditor__integer-solo").val(value);
			}
		}

		// -------------------
		// Text Control
		// -------------------

		/**
		 * Creates a control for the input linked with the integer.
		 */
		_createValueAsText(paramOptions, isInit) {

			var $control = $("<input type='text' class='wam-cleditor__text' /></span>");
			this._insertValueControl($control);
			if( isInit && this.options.value ) {
				this._setTextValue(this.options.value);
			}
		}

		/**
		 * Returns a value for the Text control.
		 * @returns {undefined}
		 */
		_getTextValue() {
			return this._conditionElement.find(".wam-cleditor__text").val();
		}

		/**
		 * Sets a value for the Text control.
		 */
		_setTextValue(value) {
			this._conditionElement.find(".wam-cleditor__text").val(value);
		}

		// -------------------
		// Helper Methods
		// -------------------

		_selectParam(value) {
			this._conditionElement.find(".wam-cleditor__param-select").val(value);
		}

		_selectOperator(value) {
			this._conditionElement.find(".wam-cleditor__operator-select").val(value);
		}

		_selectFirstOperator() {
			this._conditionElement.find(".wam-cleditor__operator-select").prop('selectedIndex', 0);
		}

		_setOperators(values) {
			var $operator = this._conditionElement.find(".wam-cleditor__operator-select");
			$operator.show().off('change');

			$operator.find("option").hide();
			for( var index in values ) {
				if( !values.hasOwnProperty(index) ) {
					continue;
				}
				$operator.find("option[value='" + values[index] + "']").show();
			}
			var value = $operator.find("option:not(:hidden):eq(0)").val();
			$operator.val(value);
		}

		_insertValueControl($control) {
			this._conditionElement.find(".wam-cleditor__condition-value").html("").append($control);
		}

		_getParamOptions() {
			var selectElement = this._conditionElement.find(".wam-cleditor__param-select"),
				optionElement = selectElement.find('option:selected');

			if( !selectElement.length ) {
				return false;
			}

			return {
				id: selectElement.val(),
				title: optionElement.text().trim(),
				type: optionElement.data('type'),
				values: optionElement.data('params'),
				description: optionElement.data('hint').trim()
			};
		}

		_createSelect(values, attrs) {
			var $select = $("<select></select>");
			if( attrs ) {
				$select.attr(attrs);
			}

			for( var index in values ) {
				if( !values.hasOwnProperty(index) ) {
					continue;
				}
				var item = values[index];
				var $option = '';

				if( typeof index === "string" && isNaN(index) === true ) {
					var $optgroup = $("<optgroup></optgroup>").attr('label', index);

					for( var subindex in item ) {
						if( !item.hasOwnProperty(subindex) ) {
							continue;
						}
						var subvalue = item[subindex];
						$option = $("<option></option>").attr('value', subvalue['value']).text(subvalue['title']);
						$optgroup.append($option);
					}
					$select.append($optgroup);
				} else {
					$option = $("<option></option>").attr('value', item['value']).text(item['title']);
					$select.append($option);
				}
			}

			return $select;
		}
	}

	class cEditorGroup {
		constructor(editor, options) {
			this.editor = editor;
			this.element = editor.element;

			this.options = $.extend({}, {
				conditions: null,
				index: null
			}, options);
			this._index = this.options.index;

			this.conditions = {};

			this.groupElement = this._createMarkup();

			this._conditionsCounter = 0;

			this._load();
		}

		getData() {
			var condtions = [];

			for( var ID in this.conditions ) {
				if( !this.conditions.hasOwnProperty(ID) ) {
					continue;
				}

				condtions.push(this.conditions[ID].getData());
			}

			return {
				type: 'group',
				conditions: condtions
			};
		}

		getCountConditions() {
			return Object.keys(this.conditions).length;
		}

		removeCondition(ID) {
			if( this.conditions[ID] ) {
				delete this.conditions[ID];
			}
		}

		_createMarkup() {
			var $group = this.editor.getTemplate('.wam-cleditor__group');
			this.element.find(".wam-cleditor__groups").append($group);

			console.log(this._index);

			if( this._index <= 1 ) {
				$group.find('.wam-cleditor__group-type').hide();
				$group.find('.js-wam-cleditor__remove-group').remove();
			} else {
				$group.find('.wam-cleditor__group-type').show();
				$group.find('.wam-cleditor__first-group-title').remove();
			}

			return $group;
		}

		_registerEvents() {
			var self = this;

			this.groupElement.find(".js-wam-cleditor__add-condition").click(function() {
				self.addCondition();
				return false;
			});

			this.groupElement.find(".js-wam-cleditor__remove-group").click(function() {
				self._remove();
				return false;
			});

			this.groupElement.on('winp.conditions-changed', function() {
				self._checkIsEmpty();
			});
		}

		_load() {
			if( !this.options.conditions ) {
				this.addCondition();
			} else {
				this._setGroupData();
			}

			this._registerEvents();
		}

		_remove() {
			this.editor.removeGroup(this._index);
			this.groupElement.remove();

			this.element.trigger('wam.filters-changed');
			this.element.trigger('wam.editor-updated');
		}

		_setGroupData() {
			this.groupElement.find('.wam-cleditor__condition').remove();

			if( this.options.conditions ) {
				for( var index in this.options.conditions ) {
					if( !this.options.conditions.hasOwnProperty(index) ) {
						continue;
					}

					this.addCondition(this.options.conditions[index]);
				}
			}

			this._checkIsEmpty();
		}

		addCondition(data) {
			if( !data ) {
				data = {type: 'condition'};
			}

			this._conditionsCounter = this._conditionsCounter + 1;
			data.index = this._index + '_' + this._conditionsCounter;

			this.conditions[data.index] = new cEditorCondition(this.editor, this, data);

			this.groupElement.trigger('winp.conditions-changed');
			this.element.trigger('wam.editor-updated');
		}

		_checkIsEmpty() {
			if( this.getCountConditions() === 0 ) {
				this.groupElement.addClass('wam-cleditor__empty');
			} else {
				this.groupElement.removeClass('wam-cleditor__empty');
			}
		}
	}

	class cEditor {
		constructor(element, options) {
			this.element = element;

			this.options = $.extend({}, {
				groups: null,
				defaultGroups: null,
				onChange: null
			}, options);

			this.groups = {};
			this.groupsCounter = 0;

			this.element = this._createMarkup();

			this._load();
		}

		getData() {
			var self = this;
			var groups = [];

			for( var ID in self.groups ) {
				if( !self.groups.hasOwnProperty(ID) ) {
					continue;
				}

				groups.push(self.groups[ID].getData());
			}

			return groups;
		}

		getTemplate(selector) {
			let tmpl = $($(this.options.template).html());
			return tmpl.find(selector).clone();
		}

		getCountGroups() {
			return Object.keys(this.groups).length;
		}

		removeGroup(ID) {
			if( this.groups[ID] ) {
				delete this.groups[ID];
			}
		}

		destroy() {
			this.element.remove();
		}

		_registerEvents() {
			var self = this;

			this.element.on('wam.editor-updated', function() {
				if( self.options.onChange ) {
					var data = self.getData();
					self.options.onChange(self.element, data);
				}
			});

			this.element.on('wam.filters-changed', function() {
				self._checkIsEmpty();
			});

			this.element.find(".js-wam-cleditor__add-group").click(function() {
				self._addGroup();
				return false;
			});
		}

		_createMarkup() {
			var $editor = $('<div></div>').addClass('wam-cleditor');
			this.element.prepend($editor);

			$editor.append(this.getTemplate('.wam-cleditor__wrap'));
			$editor.append(this.getTemplate('.wam-cleditor__buttons-group'));

			return $editor;
		}

		_load() {
			var groups;

			if( this.options.groups && this.options.groups.length > 0 ) {
				groups = this.options.groups;
			} else if( this.options.defaultGroups && this.options.defaultGroups.length > 0 ) {
				groups = this.options.defaultGroups;
			}

			if( groups ) {
				for( var index in groups ) {
					if( !groups.hasOwnProperty(index) ) {
						continue;
					}

					this._addGroup(groups[index]);
				}
			}

			this._checkIsEmpty();
			this._registerEvents();
		}

		_addGroup(data) {
			if( !data ) {
				data = {type: 'group'};
			}

			this.groupsCounter = this.groupsCounter + 1;

			this.groups[this.groupsCounter] = new cEditorGroup(this, {
				index: this.groupsCounter,
				conditions: data.conditions
			});

			this.element.trigger('wam.editor-updated');
			this.element.trigger('wam.filters-changed');
		}

		_checkIsEmpty() {
			if( this.getCountGroups() === 0 ) {
				this.element.addClass('wam-cleditor__empty');
			} else {
				this.element.removeClass('wam-cleditor__empty');
			}
		}
	}

	$.fn.wamConditionsEditor = function(options) {
		return this.each(function() {
			new cEditor($(this), options);
		});
	};

})(jQuery);