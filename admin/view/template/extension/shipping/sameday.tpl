<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <button type="submit" form="form-custom" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
                <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
            <h1><?php echo $heading_title; ?></h1>
            <ul class="breadcrumb">
                <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
                <?php } ?>
            </ul>
        </div>
    </div>
    <div class="container-fluid">
        <?php if ($error_success) { ?>
        <div class="alert alert-success"><i class="fa fa-check"></i> <?php echo $error_success; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>
        <?php if ($error_warning) { ?>
        <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3>
            </div>
            <div class="panel-body">
                <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-custom" class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-username"><?php echo $entry_username; ?></label>
                        <div class="col-sm-10">
                            <input type="text" name="sameday_username" value="<?php echo $sameday_username; ?>" placeholder="<?php echo $entry_username; ?>" id="input-username" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-password"><?php echo $entry_password; ?></label>
                        <div class="col-sm-10">
                            <input type="password" name="sameday_password" placeholder="<?php echo $entry_password; ?>" id="input-password" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-tax-class"><?php echo $entry_tax_class; ?></label>
                        <div class="col-sm-10">
                            <select name="sameday_tax_class_id" id="input-tax-class" class="form-control">
                                <option value="0"><?php echo $text_none; ?></option>
                                <?php foreach ($tax_classes as $tax_class) { ?>
                                <option value="<?php echo $tax_class['tax_class_id']; ?>" <?php if ($tax_class['tax_class_id'] == $sameday_tax_class_id) { ?> selected="selected" <?php } ?>><?php echo $tax_class['title']; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-geo-zone"><?php echo $entry_geo_zone; ?></label>
                        <div class="col-sm-10">
                            <select name="sameday_geo_zone_id" id="input-geo-zone" class="form-control">
                                <option value="0"><?php echo $text_all_zones; ?></option>
                                <?php foreach ($geo_zones as $geo_zone) { ?>
                                <option value="<?php echo $geo_zone['geo_zone_id']; ?>" <?php if ($geo_zone['geo_zone_id'] == $sameday_geo_zone_id) { ?> selected="selected" <?php } ?>><?php echo $geo_zone['name']; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-status"><?php echo $entry_status; ?></label>
                        <div class="col-sm-10">
                            <select name="sameday_status" id="input-status" class="form-control">
                                <option value="0" <?php if (!$sameday_status) { ?>selected="selected"<?php } ?>><?php echo $text_disabled; ?></option>
                                <option value="1" <?php if ($sameday_status) { ?>selected="selected"<?php } ?>><?php echo $text_enabled; ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-status-estimated-cost"><?php echo $entry_estimated_cost; ?></label>
                        <div class="col-sm-10">
                            <select name="sameday_estimated_cost" id="input-status-estimated-cost" class="form-control">
                                <option value="0" <?php if (!$sameday_estimated_cost) { ?>selected="selected"<?php } ?>><?php echo $text_disabled; ?></option>
                                <option value="1" <?php if ($sameday_estimated_cost) { ?>selected="selected"<?php } ?>><?php echo $text_enabled; ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-status-show-locker-map"><?php echo $entry_show_lockers_map; ?></label>
                        <div class="col-sm-10">
                            <select name="sameday_show_lockers_map" id="input-status-show-locker-map" class="form-control">
                                <option value="0" <?php if (!$sameday_show_lockers_map) { ?> selected="selected" <?php } ?> > <?php echo $entry_interactive_map; ?></option>
                                <option value="1" <?php if ($sameday_show_lockers_map) { ?> selected="selected" <?php } ?> > <?php echo $entry_drop_down_list; ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-sort-order-locker-max-items"><?php echo $entry_locker_max_items; ?></label>
                        <div class="col-sm-10">
                            <input type="text" name="sameday_locker_max_items" value="<?php echo $sameday_locker_max_items; ?>" placeholder="<?php echo $entry_locker_max_items; ?>" id="input-sort-order-locker-max-items" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-sort-order-sort-order"><?php echo $entry_sort_order; ?></label>
                        <div class="col-sm-10">
                            <input type="text" name="sameday_sort_order" value="<?php echo $sameday_sort_order; ?>" placeholder="<?php echo $entry_sort_order; ?>" id="input-sort-order-sort-order" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-import-local-data"><?php echo $entry_import_local_data; ?></label>
                        <div class="col-sm-10">
                            <button type="button" class="btn btn-success fa fa-download" id="input-import-local-data" data-href="<?php echo $import_local_data_href; ?>" data-actions='<?php echo $import_local_data_actions; ?>'>
                                <?php echo $entry_import_local_data; ?>
                            </button>
                            <span id="importLocalDataSpinner" style="display: none; vertical-align: middle" class="loader"></span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_services; ?></h3>
                <a class="btn btn-primary" href="<?php echo $service_refresh; ?>" data-toggle="tooltip" title="<?php echo $text_services_refresh; ?>"><i class="fa fa-refresh"></i></a>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <td class="text-left"><?php echo $column_internal_name; ?></td>
                                <td class="text-left"><?php echo $column_name; ?></td>
                                <td class="text-left"><?php echo $column_price; ?></td>
                                <td class="text-left"><?php echo $column_price_free; ?></td>
                                <td class="text-left"><?php echo $column_status; ?></td>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($services)) { ?>
                            <tr>
                                <td class="text-center" colspan="6"> <?php echo $text_services_empty; ?> </td>
                            </tr>
                            <?php } else { foreach ($services as $idx => $service) { ?>
                            <tr>
                                <td>
                                    <a href="<?php echo $service_links[$idx]; ?>"
                                    <?php if (isset($service['column_ooh_label'])) { ?>
                                        title="<?php echo $service['column_ooh_label']; ?>"
                                    <?php } ?>
                                    >
                                        <?php echo $service['sameday_name']; ?>
                                    </a>
                                </td>
                                <td><?php echo $service['name']; ?></td>
                                <td><?php echo $service['price']; ?></td>
                                <td><?php echo $service['price_free']; ?></td>
                                <td><?php echo $statuses[$service['status']]['text']; ?></td>
                            </tr>
                            <?php } } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_pickup_points; ?></h3>
                <a class="btn btn-primary" href="<?php echo $pickupPoint_refresh; ?>" data-toggle="tooltip" title="<?php echo $text_services_refresh; ?>"><i class="fa fa-refresh"></i></a>
                <a class="btn btn-primary" href="#" data-toggle="modal" data-target="#addPickupPoint" title="<?php echo $text_pickupPoint_add; ?>"><i class="fa fa-plus"></i></a>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                        <tr>
                            <td class="text-left"></td>
                            <td class="text-left"><?php echo $column_pickupPoint_samedayId; ?></td>
                            <td class="text-left"><?php echo $column_pickupPoint_alias; ?></td>
                            <td class="text-left"><?php echo $column_pickupPoint_city; ?></td>
                            <td class="text-left"><?php echo $column_pickupPoint_county; ?></td>
                            <td class="text-left"><?php echo $column_pickupPoint_address; ?></td>
                            <td class="text-left"><?php echo $column_pickupPoint_default_address; ?></td>
                            <td class="text-left"><?php echo $column_pickupPoint_action; ?></td>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($pickupPoints)) { ?>
                        <tr>
                            <td class="text-center" colspan="6"><?php echo $text_pickup_points_empty; ?></td>
                        </tr>
                        <?php } else { $i=1;foreach ($pickupPoints as $pickupPoint) { ?>
                        <tr data-sameday-id="<?php echo $pickupPoint['sameday_id']; ?>">
                            <td><?php echo $i++;?></td>
                            <td><?php echo $pickupPoint['sameday_id']; ?></td>
                            <td><?php echo $pickupPoint['sameday_alias']; ?></td>
                            <td><?php echo $pickupPoint['city']; ?></td>
                            <td><?php echo $pickupPoint['county']; ?></td>
                            <td><?php echo $pickupPoint['address']; ?></td>
                            <td><?php echo $pickupPoint['default_pickup_point'] == 1 ? $yes : $no; ?></td>
                            <td><button data-id="<?php echo $pickupPoint['sameday_id']; ?>" class="btn btn-danger" data-toggle="modal" data-target="#pickupPointDelete"><i class="fa fa-trash"></i></button></td>
                        </tr>
                        <?php } } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Lockers -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_lockers; ?> </h3>
                <a class="btn btn-primary" href="<?php echo $lockers_refresh; ?>" data-toggle="tooltip" title="<?php echo $text_lockers_refresh; ?>"><i class="fa fa-refresh"></i></a>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                        <tr>
                            <td></td>
                            <td><?php echo $column_locker_name; ?></td>
                            <td><?php echo $column_locker_county; ?></td>
                            <td><?php echo $column_locker_city; ?></td>
                            <td><?php echo $column_locker_address; ?></td>
                            <td><?php echo $column_locker_lat; ?></td>
                            <td><?php echo $column_locker_lng; ?></td>
                            <td><?php echo $column_locker_postal_code; ?></td>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($lockers)) { ?>
                        <tr>
                            <td class="text-center" colspan="8"> <?php echo $text_lockers_empty; ?> </td>
                        </tr>
                        <?php } else { ?>
                        <?php $i=1; foreach($lockers as $locker) { ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo $locker['name']; ?></td>
                            <td><?php echo $locker['county']; ?></td>
                            <td><?php echo $locker['city']; ?></td>
                            <td><?php echo $locker['address']; ?></td>
                            <td><?php echo $locker['lat']; ?></td>
                            <td><?php echo $locker['lng']; ?></td>
                            <td><?php echo $locker['postal_code']; ?></td>
                        </tr>
                        <?php } } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<?php echo $footer; ?>
<div class="modal fade" id="addPickupPoint" tabindex="-1" aria-labelledby="addPickupPointLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="addPickupPointLabel"><?php echo $text_pickupPoint_add; ?></h3>
            </div>
            <div class="modal-body">
                <form action="<?php echo $pickupPointsTest; ?>" method="post" enctype="multipart/form-data" id="form-pickupPoints" class="form-horizontal">

                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="input-pickupPointCountry"><?php echo $text_pickupPointCountry; ?></label>
                        <div class="col-sm-9">
                            <select name="pickupPointCountry" id="input-pickupPointCountry" class="form-control">
                                <?php foreach($pp_countries as $country): ?>
                                    <option value="<?php echo $country['value']; ?>"><?php echo $country['label']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="input-pickupPointCounty"><?php echo $text_pickupPointCounty; ?></label>
                        <div class="col-sm-9">
                            <select name="pickupPointCounty" id="input-pickupPointCounty" class="form-control" data-url="<?php echo $url_cities_ajax; ?>">
                                <?php foreach($pp_counties as $county): ?>
                                    <option value="<?php echo $county['id']; ?>"><?php echo $county['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="input-pickupPointCity"><?php echo $text_pickupPointCity; ?></label>
                        <div class="col-sm-9">
                            <select name="pickupPointCity" id="input-pickupPointCity" class="form-control" disabled>

                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="input-pickupPointAddress"><?php echo $text_pickupPointAddress; ?></label>
                        <div class="col-sm-9">
                            <input type="text" name="pickupPointAddress" id="input-pickupPointAddress" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="input-pickupPointDefault"><?php echo $text_pickupPointDefault; ?></label>
                        <div class="col-sm-9">
                            <input type="checkbox" name="pickupPointDefault" id="input-pickupPointDefault" class="form-check-input" value="1" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="input-pickupPointPo"><?php echo $text_pickupPointPo; ?></label>
                        <div class="col-sm-9">
                            <input type="number" name="pickupPointPo" id="input-pickupPointPo" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="input-pickupPointAlias"><?php echo $text_pickupPointAlias; ?></label>
                        <div class="col-sm-9">
                            <input type="text" name="pickupPointAlias" id="input-pickupPointAlias" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="input-pickupPointContactName"><?php echo $text_pickupPointContactName; ?></label>
                        <div class="col-sm-9">
                            <input type="text" name="pickupPointContactName" id="input-pickupPointContactName" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="input-pickupPointPhoneNumber"><?php echo $text_pickupPointPhoneNumber; ?></label>
                        <div class="col-sm-9">
                            <input type="tel" name="pickupPointPhoneNumber" id="input-pickupPointPhoneNumber" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="input-pickupPointEmail"><?php echo $text_pickupPointEmail; ?></label>
                        <div class="col-sm-9">
                            <input type="email" name="pickupPointEmail" id="input-pickupPointEmail" class="form-control" />
                        </div>
                    </div>
                    <div class="text-center">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <input type="submit" class="btn btn-primary" value="Save">
                    </div>
                </form>
                <div class="" id="pickupPointAddFeedback"></div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="pickupPointDelete" tabindex="-1" aria-labelledby="pickupPointDelete" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title text-center" id="pickupPointDeleteLabel"><?php echo $text_pickupPoint_delete; ?></h3>
            </div>
            <div class="modal-body">
                <h4 class="text-center"><?php echo $text_pickupPoint_delete_question; ?>></h4>
                <form action="<?php echo $url_deletePickupPoint; ?>" method="post" enctype="multipart/form-data" id="form-pickupPointDelete" class="form-horizontal text-center">
                    <input type="hidden" name="deletePickUpPointId" id="deletePickUpPointId" />
                    <button class="btn btn-secondary" data-dismiss="modal"><?php echo $text_pickupPoint_delete_decline; ?></button>
                    <input type="submit" class="btn btn-danger" value="<?php echo $text_pickupPoint_delete_confirm; ?>">
                </form>
            </div>
        </div>
    </div>
</div>
<script>
$(document).ready(() => {
   $(document).on('click', '#input-import-local-data', (element) => {
       let _target = element.target;
       let _actions = JSON.parse(_target.getAttribute('data-actions')) ;
       let _url = _target.getAttribute('data-href');

       importLocalData(_url, _actions);
   });

   function importLocalData(_url = '', _actions = []) {
       const _action = _actions.shift();

       if (typeof _action === "undefined") {
           window.location.reload();

           return true;
       }

       doAjaxRequest(_url, _actions, _action);
   }

   const doAjaxRequest = (_url = '', _actions = [], _action = '') => {
       $.ajax({
           url: _url,
           type: "POST",
           dataType: "JSON",
           data: jQuery.param({ 'action': _action}),
           processData: false,
           contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
           beforeSend: () => {
               showSpinner(true);
               document.getElementById('input-import-local-data').setAttribute('disabled', true);
           },
           success: () => {
               showSpinner(false);

               importLocalData(_url, _actions, _action);
           },
           error: () => {
               showSpinner(false);
           }
       });
   }

   const showSpinner = (isShow = false) => {
       let spinner = document.getElementById('importLocalDataSpinner');
       spinner.style.display = 'none'

       if (true === isShow) {
           spinner.style.display = 'inline-block';
       }
   }
});
</script>

<style>
    .loader {
        margin: 5px;
        width: 18px;
        height: 18px;
        border: 4px solid #515151;
        border-bottom-color: #8fbb6c;
        border-radius: 50%;
        display: inline-block;
        box-sizing: border-box;
        animation: rotation 1s linear infinite;
    }

    @keyframes rotation {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }
</style
