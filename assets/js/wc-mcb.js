jQuery(document).ready(function($){
	Checkout.configure({
        merchant: mcb.merchant_id,
        order: {
            amount: function() {
                //Dynamic calculation of amount
                return mcb.amount;
            },
            currency: mcb.currency,
            description: mcb.order_desc,
            id: mcb.order_id
        },
        interaction: {
            operation: 'PURCHASE', // set this field to 'PURCHASE' for 3-Party Checkout to perform a Pay Operation.
            merchant: {
                name: mcb.merchant_name
            }
		}
    });
    Checkout.showLightbox();
	function errorCallback( data ) {
		console.log(JSON.stringify(data));
    }
    function cancelCallback( data ) {
		console.log('Payment cancelled', data);
    }
    function completeCallback( data ) {
		console.log('Payment completed', data);
    }

});