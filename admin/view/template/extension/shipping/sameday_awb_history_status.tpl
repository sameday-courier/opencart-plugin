<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
            </div>

            <h1><?php echo $heading_title; ?></h1>
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
                    <h3 class="panel-title"><i class="fa fa-info-circle"></i> <?php echo $awb_history_title; ?>  </h3>
                </div>
                <div class="panel-body">
                    <div class="showAwbHistory">
                        <div class="loader">  </div> <strong style="color: #1E91CF"> <?php echo $text_awb_sync; ?>  </strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php echo $footer; ?>

<script>
    $(document).ready(function(){
        function refreshAwbHistory() {
            $.get(
                window.location,
                function (response) {
                    $('.showAwbHistory').html(response);
                },
                'html'
            );
        }

        window.onload = refreshAwbHistory();
    });
</script>

<style>
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




