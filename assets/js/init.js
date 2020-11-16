console.log('KOKOKO')

jQuery(function ($) {
    console.log("Initializing Fliqpay Payment modal");
    console.log(fliqpay_params)

	jQuery("#fliqpay-payment-button").click(function () {
        alert('IT WORKS');
        // location.href = `https://api.fliqpay.com/i/paymentButton?businessKey=${fliqpay_params.Key}&name=Shoe&description=Black leatheraffordableshoe&amount=${fliqpay_params.amount}&currency=NGN&isAmountFixed=false&customerName=yourname&customerEmail=efewebdev@gmail.com&useCurrenciesInWalletSettings=true&acceptedCurrencies=&redirectUrl=${fliqpay_params.redirect_url}&callbackUrl=${fliqpay_params.callbackUrl}&orderId=123456&settlementDestination=bank_account`
        location.href = "https://api-sandbox.coingate.com/v2"

    });
    

});
