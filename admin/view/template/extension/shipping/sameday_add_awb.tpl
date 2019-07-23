<?php echo $header; ?><?php echo $column_left; ?>

<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <button type="button" form="form-shipping" id="estimateCost" data-toggle="tooltip" data-link="<?php echo $estimate_cost_href; ?>" title='<?php echo $estimate_cost; ?>' class="btn btn-info"><i class="fa fa-money"></i></button>
                <button type="submit" form="form-shipping" data-toggle="tooltip" title='<?php echo $text_create_awb; ?>' class="btn btn-primary"><i class="fa fa-save"></i></button>
                <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
            </div>

            <h1><?php echo $heading_title_add_awb; ?></h1>
            <ul class="breadcrumb">
                <?php foreach($breadcrumbs as $breadcrumb): ?>
                <li> <a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="page-header">
        <div class="container-fluid">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-pencil"></i> Sameday - AWB </h3>
                </div>
                <div class="panel-body">
                    <div class="appendEstimateCost"></div>
                    <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-shipping" class="form-horizontal">
                        <?php
                           if (isset($awb_errors)) {
                        ?>
                        <div class="alert alert-danger fade in">
                            <a href="#" class="close" data-dismiss="alert">&times;</a>
                            <ul>
                                <?php foreach($awb_errors as $error) { ?>
                                <li>
                                    <strong> <?php echo $error; ?> </strong>
                                </li>
                                <?php } ?>
                            </ul>
                        </div>
                        <?php
                           }
                        ?>

                        <!-- AWB Options //-->
                        <div class="form-group">
                            <div style="font-size: 9.5pt; text-align: left;" class="col-sm-2 control-label"><b> <?php echo $awb_options; ?> </b></div>
                        </div>

                        <!-- Insured Value //-->
                        <div class="form-group required">
                            <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_insured_value_title; ?>"><?php echo $entry_insured_value; ?></span></label>
                            <div class="col-sm-6">
                                <input type="number" name="sameday_insured_value" value="<?php echo ($sameday_insured_value != '') ? $sameday_insured_value : 0; ?>" min="0" class="form-control"/>
                                <?php if (isset($error_insured_value)) { ?>
                                <div class="text-danger"><?php echo $error_insured_value; ?></div>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_packages_number_title; ?>"><?php echo $entry_packages_number; ?></span></label>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <input type="text" name="sameday_package_number" class="form-control input-number" id="sameday_package_qty" value="<?php echo ($sameday_package_number != '') ? $sameday_package_number : 0; ?>" readonly>
                                    <span class="input-group-btn">
                                  <button type="button" class="btn btn-info btn-number" data-type="plus" data-field="sameday_package_qty" id="plus-btn">
                                      <span class="fa fa-plus"></span>
                                  </button>
                                </span>
                                </div>
                            </div>
                            <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_calculated_weight_title; ?>"><?php echo $entry_calculated_weight; ?></span></label>
                            <div class="col-sm-1">
                                <div class="input-group">
                                    <input type="number" value="<?php echo $calculated_weight; ?>" readonly="readonly" class="form-control input-number"/>
                                </div>
                            </div>
                        </div>

                        <!-- Package Number //-->

                        <div class="form-group package_dimension_field">
                            <!-- append element here //-->
                        </div>

                        <!-- Observation //-->
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_observation_title; ?>"><?php echo $entry_observation; ?></span></label>
                            <div class="col-sm-10">
                                <input type="text" name="sameday_observation" value="<?php echo $sameday_observation; ?>" class="form-control"/>
                            </div>
                        </div>

                        <!-- Ramburs //-->
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_ramburs_title; ?>"><?php echo $entry_ramburs; ?></span></label>
                            <div class="col-sm-10">
                                <input type="number" name="sameday_ramburs" value="<?php echo $sameday_ramburs; ?>" class="form-control"/>
                            </div>
                        </div>

                        <!-- Package Type //-->
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="input-status-sameday_package_type"><span data-toggle="tooltip" title="<?php echo $entry_package_type_title; ?>"><?php echo $entry_package_type; ?></label>
                            <div class="col-sm-10">
                                <select name="sameday_package_type" id="input-status-sameday-package-type" class="form-control">
                                    <?php foreach ($packageTypes  as $_packageType) { ?>
                                    <option value="<?php echo $_packageType['value']?>" <?php if ($_packageType['value'] == $sameday_package_type) { ?>selected="selected" <?php } ?> > <?php echo $_packageType['name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <!-- Pickup Point //-->
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="input-status-sameday-pickup_point"><span data-toggle="tooltip" title="<?php echo $entry_pickup_point_title; ?>"><?php echo $entry_pickup_point; ?></label>
                            <div class="col-sm-10">
                                <select name="sameday_pickup_point" id="input-status-sameday-pickup_point" class="form-control">
                                    <?php foreach($pickupPoints as $pickupPoint) { ?>
                                    <option value="<?php echo $pickupPoint['sameday_id']; ?>" <?php if ($pickupPoint['sameday_id'] == $sameday_pickup_point || $pickupPoint['default_pickup_point'] == 1) { ?> selected="selected" <?php } ?> ><?php echo $pickupPoint['sameday_alias']; ?> </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <!-- Awb Payment //-->
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="input-status-sameday_awb_payment"><span data-toggle="tooltip" title="<?php echo $entry_awb_payment_title; ?>"><?php echo $entry_awb_payment; ?></label>
                            <div class="col-sm-10">
                                <select name="sameday_awb_payment" id="input-status-sameday_awb_payment" class="form-control">
                                    <?php foreach($awbPaymentsType as $paymentType) { ?>
                                    <option value="<?php echo $paymentType['value']; ?>" <?php if ($paymentType['value'] == $sameday_awb_payment) { ?> selected="selected" <?php } ?>><?php echo $paymentType['name']; ?> </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <!--  Awb Service //-->
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="input-status-sameday_service"><span data-toggle="tooltip" title="<?php echo $entry_service_title; ?>"><?php echo $entry_service; ?></label>
                            <div class="col-sm-10">
                                <select name="sameday_service" id="input-status-sameday_service" class="form-control">
                                    <?php foreach($services as $service) { ?>
                                        <?php if ($service['status'] > 0) { ?>
                                        <option value="<?php echo $service['sameday_id']; ?>" <?php if ($service['sameday_id'] == $default_service_id) { ?> selected="selected" <?php } ?>><?php echo $service['name']; ?> </option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <!-- Third Party Pick-up //-->
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="input-status-sameday-third_party_pickup"><span data-toggle="tooltip" title="<?php echo $entry_third_party_pickup_title; ?>"><?php echo $entry_third_party_pickup; ?></label>
                            <div class="col-sm-10">
                                <select name="sameday_third_party_pickup" id="input-status-sameday-third_party_pickup" class="form-control">
                                    <option value="0" <?php if ($sameday_third_party_pickup == '0') { ?> selected="selected" <?php } ?> > NO </option>
                                    <option value="1" <?php if ($sameday_third_party_pickup == '1') { ?> selected="selected" <?php } ?> > YES </option>
                                </select>
                            </div>
                        </div>

                        <div class="thirt_party_pickup_address" style="display: none;">

                            <!-- Third Party Pickup County //-->
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_third_party_pickup_county_title; ?>"><?php echo $entry_third_party_pickup_county ; ?></span></label>
                                <div class="col-sm-10">
                                    <select name="sameday_third_party_pickup_county" id="input-status-sameday_third_party_pickup_county" class="form-control">
                                        <?php foreach ($counties as $county) { ?>
                                        <option value="<?php echo $county['name']; ?>" <?php if ($county['name'] == $sameday_third_party_pickup_county) { ?> selected="selected" <?php } ?> > <?php echo $county['name']; ?> </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Third Party Pickup City //-->
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_third_party_pickup_city_title; ?>"><?php echo $entry_third_party_pickup_city; ?></span></label>
                                <div class="col-sm-10">
                                    <input type="text" name="sameday_third_party_pickup_city" value="<?php echo $sameday_third_party_pickup_city; ?>" class="form-control"/>
                                    <?php if (isset($error_third_party_pickup_city)) { ?>
                                    <div class="text-danger"><?php echo $error_third_party_pickup_city; ?></div>
                                    <?php } ?>
                                </div>
                            </div>

                            <!-- Third Party Pickup Address //-->
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_third_party_pickup_address_title; ?>"><?php echo $entry_third_party_pickup_address; ?></span></label>
                                <div class="col-sm-10">
                                    <input type="text" name="sameday_third_party_pickup_address" value="<?php echo $sameday_third_party_pickup_address;?>" class="form-control"/>
                                    <?php if (isset($error_third_party_pickup_address)) { ?>
                                    <div class="text-danger"><?php echo $error_third_party_pickup_address; ?></div>
                                    <?php } ?>
                                </div>
                            </div>

                            <!-- Third Party Pickup Name //-->
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_third_party_pickup_name_title; ?>"><?php echo $entry_third_party_pickup_name; ?></span></label>
                                <div class="col-sm-10">
                                    <input type="text" name="sameday_third_party_pickup_name"  value="<?php echo $sameday_third_party_pickup_name;?>" class="form-control"/>
                                    <?php if (isset($error_third_party_pickup_name)) { ?>
                                    <div class="text-danger"><?php echo $error_third_party_pickup_name; ?></div>
                                    <?php } ?>
                                </div>
                            </div>

                            <!-- Third Party Pickup Phone //-->
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_third_party_pickup_phone_title; ?>"><?php echo $entry_third_party_pickup_phone; ?></span></label>
                                <div class="col-sm-10">
                                    <input type="text" name="sameday_third_party_pickup_phone" value="<?php echo $sameday_third_party_pickup_phone;?>" class="form-control"/>
                                    <?php if (isset($error_third_party_pickup_phone)) { ?>
                                    <div class="text-danger"><?php echo $error_third_party_pickup_phone; ?></div>
                                    <?php } ?>
                                </div>
                            </div>

                            <!-- Third Party Pickup Person Type //-->
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-status-sameday-third-party-person_type"><span data-toggle="tooltip" title="<?php echo $entry_third_party_person_type_title; ?>"><?php echo $entry_third_party_person_type; ?></label>
                                <div class="col-sm-10">
                                    <select name="sameday_third_party_person_type" id="input-status-sameday-third-party-person_type" class="form-control">
                                        <option value="0" <?php if($sameday_third_party_person_type == "0") { ?> selected="selected"  <?php } ?> > <?php echo $text_type_person_individual; ?> </option>
                                        <option value="1" <?php if($sameday_third_party_person_type == "1") { ?> selected="selected"  <?php } ?> > <?php echo $text_type_person_business; ?> </option>
                                    </select>
                                </div>
                            </div>

                            <!--// Person Type Options -->
                            <div class="thirt_party_pickup_personType" style="display: none">

                                <!-- Third Party Pickup Company Name //-->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_third_party_person_company_title; ?>"><?php echo $entry_third_party_person_company; ?></span></label>
                                    <div class="col-sm-10">
                                        <input type="text" name="sameday_third_party_person_company" value="<?php echo $sameday_third_party_person_company; ?>" class="form-control"/>
                                        <?php if (isset($error_third_party_person_company)) { ?>
                                        <div class="text-danger"><?php echo $error_third_party_person_company; ?></div>
                                        <?php } ?>
                                    </div>
                                </div>

                                <!-- Third Party Pickup Company Cui //-->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_third_party_person_cif_title; ?>"><?php echo $entry_third_party_person_cif; ?></span></label>
                                    <div class="col-sm-10">
                                        <input type="text" name="sameday_third_party_person_cif" value="<?php echo $sameday_third_party_person_cif; ?>" class="form-control"/>
                                        <?php if (isset($error_third_party_person_cif)) { ?>
                                        <div class="text-danger"><?php echo $error_third_party_person_cif; ?></div>
                                        <?php } ?>
                                    </div>
                                </div>

                                <!-- Third Party Pickup Company ONRC //-->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_third_party_person_onrc_title; ?>"><?php echo $entry_third_party_person_onrc; ?></span></label>
                                    <div class="col-sm-10">
                                        <input type="text" name="sameday_third_party_person_onrc" value="<?php echo $sameday_third_party_person_onrc; ?>" class="form-control"/>
                                        <?php if (isset($error_third_party_person_onrc)) { ?>
                                        <div class="text-danger"><?php echo $error_third_party_person_onrc; ?></div>
                                        <?php } ?>
                                    </div>
                                </div>

                                <!-- Third Party Pickup Company Banca //-->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_third_party_person_bank_title; ?>"><?php echo $entry_third_party_person_bank; ?></span></label>
                                    <div class="col-sm-10">
                                        <input type="text" name="sameday_third_party_person_bank" value="<?php echo $sameday_third_party_person_bank; ?>" class="form-control"/>
                                        <?php if (isset($error_third_party_person_bank)) { ?>
                                        <div class="text-danger"><?php echo $error_third_party_person_bank; ?></div>
                                        <?php } ?>
                                    </div>
                                </div>

                                <!-- Third Party Pickup Company Iban //-->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="input-key"><span data-toggle="tooltip" title="<?php echo $entry_third_party_person_iban_title; ?>"><?php echo $entry_third_party_person_iban; ?></span></label>
                                    <div class="col-sm-10">
                                        <input type="text" name="sameday_third_party_person_iban" value="<?php echo $sameday_third_party_person_iban; ?>" class="form-control"/>
                                        <?php if (isset($error_third_party_person_iban)) { ?>
                                        <div class="text-danger"><?php echo $error_third_party_person_iban; ?></div>
                                        <?php } ?>
                                    </div>
                                </div>

                            </div>
                            <!--// End of Person Type Options -->
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).on('click', '#estimateCost', function() {
        // config vars.
        link= $(this).data('link');
        url = new URL(window.location.href);
        token = url.searchParams.get("token");
        order_id = url.searchParams.get("order_id");
        formData = new FormData($('#form-shipping')[0]);
        formData.append('order_id', order_id);

        $.ajax({
            url: link + "&token=" + token,
            data: formData,
            type: "POST",
            dataType: "JSON",
            processData: false,
            contentType: false,
            beforeSend: function(){
                $('.appendEstimateCost').html('<div class="loader">  </div>');
            },
            success: function( data ) {
                //console.log(data.errors);
                $('.appendEstimateCost').html(createMsgCost( data ));
            },
            error: function () {
                $('.appendEstimateCost').html("Something went wrong");
            }
        });

    });

    function createMsgCost( data ) {
        _msg = "";
        if (data.errors != null) {
            alertType = "alert-danger";
            data.errors.forEach(function(value) {
                _msg += "<li>\n" +
                    "<strong> " + value + "</strong>\n" +
                    "</li>\n";
            });
        } else {
            alertType = "alert-success";
            _msg += "<li>\n" +
                "<strong> " + data.success + "</strong>\n" +
                "</li>\n";
        }

        return "<div class=\"alert " + alertType + " fade in\">\n" +
            "<a href=\"#\" class=\"close\" data-dismiss=\"alert\">&times;</a>\n" +
            "<ul>\n" + _msg
            "</ul>\n" +
            "</div>";
    }

    $('#qty_input').prop('disabled', true);
    $('#plus-btn').click(function(e){
        e.preventDefault();
        $('#sameday_package_qty').val(parseInt($('#sameday_package_qty').val()) + 1 );
        if ($('#sameday_package_qty').val() > 9) {
            $('#sameday_package_qty').val(10);
        }

        createPackageDimensionField('+');
    });

    $(document).on('click','#removePackageDimensionField', function(){
        if (parseInt($('#sameday_package_qty').val()) > 1) {
            $(this).parents('.parcel').remove();
            $('#sameday_package_qty').val(parseInt($('#sameday_package_qty').val()) -1 );
        }
    });

    $('#plus-btn').trigger('click');

    function createPackageDimensionField(){
        var qty = parseInt($('#sameday_package_qty').val());

        var field = createField();

        $('.package_dimension_field').append(field);
    }

    function createField(weightValue = '', error_weight_class = '') {
        field = "<div class='parcel'><label class=\"col-sm-2 control-label\" for=\"input-length\"><?php echo $entry_package_dimension; ?></label>\n" +
            "<div class=\"col-sm-10\" style='padding-bottom: 5px;'>\n" +
            "<div class=\"row\">\n" +
            "<div class=\"col-sm-2\">\n" +
            "<input type=\"number\" name=\"sameday_package_weight[]\" value=\""+weightValue+"\" min=\"1\" placeholder=\"<?php echo $entry_weight; ?>\" id=\"input-length\" class=\"form-control input-number\" />\n" +
            "</div>\n" +
            "<div class=\"col-sm-2\">\n" +
            "<input type=\"number\" name=\"sameday_package_width[]\" value=\"\" min=\"0\" placeholder=\"<?php echo $entry_width; ?>\" id=\"input-width\" class=\"form-control input-number\" />\n" +
            "</div>\n" +
            "<div class=\"col-sm-2\">\n" +
            "<input type=\"number\" name=\"sameday_package_length[]\" value=\"\" min=\"0\" placeholder=\"<?php echo $entry_length; ?>\" id=\"input-width\" class=\"form-control input-number\" />\n" +
            "</div>\n" +
            "<div class=\"col-sm-2\">\n" +
            "<input type=\"number\" name=\"sameday_package_height[]\" value=\"\" min=\"0\" placeholder=\"<?php echo $entry_height; ?>\" id=\"input-height\" class=\"form-control input-number\" />\n" +
            "</div>\n" +
            "<div class=\"col-sm-4\">\n" +
            "<span id='removePackageDimensionField'><i class='fa fa-remove pull-left' style='vertical-align: bottom; cursor: pointer; padding-top: 12px;'></i></span>\n" +
            "</div>\n" +
            "</div>\n" +
            "<div class=\""+error_weight_class+" text-danger\"></div>\n" +
            "</div>" +
            "</div>";

        return field;
    }

    function showPackageDimensionErrors(){
        "<?php if (isset($all_errors)) { ?>"

        nrOfparcels = "<?php echo count($sameday_package_weight); ?>";
        $('#sameday_package_qty').val(nrOfparcels);

        $('.package_dimension_field').html('');
        var packagesWeight = '<?php echo json_encode($sameday_package_weight); ?>';
        for (i = 0; i < nrOfparcels; i++) {
            var weight_value = JSON.parse(packagesWeight)[i];
            var error_weight_class = 'error_weight_class';
            field = createField(weight_value, error_weight_class);
            $('.package_dimension_field').append(field);
            if (weight_value == '' || weight_value == 0) {
                $('.' + error_weight_class).html("<?php echo $error_weight; ?>");
            } else {
                $('div').removeClass(error_weight_class);
            }
        }

        "<?php } ?>"
    }

    window.onload = showPackageDimensionErrors();

    $(document).on('change', '#input-status-sameday-third_party_pickup', function(){
        var thirdPartyPickUp = $('#input-status-sameday-third_party_pickup').val();
        if (thirdPartyPickUp == 1) {
            $('.thirt_party_pickup_address').css("display", "block");
        } else {
            $('.thirt_party_pickup_address').css("display", "none");
            $('.error_thirdPartyMandatoryFields').css("display", "none");
        }
    });
    $('#input-status-sameday-third_party_pickup').trigger('change');

    $(document).on('change', '#input-status-sameday-third-party-person_type', function(){
        var personType = $('#input-status-sameday-third-party-person_type').val();
        if (personType == 1) {
            $('.thirt_party_pickup_personType').css("display", "block");
        } else {
            $('.thirt_party_pickup_personType').css("display", "none");
        }
    });
    $('#input-status-sameday-third-party-person_type').trigger('change');


</script>

<?php echo $footer;?>

<style>
    .input-number {
        text-align: center;
    }

    .loader {
        border: 3.2px solid #f3f3f3;
        border-radius: 50%;
        border-top: 3.2px solid #3498db;
        width: 24px;
        height: 24px;
        -webkit-animation: spin 2s linear infinite; /* Safari */
        animation: spin 2s linear infinite;
    }

    /* Safari */
    @-webkit-keyframes spin {
        0% { -webkit-transform: rotate(0deg); }
        100% { -webkit-transform: rotate(360deg); }
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
