$(document).ready(function() {
    let SamedayCities = window.SamedayCities;

    /**
     * @param fieldName
     *
     * @returns HTML|undefined
     */
    const getFieldByName = (fieldName) => {
        return Array.from(document.querySelectorAll('input, select'))
            .find(element => element.id.includes(fieldName)
        );
    }

    /**
     * @type {{country: (*|jQuery|HTMLElement), zone: (*|jQuery|HTMLElement), city: (*|jQuery|HTMLElement)}}
     */
    let formElements = {
        country: $(getFieldByName('country')),
        zone: $(getFieldByName('zone')),
        city: $(getFieldByName('city')),
    };

    /**
     * @param cityField
     * @param zoneId
     * @param countryId
     */
    const updateCities = (cityField, zoneId, countryId) => {
        let cities = SamedayCities?.[countryId]?.[zoneId] ?? [];
        if (cities.length > 0) {
            if (undefined !== citySelectElement && citySelectElement.length > 0) {
                populateCityField(cities, citySelectElement, cityField);
            } else {
                citySelectElement = document.createElement("select");
                citySelectElement.setAttribute("id", cityField.getAttribute('id'));
                citySelectElement.setAttribute("name", 'city');
                citySelectElement.setAttribute("class", "form-control form-control-select");

                populateCityField(cities, citySelectElement, cityField);
            }
        } else {
            if (undefined !== citySelectElement && citySelectElement.length > 0) {
                citySelectElement.replaceWith(cityField);
            }
        }
    }

    /**
     * @param value
     * @param text
     * @param cityFieldValue
     *
     * @returns {HTMLOptionElement}
     */
    const createOptionElement = (value, text, cityFieldValue = null) => {
        const option = document.createElement('option');
        option.value = value;
        option.setAttribute('data-alternate-values', `[${value}]`);
        if (value === cityFieldValue) {
            option.setAttribute('selected', true);
        }
        option.textContent = text;

        return option;
    }

    let citySelectElement;
    /**
     * @param cities
     * @param citySelectElement
     * @param cityField
     */
    const populateCityField = (cities, citySelectElement, cityField) => {
        citySelectElement.textContent = "";
        citySelectElement.appendChild(createOptionElement("", "Choose a city"));
        cities.forEach((city) => {
            citySelectElement.appendChild(createOptionElement(city.name, city.name, cityField.value));
        });

        cityField.replaceWith(citySelectElement);
    }

    if (undefined !== formElements.zone) {
        formElements.zone.on('change', (event, data = null) => {
            let zone = event.target.value;
            if (null !== data) {
                zone = data.zone;
            }
            updateCities(formElements.city[0], zone, formElements.country.val());
        });

        let isStillAttempt = true;
        setInterval(
            () => {
                if (null !== formElements.zone.val()) {
                    if (true === isStillAttempt) {
                        formElements.zone.trigger("change", [{"zone": formElements.zone.val()}]);
                        isStillAttempt = false;
                    }
                }
            },
            100
        )
    }
});