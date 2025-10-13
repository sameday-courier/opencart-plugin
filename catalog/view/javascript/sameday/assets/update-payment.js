jQuery(document).ready(($) => {
    const paymentMethodClass = 'paymentMethod';
    const cookieName = 'selected_payment_method';

    const allPaymentMethods = Array.from(document.getElementsByClassName(paymentMethodClass));
    const currentPaymentMethod = allPaymentMethods.filter(method => method.checked)[0].valueOf().value;

    const setCookie = (value) => {
        document.cookie = cookieName + '=' + value;
    }

    const getPaymentMethodFromCookie = () => {
        let cookies = document.cookie.split(';');
        let payment_method = '';
        cookies.forEach((value) => {
            if (value.indexOf('_payment_method') > 0) {
                payment_method = value.split('=')[1];
            }
        });

        return payment_method;
    }

    setCookie(currentPaymentMethod);

    $(document).on('click', '.paymentMethod', (element) => {
            let payment_code = element.target.value;

            setCookie(payment_code);
        }
    );
});