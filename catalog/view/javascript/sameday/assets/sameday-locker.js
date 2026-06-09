(function() {
    if (typeof $ === 'undefined') return;
    var _ajax = $.ajax;
    $.ajax = function(opt) {
        if (!opt || !opt.url || opt.url.indexOf('shipping_method.quote') === -1)
            return _ajax.apply(this, arguments);
        var origSuccess = opt.success;
        opt.success = function(json) {
            // Store quote data so we can inject Sameday buttons when modal is shown
            if (json && json.shipping_methods) {
                window.__samedayQuoteData = json;
            } else {
                window.__samedayQuoteData = null;
            }
            if (typeof origSuccess === 'function') origSuccess.apply(this, arguments);
        };
        return _ajax.call(this, opt);
    };
})();

(function() {
    $(document).on('shown.bs.modal', '#modal-shipping', function() {
        var quoteData = window.__samedayQuoteData;
        if (!quoteData || !quoteData.shipping_methods || !quoteData.shipping_methods.sameday) return;

        var samedayQuotes = quoteData.shipping_methods.sameday.quote || {};
        var $modal = $(this);

        $modal.find('input[name="shipping_method"]').each(function() {
            var code = ($(this).val() || '').trim();
            if (code.indexOf('sameday.') !== 0) return;

            // Find quote object for this option (quote keys can be e.g. "2H", "2H_123")
            var q = null, qKey = null;
            for (var k in samedayQuotes) {
                if (samedayQuotes[k].code === code) { q = samedayQuotes[k]; qKey = k; break; }
            }
            if (!q || !q.destCountry || !q.apiUsername) return;

            var hasLockersList = q.lockers && typeof q.lockers === 'object' && Object.keys(q.lockers).length > 0;
            if (hasLockersList) return; // Already has dropdown, no map button

            var $formCheck = $(this).closest('.form-check');
            if ($formCheck.find('.sameday-locker-map-wrap').length) return; // Already injected

            var $wrap = $('<div class="lockers mt-1 sameday-locker-map-wrap"></div>');
            $wrap.append(
                $('<button type="button" class="btn btn-sm btn-primary btn-sameday-locker-map"></button>')
                    .text('Show Sameday locations')
                    .attr({
                        'data-quote-code': code,
                        'data-dest-country': q.destCountry || '',
                        'data-dest-city': q.destCity || '',
                        'data-dest-county': q.destCounty || '',
                        'data-api-username': q.apiUsername || ''
                    })
            );
            $wrap.append($('<span class="sameday-locker-details ms-2 d-block"></span>'));
            $formCheck.append($wrap);
        });
    });
})();

$(document).on('click', '#modal-shipping .btn-sameday-locker-map', function() {
    var $btn = $(this);
    var code = $btn.attr('data-quote-code');
    var destCountry = $btn.attr('data-dest-country') || '';
    var destCity = $btn.attr('data-dest-city') || '';
    var apiUsername = $btn.attr('data-api-username') || '';
    var clientId = "b8cb2ee3-41b9-4c3d-aafe-1527b453d65e";
    var LockerPlugin = window['LockerPlugin'];
    if (!LockerPlugin) return;

    var lockerInit = {
        apiUsername: apiUsername,
        clientId: clientId,
        countryCode: destCountry,
        langCode: destCountry.toLowerCase(),
        city: destCity
    };
    LockerPlugin.init(lockerInit);
    if (LockerPlugin.options.countryCode !== destCountry || LockerPlugin.options.city !== destCity) {
        lockerInit.countryCode = destCountry;
        lockerInit.city = destCity;
        LockerPlugin.reinitializePlugin(lockerInit);
    }

    var pluginInstance = LockerPlugin.getInstance();
    var $formCheck = $btn.closest('.form-check');
    var $radio = $formCheck.find('input[name="shipping_method"]');
    var $details = $formCheck.find('.sameday-locker-details');

    pluginInstance.open();
    pluginInstance.subscribe(function(lockerData) {
        // Keep radio value as original quote code (e.g. sameday.2H.1) so save action finds it in session.
        // Only update checked state, enable the radio, and show locker details.
        $radio.prop('checked', true).prop('disabled', false);
        $details.html('<strong>' + (lockerData.name || '') + ' - ' + (lockerData.address || '') + '</strong>');

        $.ajax({
            url: 'index.php?route=extension/sameday/shipping/sameday.saveLocker&language=' + $('html').attr('lang'),
            type: 'POST',
            data: {
                locker_id: lockerData.lockerId,
                locker_address: lockerData.address
            }
        });


        pluginInstance.close();
    });
});