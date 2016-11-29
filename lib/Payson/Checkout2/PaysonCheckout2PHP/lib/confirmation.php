<html>
    <body>
        <form method="post">
            CheckoutId: <input type="text" name="checkoutId" size=40 value="<?php echo isset($_POST['checkoutId'])?$_POST['checkoutId']:"";?>" />
            <button  type="submit">Continue</button>
        </form>
    

<?php

require_once 'config.php';

// Get the details about this purchase with the checkoutID.
if(isset($_POST['checkoutId'])) {
    $callPaysonApi = new  PaysonEmbedded\PaysonApi($merchantId, $apiKey, $environment);
    $checkout = $callPaysonApi->GetCheckout($_POST['checkoutId']);
    
    
    if($checkout->status == 'canceled'){
        echo '<H3> Checkout is canceled .... </H3>';
    }elseif($checkout->status == 'created'){
        echo '<H3> Checkout is created.... </H3>';
        
    }elseif($checkout->status == 'readyToPay'){
        echo '<H3> Client is ready to pay.... </H3>';
    }elseif($checkout->status == 'readyToShip'){
         echo "<H3> Purchase has been completed and ready to be shipped. PurchaseId: ".$checkout->purchaseId."</H3>" ;
    }elseif($checkout->status == 'denied'){
        echo "<H3> The purchase is denied by any reason</H3>" ;
    }else {
        echo '<H3>Something happened when .... </H3>';
    }
    
    $checkout->snippet = '*removed*';
    
    echo '<BR><BR><h4>Details</h4><pre>';print_r($checkout);echo '</pre>';
    
} else {
    print "<H3>Enter checkoutId</h3>";
}

?>

    </body>    
</html>