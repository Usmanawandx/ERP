<script type="text/javascript">
	$(document).ready( function() {
	    
	    
	    __page_leave_confirmation('#production_form,#production_form_new')
	    
	    
        jQuery.validator.addMethod("notEmpty", function(value, element, param) {
            return __number_uf(value) > 0
        }, "{{__('manufacturing::lang.quantity_greater_than_zero')}}");

        jQuery.validator.addMethod("notEqualToWastedQuantity", function(value, element, param) {
            var waste_qty = __read_number($('#mfg_wasted_units'));
            var qty = __number_uf(value);
            return qty > waste_qty;
        }, "{{__('manufacturing::lang.waste_qty_less_than_qty')}}");

		$('#transaction_date').datetimepicker({
	        format: moment_date_format,
	        ignoreReadonly: true,
	    });

	    $('#exp_date').datepicker({
            autoclose: true,
            format: datepicker_date_format,
        });

	    production_form_validator = $('#production_form').validate();
	});
	
	$(document).on('change','.total_quantities', function(){
	    var sell_price_inc_tax = $(this).closest('tr').find('.sell_price_inc_tax').val();
	    var total_quantities = $(this).closest('tr').find('.total_quantities').val();
	    var hidden_total = parseFloat(sell_price_inc_tax) * parseFloat(total_quantities);
	    $(this).closest('tr').find('.new_total_price').val(hidden_total);

        // var final_total = $('#final_total').val();
        // var recipe_quantity = $('#recipe_quantity').val();
        // total__cost = (final_total / recipe_quantity);
        // $('#total__cost').val(total__cost);
        // $('.ing_quantity').trigger('change');
	})

    $(document).on('change dp.change','#transaction_date, #variation_id, .search_product', function(){
        $('.update-btn').attr('disabled',true);
		setTimeout(() => {
			$('#ingredients_for_unit_recipe_table tbody tr, .ingredients_for_unit_recipe_table tbody tr').each(function (index, element) {
				let product_id = $(element).find('.product_ing').val();
				let dateString = $('#transaction_date').val();
				let date = moment(dateString, 'DD-MM-YYYY').format('YYYY-MM-DD HH:mm:ss');
				$.ajax({
					url: '/reports/getValuationByDate',
					type: 'GET',
					data:{ product_id: product_id, date: date },
					success: function(response) {
						var avg_rate = $(element).find('.row_avg_rate').text(__currency_trans_from_en(response, false));
                        $(element).find('.ingredient_price').val(response);
						$(element).find('.total_quantities').trigger('change');
						setTimeout(() => {
                            $('.update-btn').attr('disabled',false);
                        }, 2000);
					}
				});
			});
            $('.update-btn').attr('disabled',false);
		}, 2000);
	});

    $(document).on('change', '.ing_quantity',function(){
        $('#recipe_quantity').trigger('change');
    })
	
	$(document).on('change', '#production_form #variation_id, #production_form #location_id', function () {
	    
		var variation_id = $("#variation_id").val();
		var location_id = $("#location_id").val();
		
// 		variation_id && 
		if(location_id) {
			$.ajax({
	            url: "/manufacturing/get-recipe-details?variation_id=" + variation_id + "&location_id=" + location_id,
	            dataType: 'json',
	            success: function(result) {
	                $('#enter_ingredients_table').html(result.ingredient_table);
	                
	                $('#enter_ingredients_table').find('.parent_id').val(variation_id);
	                
	                
	                
	                
	                if (result.is_sub_unit) {
	                	$('#recipe_quantity_input').removeClass('input-group');
	                	$('#recipe_quantity_input').addClass('input_inline');
	                	$('#unit_html').removeClass('input-group-addon');
	                } else {
	                	$('#recipe_quantity_input').addClass('input-group');
	                	$('#recipe_quantity_input').removeClass('input_inline');
	                	$('#unit_html').addClass('input-group-addon');
	                }
	                __write_number($('#recipe_quantity'), result.recipe.total_quantity);
	                $('#unit_html').html(result.unit_html);
	                $('#wasted_units_text').text(result.unit_name);

                    var mfg_wasted_units = __calculate_amount('percentage', $('#waste_percent').val(), result.recipe.total_quantity);
                    __write_number($('#mfg_wasted_units'), mfg_wasted_units);
                    __write_number($('#production_cost'), result.recipe.extra_cost);
                    $('#mfg_production_cost_type').val(result.recipe.production_cost_type);

	                __currency_convert_recursively($('#enter_ingredients_table'));
                    calculateRecipeTotal();
	            },
	        });
		} else {
			$('#enter_ingredients_table').html('');
	        calculateRecipeTotal();
		} 
	});

	$(document).on('change', '#production_cost, select.sub_unit, input.mfg_waste_percent, #mfg_production_cost_type', function(){
        var id = $(this).closest('table').attr('id');
		calculateRecipeTotal(id);
	});

    $(document).on('change', '#recipe_quantity, #sub_unit_id', function(){
        // debugger;
        var recipe_quantity = __read_number($('#recipe_quantity'));
        

        var mfg_wasted_units = __calculate_amount('percentage', $('#waste_percent').val(), recipe_quantity);
        __write_number($('#mfg_wasted_units'), mfg_wasted_units);

        var multiplier = 1;
        if ($('#sub_unit_id').length) {
            multiplier = parseFloat(
            $('#sub_unit_id')
                .find(':selected')
                .data('multiplier')
            );
            recipe_quantity = recipe_quantity; 
        }
        $('#ingredients_for_unit_recipe_table tbody tr').each( function() {
            var line_unit_quantity = parseFloat($(this).find('.total_quantities').val());
            var hidden_qnty = parseFloat($(this).find('.hidden_qnty').val());
            
            // tis condition for stock_adjustment
            if(hidden_qnty < 0){
                hidden_qnty = Math.abs(hidden_qnty);
            }
            
            // var line_multiplier = __getUnitMultiplier($(this));
            // var line_total_quantity = (recipe_quantity * hidden_qnty) ;
            var ing_quantity = parseFloat($(this).find('.ing_quantity').val());
            var line_total_quantity = (recipe_quantity * ing_quantity);
            __write_number($(this).find('.total_quantities'), line_total_quantity);
            $(this).find('.total_quantities').change();
        });
    });

	$(document).on('change', '#sub_unit_id', function(){
		var unit_name = $(this)
                .find(':selected')
                .data('unit_name');
            $('#wasted_units_text').text(unit_name);
	});

	$(document).on('change', '.total_quantities', function(){
	   // debugger
		if (production_form_validator) {
    		production_form_validator.element($(this));
		}
		var id = $(this).closest('table').attr('id');
		calculateRecipeTotal(id);
	});

	function calculateRecipeTotal(table_tr_id) {
	    debugger
	var recipe_quantity = __read_number($('#recipe_quantity'));
	var cont_rate = __read_number($('#contractor_rates'));
	console.log(cont_rate);
        var multiplier = 1;
        if ($('#sub_unit_id').length) {
            multiplier = parseFloat(
            $('#sub_unit_id')
                .find(':selected')
                .data('multiplier')
            );
            recipe_quantity = recipe_quantity * multiplier; 
        }
        var total_ingredients_cost = 0;
        var total__cost = 0;
        
            $('#ingredients_for_unit_recipe_table tbody tr, .ingredients_for_unit_recipe_table#'+ table_tr_id +' tbody tr').each( function() {
                if ($(this).find('.ingredient_price').length > 0) {
                    // var new_total_price = parseFloat($(this).find('.new_total_price').val()); 
                    var line_unit_price = parseFloat($(this).find('.ingredient_price').val());
                    var line_unit_quantity = parseFloat($(this).find('.unit_quantity').val());
                    var line_unit_total = line_unit_price * line_unit_quantity;
                    var line_total_quantity = __read_number($(this).find('.total_quantities'));
                    var line_multiplier = __getUnitMultiplier($(this));
                    var line_waste_percent = __read_number($(this).find('.mfg_waste_percent'));
    
                    var line_final_quantity = __substract_percent(line_total_quantity, line_waste_percent);
    
                    var line_total = line_unit_price * line_total_quantity * line_multiplier;
                    $(this).find('span.ingredient_total_price').text(__currency_trans_from_en(line_total, true));
                    $(this).find('span.row_final_quantity').text(line_final_quantity);
                    $(this).find('input.line_final_quantity').val(line_final_quantity);
    
                    var line_unit_name = '';
    
                    if ($(this).find('.sub_unit').length) {
                        line_unit_name = $(this).find('.sub_unit')
                        .find('option:selected')
                        .text();
                    } else {
                        line_unit_name = $(this).find('.line_unit_span').text();
                    }
                    $(this).find('.row_unit_text').text(line_unit_name);
    
                    total_ingredients_cost += line_total ;
                    // total__cost += new_total_price;
                }
            });
        $('#total_ingredient_price').text(__currency_trans_from_en((total_ingredients_cost / recipe_quantity), true));
        $('#hidden_total').val(__currency_trans_from_en(total_ingredients_cost , true));
        if(table_tr_id){
           $('table#'+table_tr_id).find('#total__cost').val(__currency_trans_from_en(total_ingredients_cost, false)); 
        }else{
            $('#total__cost').val(__currency_trans_from_en(total_ingredients_cost, false));
        }
        
        var production_cost = __read_number($('#production_cost'));
        var production_cost_type = $('#mfg_production_cost_type').val();
        if (production_cost_type == 'percentage') {
            production_cost = __calculate_amount('percentage', production_cost, total_ingredients_cost);
        } else if(production_cost_type == 'per_unit') {
            production_cost = production_cost * __read_number($('#recipe_quantity'));
        }
        $('span#total_production_cost').text(__currency_trans_from_en(production_cost, true));
        var final_price = total_ingredients_cost + production_cost;
        __write_number($('#final_total'), final_price);
        $('span#final_total_text').text(__currency_trans_from_en(final_price, true));
        __write_number($('#total'), total_ingredients_cost);
    }

    $(document).on('change', 'select.sub_unit', function() {
        var tr = $(this).closest('tr');
        var selected_option = $(this).find(':selected');
        var multiplier = parseFloat(selected_option.data('multiplier'));
        var allow_decimal = parseInt(selected_option.data('allow_decimal'));

        var qty_element = tr.find('input.total_quantities');
        var base_max_avlbl = qty_element.data('qty_available');
        var error_msg_line = 'pos_max_qty_error';

        qty_element.attr('data-decimal', allow_decimal);
        var abs_digit = true;
        if (allow_decimal) {
            abs_digit = false;
        }
        qty_element.rules('add', {
            abs_digit: abs_digit,
        });

        if (base_max_avlbl) {
            var max_avlbl = parseFloat(base_max_avlbl) / multiplier;
            var formated_max_avlbl = __number_f(max_avlbl);
            var unit_name = selected_option.data('unit_name');
            var max_err_msg = __translate(error_msg_line, {
                max_val: formated_max_avlbl,
                unit_name: unit_name,
            });
            qty_element.attr('data-rule-max-value', max_avlbl);
            qty_element.attr('data-msg-max-value', max_err_msg);
            qty_element.rules('add', {
                'max-value': max_avlbl,
                messages: {
                    'max-value': max_err_msg,
                },
            });
            qty_element.trigger('change');
        }
    });
    
     $("#contractor").change(function(){
    // alert("Sa");
    var contractor = $("#contractor").val();
   
    $.ajax({
      url:"/manufacturing/add_contract",
      type: "POST",
      data: {
      contractor: contractor,
      },
      success: function (data) {
          console.log(data.rate);
          
          $('#contractor_rates').html('');
       
          	$('#contractor_rates').append('<option selected disabled>Select Please</option>');
          $.each(data, function(index, value) {
              console.log(value);
			$('#contractor_rates').append('<option value="' + value.rate + '">' + value.rate + '</option>');

		});

      }
   
   
      
    });
    
    
  });
    $('#contractor_rates').change(function(){
    //  console.log("SA");
     var contractor_rates = __read_number($('#contractor_rates'));
     var hidden_total = __read_number($('#hidden_total'));
     
    $('#total_ingredient_price').text(__currency_trans_from_en(hidden_total+contractor_rates, true));
});

    
    
    
</script>