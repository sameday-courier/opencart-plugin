/* OC2 classic checkout: #showLockerMap button in shipping_method.tpl (OCMOD) */
(function() {
    if (typeof $ === 'undefined') return;

    function openOc2LockerMap() {
        var $ln = $('#locker_nextday');
        if (!$ln.length) return;

        var destCountry = $ln.attr('data-dest_country') || $ln.data('dest_country') || '';
        var destCity = $ln.attr('data-dest_city') || $ln.data('dest_city') || '';
        var apiUsername = $ln.attr('data-api_username') || $ln.data('api_username') || '';
        // Legacy OCMOD wrongly wrote api username into data-dest_country as a second attr.
        if (!apiUsername && destCountry && destCountry.indexOf('.') !== -1) {
            apiUsername = destCountry;
            destCountry = 'RO';
        }

        var LockerPlugin = window['LockerPlugin'];
        if (!LockerPlugin) {
            console.warn('Sameday LockerPlugin SDK not loaded');
            return;
        }

        var clientId = 'b8cb2ee3-41b9-4c3d-aafe-1527b453d65e';
        var nextDayVal = ($ln.val() || '') + '.';
        var lockerInit = {
            apiUsername: apiUsername,
            clientId: clientId,
            countryCode: destCountry,
            langCode: (destCountry || 'ro').toLowerCase(),
            city: destCity
        };

        LockerPlugin.init(lockerInit);
        if (LockerPlugin.options.countryCode !== destCountry || LockerPlugin.options.city !== destCity) {
            lockerInit.countryCode = destCountry;
            lockerInit.city = destCity;
            LockerPlugin.reinitializePlugin(lockerInit);
        }

        var pluginInstance = LockerPlugin.getInstance();
        pluginInstance.open();
        pluginInstance.subscribe(function(lockerData) {
            var locker = lockerData.lockerId + '.' + (lockerData.name || '');
            $ln.val(nextDayVal + locker);
            $ln.prop('checked', true).prop('disabled', false);
            $('#showLockerDetails').html(
                '<strong>' + (lockerData.name || '') + ' - ' + (lockerData.address || '') + '</strong>'
            );

            $.ajax({
                url: 'index.php?route=extension/shipping/sameday/saveLocker',
                type: 'POST',
                data: {
                    locker_id: lockerData.lockerId,
                    locker_address: lockerData.address
                }
            });

            pluginInstance.close();
        });
    }

    $(document).on('click', '#showLockerMap', function(e) {
        e.preventDefault();
        openOc2LockerMap();
    });
})();

/* OC4 modal checkout helpers */
(function() {
    if (typeof $ === 'undefined') return;
    var _ajax = $.ajax;
    $.ajax = function(opt) {
        if (!opt || !opt.url || opt.url.indexOf('shipping_method.quote') === -1)
            return _ajax.apply(this, arguments);
        var origSuccess = opt.success;
        opt.success = function(json) {
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
    if (typeof $ === 'undefined') return;

    $(document).on('shown.bs.modal', '#modal-shipping', function() {
        var quoteData = window.__samedayQuoteData;
        if (!quoteData || !quoteData.shipping_methods || !quoteData.shipping_methods.sameday) return;

        var samedayQuotes = quoteData.shipping_methods.sameday.quote || {};
        var $modal = $(this);

        $modal.find('input[name="shipping_method"]').each(function() {
            var code = ($(this).val() || '').trim();
            if (code.indexOf('sameday.') !== 0) return;

            var q = null;
            for (var k in samedayQuotes) {
                if (samedayQuotes[k].code === code) { q = samedayQuotes[k]; break; }
            }
            if (!q || !q.destCountry || !q.apiUsername) return;

            var hasLockersList = q.lockers && typeof q.lockers === 'object' && Object.keys(q.lockers).length > 0;
            if (hasLockersList) return;

            var $formCheck = $(this).closest('.form-check');
            if (!$formCheck.length) {
                $formCheck = $(this).closest('.radio, .form-group, label').parent();
            }
            if ($formCheck.find('.sameday-locker-map-wrap').length) return;

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
    var clientId = 'b8cb2ee3-41b9-4c3d-aafe-1527b453d65e';
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
        $radio.prop('checked', true).prop('disabled', false);
        $details.html('<strong>' + (lockerData.name || '') + ' - ' + (lockerData.address || '') + '</strong>');

        $.ajax({
            url: 'index.php?route=extension/sameday/shipping/sameday.saveLocker&language=' + ($('html').attr('lang') || ''),
            type: 'POST',
            data: {
                locker_id: lockerData.lockerId,
                locker_address: lockerData.address
            }
        });

        pluginInstance.close();
    });
});
