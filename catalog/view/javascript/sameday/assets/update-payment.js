jQuery(document).ready(($) => {
    const paymentMethodClass = 'paymentMethod';
    const cookieName = 'selected_payment_method';

    const setCookie = (value) => {
        if (value) document.cookie = cookieName + '=' + value;
    };

    const getPaymentMethodFromCookie = () => {
        const cookies = document.cookie.split(';');
        let payment_method = '';
        cookies.forEach((value) => {
            if (value.indexOf('_payment_method') > 0) {
                payment_method = value.split('=')[1];
            }
        });
        return payment_method;
    };

    const getCheckedPaymentMethod = () => {
        const allPaymentMethods = Array.from(document.getElementsByClassName(paymentMethodClass));
        const checked = allPaymentMethods.filter(method => method.checked)[0];
        return checked ? checked.value : '';
    };

    const currentPaymentMethod = getCheckedPaymentMethod() || getPaymentMethodFromCookie();
    setCookie(currentPaymentMethod);

    $(document).on('click', '.paymentMethod', (e) => {
        const payment_code = e.target.value;
        setCookie(payment_code);
    });
});