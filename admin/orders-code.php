<?php
//code for handling orders made
include("../config/function.php");

if(!isset($_SESSION["productItems"])){
    $_SESSION['productItems'] = [];
}
if(!isset($_SESSION["productItemIds"])){
    $_SESSION['productItemIds'] = [];
}

if(isset($_POST["addItem"])){
    $productId = validate($_POST["product_id"]);
    $quantity = validate($_POST["quantity"]);

    $checkProduct = mysqli_query($conn,"SELECT * FROM  products WHERE id='$productId' LIMIT 1");
    if($checkProduct){
        if(mysqli_num_rows($checkProduct) > 0 ){
            $row = mysqli_fetch_assoc($checkProduct);
            if($row['quantity']< $quantity){
                redirect('order-create.php','Only '.$row['quantity'].' is available');
            }
            $productData= [
                'product_id' => $row['id'],
                'name' => $row['name'],
                'price' => $row['price'],
                'quantity'=> $quantity,

            ];
            if(!in_array($row['id'], $_SESSION['productItemIds'])){
                array_push($_SESSION['productItemIds'],$row['id']);
                array_push($_SESSION['productItems'],$productData);
            }else{
                foreach($_SESSION['productItems'] as $key=>$prodSessionItem){
                    if($prodSessionItem['product_id'] == $row['id']){
                        $newQuantity = $prodSessionItem['quantity'] + $quantity;
                        $productData= [
                            'product_id' => $row['product_id'],
                            'name' => $row['name'],
                            'price' => $row['price'],
                            'quantity'=> $newQuantity,
            
                        ];
                        $_SESSION['productItems'][$key] = $productData;

                    }
            }
      
        

        }
        redirect('order-create.php',$row['name'].' has been added');
    }else{
        redirect('order-create.php','No such product found');
    }
}else{
    redirect('order-create.php','Something went wrong');
}
}
// code for increasing and decreasing the product amount
if(isset($_POST['productIncDec'])){
    $productId = validate($_POST['product_id']);
    $quantity= validate($_POST['quantity']);

    $flag = false;
    foreach($_SESSION['productItems'] as $key => $item){
        if($item['product_id'] == $productId){
            $flag = true;
            $_SESSION['productItems'][$key]['quantity'] = $quantity;
            
    }
    }
    if($flag){
         jsonResponse(200,'success', 'Quantity updated');
    }else{
        jsonResponse(500,'error', 'Something went wrong please refresh');
    }
    }

//code for showing the modal when the proceed to order button is clicked.
    if(isset($_POST['proceedToPlaceBtn'])){
        $phone = validate($_POST['cphone']);
        $payment_mode = validate($_POST['payment_mode']);
        $user_id = $_SESSION['loggedInUser']['user_id'];

        //checking for customer
        $checkCustomer = mysqli_query($conn,"SELECT * FROM customers WHERE phone='$phone' AND user_id='$user_id' LIMIT 1");
        if($checkCustomer){
            if(mysqli_num_rows($checkCustomer) > 0){
                $_SESSION['invoice_no'] = "INV-".rand(111111,999999);
                $_SESSION["cphone"] = $phone;
                $_SESSION["payment_mode"] = $payment_mode;
                jsonResponse(200,'success', 'Customer found');
        }else{
            $_SESSION['cphone'] = $phone;
            jsonResponse(404,'warning', 'Customer not found');
        }
    }else{
        jsonResponse(500,'error', 'Something went wrong');
    }
    }
    //code for saving customer data if the customer is not found in the database upon ordering
    if(isset($_POST['saveCustomerBtn'])){
        $name = validate($_POST['name']);
        $phone = validate($_POST['phone']);
        $email = validate($_POST['email']);
        if($name!=""&& $phone!= ""){
            $data=[
                "name"=> $name,
                "phone"=> $phone,
                "email"=> $email,
            ];
            $result = insert("customers", $data);
            if($result){
                jsonResponse(200,"success","Customer created successfully");
            }else{
                jsonResponse(500,"error","Something went wrong");
            }

        }else{
            jsonResponse(422,"warning", "Please fill all fields");
        }
    }

//code for storing the orders in the database
if(isset($_POST["saveOrder"])){
   $invoice_no = validate($_SESSION['invoice_no']);
   $phone= validate($_SESSION["cphone"]);
   $payment_mode= validate($_SESSION["payment_mode"]);
   $order_placed_by_id = $_SESSION['loggedInUser']['user_id'];

   $checkCustomer = mysqli_query($conn, "SELECT * FROM customers WHERE phone='$phone' LIMIT 1");
   if(!$checkCustomer){
    jsonResponse(500,"error","Something went wrong");
   }
   if(mysqli_num_rows($checkCustomer)> 0){
        $customerData = mysqli_fetch_assoc($checkCustomer);
        
        if(!isset($_SESSION["productItems"])){
            jsonResponse(404,"error","No items to place order");
        }
        $sessionProducts = $_SESSION['productItems'];
        $totalAmount = 0;
        foreach($sessionProducts as $amtItem){
            $totalAmount += $amtItem['price'] * $amtItem['quantity'];
        }
        $data = [
            "customer_id"=> $customerData["id"],
            "tracking_no" => rand(11111,99999),
            "invoice_no" => $invoice_no,
            "total_amount" => $totalAmount,
            'order_date' => date('Y-m-d'),
            "payment_mode" => $payment_mode,
            "order_status" => "booked",
            "order_placed_by_id" => $order_placed_by_id,
            "phone" => $phone,

        ];
        $result = insert("orders", $data);
        $lastOrderId = mysqli_insert_id($conn);

        foreach($sessionProducts as $prodItem){
            $productId = $prodItem["product_id"];
            $price = $prodItem['price'];
            $quantity = $prodItem['quantity'];

            //inserting order items
            $dataOrderItem = [
                'order_id' => $lastOrderId,
                'product_id'=> $productId,
                'price' => $price,
                'quantity' => $quantity,

            ];
            $orderItemQuery = insert('order_items', $dataOrderItem);

            //checking for quantity decreasing it and making total quantity
            $checkProductQuantityQuery = mysqli_query($conn,"SELECT * FROM products WHERE id='$productId'");
            $productQtyData = mysqli_fetch_assoc($checkProductQuantityQuery);
            $totalProductQuantity = $productQtyData["quantity"] - $quantity;

            $dataUpdate = [
                "quantity" => $totalProductQuantity,
            ];
            $updateProductQty = update('products', $productId, $dataUpdate);
        }
        unset($_SESSION['productItemIds']);
        unset($_SESSION['productItems']);
        unset($_SESSION['cphone']);
        unset($_SESSION['payment_mode']);
        unset($_SESSION['invoice_no']);

        jsonResponse(200,'success','Order placed successfully');

   }else{
    jsonResponse(404,'warning','Customer not found');
   }
}

