<html>
    <body>
        <form method="post">
            CheckoutId to cancel: <input type="text" name="checkoutId" size=40 value="<?php echo isset($_POST['checkoutId'])?$_POST['checkoutId']:"";?>" />
            <button  type="submit">Continue</button>
        </form>
    

<?php

    require_once 'config.php';
    
    // Get the details about this purchase with the checkoutID.
    if(isset($_POST['checkoutId'])) {
        $callPaysonApi = new  PaysonEmbedded\PaysonApi($merchantId, $apiKey, $environment);
        $checkout = $callPaysonApi->GetCheckout($_POST['checkoutId']);
      
        $callPaysonApi->CancelCheckout($checkout);
        
        print "<h3>Checkout cancelled</h3>";
        
    } else {
        print "<H3>Enter checkoutId</h3>";
    }
    
?>

    </body>    
</html>



