

(function($, settings) {
    var payson = undefined;
    var prevAddress = undefined;
    var controlCookie = undefined;
    var isUpdating = false

    $(function () {
        payson = document.getElementById('paysonIframe');

		Mage.Cookies.path     = '/';
		Mage.Cookies.set(settings.checkoutId, settings.controlKey);

		this.checkIfCartWasUpdated = setInterval((function() {
            if (!settings.checkoutId || isUpdating) {
                return true;
            }

            var controlKey = Mage.Cookies.get(settings.checkoutId);

            if (controlKey && controlKey != settings.controlKey) {
                updateCheckout();
                settings.controlKey = controlKey;
            }
        }).bind(this), 1000);
    });

    $(document).on('change', 'input[name=shipping_method]:radio', function () {
        var methodName = $(this).val();
        var postData = { method: methodName };

        updateCheckout(postData);
    });

    document.addEventListener('PaysonEmbeddedAddressChanged', function(evt) {
        var address = evt.detail;
        address.setAddress = true 

        if (JSON.stringify(address) === JSON.stringify(prevAddress)) { 
            return; 
        } 

        prevAddress = address;

        updateCheckout(address);
    });

    function updateCheckout(data) {
        lockPaysonForm();

    	// Sidebar is reloaded on updates
		$('.sidebar').load('/checkout2/express/update .payson-shipping, .payson-review', data, function(responseData) {
			if (responseData === 'empty_cart') {
				window.location = '/checkout/cart'
				return;
			}

            releasePaysonForm();
            updatePaysonForm();
        });
    }

    function updatePaysonForm() {
        payson.contentWindow.postMessage('updatePage', '*');
    }

    function lockPaysonForm() {
        payson.contentWindow.postMessage('lock', '*');
        isUpdating = true;
    }

    function releasePaysonForm() {
        payson.contentWindow.postMessage('release', '*');
        isUpdating = false;
    }
})($j, window.PaysonSettings = window.PaysonSettings || {});

