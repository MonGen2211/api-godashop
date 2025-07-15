<?php
class ApiPaymentController
{
    public function checkout()
    {
        //Kiểm tra giỏ hàng
        global $router;
        $cartStorage = new CartStorage();
        $cart = $cartStorage->fetch();
        if (empty($cart->getTotalProductNumber())) {
            $_SESSION['error'] = "Giỏ hàng rỗng";
            $redirectURL = $router->generate('product');
            header("location: $redirectURL");
            exit;
        }
        //Hiển thị thông tin đơn hàng
        $email = "khachvanglai@gmail.com";
        if (!empty($_SESSION['email'])) {
            $email = $_SESSION['email'];
        }
        $customerRepository = new CustomerRepository();
        $customer = $customerRepository->findEmail($email);
        require ABSPATH_SITE . 'layout/variable_address.php';
        require ABSPATH_SITE . 'view/payment/checkout.php';
    }

    public function order()
    {
        $info = json_decode(file_get_contents("php://input"));
        // var_dump($info);
        // exit;
        //lưu đơn hàng
        $email = $info->loggedUser->email;

        $customerRepository = new CustomerRepository();
        $customer = $customerRepository->findEmail($email);

        $provinceRepository = new ProvinceRepository();
        $province = $provinceRepository->find($info->deliveryInfo->province);
        $shipping_fee = $province->getShippingFee();
        $data = [];
        $data["created_date"] = date('Y-m-d H:i:s');
        $data["order_status_id"] = 1;
        $data["staff_id"] = null;
        $data["customer_id"] = $customer->getId();
        $data["shipping_fullname"] = $info->deliveryInfo->fullname;
        $data["shipping_mobile"] = $info->deliveryInfo->mobile;
        $data["payment_method"] = $info->deliveryInfo->payment_method;
        $data["shipping_ward_id"] = $info->deliveryInfo->ward;
        $data["shipping_housenumber_street"] = $info->deliveryInfo->address;
        $data["shipping_fee"] = $shipping_fee;
        $data["delivered_date"] = date('Y-m-d H:i:s', strtotime('+3 days'));

        $orderRepository = new OrderRepository();
        $order_id = $orderRepository->save($data);
        $orderItemRepository = new OrderItemRepository();
        $productRepository = new ProductRepository();
        foreach ($info->cartItems as $item) {
            $dataItem = [];
            $product = $productRepository->find($item->id);
            $dataItem['product_id'] = $item->id;
            $dataItem['order_id'] = $order_id;
            $dataItem['unit_price'] = $product->getSalePrice();
            $dataItem['qty'] = $item->qty ?? $item->quantity;
            $dataItem['total_price'] = $dataItem['unit_price'] * $dataItem['qty'];
            $orderItemRepository->save($dataItem);
        }
    }
}
