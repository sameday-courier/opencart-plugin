<table class="table table-bordered">
    <thead>
    <tr>
        <td> <?php echo $text_summary; ?> </td>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>
            <table class="table table-bordered">
                <thead>
                <tr>
                    <td class="text-left"> <?php echo $column_parcel_number; ?> </td>
                    <td class="text-left"> <?php echo $column_parcel_weight; ?> </td>
                    <td class="text-left"> <?php echo $column_delivered; ?> </td>
                    <td class="text-left"> <?php echo $column_delivery_attempts; ?> </td>
                    <td class="text-left"> <?php echo $column_is_picked_up; ?> </td>
                    <td class="text-left"> <?php echo $column_picked_up_at; ?> </td>
                </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $package) { ?>
                        <tr>
                            <td> <?php echo $package['summary']->getParcelAwbNumber(); ?></td>
                            <td> <?php echo $package['summary']->getParcelWeight(); ?> </td>
                            <td> <?php echo $package['summary']->isDelivered() ? 'Yes' : 'No'; ?></td>
                            <td> <?php echo $package['summary']->getDeliveryAttempts(); ?></td>
                            <td> <?php echo $package['summary']->isPickedUp() ? 'Yes' : 'No'; ?></td>
                            <td> <?php echo $package['summary']->getPickedUpAt() ? $statusHistory->getSummary()->getPickedUpAt()->format('Y-m-d H:i:s') : ''; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </td>
    </tr>
    </tbody>
</table>

<table class="table table-bordered">
    <thead>
    <tr>
        <td> <?php echo $text_history; ?> </td>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>
            <?php foreach ($packages as $package) { ?>
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th><?php echo $column_parcel_number; ?></th>
                    <th><?php echo $column_status; ?></th>
                    <th><?php echo $column_status_label; ?></th>
                    <th><?php echo $column_status_state; ?></th>
                    <th><?php echo $column_status_date; ?></th>
                    <th><?php echo $column_county; ?></th>
                    <th><?php echo $column_transit_location; ?></th>
                    <th><?php echo $column_reason; ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($package['history'] as $history) { ?>
                <tr>
                    <td> <?php echo $package['awb_parcel']; ?> </td>
                    <td> <?php echo $history->getName(); ?> </td>
                    <td> <?php echo $history->getLabel(); ?> </td>
                    <td> <?php echo $history->getState(); ?> </td>
                    <td> <?php echo $history->getDate()->format('Y-m-d H:i:s'); ?> </td>
                    <td> <?php echo $history->getCounty(); ?> </td>
                    <td> <?php echo $history->getTransitLocation(); ?> </td>
                    <td> <?php echo $history->getReason(); ?> </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
            <?php } ?>
        </td>
    </tr>
    </tbody>
</table>

<script>
$(document).ready(function () {
    $(document).on('click', '.details-history', function () {
        parcel_number = $(this).data('parcel_number');
        $('#parcel_history_' + parcel_number).css("display","block");
    });
});
</script>
