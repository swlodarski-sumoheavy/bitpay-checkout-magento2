define([
    'bitpay',
    'Bitpay_BPCheckout/js/bitpay/action/get-params',
    'Bitpay_BPCheckout/js/bitpay/action/delete-cookie',
    'mage/mage'
], function (bitpay, getParams, deleteCookie) {
    'use strict';

    return function (config) {
        var invoiceID = getParams()['invoiceID'],
            orderId = getParams()['order_id'],
            env = config.env,
            baseUrl = config.baseUrl,
            returnId = getParams()['return_id'],
            isPaid = false;

        if (env === 'test') {
            bitpay.enableTestMode();
        }

        window.addEventListener('message', function (event) {
            var paymentStatus = event.data.status;

            if (paymentStatus === 'paid') {
                isPaid = true;
                //clear the cookies
                deleteCookie('env');
                deleteCookie('invoicedata');
                deleteCookie('modal');

                if (returnId) {
                    window.location.replace(
                        baseUrl + 'checkout/onepage/success/?return_id=' + returnId
                    );
                } else {
                    document.getElementById('bitpay-header').innerHTML =
                      'Thank you for your purchase.';
                    document.getElementById('success-bitpay-page').style.display =
                      'block';
                }


            }
        }, false);

        //show the order info
        bitpay.onModalWillLeave(function () {
            if (!isPaid) {
                document.getElementById('bitpay-header').innerHTML = 'Redirecting to cart...';
                //clear the cookies and redirect back to the cart
                deleteCookie('env');
                deleteCookie('invoicedata');
                deleteCookie('modal');

                window.location.replace(baseUrl + 'rest/V1/bitpay-bpcheckout/close?orderID=' + orderId);

            } //endif
        });
        setTimeout(function () {
            bitpay.showInvoice(invoiceID);
        }, 500);
    };
});
