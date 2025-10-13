jQuery(document).ready(function ($) {
    const ln = $("#locker_nextday");
    const nextDayVal = ln.val() + ".";
    const destCountry = ln.data('dest_country');
    const destCity = ln.data('dest_city');
    const apiUsername = ln.data('api_username');
    $(document).on("click", "#showLockerMap", () => {
        const clientId = "b8cb2ee3-41b9-4c3d-aafe-1527b453d65e";
        const LockerPlugin = window['LockerPlugin'];
        const lockerInit = {
            'apiUsername': apiUsername,
            'clientId': clientId,
            'countryCode': destCountry,
            'langCode': destCountry.toLowerCase(),
            'city': destCity,
        };

        LockerPlugin.init(lockerInit);

        if (
            LockerPlugin.options.countryCode !== destCountry
            || LockerPlugin.options.city !== destCity
        ) {
            lockerInit.countryCode = destCountry;
            lockerInit.city = destCity;

            LockerPlugin.reinitializePlugin(lockerInit);
        }

        let pluginInstance = LockerPlugin.getInstance();

        pluginInstance.open();

        pluginInstance.subscribe((lockerData) => {
            let locker = `${lockerData.lockerId}.${lockerData.name}`;

            ln.val(nextDayVal + locker);
            ln.prop('checked', true);
            ln.prop('disabled', false);
            $('#showLockerDetails').html('<strong>' + lockerData.name + ' - ' + lockerData.address + '</strong>');

            pluginInstance.close();
        });
    });
});