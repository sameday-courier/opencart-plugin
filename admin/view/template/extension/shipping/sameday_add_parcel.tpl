<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <button type="submit" form="form-add-parcel" data-toggle="tooltip" title="<?php echo $text_create_parcel; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
                <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
            </div>
            <h1><?php echo $heading_title_add_parcel; ?></h1>
            <ul class="breadcrumb">
                <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
                <?php } ?>
            </ul>
        </div>
    </div>
    <div class="container-fluid">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-plus"></i> <?php echo $text_create_parcel; ?></h3>
            </div>
            <div class="panel-body">
                <form action="<?php echo $action; ?>" method="post" id="form-add-parcel" class="form-horizontal">
                    <?php if ($parcel_errors !== null) { ?>
                    <div class="alert alert-danger">
                        <ul style="margin-bottom: 0;">
                            <?php foreach ($parcel_errors as $error) { ?>
                            <li><strong><?php echo $error; ?></strong></li>
                            <?php } ?>
                        </ul>
                    </div>
                    <?php } ?>

                    <div class="form-group">
                        <label class="col-sm-2 control-label"><?php echo $text_awb_number; ?></label>
                        <div class="col-sm-10">
                            <p class="form-control-static"><strong><?php echo $awb_number; ?></strong></p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label"><?php echo $text_existing_parcels; ?></label>
                        <div class="col-sm-10">
                            <p class="form-control-static"><?php echo $parcel_count; ?></p>
                        </div>
                    </div>

                    <div class="form-group required">
                        <label class="col-sm-2 control-label"><?php echo $entry_package_dimension; ?></label>
                        <div class="col-sm-10">
                            <div class="row">
                                <div class="col-sm-3">
                                    <input type="number" step="any" name="sameday_package_weight[]" value="<?php echo $calculated_weight; ?>" min="0.01" placeholder="<?php echo $entry_weight; ?>" class="form-control" required="required"/>
                                    <?php if ($error_weight !== null) { ?>
                                    <div class="text-danger"><?php echo $error_weight; ?></div>
                                    <?php } ?>
                                </div>
                                <div class="col-sm-3">
                                    <input type="number" step="any" name="sameday_package_width[]" value="" min="0" placeholder="<?php echo $entry_width; ?>" class="form-control"/>
                                </div>
                                <div class="col-sm-3">
                                    <input type="number" step="any" name="sameday_package_length[]" value="" min="0" placeholder="<?php echo $entry_length; ?>" class="form-control"/>
                                </div>
                                <div class="col-sm-3">
                                    <input type="number" step="any" name="sameday_package_height[]" value="" min="0" placeholder="<?php echo $entry_height; ?>" class="form-control"/>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="sameday_observation"><span data-toggle="tooltip" title="<?php echo $entry_observation_title; ?>"><?php echo $entry_observation; ?></span></label>
                        <div class="col-sm-10">
                            <input type="text" name="sameday_observation" id="sameday_observation" value="<?php echo $sameday_observation; ?>" class="form-control"/>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="sameday_parcel_last"><span data-toggle="tooltip" title="<?php echo $entry_parcel_last_title; ?>"><?php echo $entry_parcel_last; ?></span></label>
                        <div class="col-sm-10">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="sameday_parcel_last" id="sameday_parcel_last" value="1" <?php if ($sameday_parcel_last) { ?>checked="checked"<?php } ?>/>
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php echo $footer; ?>
